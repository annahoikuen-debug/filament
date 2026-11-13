<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>二要素認証 (2FA) 検証 | あんしん 請求管理システム</title>
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
            max-width: 400px;
        }
        h1 { margin: 0 0 8px; font-size: 20px; color: #111827; }
        p.subtitle { color: #6b7280; font-size: 14px; margin-bottom: 24px; }
        .alert {
            background: #fee2e2;
            border: 1px solid #ef4444;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #991b1b;
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
            font-size: 18px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            letter-spacing: 4px;
            text-align: center;
        }
        input[type="text"]:focus { outline: none; border-color: #2563eb; }
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
        .divider {
            display: flex;
            align-items: center;
            margin: 20px 0;
            color: #9ca3af;
            font-size: 12px;
        }
        .divider::before, .divider::after { content: ''; flex: 1; border-bottom: 1px solid #e5e7eb; }
        .divider:not(:empty)::before { margin-right: 8px; }
        .divider:not(:empty)::after { margin-left: 8px; }
        button.secondary {
            background: #f3f4f6;
            color: #374151;
            margin-top: 8px;
        }
        button.secondary:hover { background: #e5e7eb; }
        .recovery-hint {
            margin-top: 16px;
            font-size: 13px;
            color: #6b7280;
        }
        .recovery-hint a { color: #2563eb; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
<div class="container">
    <h1>二要素認証 (2FA)</h1>
    <p class="subtitle">6桁の認証コードを入力してください。</p>

    @if (session('status'))
        <div class="success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('2fa.verify.submit') }}">
        @csrf
        <div class="form-group">
            <label for="code">認証コード</label>
            <input type="text" id="code" name="code" maxlength="6" autofocus required autocomplete="off" placeholder="123456">
        </div>
        <button type="submit">ログイン</button>
    </form>

    <div class="divider">または</div>

    <form method="POST" action="{{ route('2fa.verify.submit') }}">
        @csrf
        <input type="hidden" name="recovery" value="1">
        <div class="form-group">
            <label for="recovery_code">リカバリーコードを使用</label>
            <input type="text" id="recovery_code" name="code" maxlength="21" placeholder="XXXXXXXX-XXXXXXXX" required autocomplete="off">
        </div>
        <button type="submit" class="secondary">リカバリーコードでログイン</button>
    </form>

    <p class="recovery-hint">
        認証アプリが使えない場合は、<a href="{{ route('2fa.disable') }}">2FA を無効化</a>できます（再設定が必要です）。
    </p>
</div>
</body>
</html>
