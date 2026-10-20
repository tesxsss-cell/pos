@echo off
setlocal
cd /d "%~dp0"
where php >nul 2>nul
if errorlevel 1 (echo PHP belum ada di PATH. & pause & exit /b 1)
if not exist .env copy .env.example .env >nul
if not exist vendor\autoload.php (echo Jalankan composer install terlebih dahulu. & pause & exit /b 1)
if not exist database\database.sqlite type nul > database\database.sqlite
findstr /R /C:"^APP_KEY=..*" .env >nul
if errorlevel 1 php artisan key:generate
php artisan config:clear
php artisan migrate --seed
if errorlevel 1 (echo Setup gagal. Baca pesan di atas. & pause & exit /b 1)
echo Siap. Jalankan start.bat. Baca README.md untuk akun demo.
pause
