<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReorderAlert extends Model
{
    protected $fillable = ['product_id', 'branch_id', 'status', 'level', 'current_qty', 'reorder_point',
        'safety_stock', 'avg_daily_sales', 'suggested_qty', 'triggered_at', 'acknowledged_by', 'resolved_at', 'active_slot'];

    protected function casts(): array
    {
        return ['current_qty' => 'integer', 'reorder_point' => 'integer', 'safety_stock' => 'integer',
            'suggested_qty' => 'integer', 'active_slot' => 'integer', 'triggered_at' => 'datetime', 'resolved_at' => 'datetime', 'avg_daily_sales' => 'decimal:4'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', 'resolved');
    }
}
