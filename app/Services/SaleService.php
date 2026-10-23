<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\CashierShift;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SaleService
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function checkout(array $payload): Sale
    {
        // UUID hanya untuk retry permintaan ONLINE; tidak ada antrean/sinkronisasi offline.
        $uuid = $payload['request_id'];
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($payload, $uuid, $hash) {
            $user = User::whereKey($payload['user_id'])->lockForUpdate()->firstOrFail();
            if (! $user->is_active || ! $user->isKasir()) {
                throw new RuntimeException('Hanya kasir aktif yang dapat menjual.');
            }
            $existing = Sale::where('client_uuid', $uuid)->first();
            if ($existing) {
                if ($existing->user_id !== $user->id || $existing->request_hash !== $hash) {
                    throw new RuntimeException('ID permintaan sudah dipakai untuk transaksi berbeda.');
                }

                return $existing->load('items');
            }
            $shift = CashierShift::whereKey($payload['cashier_shift_id'])->where('user_id', $user->id)->where('branch_id', $user->branch_id)->lockForUpdate()->first();
            if (! $shift || ! $shift->isOpen()) {
                throw new RuntimeException('Buka shift sebelum bertransaksi. Shift ini sudah tidak aktif.');
            }
            if (empty($payload['items'])) {
                throw new RuntimeException('Keranjang tidak boleh kosong.');
            }
            $method = PaymentMethod::from($payload['payment_method']);
            $sale = Sale::create([
                'client_uuid' => $uuid, 'request_hash' => $hash,
                'invoice_no' => 'INV-'.now()->format('ymd').'-'.strtoupper((string) Str::ulid()),
                'branch_id' => $user->branch_id, 'user_id' => $user->id, 'cashier_shift_id' => $shift->id,
                'customer_name' => $payload['customer_name'] ?? null, 'sold_at' => now(),
                'payment_method' => $method, 'discount' => 0, 'status' => SaleStatus::Selesai, 'note' => $payload['note'] ?? null,
            ]);
            $subtotal = 0.0;
            $cogs = 0.0;
            $rows = collect($payload['items'])->sortBy('product_id');
            foreach ($rows as $row) {
                $product = Product::query()->active()->whereKey($row['product_id'])->lockForUpdate()->first();
                if (! $product) {
                    throw new RuntimeException('Barang tidak aktif atau sudah diarsipkan. Muat ulang daftar barang.');
                }
                $qty = (int) $row['quantity'];
                if ($qty <= 0) {
                    throw new RuntimeException('Jumlah harus lebih dari nol.');
                }
                // Harga SELALU dari server, bukan dari isian request kasir.
                $price = round((float) $product->sell_price, 2, PHP_ROUND_HALF_UP);
                if (isset($row['expected_price']) && round((float) $row['expected_price'], 2) !== $price) {
                    throw new RuntimeException('Harga barang berubah. Hapus barang dari keranjang lalu cari ulang sebelum membayar.');
                }
                $line = round($price * $qty, 2, PHP_ROUND_HALF_UP);
                $item = $sale->items()->create(['product_id' => $product->id, 'product_name' => $product->name,
                    'quantity' => $qty, 'unit_price' => $price, 'discount' => 0, 'subtotal' => $line]);
                $cost = $this->inventory->issue($product->id, $user->branch_id, $qty, $item, userId: $user->id, note: 'Penjualan '.$sale->invoice_no);
                $item->update(['unit_cost' => $cost['unit_cost'], 'cogs_total' => $cost['cost_total'], 'gross_profit' => round($line - $cost['cost_total'], 2)]);
                $subtotal += $line;
                $cogs += $cost['cost_total'];
            }
            $paid = $method === PaymentMethod::Tunai ? round((float) $payload['paid_amount'], 2) : $subtotal;
            if ($method === PaymentMethod::Tunai && $paid < $subtotal) {
                throw new RuntimeException('Pembayaran tunai kurang.');
            }
            $sale->update(['subtotal' => $subtotal, 'total' => $subtotal, 'paid_amount' => $paid, 'change_amount' => max($paid - $subtotal, 0),
                'cogs_total' => $cogs, 'gross_profit' => round($subtotal - $cogs, 2)]);
            if ($method === PaymentMethod::Tunai) {
                $shift->increment('expected_cash', $subtotal);
            }

            return $sale->fresh('items');
        }, 3);
    }

}
