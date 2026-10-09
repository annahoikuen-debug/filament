<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>【あんしん】無料トライアルの準備が完了しました</title>
</head>
<body>
  <h1>無料トライアルの準備が完了しました</h1>

  <p>{{ $trial->contact_name }} 様</p>
  <p>「あんしん」の無料トライアル環境を準備しました。</p>

  <h2>ログイン情報</h2>
  <ul>
    <li>ログインURL: {{ config('app.url') }}/admin</li>
    <li>メールアドレス: {{ $trial->email }}</li>
    <li>一時パスワード: {{ $tempPassword }}</li>
  </ul>
  <p>初回ログイン後、パスワードは必ず変更してください。</p>

  <h2>トライアル期間</h2>
  <p>14日間（{{ $trial->trial_ends_at?->format('Y/m/d') }} まで）</p>

  @if ($conversionToken)
    <h2>本契約への移行</h2>
    <p>トライアルを本契約に移行する場合は、以下の移行トークンが必要です。</p>
    <p style="font-family: monospace; font-size: 1.1em;">{{ $conversionToken }}</p>
    <p>このトークンは大切に保管してください。本契約移行や見積書のメール送信に使用します。</p>
  @endif

  <p>ご不明な点がございましたら、お気軽にお問い合わせください。</p>
  <p>高齢者施設請求管理システム「あんしん」運営チーム</p>
</body>
</html>
