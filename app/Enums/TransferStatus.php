<?php

namespace App\Enums;

// HF-03 Modul Mutasi Stok.
enum TransferStatus: string
{
    case Draft = 'draft';
    case Dikirim = 'dikirim';
    case Diterima = 'diterima';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
