<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>二要素認証 (2FA) セットアップ | あんしん 請求管理システム</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans JP", sans-serif;
            background: #f3f4f6;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .container {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 40px;
            width: 100%;
            max-width: 480px;
        }
        h1 { margin: 0 0 8px; font-size: 20px; color: #111827; }
        p.subtitle { color: #6b7280; font-size: 14px; margin-bottom: 24px; }
        .alert {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #92400e;
        }
        .success {
            background: #dcfce7;
            border: 1px solid #22c55e;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #166534;
        }
        .form-group { margin-bottom: 16px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 14px; color: #374151; }
        input[type="text"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 16px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            letter-spacing: 2px;
        }
        input[type="text"]:focus { outline: none; border-color: #2563eb; ring: 1px; }
        button {
            width: 100%;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
        }
        button:hover { background: #1d4ed8; }
        button:disabled { opacity: 0.6; cursor: not-allowed; }
        .qr-card {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            margin: 20px 0;
            text-align: center;
        }
        .qr-card img { max-width: 100%; height: auto; border-radius: 6px; }
        .secret-box {
            background: #f3f4f6;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 16px;
            word-break: break-all;
        }
        .secret-label { font-size: 12px; color: #6b7280; margin-bottom: 4px; }
        .recovery {
            background: #fffbeb;
            border: 1px solid #fde047;
            border-radius: 8px;
            padding: 16px;
            margin-top: 20px;
        }
        .recovery h3 { margin: 0 0 12px; font-size: 14px; color: #713f12; }
        .recovery ol { margin: 0; padding-left: 20px; color: #92400e; font-size: 13px; line-height: 1.6; }
        .help { margin-top: 16px; font-size: 13px; color: #6b7280; }
        .help a { color: #2563eb; text-decoration: none; }
    </style>
</head>
<body>
<div class="container">
    <h1>二要素認証 (2FA) セットアップ</h1>
    <p class="subtitle">認証アプリ (Google Authenticator 等) で以下の QR コードを読み取ってください。</p>

    @if (session('status'))
        <div class="success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert">{{ $errors->first() }}</div>
    @endif

    <div class="qr-card">
        @if (extension_loaded('gd') || class_exists(\BaconQrCode\Writer::class))
            @php
                $writer = new Writer();
                $qrData = $writer->writeString($qrUrl)->getDataUri();
            @endphp
            <img src="{{ $qrData }}" alt="QR Code">
        @else
            <p style="color:#6b7280; font-size:13px;">QR コード生成ライブラリが利用できません。秘密鍵を手動で入力してください。</p>
        @endif
        <p style="font-size:13px; color:#6b7280; margin-top:8px;">アカウント: <strong>{{ $user->email }}</strong></p>
    </div>

    <div class="secret-box">
        <div class="secret-label">秘密鍵 (手動入力用)</div>
        <input type="text" value="{{ $secret }}" readonly onclick="this.select()">
    </div>

    <form method="POST" action="{{ route('2fa.setup.submit') }}">
        @csrf
        <div class="form-group">
            <label for="code">6桁の認証コードを入力</label>
            <input type="text" id="code" name="code" maxlength="6" autofocus required autocomplete="off">
        </div>
        <button type="submit">設定を完了する</button>
    </form>

    @if (session('recovery_codes'))
        <div class="recovery">
            <h3>📋 リカバリーコード (8組)</h3>
            <p style="font-size:13px; color:#92400e; margin-bottom:8px;">スマートフォンを紛失した場合にログイン用として使用できます。各コードは1回のみ有効です。</p>
            <ol>
                @foreach (session('recovery_codes') as $code)
                    <li>{{ $code }}</li>
                @endforeach
            </ol>
            <p style="font-size:12px; color:#92400e; margin-top:12px;"><strong>必ずコピーまたはメモを取って保管してください。</strong> この画面を離れると再表示されません。</p>
        </div>
    @endif

    <p class="help">
        ご不明な点がある場合は、施設管理者までお問い合わせください。
    </p>
</div>
</body>
</html>
