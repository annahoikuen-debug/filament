<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>【あんしん】トライアル終了のお知らせ</title>
</head>
<body>
  <h1>トライアル終了まであと {{ $daysLeft }} 日です</h1>

  <p>{{ $trial->contact_name }} 様</p>
  <p>「あんしん」の無料トライアル終了まであと {{ $daysLeft }} 日（{{ $trial->trial_ends_at?->format('Y/m/d') }}）です。</p>

  <p>このまま本契約へ移行すると、導入施設150+の実績と会計ソフト3社連携の機能をそのままご利用いただけます。</p>
  <p>移行をご希望の場合は、ログイン画面からお手続きください。</p>

  <p>高齢者施設請求管理システム「あんしん」運営チーム</p>
</body>
</html>
