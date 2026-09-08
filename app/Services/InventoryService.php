<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\InventoryLayer;
use App\Models\InventoryLayerConsumption;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * HF-05 Inti metode FIFO & HPP.
 *
 * Semua perubahan persediaan wajib melewati layanan ini supaya:
 * 1. setiap barang masuk membentuk lapisan (batch) dengan harga belinya sendiri;
 * 2. setiap barang keluar mengambil lapisan tertua lebih dahulu (First In, First Out);
 * 3. HPP dihitung otomatis dari lapisan yang benar-benar terpakai;
 * 4. saldo stok dan kartu stok selalu tercatat konsisten.
 */
class InventoryService
{
    /** Barang masuk: membuat satu lapisan FIFO baru. */
    public function receive(
        int $productId,
        int $branchId,
        int $quantity,
        float $unitCost,
        ?Model $reference = null,
        StockMovementType $type = StockMovementType::PembelianMasuk,
        ?Carbon $receivedAt = null,
        ?int $userId = null,
        ?string $note = null,
    ): InventoryLayer {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Jumlah barang masuk harus lebih dari nol.');
        }

        if ($unitCost < 0) {
            throw new InvalidArgumentException('Harga beli tidak boleh negatif.');
        }

        return DB::transaction(function () use ($productId, $branchId, $quantity, $unitCost, $reference, $type, $receivedAt, $userId, $note) {
            $unitCost = round($unitCost, 2);

            $layer = InventoryLayer::create([
                'product_id' => $productId,
                'branch_id' => $branchId,
                'unit_cost' => $unitCost,
                'quantity' => $quantity,
                'remaining_quantity' => $quantity,
                'source_type' => $reference?->getMorphClass(),
                'source_id' => $reference?->getKey(),
                'received_at' => $receivedAt ?? now(),
            ]);

            $balance = $this->applyStockDelta($productId, $branchId, $quantity);

            $this->logMovement($productId, $branchId, $type, $quantity, $balance, $unitCost, round($quantity * $unitCost, 2), $reference, $userId, $note);

            return $layer;
        });
    }

    /**
     * Barang keluar dengan metode FIFO. Mengembalikan rincian lapisan yang terpakai
     * beserta total HPP-nya.
     *
     * @return array{quantity: int, cost_total: float, allocations: list<array{layer_id: int, quantity: int, unit_cost: float, cost_total: float}>}
     */
    public function issue(
        int $productId,
        int $branchId,
        int $quantity,
        Model $consumer,
        StockMovementType $type = StockMovementType::PenjualanKeluar,
        ?int $userId = null,
        ?string $note = null,
    ): array {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Jumlah barang keluar harus lebih dari nol.');
        }

        return DB::transaction(function () use ($productId, $branchId, $quantity, $consumer, $type, $userId, $note) {
            $layers = InventoryLayer::query()
                ->fifo($productId, $branchId)
                ->lockForUpdate()
                ->get();

            $outstanding = $quantity;
            $costTotal = 0.0;
            $allocations = [];
            $consumedAt = now();

            foreach ($layers as $layer) {
                if ($outstanding <= 0) {
                    break;
                }

                $taken = min($outstanding, $layer->remaining_quantity);
                $unitCost = (float) $layer->unit_cost;
                $lineCost = round($taken * $unitCost, 2);

                $layer->decrement('remaining_quantity', $taken);

                InventoryLayerConsumption::create([
                    'inventory_layer_id' => $layer->id,
                    'consumer_type' => $consumer->getMorphClass(),
                    'consumer_id' => $consumer->getKey(),
                    'quantity' => $taken,
                    'unit_cost' => $unitCost,
                    'cost_total' => $lineCost,
                    'consumed_at' => $consumedAt,
                ]);

                $allocations[] = [
                    'layer_id' => $layer->id,
                    'quantity' => $taken,
                    'unit_cost' => $unitCost,
                    'cost_total' => $lineCost,
                ];

                $costTotal += $lineCost;
                $outstanding -= $taken;
            }

            if ($outstanding > 0) {
                throw InsufficientStockException::make(
                    Product::find($productId)?->name ?? "#{$productId}",
                    $quantity,
                    $quantity - $outstanding,
                );
            }

            $costTotal = round($costTotal, 2);
            $balance = $this->applyStockDelta($productId, $branchId, -$quantity);

            $this->logMovement(
                $productId, $branchId, $type, -$quantity, $balance,
                $quantity > 0 ? round($costTotal / $quantity, 2) : 0,
                $costTotal, $consumer, $userId, $note,
            );

            return [
                'quantity' => $quantity,
                'cost_total' => $costTotal,
                'allocations' => $allocations,
            ];
        });
    }

    /**
     * Mengembalikan persediaan yang sudah dikeluarkan (misal pembatalan transaksi):
     * sisa lapisan FIFO dipulihkan ke nilai semula sehingga HPP tetap konsisten.
     */
    public function restore(
        Model $consumer,
        StockMovementType $type = StockMovementType::PembatalanPenjualan,
        ?int $userId = null,
        ?string $note = null,
    ): float {
        return DB::transaction(function () use ($consumer, $type, $userId, $note) {
            $consumptions = InventoryLayerConsumption::query()
                ->with('layer')
                ->where('consumer_type', $consumer->getMorphClass())
                ->where('consumer_id', $consumer->getKey())
                ->get();

            $restoredCost = 0.0;
            $perLocation = [];

            foreach ($consumptions as $consumption) {
                $layer = $consumption->layer;

                if (! $layer) {
                    continue;
                }

                $layer->increment('remaining_quantity', $consumption->quantity);

                $key = $layer->product_id.'-'.$layer->branch_id;
                $perLocation[$key] ??= [
                    'product_id' => $layer->product_id,
                    'branch_id' => $layer->branch_id,
                    'quantity' => 0,
                    'cost_total' => 0.0,
                ];
                $perLocation[$key]['quantity'] += $consumption->quantity;
                $perLocation[$key]['cost_total'] += (float) $consumption->cost_total;

                $restoredCost += (float) $consumption->cost_total;
                $consumption->delete();
            }

            foreach ($perLocation as $row) {
                $balance = $this->applyStockDelta($row['product_id'], $row['branch_id'], $row['quantity']);

                $this->logMovement(
                    $row['product_id'], $row['branch_id'], $type, $row['quantity'], $balance,
                    $row['quantity'] > 0 ? round($row['cost_total'] / $row['quantity'], 2) : 0,
                    round($row['cost_total'], 2), $consumer, $userId, $note,
                );
            }

            return round($restoredCost, 2);
        });
    }

    /** Saldo stok tercatat pada satu lokasi. */
    public function availableQuantity(int $productId, int $branchId): int
    {
        return (int) Stock::query()
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->value('quantity');
    }

    /** Sisa stok menurut lapisan FIFO (dipakai untuk pemeriksaan konsistensi). */
    public function layerQuantity(int $productId, int $branchId): int
    {
        return (int) InventoryLayer::query()
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->sum('remaining_quantity');
    }

    /** Nilai persediaan (Rp) berdasarkan sisa tiap lapisan FIFO. */
    public function inventoryValue(?int $branchId = null): float
    {
        return (float) InventoryLayer::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('COALESCE(SUM(remaining_quantity * unit_cost), 0) as value')
            ->value('value');
    }

    /** Perkiraan HPP bila sejumlah barang dikeluarkan sekarang (tanpa mengubah data). */
    public function estimateCost(int $productId, int $branchId, int $quantity): float
    {
        $outstanding = $quantity;
        $cost = 0.0;

        foreach (InventoryLayer::query()->fifo($productId, $branchId)->get() as $layer) {
            if ($outstanding <= 0) {
                break;
            }

            $taken = min($outstanding, $layer->remaining_quantity);
            $cost += $taken * (float) $layer->unit_cost;
            $outstanding -= $taken;
        }

        return round($cost, 2);
    }

    /** Menyesuaikan saldo stok dan mengembalikan saldo terbaru. */
    private function applyStockDelta(int $productId, int $branchId, int $delta): int
    {
        $stock = Stock::query()
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->lockForUpdate()
            ->first();

        if (! $stock) {
            $stock = Stock::create([
                'product_id' => $productId,
                'branch_id' => $branchId,
                'quantity' => 0,
            ]);
        }

        $newQuantity = $stock->quantity + $delta;

        if ($newQuantity < 0) {
            throw InsufficientStockException::make(
                Product::find($productId)?->name ?? "#{$productId}",
                abs($delta),
                $stock->quantity,
            );
        }

        $stock->quantity = $newQuantity;
        $stock->save();

        return $newQuantity;
    }

    private function logMovement(
        int $productId,
        int $branchId,
        StockMovementType $type,
        int $quantity,
        int $balanceAfter,
        ?float $unitCost,
        ?float $costTotal,
        ?Model $reference,
        ?int $userId,
        ?string $note,
    ): StockMovement {
        return StockMovement::create([
            'product_id' => $productId,
            'branch_id' => $branchId,
            'type' => $type,
            'quantity' => $quantity,
            'balance_after' => $balanceAfter,
            'unit_cost' => $unitCost,
            'cost_total' => $costTotal,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'user_id' => $userId,
            'note' => $note,
        ]);
    }
}
