<?php

namespace App\Enums;

// HF-01 Autentikasi & Hak Akses.
enum UserRole: string
{
    case Pemilik = 'pemilik';
    case Admin = 'admin';
    case ManagerCabang = 'manager_cabang';
    case Kasir = 'kasir';

    public function label(): string
    {
        return match ($this) {
            self::Pemilik => 'Pemilik Usaha',
            self::Admin => 'Admin',
            self::ManagerCabang => 'Manager Cabang',
            self::Kasir => 'Kasir',
        };
    }

    /** Peran pusat: berhak melihat data seluruh cabang. */
    public function isCentral(): bool
    {
        return in_array($this, [self::Pemilik, self::Admin], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $r) => [$r->value => $r->label()])->all();
    }
}
