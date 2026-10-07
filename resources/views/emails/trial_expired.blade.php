<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>【あんしん】トライアル期限切れ</title>
</head>
<body>
  <h1>無料トライアルの期限が切れました</h1>

  <p>{{ $trial->contact_name }} 様</p>
  <p>「あんしん」の無料トライアル（{{ $trial->trial_ends_at?->format('Y/m/d') }} 終了）の期限が切れました。</p>

  <p>引き続きご利用をご希望の場合は、本契約への移行またはトライアル延長のお問い合わせを受け付けております。</p>
  <p>お気軽にご連絡ください。</p>

  <p>高齢者施設請求管理システム「あんしん」運営チーム</p>
</body>
</html>
