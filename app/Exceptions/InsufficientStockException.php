<?php

namespace App\Exceptions;

use RuntimeException;

// Dipakai layanan persediaan bila stok FIFO tidak cukup untuk dikeluarkan.
class InsufficientStockException extends RuntimeException
{
    public static function make(string $productName, int $requested, int $available): self
    {
        return new self("Stok {$productName} tidak mencukupi. Diminta {$requested}, tersedia {$available}.");
    }
}
