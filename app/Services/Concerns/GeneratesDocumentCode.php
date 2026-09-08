<?php

namespace App\Services\Concerns;

use Illuminate\Support\Facades\DB;

// Penomoran dokumen harian: PB-260908-0001, RQ-260908-0001, MT-260908-0001, dst.
trait GeneratesDocumentCode
{
    protected function nextDocumentCode(string $prefix, string $table, string $column = 'code'): string
    {
        $base = $prefix.'-'.now()->format('ymd').'-';

        $last = DB::table($table)
            ->where($column, 'like', $base.'%')
            ->max($column);

        $sequence = $last ? ((int) substr((string) $last, -4)) + 1 : 1;

        return $base.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
