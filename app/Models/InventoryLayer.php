<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

// HF-05 Lapisan persediaan FIFO: satu baris = satu batch barang masuk
// beserta harga belinya. Pengeluaran stok selalu mengambil lapisan tertua.
class InventoryLayer extends Model
{
    protected $fillable = [
        'product_id', 'branch_id', 'unit_cost', 'quantity',
        'remaining_quantity', 'source_type', 'source_id', 'received_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'quantity' => 'integer',
            'remaining_quantity' => 'integer',
            'received_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(InventoryLayerConsumption::class);
    }

    /** Lapisan yang masih tersedia, diurutkan sesuai aturan FIFO. */
    public function scopeFifo(Builder $query, int $productId, int $branchId): Builder
    {
        return $query->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('received_at')
            ->orderBy('id');
    }

    public function remainingValue(): float
    {
        return round($this->remaining_quantity * (float) $this->unit_cost, 2);
    }
}
