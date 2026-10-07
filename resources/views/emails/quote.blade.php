<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>【あんしん】見積書</title>
</head>
<body>
  <h1>見積書</h1>

  <p>{{ $trial->company_name }} 様</p>
  <p>下記の通りお見積りいたします。</p>

  <h2>ご提案プラン</h2>
  <ul>
    <li>プラン: {{ $quote['plan_name'] }}</li>
    <li>月額料金: ¥{{ number_format($quote['monthly_price']) }}（税別）</li>
  </ul>

  @if ($quote['note'])
    <p>{{ $quote['note'] }}</p>
  @endif

  <p>ご質問やお申込みはお気軽にどうぞ。</p>
  <p>高齢者施設請求管理システム「あんしん」運営チーム</p>
</body>
</html>
