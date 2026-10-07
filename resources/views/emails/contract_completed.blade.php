<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>【あんしん】本契約完了</title>
</head>
<body>
  <h1>本契約が完了しました</h1>

  <p>{{ $trial->contact_name }} 様</p>
  <p>「あんしん」の本契約が完了しました。</p>

  <h2>契約内容</h2>
  <ul>
    <li>プラン: {{ $plan === 'starter' ? 'スターター' : ($plan === 'standard' ? 'スタンダード' : 'エンタープライズ') }}</li>
    <li>月額料金: ¥{{ number_format($monthlyPrice) }}（税別）</li>
    <li>利用開始日: {{ now()->format('Y/m/d') }}</li>
  </ul>

  <p>トライアル中のデータはそのままご利用いただけます。</p>
  <p>高齢者施設請求管理システム「あんしん」運営チーム</p>
</body>
</html>
