@echo off
title CSTA HR
cd /d "%~dp0"

where php >nul 2>nul
if errorlevel 1 (
    echo PHP was not found. Install PHP and add it to your PATH first.
    pause
    exit /b 1
)

echo Starting CSTA HR...
echo Leave this window open while you use the app.
timeout /t 2 /nobreak >nul
start "" "http://127.0.0.1:8000/System.php"
php -S 127.0.0.1:8000 -t "%~dp0"

echo.
echo The CSTA HR server has stopped.
pause
