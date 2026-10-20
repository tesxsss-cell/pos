<?php

namespace App\Http\Controllers;

use App\Services\InventoryService;
use App\Services\ReportService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly ReportService $reports, private readonly InventoryService $inventory) {}

    public function index(Request $request)
    {
        if ($request->user()->isKasir()) {
            return redirect()->route('pos.index');
        }
        $data = $request->validate(['dari' => ['nullable', 'date'], 'sampai' => ['nullable', 'date', 'after_or_equal:dari']]);
        $from = $data['dari'] ?? now()->startOfMonth()->toDateString();
        $to = $data['sampai'] ?? now()->toDateString();
        $id = $request->user()->branch_id;

        return view('dashboard', ['from' => $from, 'to' => $to, 'summary' => $this->reports->summary($id, $from, $to),
            'lowStocks' => $this->reports->lowStocks($id), 'inventoryValue' => $this->inventory->inventoryValue($id),
            'shifts' => $this->reports->shiftRecap($id, $from, $to)]);
    }
}
