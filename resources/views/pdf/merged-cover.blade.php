<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>統合請求書 - {{ $resident->name }}様 ({{ $billing_year_month }})</title>
    <style>
        @page {
            margin: 20mm 15mm;
            @bottom-center {
                content: "第 " counter(page) " 頁 / 計 " counter(pages) " 頁";
                font-size: 9px;
                color: #64748b;
            }
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'ipaexg', sans-serif;
            font-size: 11px;
            line-height: 1.6;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }
        .container { max-width: 100%; }
        
        /* ヘッダー */
        .header {
            text-align: center;
            border-bottom: 3px solid #1e3a8a;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .title { font-size: 24px; font-weight: bold; color: #1e3a8a; margin: 0 0 8px; }
        .subtitle { font-size: 14px; color: #475569; margin: 0; }
        
        /* 施設・入居者情報 */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        .info-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
        }
        .info-label { font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .info-value { font-size: 14px; font-weight: 600; color: #1e293b; }
        
        /* 金額サマリー */
        .summary-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .summary-table th, .summary-table td { padding: 10px 12px; text-align: right; border: 1px solid #e2e8f0; }
        .summary-table th { background: #f1f5f9; font-weight: 600; text-align: left; }
        .summary-table .category { text-align: left; font-weight: 600; }
        .summary-table .subtotal-row { background: #fef3c7; font-weight: 600; }
        .summary-table .total-row { background: #1e3a8a; color: white; font-weight: 700; font-size: 13px; }
        .summary-table .care-header { background: #dbeafe; font-weight: 600; }
        .amount { text-align: right; font-variant-numeric: tabular-nums; }
        
        /* 明細内訳 */
        .detail-section { margin-bottom: 20px; }
        .detail-title { font-size: 13px; font-weight: 600; color: #1e3a8a; border-left: 4px solid #1e3a8a; padding-left: 10px; margin-bottom: 12px; }
        .detail-table { width: 100%; border-collapse: collapse; font-size: 10px; }
        .detail-table th, .detail-table td { padding: 6px 8px; border: 1px solid #e2e8f0; }
        .detail-table th { background: #f1f5f9; text-align: left; }
        .detail-table .right { text-align: right; }
        
        /* フッター */
        .footer {
            margin-top: 32px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
            font-size: 10px;
            color: #64748b;
            text-align: center;
        }
        .generated { margin-bottom: 8px; }
        
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <div class="container">
        <!-- 表紙ヘッダー -->
        <div class="header">
            <h1 class="title">統合請求書</h1>
            <p class="subtitle">{{ $billing_year_month }}月分 &nbsp;|&nbsp; {{ $resident->room_number }}号室 {{ $resident->name }}様</p>
        </div>

        <!-- 基本情報 -->
        <div class="info-grid">
            <div class="info-card">
                <div class="info-label">施設名</div>
                <div class="info-value">{{ $facility['name'] ?? '未設定' }}</div>
            </div>
            <div class="info-card">
                <div class="info-label">請求年月</div>
                <div class="info-value">{{ $billing_year_month }}</div>
            </div>
            <div class="info-card">
                <div class="info-label">入居者</div>
                <div class="info-value">{{ $resident->room_number }}号室 {{ $resident->name }}</div>
            </div>
            <div class="info-card">
                <div class="info-label">作成日時</div>
                <div class="info-value">{{ $generated_at }}</div>
            </div>
        </div>

        <!-- 金額サマリー -->
        <table class="summary-table">
            <thead>
                <tr>
                    <th class="category">区分</th>
                    <th>金額 (税抜)</th>
                    <th>消費税</th>
                    <th>税込合計</th>
                </tr>
            </thead>
            <tbody>
                <!-- 住居費 -->
                <tr>
                    <td class="category" colspan="4" style="background:#f1f5f9; font-weight:600;">■ 住居費・自費サービス</td>
                </tr>
                <tr>
                    <td class="category">家賃</td>
                    <td class="amount">¥{{ number_format($housing['rent']) }}</td>
                    <td class="amount">¥0</td>
                    <td class="amount">¥{{ number_format($housing['rent']) }}</td>
                </tr>
                <tr>
                    <td class="category">管理費</td>
                    <td class="amount">¥{{ number_format($housing['management']) }}</td>
                    <td class="amount">¥{{ number_format(round($housing['management'] * 0.1)) }}</td>
                    <td class="amount">¥{{ number_format($housing['management'] + round($housing['management'] * 0.1)) }}</td>
                </tr>
                <tr>
                    <td class="category">自費サービス</td>
                    <td class="amount">¥{{ number_format($housing['service']) }}</td>
                    <td class="amount">¥{{ number_format(round($housing['service'] * 0.1)) }}</td>
                    <td class="amount">¥{{ number_format($housing['service'] + round($housing['service'] * 0.1)) }}</td>
                </tr>
                <tr class="subtotal-row">
                    <td class="category">住居費小計</td>
                    <td class="amount">¥{{ number_format($housing['rent'] + $housing['management'] + $housing['service']) }}</td>
                    <td class="amount">¥{{ number_format(round(($housing['management'] + $housing['service']) * 0.1)) }}</td>
                    <td class="amount">¥{{ number_format($housing['total']) }}</td>
                </tr>

                <!-- 介護サービス -->
                @if(!empty($care_services))
                <tr>
                    <td class="category care-header" colspan="4">■ 介護保険サービス（外部システム発行）</td>
                </tr>
                @foreach($care_services as $service)
                <tr>
                    <td class="category">{{ $service['label'] }}</td>
                    <td class="amount">¥{{ number_format($service['amount']) }}</td>
                    <td class="amount">¥{{ number_format($service['tax']) }}</td>
                    <td class="amount">¥{{ number_format($service['total']) }}</td>
                </tr>
                @endforeach
                <tr class="subtotal-row">
                    <td class="category">介護サービス小計</td>
                    <td class="amount">¥{{ number_format(array_sum(array_column($care_services, 'amount'))) }}</td>
                    <td class="amount">¥{{ number_format(array_sum(array_column($care_services, 'tax'))) }}</td>
                    <td class="amount">¥{{ number_format($care_total) }}</td>
                </tr>
                @endif

                <!-- 総合計 -->
                <tr class="total-row">
                    <td class="category">総合計（税込）</td>
                    <td class="amount">¥{{ number_format($housing['rent'] + $housing['management'] + $housing['service'] + array_sum(array_column($care_services, 'amount'))) }}</td>
                    <td class="amount">¥{{ number_format(round(($housing['management'] + $housing['service']) * 0.1) + array_sum(array_column($care_services, 'tax'))) }}</td>
                    <td class="amount">¥{{ number_format($grand_total) }}</td>
                </tr>
            </tbody>
        </table>

        <!-- 明細内訳：住居費 -->
        <div class="detail-section">
            <h3 class="detail-title">住居費・自費サービス 明細</h3>
            <table class="detail-table">
                <thead>
                    <tr>
                        <th style="width:50%">項目</th>
                        <th class="right" style="width:15%">単価</th>
                        <th class="right" style="width:15%">数量</th>
                        <th class="right" style="width:20%">金額</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>家賃（{{ $billing_year_month }}月分）</td>
                        <td class="right">¥{{ number_format($resident->base_rent) }}</td>
                        <td class="right">1ヶ月</td>
                        <td class="right">¥{{ number_format($housing['rent']) }}</td>
                    </tr>
                    <tr>
                        <td>管理費（{{ $billing_year_month }}月分）</td>
                        <td class="right">¥{{ number_format($resident->base_management_fee) }}</td>
                        <td class="right">1ヶ月</td>
                        <td class="right">¥{{ number_format($housing['management']) }}</td>
                    </tr>
                    @if($housing['service'] > 0)
                    <tr>
                        <td>自費サービス計</td>
                        <td class="right" colspan="2">－</td>
                        <td class="right">¥{{ number_format($housing['service']) }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <!-- 明細内訳：介護サービス -->
        @if(!empty($care_services))
        <div class="detail-section page-break">
            <h3 class="detail-title">介護保険サービス 明細（外部システム発行分）</h3>
            <p style="font-size:10px; color:#64748b; margin-bottom:12px;">※ 詳細なサービス内訳・単価・回数は各サービス種別の請求書（別添PDF）をご参照ください。</p>
            <table class="detail-table">
                <thead>
                    <tr>
                        <th style="width:40%">サービス種別</th>
                        <th style="width:20%">外部請求番号</th>
                        <th class="right" style="width:20%">税込金額</th>
                        <th style="width:20%">備考</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($care_services as $service)
                    <tr>
                        <td>{{ $service['label'] }}</td>
                        <td>－</td>
                        <td class="right">¥{{ number_format($service['total']) }}</td>
                        <td>外部システム発行</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- フッター -->
        <div class="footer">
            <div class="generated">作成日時: {{ $generated_at }}</div>
            <div>本請求書は住居費と介護サービス費用を統合したものです。介護サービスの詳細内訳は別添の各請求書をご確認ください。</div>
            <div style="margin-top:8px;">{{ $facility['name'] ?? '施設名' }} &nbsp;|&nbsp; {{ $facility['phone'] ?? '' }} &nbsp;|&nbsp; {{ $facility['email'] ?? '' }}</div>
        </div>
    </div>
</body>
</html>