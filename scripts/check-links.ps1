# 静的サイト（website/）の内部リンク・基本構文チェック
# 使い方: powershell -File scripts/check-links.ps1   （または pwsh -File ...）
#
# - 全HTMLの内部リンク（href）が実在ファイルに解決するか検証
# - HTML構文の明らかな破損（閉じ引用符欠け・未閉鎖タグ・重複main）を検出
# 終了コード: 問題検出時 1 / 正常時 0

$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
$websiteRoot = Join-Path $repoRoot 'website'

if (-not (Test-Path $websiteRoot)) {
    Write-Output "website/ directory not found: $websiteRoot"
    exit 1
}

$files = Get-ChildItem $websiteRoot -Recurse -Filter *.html
$broken = @()
$issues = @()

foreach ($f in $files) {
    $content = [System.IO.File]::ReadAllText($f.FullName, [System.Text.Encoding]::UTF8)
    $rel = $f.FullName.Substring($websiteRoot.Length + 1)

    # 内部リンク検証
    $matches2 = [regex]::Matches($content, 'href="([^"]+)"')
    foreach ($m in $matches2) {
        $href = $m.Groups[1].Value
        if ($href -match '^(#|tel:|https?:|mailto:|//)') { continue }
        $resolved = [System.IO.Path]::GetFullPath((Join-Path -Path $f.Directory.FullName -ChildPath $href))
        if (-not (Test-Path -LiteralPath $resolved)) {
            $broken += "$rel -> $href"
        }
    }

    # 構文チェック（明らかな破損のみ）
    if ($content -notmatch '<!DOCTYPE html>') { $issues += "${rel}: missing DOCTYPE" }
    if ($content -notmatch '</html>\s*$') { $issues += "${rel}: missing closing html" }
    if ($content -match 'class="[^">]*>|c-card\^|viewBox="[^">]*>|</\w+\s*\r?\n') { $issues += "${rel}: broken attributes/tags" }
    if (([regex]::Matches($content, '<main\b')).Count -ne 1) { $issues += "${rel}: <main> count != 1" }
}

if ($broken.Count -gt 0) {
    Write-Output 'BROKEN LINKS:'
    $broken | ForEach-Object { Write-Output "  $_" }
}
if ($issues.Count -gt 0) {
    Write-Output 'STRUCTURE ISSUES:'
    $issues | ForEach-Object { Write-Output "  $_" }
}

Write-Output ''
Write-Output "Checked $($files.Count) HTML files"

if ($broken.Count -gt 0 -or $issues.Count -gt 0) {
    exit 1
}
Write-Output 'ALL CHECKS PASSED'
exit 0
