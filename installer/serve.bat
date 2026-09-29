@echo off
REM Quick dev server (not for production). Usage: installer\serve.bat [port]
set CODE=%~dp0..\sapiqo
set PORT=%1
if "%PORT%"=="" set PORT=8000
echo Sapiqo dev server on http://localhost:%PORT%  (Ctrl+C to stop)
php -d max_execution_time=0 -S localhost:%PORT% -t "%CODE%\public" "%CODE%\public\router.php"
