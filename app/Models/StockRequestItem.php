<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockRequestItem extends Model
{
    protected $fillable = ['stock_request_id', 'product_id', 'quantity_requested', 'quantity_approved'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['quantity_requested' => 'integer', 'quantity_approved' => 'integer'];
    }

    public function stockRequest(): BelongsTo
    {
        return $this->belongsTo(StockRequest::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
