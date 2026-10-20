<?php

namespace App\Support;

use App\Models\Branch;

final class Shop
{
    public static function id(): int
    {
        return (int) Branch::query()->sole()->id;
    }
}
