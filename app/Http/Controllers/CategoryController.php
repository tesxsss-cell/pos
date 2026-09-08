<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// HF-02 Data Induk: kategori barang.
class CategoryController extends Controller
{
    public function index(): View
    {
        return view('categories.index', [
            'categories' => Category::query()->withCount('products')->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('categories.form', ['category' => new Category(['is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Category::create($this->validated($request));

        return redirect()->route('categories.index')->with('status', 'Kategori ditambahkan.');
    }

    public function edit(Category $category): View
    {
        return view('categories.form', ['category' => $category]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $category->update($this->validated($request, $category));

        return redirect()->route('categories.index')->with('status', 'Kategori diperbarui.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return back()->withErrors(['name' => 'Kategori masih dipakai oleh barang lain.']);
        }

        $category->delete();

        return redirect()->route('categories.index')->with('status', 'Kategori dihapus.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:20', 'unique:categories,code,'.($category?->id ?? 0)],
            'name' => ['required', 'string', 'max:100', 'unique:categories,name,'.($category?->id ?? 0)],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        // Kolom `code` wajib (NOT NULL + unique) sehingga dibuatkan otomatis
        // bila pengguna mengosongkannya.
        $data['code'] = $data['code'] ?: ($category?->code ?: $this->generateCode($data['name'], $category));
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    /** Membuat kode unik dari nama kategori, contoh: "Minuman" -> KAT-MIN. */
    private function generateCode(string $name, ?Category $category = null): string
    {
        $letters = strtoupper((string) preg_replace('/[^A-Za-z]/', '', $name));
        $base = 'KAT-'.(substr($letters, 0, 3) ?: 'UMU');
        $code = $base;
        $urutan = 1;

        while (
            Category::query()
                ->where('code', $code)
                ->when($category, fn ($query) => $query->whereKeyNot($category->getKey()))
                ->exists()
        ) {
            $urutan++;
            $code = $base.$urutan;
        }

        return substr($code, 0, 20);
    }
}
