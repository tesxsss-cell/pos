<?php
namespace App\Support;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
class TablePage
{
    public static function make(Collection $rows, string $name = 'page'): LengthAwarePaginator
    {
        $page = max(1, (int) request()->query($name, 1));
        return (new LengthAwarePaginator($rows->forPage($page, 10)->values(), $rows->count(), 10, $page,
            ['path' => request()->url(), 'pageName' => $name]))->withQueryString();
    }
}
