<?php

namespace App\Services;

use App\Enums\PurchaseStatus;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Purchase;
use App\Services\Concerns\GeneratesDocumentCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * HF-03 Penerimaan barang dari pemasok.
 * Saat dokumen diposting, tiap baris membentuk lapisan FIFO baru di gudang.
 */
class PurchaseService
{
    use GeneratesDocumentCode;

    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{product_id: int, quantity: int, unit_cost: float}>  $items
     */
    public function create(array $data, array $items, int $userId): Purchase
    {
        return DB::transaction(function () use ($data, $items, $userId) {
            $purchase = Purchase::create([
                'code' => $this->nextDocumentCode('PB', 'purchases'),
                'supplier_id' => $data['supplier_id'],
                'branch_id' => $data['branch_id'],
                'user_id' => $userId,
                'purchase_date' => $data['purchase_date'] ?? now()->toDateString(),
                'status' => PurchaseStatus::Draft,
                'note' => $data['note'] ?? null,
            ]);

            $total = 0.0;

            foreach ($items as $item) {
                $subtotal = round($item['quantity'] * $item['unit_cost'], 2);
                $total += $subtotal;

                $purchase->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'subtotal' => $subtotal,
                ]);
            }

            $purchase->update(['total_cost' => round($total, 2)]);

            return $purchase->load('items');
        });
    }

    /** Memposting dokumen: stok bertambah dan lapisan FIFO terbentuk. */
    public function post(Purchase $purchase, int $userId): Purchase
    {
        if (! $purchase->isDraft()) {
            throw new RuntimeException('Dokumen penerimaan barang ini sudah diposting atau dibatalkan.');
        }

        return DB::transaction(function () use ($purchase, $userId) {
            $receivedAt = Carbon::parse($purchase->purchase_date)->setTimeFrom(now());

            foreach ($purchase->items as $item) {
                $this->inventory->receive(
                    productId: $item->product_id,
                    branchId: $purchase->branch_id,
                    quantity: $item->quantity,
                    unitCost: (float) $item->unit_cost,
                    reference: $purchase,
                    type: StockMovementType::PembelianMasuk,
                    receivedAt: $receivedAt,
                    userId: $userId,
                    note: 'Penerimaan barang '.$purchase->code,
                );
            }

            $purchase->update([
                'status' => PurchaseStatus::Posted,
                'posted_at' => now(),
            ]);

            return $purchase->fresh('items');
        });
    }

    public function cancel(Purchase $purchase): Purchase
    {
        if (! $purchase->isDraft()) {
            throw new RuntimeException('Hanya dokumen berstatus draft yang dapat dibatalkan.');
        }

        $purchase->update(['status' => PurchaseStatus::Cancelled]);

        return $purchase;
    }

    /** Harga beli terakhir sebagai saran pengisian form. */
    public function lastUnitCost(Product $product): ?float
    {
        $value = $product->inventoryLayers()->latest('received_at')->value('unit_cost');

        return $value !== null ? (float) $value : null;
    }
}
