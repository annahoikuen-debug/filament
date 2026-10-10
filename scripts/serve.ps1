# Serve script - run from project root
$PROJECT_DIR = Split-Path -Parent $PSScriptRoot
Set-Location $PROJECT_DIR
$php = "C:\Users\user\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
if (-not (Test-Path $php)) { $php = "php" }
& $php artisan serve --host=0.0.0.0 --port=8000

