<?php

namespace App\Http\Controllers;

use App\Models\Branch;
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
 * Dokumen dibuat sebagai draft, lalu diposting agar stok masuk dan lapisan
 * FIFO (batch harga beli) terbentuk.
 */
class PurchaseController extends Controller
{
    public function __construct(private readonly PurchaseService $purchases) {}

    public function index(Request $request): View
    {
        return view('purchases.index', [
            'purchases' => Purchase::query()
                ->with(['supplier', 'branch', 'user'])
                ->when($request->filled('cabang'), fn ($q) => $q->where('branch_id', $request->integer('cabang')))
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
                ->latest('purchase_date')
                ->latest('id')
                ->paginate(20)
                ->withQueryString(),
            'branches' => Branch::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('purchases.form', [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'branches' => Branch::query()->active()->orderBy('type')->orderBy('name')->get(),
            'products' => Product::query()->active()->orderBy('name')->get(['id', 'sku', 'name', 'unit']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'purchase_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $purchase = $this->purchases->create($data, $data['items'], $request->user()->id);

        return redirect()->route('purchases.show', $purchase)
            ->with('status', "Dokumen {$purchase->code} tersimpan sebagai draft. Posting untuk menambah stok.");
    }

    public function show(Purchase $purchase): View
    {
        return view('purchases.show', [
            'purchase' => $purchase->load(['items.product', 'supplier', 'branch', 'user']),
        ]);
    }

    /** Posting dokumen: stok bertambah & lapisan FIFO terbentuk. */
    public function post(Request $request, Purchase $purchase): RedirectResponse
    {
        try {
            $this->purchases->post($purchase, $request->user()->id);
        } catch (Throwable $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', "Dokumen {$purchase->code} diposting. Stok dan batch FIFO diperbarui.");
    }
}
