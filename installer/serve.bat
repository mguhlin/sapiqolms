@echo off
REM Quick dev server (not for production). Usage: installer\serve.bat [port]
set CODE=%~dp0..\sapiqo
set PORT=%1
if "%PORT%"=="" set PORT=8000
echo Sapiqo dev server on http://localhost:%PORT%  (Ctrl+C to stop)
REM -d session.upload_progress.enabled=0: PHP2Shell mitigation (feature unused by the app).
php -d max_execution_time=0 -d session.upload_progress.enabled=0 -S localhost:%PORT% -t "%CODE%\public" "%CODE%\public\router.php"
