<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>【あんしん】デモ面談の予約確認</title>
</head>
<body>
  <h1>デモ面談の予約を受け付けました</h1>

  <p>{{ $booking->name }} 様</p>
  <p>下記の内容でデモ面談の予約を受け付けました。</p>

  <h2>予約内容</h2>
  <ul>
    <li>日時: {{ $booking->preferred_date }} {{ $booking->preferred_time }}</li>
    <li>施設名: {{ $booking->trial->company_name }}</li>
  </ul>

  @if ($booking->notes)
    <p>備考: {{ $booking->notes }}</p>
  @endif

  <p>営業担当者より確認のご連絡をいたします。</p>
  <p>高齢者施設請求管理システム「あんしん」運営チーム</p>
</body>
</html>
