<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * HF-04 Transaksi penjualan (POS) + HF-05 perhitungan HPP & laba kotor.
 *
 * Setiap baris penjualan mengambil persediaan dengan FIFO, sehingga HPP dan
 * laba kotor dihitung otomatis dari harga beli batch yang benar-benar terpakai.
 */
class SaleService
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * @param  array{branch_id: int, user_id: int, cashier_shift_id?: int|null, customer_name?: string|null, payment_method?: string, discount?: float, paid_amount?: float, note?: string|null, items: list<array{product_id: int, quantity: int, unit_price?: float, discount?: float}>}  $payload
     */
    public function checkout(array $payload): Sale
    {
        if (empty($payload['items'])) {
            throw new RuntimeException('Transaksi tidak boleh kosong.');
        }

        return DB::transaction(function () use ($payload) {
            $branchId = (int) $payload['branch_id'];
            $paymentMethod = PaymentMethod::from($payload['payment_method'] ?? PaymentMethod::Tunai->value);
            $billDiscount = round((float) ($payload['discount'] ?? 0), 2);

            $sale = Sale::create([
                'invoice_no' => $this->nextInvoiceNumber($branchId),
                'branch_id' => $branchId,
                'user_id' => (int) $payload['user_id'],
                'cashier_shift_id' => $payload['cashier_shift_id'] ?? null,
                'customer_name' => $payload['customer_name'] ?? null,
                'sold_at' => now(),
                'payment_method' => $paymentMethod,
                'discount' => $billDiscount,
                'status' => SaleStatus::Selesai,
                'note' => $payload['note'] ?? null,
            ]);

            $subtotal = 0.0;
            $cogsTotal = 0.0;

            foreach ($payload['items'] as $row) {
                $product = Product::findOrFail($row['product_id']);
                $quantity = (int) $row['quantity'];

                if ($quantity <= 0) {
                    throw new RuntimeException("Jumlah barang {$product->name} harus lebih dari nol.");
                }

                $unitPrice = round((float) ($row['unit_price'] ?? $product->sell_price), 2);
                $itemDiscount = round((float) ($row['discount'] ?? 0), 2);
                $lineSubtotal = round($quantity * $unitPrice - $itemDiscount, 2);

                $item = $sale->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => $itemDiscount,
                    'subtotal' => $lineSubtotal,
                ]);

                // Pengeluaran stok FIFO -> menghasilkan HPP baris ini.
                $result = $this->inventory->issue(
                    productId: $product->id,
                    branchId: $branchId,
                    quantity: $quantity,
                    consumer: $item,
                    type: StockMovementType::PenjualanKeluar,
                    userId: (int) $payload['user_id'],
                    note: 'Penjualan '.$sale->invoice_no,
                );

                $item->update([
                    'cogs_total' => $result['cost_total'],
                    'gross_profit' => round($lineSubtotal - $result['cost_total'], 2),
                ]);

                $subtotal += $lineSubtotal;
                $cogsTotal += $result['cost_total'];
            }

            $total = round($subtotal - $billDiscount, 2);

            if ($total < 0) {
                throw new RuntimeException('Diskon melebihi nilai transaksi.');
            }

            $paid = round((float) ($payload['paid_amount'] ?? $total), 2);

            if ($paymentMethod === PaymentMethod::Tunai && $paid < $total) {
                throw new RuntimeException('Jumlah pembayaran kurang dari total transaksi.');
            }

            $sale->update([
                'subtotal' => round($subtotal, 2),
                'total' => $total,
                'paid_amount' => $paid,
                'change_amount' => round(max($paid - $total, 0), 2),
                'cogs_total' => round($cogsTotal, 2),
                'gross_profit' => round($total - $cogsTotal, 2),
            ]);

            // HF-06: perkiraan uang tunai di laci kasir mengikuti penjualan tunai.
            if ($paymentMethod === PaymentMethod::Tunai && $sale->cashier_shift_id) {
                $sale->shift?->increment('expected_cash', $total);
            }

            return $sale->fresh('items');
        });
    }

    /** Pembatalan transaksi: stok dan lapisan FIFO dikembalikan seperti semula. */
    public function void(Sale $sale, int $userId, ?string $reason = null): Sale
    {
        if (! $sale->isCompleted()) {
            throw new RuntimeException('Transaksi ini sudah dibatalkan.');
        }

        return DB::transaction(function () use ($sale, $userId, $reason) {
            foreach ($sale->items as $item) {
                $this->inventory->restore(
                    consumer: $item,
                    type: StockMovementType::PembatalanPenjualan,
                    userId: $userId,
                    note: 'Pembatalan '.$sale->invoice_no,
                );
            }

            if ($sale->payment_method === PaymentMethod::Tunai && $sale->cashier_shift_id) {
                $sale->shift?->decrement('expected_cash', (float) $sale->total);
            }

            $sale->update([
                'status' => SaleStatus::Dibatalkan,
                'voided_by' => $userId,
                'voided_at' => now(),
                'note' => $reason ?? $sale->note,
            ]);

            return $sale->fresh('items');
        });
    }

    /** Nomor struk harian per cabang: INV-CBG01-260908-0001 */
    public function nextInvoiceNumber(int $branchId): string
    {
        $code = str_replace('-', '', (string) Branch::query()->whereKey($branchId)->value('code'));
        $base = 'INV-'.$code.'-'.now()->format('ymd').'-';

        $last = Sale::query()->where('invoice_no', 'like', $base.'%')->max('invoice_no');
        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $base.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
