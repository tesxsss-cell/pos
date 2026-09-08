<?php

namespace App\Models;

use App\Enums\PurchaseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// HF-03 Penerimaan barang dari pemasok (pembentuk lapisan FIFO di gudang).
class Purchase extends Model
{
    protected $fillable = [
        'code', 'supplier_id', 'branch_id', 'user_id', 'purchase_date',
        'status', 'total_cost', 'note', 'posted_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'status' => PurchaseStatus::class,
            'total_cost' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function isDraft(): bool
    {
        return $this->status === PurchaseStatus::Draft;
    }
}
