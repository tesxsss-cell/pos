<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Persediaan rata-rata bergerak per barang dan lokasi sesuai PSAK 14. */
class InventoryService
{
    /** Baris stok menjadi kunci serialisasi penerimaan, pengeluaran, dan evaluasi ulang. */
    private function lockedStock(int $productId, int $branchId): Stock
    {
        Stock::query()->insertOrIgnore([
            'product_id' => $productId, 'branch_id' => $branchId,
            'quantity' => 0, 'avg_cost' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return Stock::where('product_id', $productId)->where('branch_id', $branchId)->lockForUpdate()->firstOrFail();
    }

    public function receive(
        int $productId, int $branchId, int $quantity, float $unitCost,
        ?Model $reference = null,
        StockMovementType $type = StockMovementType::PembelianMasuk,
        ?Carbon $receivedAt = null, ?int $userId = null, ?string $note = null,
    ): Stock {
        if ($quantity <= 0 || $unitCost < 0) {
            throw new InvalidArgumentException('Jumlah harus positif dan harga pokok tidak boleh negatif.');
        }

        return DB::transaction(function () use ($productId, $branchId, $quantity, $unitCost, $reference, $type, $userId, $note) {
            $stock = $this->lockedStock($productId, $branchId);
            $unitCost = round($unitCost, 4, PHP_ROUND_HALF_UP);
            $average = $stock->quantity <= 0 ? $unitCost
                : (($stock->quantity * (float) $stock->avg_cost) + ($quantity * $unitCost)) / ($stock->quantity + $quantity);
            $stock->update(['quantity' => $stock->quantity + $quantity, 'avg_cost' => round($average, 4, PHP_ROUND_HALF_UP)]);
            $this->logMovement($stock, $type, $quantity, $unitCost, $reference, $userId, $note);

            return $stock;
        });
    }

    /** HPP keluar menggunakan rata-rata saat pengeluaran; rata-rata tidak berubah. */
    public function issue(
        int $productId, int $branchId, int $quantity, Model $consumer,
        StockMovementType $type = StockMovementType::PenjualanKeluar,
        ?int $userId = null, ?string $note = null,
    ): array {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Jumlah barang keluar harus lebih dari nol.');
        }

        return DB::transaction(function () use ($productId, $branchId, $quantity, $consumer, $type, $userId, $note) {
            $stock = $this->lockedStock($productId, $branchId);
            if ($stock->quantity < $quantity) {
                throw InsufficientStockException::make(Product::find($productId)?->name ?? "#{$productId}", $quantity, $stock->quantity);
            }
            $unitCost = (float) $stock->avg_cost;
            $stock->update(['quantity' => $stock->quantity - $quantity]);
            $this->logMovement($stock, $type, -$quantity, $unitCost, $consumer, $userId, $note);

            return ['quantity' => $quantity, 'unit_cost' => $unitCost, 'cost_total' => round($quantity * $unitCost, 2, PHP_ROUND_HALF_UP)];
        });
    }


    public function availableQuantity(int $productId, int $branchId): int
    {
        return (int) Stock::where('product_id', $productId)->where('branch_id', $branchId)->value('quantity');
    }

    public function inventoryValue(?int $branchId = null): float
    {
        return round((float) Stock::when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('COALESCE(SUM(quantity * avg_cost), 0) as value')->value('value'), 2, PHP_ROUND_HALF_UP);
    }

    public function estimateCost(int $productId, int $branchId, int $quantity): float
    {
        $cost = Stock::where('product_id', $productId)->where('branch_id', $branchId)->value('avg_cost') ?? 0;

        return round($quantity * (float) $cost, 2, PHP_ROUND_HALF_UP);
    }

    private function logMovement(Stock $stock, StockMovementType $type, int $quantity, float $unitCost, ?Model $reference, ?int $userId, ?string $note): void
    {
        StockMovement::create([
            'product_id' => $stock->product_id, 'branch_id' => $stock->branch_id,
            'type' => $type, 'quantity' => $quantity, 'balance_after' => $stock->quantity,
            'unit_cost' => $unitCost, 'cost_total' => round(abs($quantity) * $unitCost, 2, PHP_ROUND_HALF_UP),
            'avg_cost_after' => $stock->avg_cost,
            'reference_type' => $reference?->getMorphClass(), 'reference_id' => $reference?->getKey(),
            'user_id' => $userId, 'note' => $note,
        ]);
    }
}
