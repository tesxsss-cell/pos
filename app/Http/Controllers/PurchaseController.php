<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * HF-03 Penerimaan barang dari pemasok.
 * Dokumen dibuat sebagai draf, lalu diposting untuk memperbarui stok dan HPP rata-rata.
 */
class PurchaseController extends Controller
{
    public function __construct(private readonly PurchaseService $purchases) {}

    public function index(Request $request): View
    {
        return view('purchases.index', [
            'purchases' => Purchase::query()
                ->with(['supplier', 'branch', 'user'])
                ->where('branch_id', $request->user()->branch_id)

                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
                ->latest('purchase_date')
                ->latest('id')
                ->paginate(10)
                ->withQueryString(),
            'branches' => collect([$request->user()->branch]),
        ]);
    }

    public function create(Request $request): View
    {
        return view('purchases.form', [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'branches' => collect([$request->user()->branch]),
            'products' => Product::query()->active()->orderBy('name')->get(['id', 'sku', 'name', 'unit']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],

            'purchase_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ]);

        $data['branch_id'] = $request->user()->branch_id;
        $purchase = $this->purchases->create($data, $data['items'], $request->user()->id);

        return redirect()->route('purchases.show', $purchase)
            ->with('status', "Dokumen {$purchase->code} tersimpan sebagai draft. Posting untuk menambah stok.");
    }

    public function show(Request $request, Purchase $purchase): View
    {
        abort_if($request->user()->branch_id !== $purchase->branch_id, 403);

        return view('purchases.show', [
            'purchase' => $purchase->load(['items.product', 'supplier', 'branch', 'user']),
        ]);
    }

    /** Posting dokumen: stok bertambah & biaya rata-rata bergerak terbentuk. */
    public function post(Request $request, Purchase $purchase): RedirectResponse
    {
        abort_if($request->user()->branch_id !== $purchase->branch_id, 403);
        try {
            $this->purchases->post($purchase, $request->user()->id);
        } catch (Throwable $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', "Dokumen {$purchase->code} diposting. Stok dan HPP rata-rata diperbarui.");
    }
}
