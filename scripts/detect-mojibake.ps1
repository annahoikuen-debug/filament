# 文字化け（エンコーディング破損）検出スクリプト
# 使い方: powershell -File scripts/detect-mojibake.ps1   （または pwsh -File ...）
#
# UTF-8ファイルがShift-JIS等で誤読・再保存された際に発生する
# 典型的な化け文字を検出し、CI等で品質ゲートとして利用できます。
# 終了コード: 化け検出時 1 / 正常時 0

$ErrorActionPreference = 'Stop'

# 典型的な化け文字パターン（UTF-8→Shift-JIS誤読由来）
$patterns = @(
    '譏取悃',      # 「明朝」等の化け
    '繧薙',        # 「あんしん」等の化け
    '縺・・',      # 「〜す」等の化け
    '騾∽ｿ',        # 「送信」の化け
    '蜷・',        # 「名前」等の化け
    '蜿ｷ',         # 「番号」等の化け
    '荳諡',       # 「一括」の化け
    '莠呈',        # 「互換」の化け
    '邨・',        # 「ごと」等の化け
    '險倬',        # 「設定」の化け
    '譌･',         # 「日」の化け
    '譛',         # 「最」の化け
    '蜈・',        # 「入力」等の化け
    '蛻・',        # 「利用」等の化け
    '驕ｩ譬',       # 「適格」の化け
    '遶�',         # 化けの断片
    '豕ｨ諠',       # 「表示」の化け
    'współ'      # ポーランド語混入（エンコード破損の兆候）
)

$repoRoot = Split-Path -Parent $PSScriptRoot
$dirs = @('app', 'config', 'database', 'resources', 'routes', 'tests', 'scripts') | ForEach-Object { Join-Path $repoRoot $_ }
$files = Get-ChildItem $dirs -Recurse -Include *.php, *.blade.php, *.json -ErrorAction SilentlyContinue

$hits = @{}
foreach ($f in $files) {
    $lines = [System.IO.File]::ReadAllLines($f.FullName, [System.Text.Encoding]::UTF8)
    for ($i = 0; $i -lt $lines.Count; $i++) {
        foreach ($p in $patterns) {
            if ($lines[$i].Contains($p)) {
                $key = $f.FullName.Replace($repoRoot + '\', '').Replace($repoRoot + '/', '')
                if (-not $hits.ContainsKey($key)) { $hits[$key] = @() }
                $hits[$key] += [PSCustomObject]@{ Line = $i + 1; Text = $lines[$i].Trim() }
                break
            }
        }
    }
}

$total = 0
foreach ($entry in $hits.GetEnumerator() | Sort-Object Name) {
    $total += $entry.Value.Count
    Write-Output "=== $($entry.Key) ($($entry.Value.Count) lines) ==="
    $entry.Value | Select-Object -First 5 | ForEach-Object { Write-Output "  L$($_.Line): $($_.Text)" }
}

Write-Output ""
Write-Output "TOTAL: $total lines with mojibake in $($hits.Count) files"

if ($total -gt 0) {
    exit 1
}
exit 0
