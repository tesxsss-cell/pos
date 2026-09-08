<?php

namespace App\Models;

use App\Enums\StockRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// HF-03 Request stok: cabang meminta pengiriman barang dari gudang utama.
class StockRequest extends Model
{
    protected $fillable = [
        'code', 'branch_id', 'requested_by', 'status', 'note',
        'responded_by', 'responded_at', 'response_note',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => StockRequestStatus::class,
            'responded_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockRequestItem::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class);
    }

    public function isPending(): bool
    {
        return $this->status === StockRequestStatus::Menunggu;
    }
}
