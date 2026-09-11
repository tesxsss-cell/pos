<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * HF-04 Pemetaan barcode hasil pemindaian -> barang.
 *
 * sell_price bersifat opsional:
 *  - null  : harga mengikuti harga master barang (harga tetap sama saat harga master tidak berubah)
 *  - diisi : harga khusus barcode ini (mis. kemasan renceng vs dus)
 */
class ProductBarcode extends Model
{
    protected $fillable = [
        'product_id', 'barcode', 'sell_price', 'default_quantity', 'branch_id', 'registered_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sell_price' => 'decimal:2',
            'default_quantity' => 'integer',
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

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /** Harga yang dipakai saat barcode ini dipindai. */
    public function effectivePrice(): float
    {
        return $this->sell_price !== null
            ? (float) $this->sell_price
            : (float) ($this->product?->sell_price ?? 0);
    }

    /** Alat pemindai kadang menyisipkan spasi/enter di ujung kode. */
    public static function normalize(?string $code): string
    {
        return trim((string) preg_replace('/[\p{C}\s]+/u', '', (string) $code));
    }
}
