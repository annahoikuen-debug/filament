@echo off
chcp 65001 >nul
title Laravel Filament Project Startup

set PHP_PATH=C:\Users\user\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe

cd /d "E:\seikyu\filament\docs-assets\app"

echo ========================================
echo  Laravel Filament プロジェクト起動
echo ========================================
echo.

echo [1/4] PHP開発サーバー起動中...
start "Laravel Server" cmd /k "\"%PHP_PATH%\" artisan serve"

echo [2/4] キューワーカー起動中...
start "Queue Worker" cmd /k "\"%PHP_PATH%\" artisan queue:listen --tries=1 --timeout=0"

echo [3/4] ログビューアー (Pail) 起動中...
start "Laravel Pail" cmd /k "\"%PHP_PATH%\" artisan pail --timeout=0"

echo [4/4] Vite開発サーバー起動中...
start "Vite Dev Server" cmd /k "npm run dev"

echo.
echo ========================================
echo  すべてのサービスが起動しました！
echo ========================================
echo.
echo アクセス先: http://localhost:8000
echo.
echo 終了するには、各コマンドプロンプトウィンドウを閉じてください。
echo.

pause