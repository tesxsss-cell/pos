<?php

namespace App\Enums;

// HF-03 Fitur Request Stok.
enum StockRequestStatus: string
{
    case Menunggu = 'menunggu';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';
    case Terpenuhi = 'terpenuhi';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Menunggu => 'bg-amber-100 text-amber-800',
            self::Disetujui => 'bg-blue-100 text-blue-800',
            self::Terpenuhi => 'bg-emerald-100 text-emerald-800',
            self::Ditolak, self::Dibatalkan => 'bg-rose-100 text-rose-800',
        };
    }
}
