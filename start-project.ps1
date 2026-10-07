# Laravel Filament Project Startup (PowerShell version)

$PROJECT_DIR = "E:\seikyu\filament\docs-assets\app"
$SCRIPTS_DIR = "E:\seikyu\scripts"

Write-Host "========================================"
Write-Host "  Laravel Filament Project Startup"
Write-Host "========================================"
Write-Host ""

Set-Location $PROJECT_DIR

Write-Host "[1/3] Starting PHP server..."
Start-Process powershell -ArgumentList "-NoExit", "-File", "$SCRIPTS_DIR\serve.ps1"

Write-Host "[2/3] Starting Queue Worker..."
Start-Process powershell -ArgumentList "-NoExit", "-File", "$SCRIPTS_DIR\queue.ps1"

Write-Host "[3/3] Starting Vite Dev Server..."
Start-Process powershell -ArgumentList "-NoExit", "-Command", "npm run dev"

Write-Host ""
Write-Host "========================================"
Write-Host "  All services started!"
Write-Host "========================================"
Write-Host ""
Write-Host "Access: http://localhost:8000"
Write-Host ""
Write-Host "Close each window to stop."
Write-Host ""

Read-Host "Press Enter to exit"