@echo off
REM One-time setup for XAMPP: creates .env, the database, tables, default data and your admin login.
REM 1) Start MySQL in the XAMPP Control Panel.   2) Double-click this file.
REM NOTE: avoid the characters  & % ^ "  in the password you type here.
cd /d "%~dp0"
echo ===== Firecrackers shop - setup =====
set "DBUSER=root"
set "DBPASS="
set "DBNAME=firecracker_shop"
set /p DBUSER=MySQL user [root]: 
set /p DBPASS=MySQL password [press Enter if none - XAMPP default]: 
set /p DBNAME=Database name [firecracker_shop]: 
set /p ADMINMAIL=Admin email: 
set /p ADMINPASS=Admin password (min 10 characters): 
set /p DEMO=Add 21 demo products? (y/n) [y]: 
set "DEMOFLAG=--demo"
if /i "%DEMO%"=="n" set "DEMOFLAG="
call "%~dp0console.bat" install %DEMOFLAG% --db-user=%DBUSER% --db-pass=%DBPASS% --db-name=%DBNAME% --admin-email=%ADMINMAIL% --admin-password=%ADMINPASS% --url=http://127.0.0.1:8000
if errorlevel 1 ( echo. & echo Setup failed - read the message above. & pause & exit /b 1 )
echo.
call "%~dp0console.bat" doctor
echo.
echo Done. Now double-click start.bat and open http://127.0.0.1:8000
pause
