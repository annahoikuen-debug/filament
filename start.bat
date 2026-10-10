@echo off
chcp 65001 >nul
setlocal
cd /d "%~dp0"

set "PHP_DIR=C:\Users\user\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe"
set "COMPOSER_DIR=C:\ProgramData\ComposerSetup\bin"
set "PATH=%PHP_DIR%;%COMPOSER_DIR%;%PATH%"
set "APP_DIR=E:\seikyu"

echo ======================================================================
echo   Facility Billing System (Filament v3) - Launcher
echo ======================================================================
echo.
echo   1) Simple - Server only (artisan serve)
echo   2) Full   - Server + Queue + Pail + Vite
echo   3) Setup + Simple
echo   4) Setup + Full
echo.
set /p "CHOICE=Select (1-4) [default: 1]: "

if "%CHOICE%"=="" set CHOICE=1
if "%CHOICE%"=="1" goto SIMPLE
if "%CHOICE%"=="2" goto FULL
if "%CHOICE%"=="3" goto SETUP_SIMPLE
if "%CHOICE%"=="4" goto SETUP_FULL

echo Invalid choice.
pause
exit /b 1

:SETUP_SIMPLE
echo.
echo [Setup] Installing dependencies...
cd /d "%APP_DIR%"
if not exist "vendor\autoload.php" (
    composer install --no-interaction
    if errorlevel 1 (
        echo [Error] composer install failed
        pause
        exit /b 1
    )
)
if not exist ".env" (
    copy .env.example .env
    php artisan key:generate --no-interaction
)
echo [Setup] Running migrations...
php artisan migrate --force --no-interaction
goto SIMPLE

:SETUP_FULL
echo.
echo [Setup] Installing dependencies...
cd /d "%APP_DIR%"
if not exist "vendor\autoload.php" (
    composer install --no-interaction
    if errorlevel 1 (
        echo [Error] composer install failed
        pause
        exit /b 1
    )
)
if not exist ".env" (
    copy .env.example .env
    php artisan key:generate --no-interaction
)
echo [Setup] Running migrations...
php artisan migrate --force --no-interaction
goto FULL

:SIMPLE
cd /d "%APP_DIR%"
echo.
echo ======================================================================
echo   Simple Mode
echo ======================================================================
echo.
echo   URL:      http://localhost:8000/admin
echo   Email:    admin@care-himawari.example.jp
echo   Password: password
echo.
echo ======================================================================
echo   Starting server... Ctrl+C to stop
echo ======================================================================
echo.
start "" "http://localhost:8000/admin"
php artisan serve --host=0.0.0.0 --port=8000
goto END

:FULL
cd /d "%APP_DIR%"
echo.
echo ======================================================================
echo   Full Mode (each service in separate window)
echo ======================================================================
echo.
echo [1/4] Starting PHP server...
start "Laravel Server" cmd /k "php artisan serve"

echo [2/4] Starting Queue Worker...
start "Queue Worker" cmd /k "php artisan queue:listen --tries=1 --timeout=0"

echo [3/4] Starting Log Viewer (Pail)...
start "Laravel Pail" cmd /k "php artisan pail --timeout=0"

echo [4/4] Starting Vite Dev Server...
start "Vite Dev Server" cmd /k "npm run dev"

echo.
echo ======================================================================
echo   All services started!
echo ======================================================================
echo.
echo Access: http://localhost:8000/admin
echo.
echo Close each window to stop.
echo.

:END
pause