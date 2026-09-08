<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * HF-01 Kelola akun.
 * - Pemilik: boleh membuat/mengubah akun admin, manager cabang, dan kasir.
 * - Admin: hanya boleh mengelola akun manager cabang dan kasir.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        return view('users.index', [
            'users' => User::query()
                ->with('branch')
                ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q')->toString().'%'))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'roles' => UserRole::options(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('users.form', [
            'user' => new User(['is_active' => true]),
            'roles' => $this->assignableRoles($request->user()),
            'branches' => Branch::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['password'] = Hash::make($data['password']);

        User::create($data);

        return redirect()->route('users.index')->with('status', 'Akun baru berhasil dibuat.');
    }

    public function edit(Request $request, User $user): View
    {
        $this->guardTarget($request, $user);

        return view('users.form', [
            'user' => $user,
            'roles' => $this->assignableRoles($request->user()),
            'branches' => Branch::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->guardTarget($request, $user);

        $data = $this->validated($request, $user);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return redirect()->route('users.index')->with('status', 'Akun diperbarui.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->guardTarget($request, $user);

        if ($user->id === $request->user()->id) {
            return back()->withErrors(['name' => 'Anda tidak dapat menghapus akun sendiri.']);
        }

        // Nonaktifkan agar riwayat transaksi kasir tetap terbaca.
        $user->update(['is_active' => false]);

        return redirect()->route('users.index')->with('status', 'Akun dinonaktifkan.');
    }

    /** @return array<string, string> */
    private function assignableRoles(User $actor): array
    {
        $roles = UserRole::options();
        unset($roles[UserRole::Pemilik->value]);

        if (! $actor->isPemilik()) {
            unset($roles[UserRole::Admin->value]);
        }

        return $roles;
    }

    private function guardTarget(Request $request, User $user): void
    {
        $actor = $request->user();

        abort_if(
            ! $actor->isPemilik() && $user->hasRole(UserRole::Pemilik, UserRole::Admin) && $actor->id !== $user->id,
            403,
            'Hanya pemilik yang dapat mengelola akun admin.',
        );
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?User $user = null): array
    {
        $roles = array_keys($this->assignableRoles($request->user()));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in($roles)],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id', Rule::requiredIf(fn () => in_array($request->input('role'), [UserRole::ManagerCabang->value, UserRole::Kasir->value], true))],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
