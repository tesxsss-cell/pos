<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\Branch;
use Illuminate\Validation\ValidationException;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

class PosController extends Controller
{
    public function __construct(private readonly SaleService $sales) {}

    public function index(Request $request)
    {
        return view('pos.index', ['shift' => $request->user()->openShift()]);
    }

    public function products(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'barcode' => ['nullable', 'string', 'max:60']]);
        $q = trim($data['q'] ?? '');
        $products = Product::query()->active()->search($q)
            ->with(['barcodes', 'stocks' => fn ($query) => $query->where('branch_id', $request->user()->branch_id)])
            ->orderBy('name')->orderBy('id')->paginate(10)->withQueryString();

        $initialCode = $data['barcode'] ?? '';
        return view('pos.products', compact('products', 'q', 'initialCode'));
    }

    public function history(Request $request)
    {
        $data = $request->validate([
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::in(['selesai', 'dibatalkan'])],
        ]);
        $from = $data['dari'] ?? null;
        $to = $data['sampai'] ?? null;
        if ($from && $to && $to < $from) {
            throw ValidationException::withMessages(['sampai' => 'Tanggal akhir tidak boleh sebelum tanggal awal.']);
        }
        $status = $data['status'] ?? null;
        $sales = Sale::query()->where('user_id', $request->user()->id)
            ->where('branch_id', $request->user()->branch_id)
            ->when($from, fn ($query) => $query->whereDate('sold_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('sold_at', '<=', $to))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByDesc('sold_at')->orderByDesc('id')->paginate(10)->withQueryString();

        return view('pos.history', compact('sales', 'from', 'to', 'status'));
    }

    public function lookup(Request $request)
    {
        $data = $request->validate(['q' => ['required', 'string', 'max:150']]);
        $term = trim($data['q']);
        $code = ProductBarcode::normalize($term);
        $alias = ProductBarcode::where('barcode', $code)->value('product_id');
        $rows = Product::query()->active()->with(['stocks' => fn ($q) => $q->where('branch_id', $request->user()->branch_id)])
            ->where(fn ($q) => $q->where('barcode', $code)->orWhere('sku', 'like', '%'.$term.'%')->orWhere('name', 'like', '%'.$term.'%')
                ->orWhereHas('barcodes', fn ($b) => $b->where('barcode', $code)))
            ->orderByRaw('CASE WHEN barcode = ? OR sku = ? OR id = ? THEN 0 ELSE 1 END', [$code, $term, $alias])->orderBy('name')->orderBy('id')
            ->paginate(10)->withQueryString();
        return response()->json(['data' => $rows->getCollection()->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku,
            'barcode' => $p->barcode, 'matched_barcode' => ((int) $alias === (int) $p->id || $p->barcode === $code) ? $code : null,
            'unit' => $p->unit, 'sell_price' => (float) $p->sell_price, 'stock' => (int) ($p->stocks->first()?->quantity ?? 0)]),
            'current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'total' => $rows->total()]);
    }

    public function storeBarcode(Request $request)
    {
        $request->validate(['barcode' => ['required', 'string', 'max:150']]);
        $request->merge(['barcode' => ProductBarcode::normalize($request->input('barcode'))]);
        $data = $request->validate(['barcode' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9._:\/-]+$/'],
            'product_id' => ['required', 'integer'], 'sell_price' => ['prohibited'], 'default_quantity' => ['prohibited']]);
        return DB::transaction(function () use ($request, $data) {
            Branch::whereKey($request->user()->branch_id)->lockForUpdate()->firstOrFail();
            $product = Product::query()->active()->whereKey($data['product_id'])->lockForUpdate()->first();
            if (! $product) { throw ValidationException::withMessages(['product_id' => 'Barang tidak aktif atau tidak tersedia.']); }
            $master = Product::withTrashed()->where('barcode', $data['barcode'])->first();
            $alias = ProductBarcode::where('barcode', $data['barcode'])->first();
            if (($master && $master->id !== $product->id) || ($alias && $alias->product_id !== $product->id)) {
                throw ValidationException::withMessages(['barcode' => 'Barcode sudah dipakai barang lain.']);
            }
            $row = ProductBarcode::firstOrCreate(['barcode' => $data['barcode']], ['product_id' => $product->id,
                'sell_price' => null, 'default_quantity' => 1, 'branch_id' => $request->user()->branch_id, 'registered_by' => $request->user()->id]);
            return response()->json(['message' => 'Barcode tersimpan. Harga tetap mengikuti master barang.', 'barcode' => $row->barcode]);
        }, 3);
    }

    public function checkout(Request $request)
    {
        $data = $request->validate([
            'request_id' => ['required', 'uuid'], 'cashier_shift_id' => ['required', 'integer'],
            'customer_name' => ['nullable', 'string', 'max:100'],
            'payment_method' => ['required', Rule::in(['tunai', 'qris', 'transfer', 'debit'])],
            'payment_confirmed' => ['required_if:payment_method,qris,transfer,debit', 'accepted_if:payment_method,qris,transfer,debit'],
            'paid_amount' => ['required_if:payment_method,tunai', 'nullable', 'numeric', 'min:0', 'max:999999999999'],
            'note' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.expected_price' => ['sometimes', 'numeric', 'min:0', 'max:999999999'],
            'items.*.unit_price' => ['prohibited'], 'items.*.discount' => ['prohibited'], 'discount' => ['prohibited'],
        ]);
        try {
            $sale = $this->sales->checkout([...$data, 'user_id' => $request->user()->id]);
        } catch (QueryException $e) {
            report($e);

            return response()->json(['message' => 'Server belum dapat mengonfirmasi transaksi. Coba kembali dengan ID permintaan yang sama.'], 503);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Transaksi tersimpan di server.', 'invoice_no' => $sale->invoice_no,
            'total' => (float) $sale->total, 'change_amount' => (float) $sale->change_amount, 'expected_cash' => (float) $sale->shift->fresh()->expected_cash,
            'sold_at_label' => $sale->sold_at->format('d/m H:i'),
            'sale_status' => $sale->status->value,
            'receipt_url' => route('pos.receipt', $sale)]);
    }

    public function receipt(Request $request, Sale $sale)
    {
        abort_if($sale->branch_id !== $request->user()->branch_id, 403);
        abort_if($request->user()->isKasir() && $sale->user_id !== $request->user()->id, 403);

        return view('pos.receipt', ['sale' => $sale->load(['items', 'branch', 'cashier'])]);
    }

    public function sales(Request $request)
    {
        $data = $request->validate(['dari' => ['nullable', 'date'], 'sampai' => ['nullable', 'date', 'after_or_equal:dari']]);
        $from = $data['dari'] ?? now()->toDateString();
        $to = $data['sampai'] ?? now()->toDateString();

        return view('pos.sales', ['from' => $from, 'to' => $to, 'sales' => Sale::where('branch_id', $request->user()->branch_id)
            ->between($from, $to)->with(['cashier', 'shift'])->latest('id')->paginate(10)->withQueryString()]);
    }

    public function void(Request $request, Sale $sale)
    {
        abort_unless($request->user()->isPengelola(), 403);
        abort_if($sale->branch_id !== $request->user()->branch_id, 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255'], 'confirm_return' => ['accepted']]);
        try {
            $this->sales->void($sale, $request->user()->id, $data['reason']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return back()->with('status', 'Penjualan dibatalkan. Stok dan kas sistem sudah dikoreksi; riwayat tetap ada.');
    }

    public function openShift(Request $request)
    {
        $data = $request->validate(['opening_cash' => ['required', 'numeric', 'min:0', 'max:999999999999']]);

        return DB::transaction(function () use ($request, $data) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if ($user->openShift()) {
                return back()->withErrors(['opening_cash' => 'Masih ada shift terbuka.']);
            }
            CashierShift::create(['branch_id' => $user->branch_id, 'user_id' => $user->id, 'opened_at' => now(),
                'opening_cash' => $data['opening_cash'], 'expected_cash' => $data['opening_cash'], 'status' => 'dibuka']);

            return back()->with('status', 'Shift dibuka. Silakan bertransaksi.');
        }, 3);
    }

    public function closeShift(Request $request)
    {
        $data = $request->validate(['actual_cash' => ['required', 'numeric', 'min:0', 'max:999999999999'], 'note' => ['nullable', 'string', 'max:255']]);

        return DB::transaction(function () use ($request, $data) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $shift = CashierShift::where('user_id', $user->id)->where('status', 'dibuka')->lockForUpdate()->first();
            if (! $shift) {
                return back()->withErrors(['actual_cash' => 'Tidak ada shift terbuka.']);
            }
            $shift->update(['closed_at' => now(), 'actual_cash' => $data['actual_cash'],
                'difference' => round($data['actual_cash'] - (float) $shift->expected_cash, 2), 'note' => $data['note'] ?? null, 'status' => 'ditutup']);

            return back()->with('status', 'Shift ditutup. Selisih kas: Rp '.number_format((float) $shift->difference, 0, ',', '.'));
        }, 3);
    }
}
