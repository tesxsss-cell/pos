<?php

namespace App\Services;

use App\Enums\SaleStatus;
use App\Models\CashierShift;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * HF-06 Dasbor & Pelaporan.
 * Semua laporan dapat difilter berdasarkan rentang tanggal dan cabang
 * ($branchId = null berarti seluruh cabang, hanya untuk pemilik & admin).
 */
class ReportService
{
    /**
     * Ringkasan penjualan, HPP, dan laba kotor.
     *
     * @return array{transactions: int, items_sold: int, revenue: float, cogs: float, gross_profit: float, average_transaction: float}
     */
    public function summary(?int $branchId, string $from, string $to): array
    {
        $sales = Sale::query()
            ->completed()
            ->forBranch($branchId)
            ->between($from, $to)
            ->selectRaw('COUNT(*) as transactions')
            ->selectRaw('COALESCE(SUM(total), 0) as revenue')
            ->selectRaw('COALESCE(SUM(cogs_total), 0) as cogs')
            ->selectRaw('COALESCE(SUM(gross_profit), 0) as gross_profit')
            ->first();

        $itemsSold = (int) SaleItem::query()
            ->whereHas('sale', fn ($q) => $q->completed()->forBranch($branchId)->between($from, $to))
            ->sum('quantity');

        $transactions = (int) ($sales->transactions ?? 0);
        $revenue = (float) ($sales->revenue ?? 0);

        return [
            'transactions' => $transactions,
            'items_sold' => $itemsSold,
            'revenue' => $revenue,
            'cogs' => (float) ($sales->cogs ?? 0),
            'gross_profit' => (float) ($sales->gross_profit ?? 0),
            'average_transaction' => $transactions > 0 ? round($revenue / $transactions, 2) : 0.0,
        ];
    }

    /** Deret harian untuk grafik laba/rugi. */
    public function dailySeries(?int $branchId, string $from, string $to): Collection
    {
        return Sale::query()
            ->completed()
            ->forBranch($branchId)
            ->between($from, $to)
            ->selectRaw('date(sold_at) as tanggal')
            ->selectRaw('COUNT(*) as transaksi')
            ->selectRaw('COALESCE(SUM(total), 0) as penjualan')
            ->selectRaw('COALESCE(SUM(cogs_total), 0) as hpp')
            ->selectRaw('COALESCE(SUM(gross_profit), 0) as laba_kotor')
            ->groupBy('tanggal')
            ->orderBy('tanggal')
            ->get();
    }

    /** Rekap per bulan dalam satu tahun (dikelompokkan di PHP agar aman untuk MySQL & SQLite). */
    public function monthlySeries(?int $branchId, int $year): Collection
    {
        $from = Carbon::create($year, 1, 1)->toDateString();
        $to = Carbon::create($year, 12, 31)->toDateString();

        return $this->dailySeries($branchId, $from, $to)
            ->groupBy(fn ($row) => Carbon::parse($row->tanggal)->format('Y-m'))
            ->map(fn (Collection $rows, string $month) => [
                'bulan' => $month,
                'nama_bulan' => Carbon::parse($month.'-01')->translatedFormat('F Y'),
                'transaksi' => (int) $rows->sum('transaksi'),
                'penjualan' => (float) $rows->sum('penjualan'),
                'hpp' => (float) $rows->sum('hpp'),
                'laba_kotor' => (float) $rows->sum('laba_kotor'),
            ])
            ->values();
    }

    /** Grafik produk terlaris. */
    public function topProducts(?int $branchId, string $from, string $to, int $limit = 10): Collection
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.status', SaleStatus::Selesai->value)
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->whereDate('sales.sold_at', '>=', $from)
            ->whereDate('sales.sold_at', '<=', $to)
            ->selectRaw('products.id, products.name, products.sku')
            ->selectRaw('SUM(sale_items.quantity) as qty_terjual')
            ->selectRaw('SUM(sale_items.subtotal) as nilai_penjualan')
            ->selectRaw('SUM(sale_items.gross_profit) as laba_kotor')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('qty_terjual')
            ->limit($limit)
            ->get();
    }

    /** HF-06 Peringatan stok: barang yang menyentuh batas minimum. */
    public function lowStocks(?int $branchId): Collection
    {
        return Stock::query()
            ->with(['product:id,name,sku,unit,min_stock', 'branch:id,name,code'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->lowStock()
            ->orderBy('quantity')
            ->get();
    }

    /** Rekap shift kasir: uang tunai di laci vs catatan sistem. */
    public function shiftRecap(?int $branchId, string $from, string $to): Collection
    {
        return CashierShift::query()
            ->with(['user:id,name', 'branch:id,name'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereDate('opened_at', '>=', $from)
            ->whereDate('opened_at', '<=', $to)
            ->orderByDesc('opened_at')
            ->get();
    }

    /** Kartu stok satu barang pada satu lokasi. */
    public function stockCard(int $productId, int $branchId, string $from, string $to): Collection
    {
        return StockMovement::query()
            ->with('user:id,name')
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /** Sisa lapisan FIFO (batch) sebuah barang: bukti urutan pemakaian persediaan. */
    public function fifoLayers(int $productId, int $branchId): Collection
    {
        return DB::table('inventory_layers')
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('received_at')
            ->orderBy('id')
            ->select('id', 'unit_cost', 'quantity', 'remaining_quantity', 'received_at')
            ->get();
    }
}
