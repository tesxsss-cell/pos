<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Tunai = 'tunai';
    case Transfer = 'transfer';
    case Qris = 'qris';
    case Debit = 'debit';

    public function label(): string
    {
        return match ($this) {
            self::Qris => 'QRIS',
            default => ucfirst($this->value),
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $m) => [$m->value => $m->label()])->all();
    }
}
