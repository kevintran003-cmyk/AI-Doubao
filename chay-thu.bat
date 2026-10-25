@echo off
rem Chay thu app tren may tinh (mien phi, khong can hosting).
rem Can PHP: winget install PHP.PHP.8.4
cd /d "%~dp0"

where php >nul 2>nul || (
  echo Chua co PHP. Mo PowerShell va chay: winget install PHP.PHP.8.4
  pause
  exit /b 1
)
for /f "delims=" %%p in ('php -n -r "echo dirname(PHP_BINARY);"') do set "PHPDIR=%%p\"

if not exist "app\api\config.php" copy "app\api\config.sample.php" "app\api\config.php" >nul

echo.
echo  App dang chay tai: http://localhost:8000/app/
echo  Dong cua so nay de tat app.
echo.
start "" "http://localhost:8000/app/"
php -d "extension_dir=%PHPDIR%ext" -d extension=curl -d extension=mbstring -d extension=openssl -S localhost:8000
