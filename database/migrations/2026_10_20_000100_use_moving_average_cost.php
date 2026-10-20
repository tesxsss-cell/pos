<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stocks', fn (Blueprint $table) => $table->decimal('avg_cost', 18, 4)->default(0));
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('unit_cost', 18, 4)->change();
            $table->decimal('avg_cost_after', 18, 4)->nullable();
        });
        Schema::table('sale_items', fn (Blueprint $table) => $table->decimal('unit_cost', 18, 4)->default(0));
        Schema::table('stock_transfer_items', fn (Blueprint $table) => $table->decimal('unit_cost', 18, 4)->default(0));

        // Konversi nilai sisa persediaan lama tanpa mengubah saldo kuantitas produksi.
        if (Schema::hasTable('inventory_layers')) {
            DB::table('inventory_layers')->select('product_id', 'branch_id')
                ->selectRaw('SUM(remaining_quantity) as qty, SUM(remaining_quantity * unit_cost) as nilai')
                ->where('remaining_quantity', '>', 0)->groupBy('product_id', 'branch_id')->orderBy('product_id')->get()
                ->each(function ($row) {
                    DB::table('stocks')->updateOrInsert(['product_id' => $row->product_id, 'branch_id' => $row->branch_id],
                        ['avg_cost' => round($row->nilai / $row->qty, 4, PHP_ROUND_HALF_UP)]);
                });
        }
        DB::table('sale_items')->where('quantity', '>', 0)->update(['unit_cost' => DB::raw('ROUND(cogs_total * 1.0 / quantity, 4)')]);
        // Mutasi yang masih dalam perjalanan tetap membawa nilai pengeluaran semula.
        DB::table('stock_transfer_items')->orderBy('id')->each(function ($item) {
            $rincian = json_decode($item->cost_layers ?? '[]', true) ?: [];
            $nilai = array_sum(array_map(fn ($r) => $r['quantity'] * $r['unit_cost'], $rincian));
            $jumlah = array_sum(array_column($rincian, 'quantity'));
            $cost = $jumlah > 0 ? $nilai / $jumlah : ($item->quantity_sent > 0 ? $item->cost_total / $item->quantity_sent : 0);
            DB::table('stock_transfer_items')->where('id', $item->id)->update(['unit_cost' => round($cost, 4, PHP_ROUND_HALF_UP)]);
        });
        Schema::table('stock_transfer_items', fn (Blueprint $table) => $table->dropColumn('cost_layers'));
        Schema::dropIfExists('inventory_layer_consumptions');
        Schema::dropIfExists('inventory_layers');
    }

    public function down(): void
    {
        throw new RuntimeException('Konversi biaya tidak dapat dibalik otomatis. Pulihkan cadangan basis data sebelum peningkatan.');
    }
};
