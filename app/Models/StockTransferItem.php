<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class StockTransferItem extends Model
{
    protected $fillable = [
        'stock_transfer_id', 'product_id', 'quantity_sent',
        'quantity_received', 'cost_total', 'cost_layers',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity_sent' => 'integer',
            'quantity_received' => 'integer',
            'cost_total' => 'decimal:2',
            'cost_layers' => 'array',   // rincian lapisan FIFO yang dikirim
        ];
    }

    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function consumptions(): MorphMany
    {
        return $this->morphMany(InventoryLayerConsumption::class, 'consumer');
    }
}
