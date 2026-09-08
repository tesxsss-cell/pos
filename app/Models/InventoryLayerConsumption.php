<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

// HF-05 Jejak pemakaian lapisan FIFO -> dasar penelusuran HPP tiap transaksi.
class InventoryLayerConsumption extends Model
{
    protected $fillable = [
        'inventory_layer_id', 'consumer_type', 'consumer_id',
        'quantity', 'unit_cost', 'cost_total', 'consumed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'decimal:2',
            'cost_total' => 'decimal:2',
            'consumed_at' => 'datetime',
        ];
    }

    public function layer(): BelongsTo
    {
        return $this->belongsTo(InventoryLayer::class, 'inventory_layer_id');
    }

    public function consumer(): MorphTo
    {
        return $this->morphTo();
    }
}
