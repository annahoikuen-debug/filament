<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>請求書のご案内</title>
</head>
<body style="font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
  <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
    <h1 style="color: #2c3e50; margin-top: 0; font-size: 24px;">請求書のご案内</h1>
    <p style="margin: 0;">{{ $invoice->resident->name ?? '入居者様' }} 様</p>
  </div>

  <p>平素より大変お世話になっております。<br>
  下記の通り、{{ $invoice->billing_year_month }}月分の請求書を発行いたしました。</p>

  <table style="width: 100%; border-collapse: collapse; margin: 20px 0; background: #fff;">
    <tr>
      <th style="padding: 12px; border: 1px solid #dee2e6; background: #f8f9fa; text-align: left; width: 30%;">請求年月</th>
      <td style="padding: 12px; border: 1px solid #dee2e6;">{{ $invoice->billing_year_month }}</td>
    </tr>
    <tr>
      <th style="padding: 12px; border: 1px solid #dee2e6; background: #f8f9fa; text-align: left;">入居者名</th>
      <td style="padding: 12px; border: 1px solid #dee2e6;">{{ $invoice->resident->name ?? '' }}</td>
    </tr>
    <tr>
      <th style="padding: 12px; border: 1px solid #dee2e6; background: #f8f9fa; text-align: left;">部屋番号</th>
      <td style="padding: 12px; border: 1px solid #dee2e6;">{{ $invoice->resident->room_number ?? '' }}号室</td>
    </tr>
    <tr>
      <th style="padding: 12px; border: 1px solid #dee2e6; background: #f8f9fa; text-align: left;">合計請求額（税込）</th>
      <td style="padding: 12px; border: 1px solid #dee2e6; font-size: 18px; font-weight: bold; color: #2c3e50;">¥{{ number_format($invoice->total_with_tax ?? ($invoice->total_amount + ($invoice->tax_amount ?? 0))) }}</td>
    </tr>
    <tr>
      <th style="padding: 12px; border: 1px solid #dee2e6; background: #f8f9fa; text-align: left;">内訳</th>
      <td style="padding: 12px; border: 1px solid #dee2e6;">
        家賃: ¥{{ number_format($invoice->rent_subtotal) }}<br>
        管理費: ¥{{ number_format($invoice->management_fee_subtotal) }}<br>
        自費サービス: ¥{{ number_format($invoice->service_subtotal) }}<br>
        消費税: ¥{{ number_format($invoice->tax_amount ?? 0) }}
      </td>
    </tr>
  </table>

  <div style="text-align: center; margin: 30px 0;">
    <a href="{{ $pdfUrl }}" style="display: inline-block; background: #3490dc; color: #fff; padding: 14px 28px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 16px;">
      請求書PDFを確認・ダウンロード
    </a>
  </div>

  @if ($message)
    <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 6px; margin: 20px 0;">
      <p style="margin: 0;"><strong>担当者からのメッセージ：</strong></p>
      <p style="margin: 10px 0 0 0; white-space: pre-wrap;">{{ $message }}</p>
    </div>
  @endif

  <p style="color: #6c757d; font-size: 14px;">
    ※ 本メールは送信専用です。ご質問がある場合は施設まで直接ご連絡ください。<br>
    ※ PDFのダウンロードリンクは一定期間有効です。お早めにご確認ください。
  </p>

  <hr style="border: none; border-top: 1px solid #dee2e6; margin: 30px 0;">
  <p style="margin: 0; color: #6c757d; font-size: 12px;">{{ config('facility.name', '高齢者施設請求管理システム') }} 運営チーム</p>
</body>
</html>