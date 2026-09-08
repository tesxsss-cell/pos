<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stock extends Model
{
    protected $fillable = ['product_id', 'branch_id', 'quantity', 'min_stock'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity' => 'integer', 'min_stock' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Batas minimum efektif: per lokasi bila diisi, jika tidak memakai batas barang. */
    public function effectiveMinStock(): int
    {
        return (int) ($this->min_stock ?? $this->product?->min_stock ?? 0);
    }

    /** HF-06 Sistem Peringatan Stok: stok menyentuh batas minimum. */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereRaw(
            'stocks.quantity <= COALESCE(stocks.min_stock, (select products.min_stock from products where products.id = stocks.product_id))'
        );
    }
}
