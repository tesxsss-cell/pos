<?php

namespace App\Services\Concerns;

use Illuminate\Support\Str;

trait GeneratesDocumentCode
{
    protected function nextDocumentCode(string $prefix, string $table): string
    {
        return $prefix.'-'.now()->format('ymd').'-'.strtoupper((string) Str::ulid());
    }
}
