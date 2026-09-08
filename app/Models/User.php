<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['branch_id', 'name', 'email', 'role', 'phone', 'is_active', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function cashierShifts(): HasMany
    {
        return $this->hasMany(CashierShift::class);
    }

    public function hasRole(UserRole|string ...$roles): bool
    {
        foreach ($roles as $role) {
            $value = $role instanceof UserRole ? $role->value : $role;
            if ($this->role->value === $value) {
                return true;
            }
        }

        return false;
    }

    public function isPemilik(): bool
    {
        return $this->hasRole(UserRole::Pemilik);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::Admin);
    }

    public function isManagerCabang(): bool
    {
        return $this->hasRole(UserRole::ManagerCabang);
    }

    public function isKasir(): bool
    {
        return $this->hasRole(UserRole::Kasir);
    }

    /** Pemilik & admin dapat melihat seluruh cabang, peran lain dibatasi cabangnya. */
    public function isCentral(): bool
    {
        return $this->role->isCentral();
    }

    /** Cabang yang boleh diakses pengguna ini (null = semua cabang). */
    public function scopedBranchId(): ?int
    {
        return $this->isCentral() ? null : $this->branch_id;
    }

    public function openShift(): ?CashierShift
    {
        return $this->cashierShifts()->where('status', 'dibuka')->latest('opened_at')->first();
    }
}
