<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

// HF-02 Data Induk: master barang (SKU, barcode, harga, stok minimum).
class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $branchId = $request->user()->branch_id;

        $products = Product::query()
            ->with(['category', 'stocks' => fn ($q) => $branchId ? $q->where('branch_id', $branchId) : $q])
            ->when($request->filled('q'), fn ($q) => $q->search($request->string('q')->toString()))
            ->when($request->filled('kategori'), fn ($q) => $q->where('category_id', $request->integer('kategori')))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'categories' => Category::query()->orderBy('name')->get(),
            'branches' => collect(),
            'branchId' => $branchId,
        ]);
    }

    public function create(): View
    {
        return view('products.form', [
            'product' => new Product(['is_active' => true, 'unit' => 'pcs', 'min_stock' => 0]),
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            \App\Models\Branch::whereKey($request->user()->branch_id)->lockForUpdate()->firstOrFail();
            $product = Product::create($this->validated($request));
            Stock::create(['product_id' => $product->id, 'branch_id' => $request->user()->branch_id, 'quantity' => 0, 'avg_cost' => 0]);
        });

        return redirect()->route('products.index')->with('status', 'Barang baru berhasil disimpan.');
    }

    public function edit(Product $product): View
    {
        return view('products.form', [
            'product' => $product,
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product) {
            \App\Models\Branch::whereKey($request->user()->branch_id)->lockForUpdate()->firstOrFail();
            $product->update($this->validated($request, $product));
        }, 3);

        return redirect()->route('products.index')->with('status', 'Data barang diperbarui.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        // Soft delete: riwayat penjualan & biaya rata-rata bergerak lama tetap utuh.
        if ($product->stocks()->where('quantity', '>', 0)->exists()) {
            return back()->withErrors(['name' => 'Barang masih memiliki stok. Nonaktifkan barang, jangan arsipkan saldo persediaan.']);
        }
        $product->delete();

        return redirect()->route('products.index')->with('status', 'Barang diarsipkan.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Product $product = null): array
    {
        $id = $product?->id ?? 0;

        $data = $request->validate([
            'sku' => ['required', 'string', 'max:40', "unique:products,sku,{$id}"],
            'barcode' => ['nullable', 'string', 'max:50', "unique:products,barcode,{$id}", \Illuminate\Validation\Rule::unique('product_barcodes', 'barcode')->where(fn ($q) => $q->where('product_id', '<>', $id))],
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'unit' => ['required', 'string', 'max:20'],
            'sell_price' => ['required', 'numeric', 'min:0', 'max:999999999'],

            'min_stock' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
