<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// HF-04 Transaksi penjualan (POS) + HF-05 HPP & laba kotor per transaksi.
class Sale extends Model
{
    protected $fillable = [
        'invoice_no', 'branch_id', 'user_id', 'cashier_shift_id', 'customer_name', 'sold_at',
        'subtotal', 'discount', 'total', 'paid_amount', 'change_amount', 'payment_method',
        'cogs_total', 'gross_profit', 'status', 'voided_by', 'voided_at', 'note',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sold_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'cogs_total' => 'decimal:2',
            'gross_profit' => 'decimal:2',
            'status' => SaleStatus::class,
            'voided_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'cashier_shift_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === SaleStatus::Selesai;
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', SaleStatus::Selesai);
    }

    public function scopeForBranch(Builder $query, ?int $branchId): Builder
    {
        return $query->when($branchId, fn (Builder $q) => $q->where('branch_id', $branchId));
    }

    public function scopeBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query->when($from, fn (Builder $q) => $q->whereDate('sold_at', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('sold_at', '<=', $to));
    }
}
