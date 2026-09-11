<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'sku', 'barcode', 'name', 'unit',
        'sell_price', 'min_stock', 'description', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sell_price' => 'decimal:2',
            'min_stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    /** HF-04: daftar barcode yang didaftarkan kasir (satu barang boleh banyak barcode). */
    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class);
    }

    public function inventoryLayers(): HasMany
    {
        return $this->hasMany(InventoryLayer::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Pencarian kasir: nama, SKU, barcode utama, atau barcode hasil pendaftaran kasir. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $code = ProductBarcode::normalize($term);

        return $query->when($term, fn (Builder $q) => $q->where(function (Builder $q) use ($term, $code) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhere('barcode', $term);

            if ($code !== '') {
                $q->orWhere('barcode', $code)
                    ->orWhereHas('barcodes', fn (Builder $b) => $b->where('barcode', $code));
            }
        }));
    }

    public function stockAt(int $branchId): int
    {
        return (int) $this->stocks()->where('branch_id', $branchId)->value('quantity');
    }
}
