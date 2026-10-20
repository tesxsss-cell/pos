<?php

namespace App\Enums;

enum UserRole: string
{
    case Pengelola = 'pengelola';
    case Kasir = 'kasir';

    public function label(): string
    {
        return $this === self::Pengelola ? 'Pengelola' : 'Kasir';
    }

    public static function options(): array
    {
        return ['pengelola' => 'Pengelola', 'kasir' => 'Kasir'];
    }
}
