<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// HF-02 Data Induk: pemasok.
class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        return view('suppliers.index', [
            'suppliers' => Supplier::query()
                ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q')->toString().'%'))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('suppliers.form', ['supplier' => new Supplier(['is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Supplier::create($this->validated($request));

        return redirect()->route('suppliers.index')->with('status', 'Pemasok ditambahkan.');
    }

    public function edit(Supplier $supplier): View
    {
        return view('suppliers.form', ['supplier' => $supplier]);
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validated($request, $supplier));

        return redirect()->route('suppliers.index')->with('status', 'Data pemasok diperbarui.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->purchases()->exists()) {
            return back()->withErrors(['name' => 'Pemasok sudah memiliki riwayat pembelian, nonaktifkan saja.']);
        }

        $supplier->delete();

        return redirect()->route('suppliers.index')->with('status', 'Pemasok dihapus.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:20', 'unique:suppliers,code,'.($supplier?->id ?? 0)],
            'name' => ['required', 'string', 'max:150'],
            'contact_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:25'],
            'email' => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        // Kolom `code` wajib (NOT NULL + unique) sehingga dibuatkan otomatis
        // bila pengguna mengosongkannya.
        $data['code'] = $data['code'] ?: ($supplier?->code ?: $this->generateCode());
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    /** Membuat kode pemasok berurutan, contoh: SUP-001. */
    private function generateCode(): string
    {
        $urutan = (int) Supplier::query()->max('id') + 1;

        do {
            $code = 'SUP-'.str_pad((string) $urutan, 3, '0', STR_PAD_LEFT);
            $urutan++;
        } while (Supplier::query()->where('code', $code)->exists());

        return $code;
    }
}
