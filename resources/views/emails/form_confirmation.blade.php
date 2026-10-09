{{-- フォーム受付確認メール --}}
<div style="font-family: 'Noto Sans JP', 'Hiragino Kaku Gothic ProN', 'Meiryo', sans-serif; max-width: 600px; margin: 0 auto; color: #111827;">
    <div style="background: #1e3a8a; padding: 20px 24px;">
        <h1 style="color: #ffffff; font-size: 20px; margin: 0;">高齢者施設請求管理システム「あんしん」</h1>
    </div>

    <div style="padding: 24px; border: 1px solid #e5e7eb; border-top: none;">
        <p style="margin-top: 0;">お問い合わせありがとうございます。</p>
        <p>以下の内容で受け付けました。担当者より2営業日以内にご連絡いたします。</p>

        <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
            <tr>
                <th style="text-align: left; padding: 8px; border-bottom: 1px solid #e5e7eb; background: #f9fafb; width: 35%;">受付番号</th>
                <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">{{ $submission->id }}</td>
            </tr>
            <tr>
                <th style="text-align: left; padding: 8px; border-bottom: 1px solid #e5e7eb; background: #f9fafb;">受付日時</th>
                <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">{{ $submission->created_at->format('Y年m月d日 H:i') }}</td>
            </tr>
            @if($submission->company)
            <tr>
                <th style="text-align: left; padding: 8px; border-bottom: 1px solid #e5e7eb; background: #f9fafb;">施設・法人名</th>
                <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">{{ $submission->company }}</td>
            </tr>
            @endif
            <tr>
                <th style="text-align: left; padding: 8px; border-bottom: 1px solid #e5e7eb; background: #f9fafb;">お名前</th>
                <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">{{ $submission->name }}</td>
            </tr>
        </table>

        @if($downloadUrl)
            <p style="margin: 16px 0;">
                資料は下記のURLからダウンロードできます（URLの有効期限：7日間）。<br>
                <a href="{{ $downloadUrl }}" style="color: #1e40af; word-break: break-all;">{{ $downloadUrl }}</a>
            </p>
        @endif

        <p style="font-size: 13px; color: #6b7280;">
            本メールに心当たりのない場合は、お手数ですが削除してください。
        </p>
    </div>

    <div style="padding: 16px 24px; font-size: 12px; color: #9ca3af;">
        © 2026 アンシン株式会社. All Rights Reserved.
    </div>
</div>
