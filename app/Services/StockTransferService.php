<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Enums\StockRequestStatus;
use App\Enums\TransferStatus;
use App\Models\StockRequest;
use App\Models\StockTransfer;
use App\Services\Concerns\GeneratesDocumentCode;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * HF-03 Modul Mutasi Stok (gudang utama -> cabang).
 *
 * Saat dikirim, barang keluar dari gudang memakai FIFO sehingga harga pokok
 * tiap lapisan diketahui. Saat diterima cabang, lapisan tersebut dibentuk
 * ulang di cabang dengan harga yang sama -> riwayat harga beli tetap terbawa.
 */
class StockTransferService
{
    use GeneratesDocumentCode;

    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * @param  list<array{product_id: int, quantity: int}>  $items
     */
    public function create(int $fromBranchId, int $toBranchId, array $items, ?string $note = null, ?StockRequest $request = null): StockTransfer
    {
        if ($fromBranchId === $toBranchId) {
            throw new RuntimeException('Lokasi asal dan tujuan mutasi stok tidak boleh sama.');
        }

        return DB::transaction(function () use ($fromBranchId, $toBranchId, $items, $note, $request) {
            $transfer = StockTransfer::create([
                'code' => $this->nextDocumentCode('MT', 'stock_transfers'),
                'from_branch_id' => $fromBranchId,
                'to_branch_id' => $toBranchId,
                'stock_request_id' => $request?->id,
                'status' => TransferStatus::Draft,
                'note' => $note,
            ]);

            foreach ($items as $item) {
                if ((int) $item['quantity'] <= 0) {
                    continue;
                }

                $transfer->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity_sent' => $item['quantity'],
                ]);
            }

            return $transfer->load('items');
        });
    }

    /** Membuat dokumen mutasi dari request stok cabang yang telah disetujui. */
    public function createFromRequest(StockRequest $request, int $fromBranchId, ?string $note = null): StockTransfer
    {
        if ($request->status !== StockRequestStatus::Disetujui) {
            throw new RuntimeException('Request stok belum disetujui.');
        }

        $items = $request->items
            ->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity_approved > 0 ? $item->quantity_approved : $item->quantity_requested,
            ])
            ->all();

        return $this->create($fromBranchId, $request->branch_id, $items, $note ?? 'Pemenuhan '.$request->code, $request);
    }

    /** Pengiriman: stok keluar dari gudang asal memakai FIFO. */
    public function ship(StockTransfer $transfer, int $userId): StockTransfer
    {
        if (! $transfer->isDraft()) {
            throw new RuntimeException('Dokumen mutasi stok ini sudah dikirim atau dibatalkan.');
        }

        return DB::transaction(function () use ($transfer, $userId) {
            $totalCost = 0.0;

            foreach ($transfer->items as $item) {
                $result = $this->inventory->issue(
                    productId: $item->product_id,
                    branchId: $transfer->from_branch_id,
                    quantity: $item->quantity_sent,
                    consumer: $item,
                    type: StockMovementType::TransferKeluar,
                    userId: $userId,
                    note: 'Mutasi stok '.$transfer->code,
                );

                $item->update([
                    'cost_total' => $result['cost_total'],
                    'cost_layers' => $result['allocations'],
                ]);

                $totalCost += $result['cost_total'];
            }

            $transfer->update([
                'status' => TransferStatus::Dikirim,
                'shipped_by' => $userId,
                'shipped_at' => now(),
                'total_cost' => round($totalCost, 2),
            ]);

            return $transfer->fresh('items');
        });
    }

    /**
     * Penerimaan di cabang. Lapisan FIFO dibentuk ulang di cabang dengan harga
     * pokok yang sama. Selisih kurang (barang tidak sampai) dikembalikan ke
     * gudang asal sebagai penyesuaian agar stok tetap seimbang.
     *
     * @param  array<int, int>  $receivedQuantities  [stock_transfer_item_id => jumlah diterima]
     */
    public function receive(StockTransfer $transfer, int $userId, array $receivedQuantities = []): StockTransfer
    {
        if (! $transfer->isShipped()) {
            throw new RuntimeException('Hanya dokumen berstatus dikirim yang dapat diterima.');
        }

        return DB::transaction(function () use ($transfer, $userId, $receivedQuantities) {
            $totalCost = 0.0;

            foreach ($transfer->items as $item) {
                $received = (int) ($receivedQuantities[$item->id] ?? $item->quantity_sent);
                $received = max(0, min($received, $item->quantity_sent));

                $outstanding = $received;
                $itemCost = 0.0;
                $returns = [];

                foreach (($item->cost_layers ?? []) as $layer) {
                    $layerQuantity = (int) $layer['quantity'];
                    $unitCost = (float) $layer['unit_cost'];
                    $taken = min($outstanding, $layerQuantity);

                    if ($taken > 0) {
                        $this->inventory->receive(
                            productId: $item->product_id,
                            branchId: $transfer->to_branch_id,
                            quantity: $taken,
                            unitCost: $unitCost,
                            reference: $item,
                            type: StockMovementType::TransferMasuk,
                            receivedAt: now(),
                            userId: $userId,
                            note: 'Penerimaan mutasi '.$transfer->code,
                        );

                        $itemCost += $taken * $unitCost;
                        $outstanding -= $taken;
                    }

                    if ($layerQuantity - $taken > 0) {
                        $returns[] = ['quantity' => $layerQuantity - $taken, 'unit_cost' => $unitCost];
                    }
                }

                foreach ($returns as $return) {
                    $this->inventory->receive(
                        productId: $item->product_id,
                        branchId: $transfer->from_branch_id,
                        quantity: $return['quantity'],
                        unitCost: $return['unit_cost'],
                        reference: $item,
                        type: StockMovementType::PenyesuaianMasuk,
                        receivedAt: now(),
                        userId: $userId,
                        note: 'Selisih penerimaan mutasi '.$transfer->code,
                    );
                }

                $item->update([
                    'quantity_received' => $received,
                    'cost_total' => round($itemCost, 2),
                ]);

                $totalCost += $itemCost;
            }

            $transfer->update([
                'status' => TransferStatus::Diterima,
                'received_by' => $userId,
                'received_at' => now(),
                'total_cost' => round($totalCost, 2),
            ]);

            $transfer->stockRequest?->update(['status' => StockRequestStatus::Terpenuhi]);

            return $transfer->fresh('items');
        });
    }

    /** Pembatalan dokumen: hanya selama masih draft (belum ada stok bergerak). */
    public function cancel(StockTransfer $transfer): StockTransfer
    {
        if (! $transfer->isDraft()) {
            throw new RuntimeException('Dokumen yang sudah dikirim tidak dapat dibatalkan.');
        }

        $transfer->update(['status' => TransferStatus::Dibatalkan]);

        return $transfer;
    }
}
