<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// HF-02 Data Induk: gudang pusat & cabang.
class BranchController extends Controller
{
    public function index(): View
    {
        return view('branches.index', [
            'branches' => Branch::query()->withCount('users')->orderBy('type')->orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('branches.form', ['branch' => new Branch(['type' => 'cabang', 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Branch::create($this->validated($request));

        return redirect()->route('branches.index')->with('status', 'Lokasi baru ditambahkan.');
    }

    public function edit(Branch $branch): View
    {
        return view('branches.form', ['branch' => $branch]);
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $branch->update($this->validated($request, $branch));

        return redirect()->route('branches.index')->with('status', 'Data lokasi diperbarui.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        if ($branch->sales()->exists() || $branch->stocks()->exists()) {
            return back()->withErrors(['name' => 'Lokasi sudah memiliki data stok/penjualan, nonaktifkan saja.']);
        }

        $branch->delete();

        return redirect()->route('branches.index')->with('status', 'Lokasi dihapus.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Branch $branch = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:branches,code,'.($branch?->id ?? 0)],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:gudang,cabang'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
