<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// HF-06 Rekap shift kasir (uang tunai di laci vs sistem).
class CashierShift extends Model
{
    protected $fillable = [
        'branch_id', 'user_id', 'opened_at', 'closed_at', 'opening_cash',
        'expected_cash', 'actual_cash', 'difference', 'status', 'note',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_cash' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'actual_cash' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'dibuka';
    }

    /** Penjualan tunai selama shift (dasar perhitungan uang laci). */
    public function cashSalesTotal(): float
    {
        return (float) $this->sales()
            ->where('status', 'selesai')
            ->where('payment_method', 'tunai')
            ->sum('total');
    }
}
