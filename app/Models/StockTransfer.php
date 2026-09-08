<?php

namespace App\Models;

use App\Enums\TransferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// HF-03 Modul Mutasi Stok: dokumen pengiriman gudang -> cabang.
class StockTransfer extends Model
{
    protected $fillable = [
        'code', 'from_branch_id', 'to_branch_id', 'stock_request_id', 'status',
        'total_cost', 'note', 'shipped_by', 'shipped_at', 'received_by', 'received_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => TransferStatus::class,
            'total_cost' => 'decimal:2',
            'shipped_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function stockRequest(): BelongsTo
    {
        return $this->belongsTo(StockRequest::class);
    }

    public function shipper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipped_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function isDraft(): bool
    {
        return $this->status === TransferStatus::Draft;
    }

    public function isShipped(): bool
    {
        return $this->status === TransferStatus::Dikirim;
    }
}
