<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 税込み合計額 (total_with_tax) カラムを追加する。
 *
 * 従来は税抜の total_amount が画面・CSV・集計の各所で「請求合計」として
 * 混在使用されており、全銀フォーマットの税込引落額や請求書PDFの税込合計と
 * 食い違うことがあった。税込合計を明示的に永続化し、SQL集計・CSV出力を
 * ここへ統一する（表示用アクセッサ total_with_tax は引き続き内訳から再計算）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_invoices', function (Blueprint $table) {
            $table->unsignedInteger('total_with_tax')->default(0)->after('total_amount');
        });

        // 既存行のバックフィル
        // 優先順位はモデルアクセッサと同じ: tax_breakdown → 保存された tax_amount → floor算出
        DB::table('monthly_invoices')->orderBy('id')->chunk(200, function ($rows) {
            foreach ($rows as $row) {
                $breakdown = $row->tax_breakdown ? json_decode($row->tax_breakdown, true) : null;

                if (is_array($breakdown)
                    && isset($breakdown['standard']['tax_amount'], $breakdown['reduced']['tax_amount'])) {
                    $tax = (int) $breakdown['standard']['tax_amount'] + (int) $breakdown['reduced']['tax_amount'];
                } elseif ($row->tax_amount !== null) {
                    $tax = (int) $row->tax_amount;
                } else {
                    $taxable = $row->taxable_amount !== null
                        ? (int) $row->taxable_amount
                        : (int) $row->management_fee_subtotal + (int) $row->service_subtotal;
                    $tax = (int) floor($taxable * (((float) ($row->tax_rate ?? 10)) / 100));
                }

                DB::table('monthly_invoices')
                    ->where('id', $row->id)
                    ->update(['total_with_tax' => (int) $row->total_amount + $tax]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('monthly_invoices', function (Blueprint $table) {
            $table->dropColumn('total_with_tax');
        });
    }
};
