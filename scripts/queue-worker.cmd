@echo off
cd /d "C:\laragon\www\krettel-app"
:loop
php artisan queue:work --sleep=2 --tries=3 --timeout=7300
echo Worker stopped, restarting in 5 seconds...
timeout /t 5 /nobreak >nul
goto loop
