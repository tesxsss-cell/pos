<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id', 'product_id', 'product_name', 'quantity',
        'unit_price', 'discount', 'subtotal', 'cogs_total', 'gross_profit',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'cogs_total' => 'decimal:2',
            'gross_profit' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Rincian lapisan FIFO yang dipakai baris penjualan ini. */
    public function consumptions(): MorphMany
    {
        return $this->morphMany(InventoryLayerConsumption::class, 'consumer');
    }
}
