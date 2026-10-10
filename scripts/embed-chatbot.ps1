# Chatbot widget embed script - 静的サイト全ページへの冪等埋め込み
# Usage: pwsh scripts/embed-chatbot.ps1
# 404.html には埋め込みしない。重複挿入ガード付き。

$ErrorActionPreference = 'Stop'
$websiteRoot = Join-Path $PSScriptRoot '..\website'
$snippet = @'
<!-- Chatbot widget -->
<script defer src="/chatbot/widget.js" data-api-url="/api/public/chatbot/message" data-primary-color="#1e3a8a"></script>
'@

$marker = '<!-- Chatbot widget -->'

Get-ChildItem -Path $websiteRoot -Filter '*.html' -Recurse | ForEach-Object {
    $file = $_.FullName

    # 404ページ除外
    if ($_.Name -eq '404.html') {
        Write-Host "SKIP (404): $file"
        return
    }

    $content = Get-Content -Path $file -Raw -Encoding UTF8

    # 重複挿入ガード
    if ($content.Contains($marker)) {
        Write-Host "SKIP (already embedded): $file"
        return
    }

    if ($content -match '</body>') {
        $newContent = $content -replace '</body>', ($snippet + '</body>')
        # BOM付きUTF-8で保存（既存ファイルの文字化け防止）
        $utf8Bom = New-Object System.Text.UTF8Encoding $true
        [System.IO.File]::WriteAllText($file, $newContent, $utf8Bom)
        Write-Host "EMBEDDED: $file"
    } else {
        Write-Host "WARN (no </body>): $file"
    }
}

Write-Host 'Done.'
