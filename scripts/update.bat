@echo off
rem AClear updater — drag the new aclear-vX.Y.Z.zip onto this file.

rem Extracting a release overwrites this very file, which breaks a running .bat,
rem so hand off to a copy in %TEMP% first (a batch started without CALL never returns).
if not "%~3"=="handoff" (
    copy /y "%~f0" "%TEMP%\aclear-update.bat" >nul
    "%TEMP%\aclear-update.bat" "%~1" "%~dp0." handoff
)

setlocal
set "ZIP=%~1"
set "APP=%~2"

if not exist "%ZIP%" (
    echo Drag the new aclear-vX.Y.Z.zip file onto update.bat to install it.
    pause
    exit /b 1
)

cd /d "%APP%" || goto :fail

echo [1/5] Putting AClear into maintenance mode...
php artisan down || goto :fail

echo [2/5] Backing up the database...
php artisan app:backup || goto :failup

echo [3/5] Installing new files...
tar.exe -x -f "%ZIP%" -C "%APP%" || goto :failup

echo [4/5] Updating the database...
php artisan migrate --force || goto :failup

echo [5/5] Refreshing caches...
php artisan optimize:clear
php artisan optimize || goto :failup

php artisan up
echo.
echo Update complete.
pause
exit /b 0

:failup
php artisan up
:fail
echo.
echo UPDATE STOPPED. Take a photo of this window and contact support.
echo If step 2 finished, a backup was saved before anything changed.
pause
exit /b 1
