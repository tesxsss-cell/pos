<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\CashierShift;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\Sale;
use App\Services\BarcodeService;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * HF-04 Transaksi Penjualan (POS): pencarian/pemindaian barcode, pendaftaran
 * barcode barang dari halaman kasir, checkout, struk, pembatalan transaksi,
 * serta buka/tutup shift kasir (HF-06).
 */
class PosController extends Controller
{
    public function __construct(
        private readonly SaleService $sales,
        private readonly BarcodeService $barcodes,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_if(! $user->branch_id, 403, 'Akun Anda belum ditempatkan pada cabang mana pun.');

        // Shift kasir dibuat otomatis sehingga selalu terbuka. Kasir tetap bisa
        // menutup shift lewat tombol "Tutup shift"; shift baru akan dibuka lagi
        // otomatis saat halaman kasir dibuka kembali.
        $shift = $user->openShift();

        if (! $shift) {
            $shift = CashierShift::create([
                'branch_id' => $user->branch_id,
                'user_id' => $user->id,
                'opened_at' => now(),
                'opening_cash' => 0,
                'expected_cash' => 0,
                'status' => 'dibuka',
            ]);
        }

        return view('pos.index', [
            'branch' => $user->branch,
            'shift' => $shift,
            'paymentMethods' => PaymentMethod::options(),
            // Hanya peran ini yang boleh mengubah harga master dari halaman kasir.
            'canUpdatePrice' => $user->hasRole('admin', 'pemilik', 'manager_cabang'),
            'recentSales' => Sale::query()
                ->where('branch_id', $user->branch_id)
                ->where('user_id', $user->id)
                ->latest('sold_at')
                ->limit(10)
                ->get(),
        ]);
    }

    /**
     * HF-04 Daftar produk cabang untuk kasir. Menampilkan barang milik cabang
     * kasir (nama, harga, stok, barcode terdaftar) dalam bentuk tabel. Tiap
     * baris punya aksi "Daftarkan barcode" / "Ganti barcode" yang membuka pop up
     * pemindai barcode.
     */
    public function products(Request $request): View
    {
        $user = $request->user();
        abort_if(! $user->branch_id, 403, 'Akun Anda belum ditempatkan pada cabang mana pun.');

        $branchId = $user->branch_id;
        $kataKunci = trim($request->string('q')->toString());

        $paginator = $this->barcodes->productQuery($branchId)
            ->active()
            ->when($kataKunci !== '', fn ($query) => $query->search($kataKunci))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $rows = $paginator->getCollection()
            ->map(fn (Product $product) => $this->barcodes->present($product, $branchId))
            ->values()
            ->all();

        return view('pos.products', [
            'branch' => $user->branch,
            'paginator' => $paginator,
            'rows' => $rows,
            'q' => $kataKunci,
            // Hanya peran ini yang boleh mengubah harga master; kasir tidak.
            'canUpdatePrice' => $user->hasRole('admin', 'pemilik', 'manager_cabang'),
        ]);
    }

    /** Dipakai input pemindaian barcode/QR maupun pencarian manual. */
    public function lookup(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:1']]);

        $branchId = $request->user()->branch_id;
        $kataKunci = trim($request->string('q')->toString());
        $kode = $this->barcodes->normalize($kataKunci);

        $terdaftar = $this->barcodes->resolve($kode);
        $barangBarcode = $terdaftar?->product
            ?? ($kode !== '' ? Product::query()->where('barcode', $kode)->first() : null);

        $daftar = $this->barcodes->productQuery($branchId)
            ->active()
            ->search($kataKunci)
            ->orderBy('name')
            ->limit(15)
            ->get();

        // Barang pemilik barcode selalu di urutan pertama walau namanya tidak mirip.
        if ($barangBarcode && ! $daftar->contains('id', $barangBarcode->id)) {
            $daftar->prepend(
                $this->barcodes->productQuery($branchId)->whereKey($barangBarcode->id)->first() ?? $barangBarcode
            );
        }

        $data = $daftar
            ->map(fn (Product $product) => $this->barcodes->present(
                $product,
                $branchId,
                $terdaftar && $terdaftar->product_id === $product->id ? $terdaftar : null,
            ))
            ->values();

        return response()->json([
            'data' => $data,
            'scanned_barcode' => $kode !== '' ? $kode : null,
            'barcode_registered' => $kode !== '' && $barangBarcode !== null,
        ]);
    }

    /**
     * Cek satu hasil pemindaian: barcode sudah terdaftar atau belum.
     * Bila sudah, informasi barang (harga & jumlah tersimpan) langsung dikirim.
     */
    public function resolveBarcode(Request $request): JsonResponse
    {
        $request->validate(['barcode' => ['required', 'string', 'max:80']]);

        $branchId = $request->user()->branch_id;
        $kode = $this->barcodes->normalize($request->string('barcode')->toString());

        $terdaftar = $this->barcodes->resolve($kode);
        $product = $terdaftar?->product
            ?? ($kode !== '' ? Product::query()->where('barcode', $kode)->first() : null);

        if (! $product instanceof Product) {
            return response()->json([
                'barcode' => $kode,
                'registered' => false,
                'product' => null,
                'message' => 'Barcode belum terdaftar. Ketik nama barangnya untuk dicocokkan.',
            ]);
        }

        $product = $this->barcodes->productQuery($branchId)->whereKey($product->id)->first() ?? $product;

        return response()->json([
            'barcode' => $kode,
            'registered' => true,
            'product' => $this->barcodes->present($product, $branchId, $terdaftar),
            'message' => $product->name.' siap dijual.',
        ]);
    }

