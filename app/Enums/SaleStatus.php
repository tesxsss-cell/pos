<?php

namespace App\Enums;

enum SaleStatus: string
{
    case Selesai = 'selesai';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
