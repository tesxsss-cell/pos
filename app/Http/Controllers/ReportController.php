<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

// HF-06 Pelaporan: harian, bulanan, laba/rugi, stok, kartu stok, shift kasir.
class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function daily(Request $request): View
    {
        [$from, $to, $branchId] = $this->filters($request, now()->toDateString(), now()->toDateString());

        return view('reports.daily', [
            ...$this->shared($request, $from, $to, $branchId),
            'summary' => $this->reports->summary($branchId, $from, $to),
            'series' => $this->reports->dailySeries($branchId, $from, $to),
            'topProducts' => $this->reports->topProducts($branchId, $from, $to),
        ]);
    }

    public function monthly(Request $request): View
    {
        $year = $request->integer('tahun') ?: (int) now()->year;
        $branchId = $this->branchFilter($request);

        return view('reports.monthly', [
            'year' => $year,
            'branchId' => $branchId,
            'branches' => $this->branchOptions($request),
            'series' => $this->reports->monthlySeries($branchId, $year),
        ]);
    }

    public function profit(Request $request): View
    {
        [$from, $to, $branchId] = $this->filters($request, now()->startOfMonth()->toDateString(), now()->toDateString());

        return view('reports.profit', [
            ...$this->shared($request, $from, $to, $branchId),
            'summary' => $this->reports->summary($branchId, $from, $to),
            'series' => $this->reports->dailySeries($branchId, $from, $to),
        ]);
    }

    /** Stok per lokasi + peringatan stok minimum. */
    public function stock(Request $request): View
    {
        $branchId = $this->branchFilter($request);

        return view('reports.stock', [
            'branchId' => $branchId,
            'branches' => $this->branchOptions($request),
            'lowStocks' => $this->reports->lowStocks($branchId),
        ]);
    }

    /** Kartu stok: penelusuran seluruh pergerakan satu barang. */
    public function stockCard(Request $request): View
    {
        $user = $request->user();

        $data = $request->validate([
            'barang' => ['nullable', 'integer', 'exists:products,id'],
            'cabang' => ['nullable', 'integer', 'exists:branches,id'],
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date'],
        ]);

        $from = $data['dari'] ?? now()->startOfMonth()->toDateString();
        $to = $data['sampai'] ?? now()->toDateString();
        $branchId = $user->isCentral() ? ($data['cabang'] ?? null) : $user->branch_id;
        $productId = $data['barang'] ?? null;

        return view('reports.stock-card', [
            'from' => $from,
            'to' => $to,
            'branchId' => $branchId,
            'productId' => $productId,
            'branches' => $this->branchOptions($request),
            'products' => Product::query()->active()->orderBy('name')->get(['id', 'name', 'sku']),
            'movements' => ($productId && $branchId)
                ? $this->reports->stockCard($productId, $branchId, $from, $to)
                : collect(),
            'layers' => ($productId && $branchId)
                ? $this->reports->fifoLayers($productId, $branchId)
                : collect(),
        ]);
    }

    public function shift(Request $request): View
    {
        [$from, $to, $branchId] = $this->filters($request, now()->startOfMonth()->toDateString(), now()->toDateString());

        return view('reports.shift', [
            ...$this->shared($request, $from, $to, $branchId),
            'shifts' => $this->reports->shiftRecap($branchId, $from, $to),
        ]);
    }

    /** @return array{0: string, 1: string, 2: int|null} */
    private function filters(Request $request, string $defaultFrom, string $defaultTo): array
    {
        return [
            $request->date('dari')?->toDateString() ?? $defaultFrom,
            $request->date('sampai')?->toDateString() ?? $defaultTo,
            $this->branchFilter($request),
        ];
    }

    private function branchFilter(Request $request): ?int
    {
        $user = $request->user();

        return $user->isCentral() ? ($request->integer('cabang') ?: null) : $user->branch_id;
    }

    private function branchOptions(Request $request)
    {
        return $request->user()->isCentral()
            ? Branch::query()->active()->orderBy('name')->get()
            : collect([$request->user()->branch])->filter();
    }

    /** @return array<string, mixed> */
    private function shared(Request $request, string $from, string $to, ?int $branchId): array
    {
        return [
            'from' => $from,
            'to' => $to,
            'branchId' => $branchId,
            'branches' => $this->branchOptions($request),
        ];
    }
}