    /** Rekomendasi barang dari nama yang diketik kasir pada panel pendaftaran barcode. */
    public function suggestProducts(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:1']]);

        $branchId = $request->user()->branch_id;

        $data = $this->barcodes->suggest($request->string('q')->toString())
            ->map(fn (Product $product) => $this->barcodes->present($product, $branchId))
            ->values();

        return response()->json(['data' => $data]);
    }

    /**
     * Simpan pemetaan barcode -> barang (harga & jumlah bawaan ikut disimpan).
     * Harga yang sama dengan harga master disimpan sebagai "ikut master", sehingga
     * harga tetap sama selama harga master tidak diubah.
     */
    public function storeBarcode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'barcode' => ['required', 'string', 'max:80'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'name' => ['nullable', 'string', 'max:150'],
            'sell_price' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'update_product_price' => ['nullable', 'boolean'],
            'replace' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();

        if (blank($data['product_id'] ?? null) && blank($data['name'] ?? null)) {
            return response()->json([
                'message' => 'Ketik nama barang lebih dahulu lalu pilih rekomendasinya.',
                'suggestions' => [],
            ], 422);
        }

        try {
            $barcode = $this->barcodes->register([
                'barcode' => $data['barcode'],
                'product_id' => $data['product_id'] ?? null,
                'name' => $data['name'] ?? null,
                'sell_price' => $data['sell_price'] ?? null,
                'default_quantity' => $data['quantity'] ?? 1,
                'update_product_price' => (bool) ($data['update_product_price'] ?? false),
                'replace' => (bool) ($data['replace'] ?? false),
            ], $user);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'suggestions' => $this->barcodes->suggest($data['name'] ?? null)
                    ->map(fn (Product $product) => $this->barcodes->present($product, $user->branch_id))
                    ->values(),
            ], 422);
        }

        $product = $this->barcodes->productQuery($user->branch_id)
            ->whereKey($barcode->product_id)
            ->first();

        return response()->json([
            'message' => 'Barcode '.$barcode->barcode.' tersimpan untuk '.$product->name.'.',
            'barcode' => $barcode->barcode,
            'product' => $this->barcodes->present($product, $user->branch_id, $barcode),
        ]);
    }

    /** Lepas barcode dari barang (mis. salah tempel / barcode pabrik berubah). */
    public function destroyBarcode(Request $request, ProductBarcode $productBarcode): JsonResponse
    {
        $nama = $productBarcode->product?->name ?? 'barang';
        $kode = $this->barcodes->unregister($productBarcode);

        return response()->json([
            'message' => 'Barcode '.$kode.' dilepas dari '.$nama.'.',
        ]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_name' => ['nullable', 'string', 'max:100'],
            'payment_method' => ['required', 'string', 'in:'.implode(',', array_keys(PaymentMethod::options()))],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $user = $request->user();

        try {
            $sale = $this->sales->checkout([
                ...$data,
                'branch_id' => $user->branch_id,
                'user_id' => $user->id,
                'cashier_shift_id' => $user->openShift()?->id,
            ]);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Transaksi berhasil disimpan.',
            'invoice_no' => $sale->invoice_no,
            'total' => (float) $sale->total,
            'change_amount' => (float) $sale->change_amount,
            'receipt_url' => route('pos.receipt', $sale),
        ]);
    }

    public function receipt(Request $request, Sale $sale): View
    {
        $this->authorizeBranch($request, $sale->branch_id);

        return view('pos.receipt', [
            'sale' => $sale->load(['items', 'branch', 'cashier']),
        ]);
    }

    /** Pembatalan transaksi: stok & lapisan FIFO dikembalikan. */
    public function void(Request $request, Sale $sale): RedirectResponse
    {
        $this->authorizeBranch($request, $sale->branch_id);

        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        try {
            $this->sales->void($sale, $request->user()->id, $data['reason']);
        } catch (Throwable $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return back()->with('status', "Transaksi {$sale->invoice_no} dibatalkan.");
    }

    public function openShift(Request $request): RedirectResponse
    {
        $data = $request->validate(['opening_cash' => ['required', 'numeric', 'min:0']]);
        $user = $request->user();

        if ($user->openShift()) {
            return back()->withErrors(['opening_cash' => 'Masih ada shift yang belum ditutup.']);
        }

        CashierShift::create([
            'branch_id' => $user->branch_id,
            'user_id' => $user->id,
            'opened_at' => now(),
            'opening_cash' => $data['opening_cash'],
            'expected_cash' => $data['opening_cash'],
            'status' => 'dibuka',
        ]);

        return back()->with('status', 'Shift kasir dibuka.');
    }

    /** Tutup shift: bandingkan uang fisik di laci dengan catatan sistem. */
    public function closeShift(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'actual_cash' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $shift = $request->user()->openShift();

        if (! $shift) {
            return back()->withErrors(['actual_cash' => 'Tidak ada shift yang sedang dibuka.']);
        }

        $shift->update([
            'closed_at' => now(),
            'actual_cash' => $data['actual_cash'],
            'difference' => round($data['actual_cash'] - (float) $shift->expected_cash, 2),
            'status' => 'ditutup',
            'note' => $data['note'] ?? null,
        ]);

        return back()->with('status', 'Shift kasir ditutup. Selisih: Rp '.number_format((float) $shift->fresh()->difference, 0, ',', '.'));
    }

    private function authorizeBranch(Request $request, int $branchId): void
    {
        $user = $request->user();

        abort_if(! $user->isCentral() && $user->branch_id !== $branchId, 403, 'Transaksi ini bukan milik cabang Anda.');
    }
}
