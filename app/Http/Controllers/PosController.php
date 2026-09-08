<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\CashierShift;
use App\Models\Product;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * HF-04 Transaksi Penjualan (POS): pencarian/pemindaian barcode, checkout,
 * struk, pembatalan transaksi, serta buka/tutup shift kasir (HF-06).
 */
class PosController extends Controller
{
    public function __construct(private readonly SaleService $sales) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_if(! $user->branch_id, 403, 'Akun Anda belum ditempatkan pada cabang mana pun.');

        return view('pos.index', [
            'branch' => $user->branch,
            'shift' => $user->openShift(),
            'paymentMethods' => PaymentMethod::options(),
            'recentSales' => Sale::query()
                ->where('branch_id', $user->branch_id)
                ->where('user_id', $user->id)
                ->latest('sold_at')
                ->limit(10)
                ->get(),
        ]);
    }

    /** Dipakai input pemindaian barcode/QR maupun pencarian manual. */
    public function lookup(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:1']]);

        $branchId = $request->user()->branch_id;

        $products = Product::query()
            ->active()
            ->search($request->string('q')->toString())
            ->with(['stocks' => fn ($q) => $q->where('branch_id', $branchId)])
            ->limit(15)
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'unit' => $product->unit,
                'sell_price' => (float) $product->sell_price,
                'stock' => (int) ($product->stocks->first()->quantity ?? 0),
            ]);

        return response()->json(['data' => $products]);
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
