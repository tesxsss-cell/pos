<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\InventoryService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * HF-06 Dasbor: laba/rugi, produk terlaris, rekap shift kasir, dan peringatan
 * stok, dengan filter rentang tanggal + cabang sesuai hak akses pengguna.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly InventoryService $inventory,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $from = $request->date('dari')?->toDateString() ?? now()->startOfMonth()->toDateString();
        $to = $request->date('sampai')?->toDateString() ?? now()->toDateString();

        // Pemilik & admin bebas memilih cabang, peran lain terikat cabangnya.
        $branchId = $user->isCentral()
            ? ($request->integer('cabang') ?: null)
            : $user->branch_id;

        return view('dashboard', [
            'from' => $from,
            'to' => $to,
            'branchId' => $branchId,
            'branches' => $user->isCentral() ? Branch::query()->active()->orderBy('name')->get() : collect(),
            'summary' => $this->reports->summary($branchId, $from, $to),
            'dailySeries' => $this->reports->dailySeries($branchId, $from, $to),
            'topProducts' => $this->reports->topProducts($branchId, $from, $to, 5),
            'lowStocks' => $this->reports->lowStocks($branchId)->take(10),
            'shifts' => $this->reports->shiftRecap($branchId, $from, $to)->take(5),
            'inventoryValue' => $this->inventory->inventoryValue($branchId),
        ]);
    }
}
