# Billing System - Local Server Startup (PowerShell version)

$PHP_PATH = "C:\Users\user\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
$COMPOSER_PATH = "C:\ProgramData\ComposerSetup\bin\composer.bat"
$PROJECT_DIR = "E:\seikyu\filament\docs-assets\app"

Write-Host "========================================"
Write-Host "  Billing System - Local Server Startup"
Write-Host "========================================"
Write-Host ""

if (-not (Test-Path $PHP_PATH)) {
    Write-Error "[Error] PHP not found: $PHP_PATH"
    Read-Host "Press Enter to exit"
    exit 1
}

Set-Location $PROJECT_DIR

if (-not (Test-Path "vendor\autoload.php")) {
    Write-Host "[Info] Installing dependencies..."
    & $COMPOSER_PATH install --no-interaction
    if ($LASTEXITCODE -ne 0) {
        Write-Error "[Error] composer install failed."
        Read-Host "Press Enter to exit"
        exit 1
    }
}

if (-not (Test-Path ".env")) {
    Write-Host "[Info] Creating .env file..."
    Copy-Item ".env.example" ".env" -Force
    & $PHP_PATH artisan key:generate --no-interaction
}

Write-Host "[Info] Checking database..."
& $PHP_PATH artisan migrate --force --no-interaction | Out-Null

$LOCAL_IP = $null
$ipconfig = ipconfig
foreach ($line in $ipconfig) {
    if ($line -match 'IPv4.*192\.') { $LOCAL_IP = ($line -split ':')[1].Trim() }
    elseif ($line -match 'IPv4.*10\.') { if (-not $LOCAL_IP) { $LOCAL_IP = ($line -split ':')[1].Trim() } }
    elseif ($line -match 'IPv4.*172\.') { if (-not $LOCAL_IP) { $LOCAL_IP = ($line -split ':')[1].Trim() } }
}

Write-Host ""
Write-Host "========================================"
Write-Host "  Server Starting..."
Write-Host "========================================"
Write-Host ""
Write-Host "  Local access:    http://localhost:8000"
if ($LOCAL_IP) { Write-Host "  Mobile access: http://$LOCAL_IP:8000" }
Write-Host ""
Write-Host "  Opening browser..."
Write-Host "  Press Ctrl+C to stop"
Write-Host "========================================"
Write-Host ""

Start-Sleep -Seconds 3
Start-Process "http://localhost:8000"

& $PHP_PATH artisan serve --host=0.0.0.0 --port=8000