# Laravel Filament Project Startup (PowerShell迚・

$PROJECT_DIR = "E:\seikyu"
$SCRIPTS_DIR = "E:\seikyu\scripts"

Write-Host "========================================"
Write-Host "  Laravel Filament 繝励Ο繧ｸ繧ｧ繧ｯ繝郁ｵｷ蜍・
Write-Host "========================================"
Write-Host ""

Set-Location $PROJECT_DIR

Write-Host "[1/4] PHP髢狗匱繧ｵ繝ｼ繝舌・襍ｷ蜍穂ｸｭ..."
Start-Process powershell -ArgumentList "-NoExit", "-File", "$SCRIPTS_DIR\serve.ps1"

Write-Host "[2/4] 繧ｭ繝･繝ｼ繝ｯ繝ｼ繧ｫ繝ｼ襍ｷ蜍穂ｸｭ..."
Start-Process powershell -ArgumentList "-NoExit", "-File", "$SCRIPTS_DIR\queue.ps1"

Write-Host "[3/4] 繝ｭ繧ｰ繝薙Η繝ｼ繧｢繝ｼ (Pail) 襍ｷ蜍穂ｸｭ..."
Start-Process powershell -ArgumentList "-NoExit", "-File", "$SCRIPTS_DIR\pail.ps1"

# NOTE: no package.json in this project - Vite dev server not required
Write-Host "[3/3] Done. (Vite not needed - no package.json in this project)"

Write-Host ""
Write-Host "========================================"
Write-Host "  縺吶∋縺ｦ縺ｮ繧ｵ繝ｼ繝薙せ縺瑚ｵｷ蜍輔＠縺ｾ縺励◆・・
Write-Host "========================================"
Write-Host ""
Write-Host "繧｢繧ｯ繧ｻ繧ｹ蜈・ http://localhost:8000"
Write-Host ""
Write-Host "邨ゆｺ・☆繧九↓縺ｯ縲∝推繧ｳ繝槭Φ繝峨・繝ｭ繝ｳ繝励ヨ繧ｦ繧｣繝ｳ繝峨え繧帝哩縺倥※縺上□縺輔＞縲・
Write-Host ""

Read-Host "Press Enter to exit"
