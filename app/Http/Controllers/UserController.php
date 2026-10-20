<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        return view('users.index', ['users' => User::where('branch_id', $request->user()->branch_id)->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q')->toString().'%'))->orderBy('name')->paginate(10)->withQueryString(), 'roles' => ['pengelola' => 'Pengelola', 'kasir' => 'Kasir']]);
    }

    public function create(Request $request)
    {
        return view('users.form', ['user' => new User(['is_active' => true, 'role' => 'kasir']), 'roles' => ['kasir' => 'Kasir']]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['role'] = 'kasir';
        $data['branch_id'] = $request->user()->branch_id;
        User::create($data);

        return redirect()->route('users.index')->with('status', 'Akun kasir dibuat.');
    }

    public function edit(Request $request, User $user)
    {
        $this->guard($request, $user);

        return view('users.form', ['user' => $user, 'roles' => [$user->role->value => $user->role->label()]]);
    }

    public function update(Request $request, User $user)
    {
        $this->guard($request, $user);
        $data = $this->validated($request, $user);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if ($user->id === $request->user()->id) {
            $data['is_active'] = true;
        }
        if (! $data['is_active'] && $user->openShift()) {
            return back()->withErrors(['name' => 'Tutup shift kasir sebelum menonaktifkan akun.']);
        }
        $user->update($data);

        return redirect()->route('users.index')->with('status', 'Akun diperbarui.');
    }

    public function destroy(Request $request, User $user)
    {
        $this->guard($request, $user);
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['name' => 'Tidak dapat menonaktifkan akun sendiri.']);
        }
        if ($user->openShift()) {
            return back()->withErrors(['name' => 'Tutup shift kasir sebelum menonaktifkan akun.']);
        }
        $user->update(['is_active' => false]);

        return redirect()->route('users.index')->with('status', 'Akun dinonaktifkan; riwayat tetap ada.');
    }

    private function guard(Request $request, User $user): void
    {
        abort_if($user->branch_id !== $request->user()->branch_id, 403);
        abort_if($user->isPengelola() && $user->id !== $request->user()->id, 403, 'Pengelola hanya dapat mengubah akun sendiri atau akun kasir.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:25'], 'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'role' => ['sometimes', Rule::in([$user?->role->value ?? 'kasir'])]]);
        unset($data['role']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}

