<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// HF-02 Data Induk: master barang (SKU, barcode, harga, stok minimum).
class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $branchId = $request->integer('cabang') ?: null;

        $products = Product::query()
            ->with(['category', 'stocks' => fn ($q) => $branchId ? $q->where('branch_id', $branchId) : $q])
            ->when($request->filled('q'), fn ($q) => $q->search($request->string('q')->toString()))
            ->when($request->filled('kategori'), fn ($q) => $q->where('category_id', $request->integer('kategori')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'categories' => Category::query()->orderBy('name')->get(),
            'branches' => Branch::query()->active()->orderBy('name')->get(),
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
        Product::create($this->validated($request));

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
        $product->update($this->validated($request, $product));

        return redirect()->route('products.index')->with('status', 'Data barang diperbarui.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        // Soft delete: riwayat penjualan & lapisan FIFO lama tetap utuh.
        $product->delete();

        return redirect()->route('products.index')->with('status', 'Barang diarsipkan.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Product $product = null): array
    {
        $id = $product?->id ?? 0;

        $data = $request->validate([
            'sku' => ['required', 'string', 'max:50', "unique:products,sku,{$id}"],
            'barcode' => ['nullable', 'string', 'max:50', "unique:products,barcode,{$id}"],
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'unit' => ['required', 'string', 'max:20'],
            'sell_price' => ['required', 'numeric', 'min:0'],
            'min_stock' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
