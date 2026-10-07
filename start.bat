@echo off
setlocal
cd /d "%~dp0"

set "PHP_DIR=C:\Users\user\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe"
set "COMPOSER_DIR=C:\ProgramData\ComposerSetup\bin"
set "PATH=%PHP_DIR%;%COMPOSER_DIR%;%PATH%"

echo ======================================================================
echo   Facility Billing System (Filament v3)
echo ======================================================================
echo.
echo   URL:      http://localhost:8000/admin
echo   Email:    admin@care-himawari.example.jp
echo   Password: password
echo.
echo ======================================================================
echo   Server is running. Press Ctrl+C to stop.
echo ======================================================================
echo.

start "" "http://localhost:8000/admin"

php artisan serve --host=0.0.0.0 --port=8000
