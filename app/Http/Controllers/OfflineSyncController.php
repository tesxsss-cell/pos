<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\Sale;
use App\Services\BarcodeService;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mendukung mode offline halaman kasir.
 *
 * - catalog():      mengirim katalog + stok + daftar barcode agar bisa disimpan di IndexedDB.
 * - store():        menerima antrian transaksi yang dibuat saat jaringan mati.
 * - storeBarcodes():menerima antrian pendaftaran barcode yang dibuat saat jaringan mati.
 */
class OfflineSyncController extends Controller
{
    public function __construct(
        private readonly SaleService $sales,
        private readonly BarcodeService $barcodes,
    ) {
    }

    /**
     * Katalog ringan untuk disimpan di peramban kasir.
     * Setiap barang membawa daftar barcode beserta harga & jumlah bawaannya,
     * sehingga pemindaian tetap berjalan walau jaringan mati.
     */
    public function catalog(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchId = $user->scopedBranchId() ?? (int) $request->integer('cabang');

        $products = $this->barcodes->productQuery($branchId)
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => $this->barcodes->present($product, $branchId));

        return response()->json([
            'branch_id' => $branchId,
            'server_time' => now()->toIso8601String(),
            'data' => $products,
        ]);
    }

    /**
     * Menerima kumpulan transaksi offline lalu memprosesnya satu per satu.
     *
     * Hasil per transaksi dikembalikan agar peramban tahu mana yang boleh
     * dihapus dari antrian dan mana yang perlu ditinjau kasir.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sales' => ['required', 'array', 'min:1', 'max:50'],
            'sales.*.client_uuid' => ['required', 'string', 'max:36'],
            'sales.*.sold_at' => ['nullable', 'date'],
            'sales.*.customer_name' => ['nullable', 'string', 'max:120'],
            'sales.*.payment_method' => ['required', 'string', 'in:tunai,transfer,qris,debit'],
            'sales.*.discount' => ['nullable', 'numeric', 'min:0'],
            'sales.*.paid_amount' => ['nullable', 'numeric', 'min:0'],
            'sales.*.note' => ['nullable', 'string', 'max:255'],
            'sales.*.items' => ['required', 'array', 'min:1'],
            'sales.*.items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'sales.*.items.*.quantity' => ['required', 'integer', 'min:1'],
            'sales.*.items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'sales.*.items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $user = $request->user();
        $branchId = $user->scopedBranchId();

        if (! $branchId) {
            return response()->json(['message' => 'Akun ini tidak terikat pada cabang mana pun.'], 422);
        }

        $shift = $user->openShift();
        $hasil = [];

        foreach ($data['sales'] as $offline) {
            $sudahAda = Sale::query()->where('client_uuid', $offline['client_uuid'])->first();

            if ($sudahAda) {
                $hasil[] = [
                    'client_uuid' => $offline['client_uuid'],
                    'status' => 'duplikat',
                    'invoice_no' => $sudahAda->invoice_no,
                ];

                continue;
            }

            try {
                $sale = $this->sales->checkout([
                    'branch_id' => $branchId,
                    'user_id' => $user->id,
                    'cashier_shift_id' => $shift?->id,
                    'customer_name' => $offline['customer_name'] ?? null,
                    'payment_method' => $offline['payment_method'],
                    'discount' => $offline['discount'] ?? 0,
                    'paid_amount' => $offline['paid_amount'] ?? 0,
                    'note' => trim(($offline['note'] ?? '').' [offline]'),
                    'items' => $offline['items'],
                ]);

                $sale->forceFill([
                    'client_uuid' => $offline['client_uuid'],
                    'synced_at' => now(),
                    'sold_at' => isset($offline['sold_at'])
                        ? Carbon::parse($offline['sold_at'])
                        : $sale->sold_at,
                ])->save();

                $hasil[] = [
                    'client_uuid' => $offline['client_uuid'],
                    'status' => 'tersimpan',
                    'invoice_no' => $sale->invoice_no,
                    'receipt_url' => route('pos.receipt', $sale),
                ];
            } catch (InsufficientStockException $e) {
                $hasil[] = [
                    'client_uuid' => $offline['client_uuid'],
                    'status' => 'gagal',
                    'message' => $e->getMessage(),
                ];
            } catch (Throwable $e) {
                Log::error('Gagal sinkronisasi transaksi offline', [
                    'client_uuid' => $offline['client_uuid'],
                    'error' => $e->getMessage(),
                ]);

                $hasil[] = [
                    'client_uuid' => $offline['client_uuid'],
                    'status' => 'gagal',
                    'message' => 'Transaksi tidak dapat diproses server.',
                ];
            }
        }

        return response()->json([
            'message' => 'Sinkronisasi selesai.',
            'results' => $hasil,
        ]);
    }

    /**
     * HF-04: pendaftaran barcode yang dibuat kasir saat jaringan mati.
     * Barcode disimpan lokal lebih dahulu, lalu dikirim ke server di sini.
     */
    public function storeBarcodes(Request $request): JsonResponse
    {
        $data = $request->validate([
            'barcodes' => ['required', 'array', 'min:1', 'max:50'],
            'barcodes.*.client_uuid' => ['required', 'string', 'max:36'],
            'barcodes.*.barcode' => ['required', 'string', 'max:80'],
            'barcodes.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'barcodes.*.name' => ['nullable', 'string', 'max:150'],
            'barcodes.*.sell_price' => ['nullable', 'numeric', 'min:0'],
            'barcodes.*.quantity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'barcodes.*.replace' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $hasil = [];

        foreach ($data['barcodes'] as $offline) {
            try {
                $barcode = $this->barcodes->register([
                    'barcode' => $offline['barcode'],
                    'product_id' => $offline['product_id'] ?? null,
                    'name' => $offline['name'] ?? null,
                    'sell_price' => $offline['sell_price'] ?? null,
                    'default_quantity' => $offline['quantity'] ?? 1,
                    'update_product_price' => false,   // harga master tidak diubah lewat jalur offline
                    'replace' => (bool) ($offline['replace'] ?? false),
                ], $user);

                $hasil[] = [
                    'client_uuid' => $offline['client_uuid'],
                    'status' => 'tersimpan',
                    'barcode' => $barcode->barcode,
                    'product_id' => $barcode->product_id,
                ];
            } catch (Throwable $e) {
                Log::warning('Gagal sinkronisasi pendaftaran barcode', [
                    'client_uuid' => $offline['client_uuid'],
                    'error' => $e->getMessage(),
                ]);

                $hasil[] = [
                    'client_uuid' => $offline['client_uuid'],
                    'status' => 'gagal',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'message' => 'Sinkronisasi barcode selesai.',
            'results' => $hasil,
        ]);
    }
}
