# Laravel Filament Project Startup (PowerShell version)

$PROJECT_DIR = $PSScriptRoot
if (-not $PROJECT_DIR) {
    $PROJECT_DIR = (Get-Location).Path
}
$SCRIPTS_DIR = Join-Path $PROJECT_DIR "scripts"

Write-Host "========================================"
Write-Host "  Laravel Filament Project Startup"
Write-Host "========================================"
Write-Host ""

Set-Location $PROJECT_DIR

# --- [事前健全性チェック / Pre-flight Checks] ---
Write-Host "[Check] Running pre-flight system checks..." -ForegroundColor Cyan

# 1. .env の存在チェック
$envFile = Join-Path $PROJECT_DIR ".env"
if (-not (Test-Path $envFile)) {
    if (Test-Path (Join-Path $PROJECT_DIR ".env.example")) {
        Write-Warning "`.env` was not found. Creating `.env` from `.env.example`..."
        Copy-Item (Join-Path $PROJECT_DIR ".env.example") $envFile
        php artisan key:generate
    } else {
        Write-Error "`.env` file is missing. Please create it before starting."
        Read-Host "Press Enter to exit"
        exit 1
    }
}

# 2. SQLite データベースファイルの存在チェック
$dbDir = Join-Path $PROJECT_DIR "database"
$sqliteFile = Join-Path $dbDir "database.sqlite"
if (-not (Test-Path $sqliteFile)) {
    Write-Host "Creating empty database.sqlite file..." -ForegroundColor Yellow
    New-Item -ItemType File -Path $sqliteFile -Force | Out-Null
    Write-Host "Running migrations..." -ForegroundColor Yellow
    php artisan migrate --force
}

# 3. ログディレクトリ & ファイルの準備
$logDir = Join-Path $PROJECT_DIR "storage\logs"
if (-not (Test-Path $logDir)) {
    New-Item -ItemType Directory -Path $logDir -Force | Out-Null
}

Write-Host "[OK] Pre-flight checks passed." -ForegroundColor Green
Write-Host ""

# --- [プロセス起動 / Process Launch] ---
Write-Host "[1/3] Starting PHP server..."
Start-Process powershell -ArgumentList "-NoExit", "-File", "$SCRIPTS_DIR\serve.ps1"

Write-Host "[2/3] Starting Queue Worker..."
Start-Process powershell -ArgumentList "-NoExit", "-File", "$SCRIPTS_DIR\queue.ps1"

Write-Host "[3/3] Starting Log Viewer..."
Start-Process powershell -ArgumentList "-NoExit", "-File", "$SCRIPTS_DIR\pail.ps1"

Write-Host ""
Write-Host "========================================"
Write-Host "  All services started!"
Write-Host "========================================"
Write-Host ""
Write-Host "Access: http://localhost:8000/admin" -ForegroundColor Cyan
Write-Host ""
Write-Host "Close each window to stop."
Write-Host ""

# ブラウザ自動オープン (2秒待機後に開く)
Start-Sleep -Seconds 2
Start-Process "http://localhost:8000/admin"

Read-Host "Press Enter to exit"