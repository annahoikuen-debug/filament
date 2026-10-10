# Laravel Project Startup (PowerShell version)
# Usage: powershell -ExecutionPolicy Bypass -File start-project.ps1

$PROJECT_DIR = "E:\seikyu"
$SCRIPTS_DIR = "E:\seikyu\scripts"

Write-Host "========================================"
Write-Host "  Laravel Project Startup"
Write-Host "========================================"
Write-Host ""

Set-Location $PROJECT_DIR

Write-Host "[1/3] Starting PHP server..."
Start-Process powershell -ArgumentList "-NoExit", "-File", "$SCRIPTS_DIR\serve.ps1"

Write-Host "[2/3] Starting Queue Worker..."
Start-Process powershell -ArgumentList "-NoExit", "-File", "$SCRIPTS_DIR\queue.ps1"

# NOTE: This project has no package.json / Vite assets (Filament ships its own assets).
# Vite dev server is not required.
Write-Host "[2/3] Done. (Vite not needed - no package.json in this project)"

Write-Host ""
Write-Host "========================================"
Write-Host "  All services started!"
Write-Host "========================================"
Write-Host ""
Write-Host "Access: http://localhost:8000/admin"
Write-Host ""
Write-Host "Close each window to stop."
Write-Host ""

Read-Host "Press Enter to exit"
