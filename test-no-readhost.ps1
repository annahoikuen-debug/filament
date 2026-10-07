Read-Host "Press Enter to exit"
# Laravel Filament Project Startup (PowerShell牁E

$PROJECT_DIR = "E:\seikyu\filament\docs-assets\app"
$SCRIPTS_DIR = "E:\seikyu\scripts"

Write-Host "========================================"
Write-Host "  Laravel Filament プロジェクト起勁E
Write-Host "========================================"
Write-Host ""

Set-Location $PROJECT_DIR

Write-Host "[1/4] PHP開発サーバ�E起動中..."
Start-Process powershell -ArgumentList "-NoExit", "-File", "$SCRIPTS_DIR\serve.ps1"

Write-Host "[2/4] キューワーカー起動中..."
Start-Process powershell -ArgumentList "-NoExit", "-File", "$SCRIPTS_DIR\queue.ps1"

Write-Host "[3/4] ログビューアー (Pail) 起動中..."
Start-Process powershell -ArgumentList "-NoExit", "-File", "$SCRIPTS_DIR\pail.ps1"

Write-Host "[4/4] Vite開発サーバ�E起動中..."
Start-Process powershell -ArgumentList "-NoExit", "-Command", "npm run dev"

Write-Host ""
Write-Host "========================================"
Write-Host "  すべてのサービスが起動しました�E�E
Write-Host "========================================"
Write-Host ""
Write-Host "アクセス允E http://localhost:8000"
Write-Host ""
Write-Host "終亁E��るには、各コマンド�Eロンプトウィンドウを閉じてください、E
Write-Host ""

