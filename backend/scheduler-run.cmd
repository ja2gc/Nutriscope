@echo off
REM Nutriscope Laravel scheduler tick (rnd.md follow-up reminders).
REM Runs every minute via Windows Task Scheduler; dispatches due scheduled commands.
REM Backend runs on host; MySQL is the docker container on 127.0.0.1:3306.
REM Register: schtasks /Create /SC MINUTE /MO 1 /TN "NutriscopeScheduler" /TR "%~dp0scheduler-run.cmd" /F
REM Remove:   schtasks /Delete /TN "NutriscopeScheduler" /F
set DB_HOST=127.0.0.1
cd /d "%~dp0"
php artisan schedule:run >> storage\logs\scheduler.log 2>&1
