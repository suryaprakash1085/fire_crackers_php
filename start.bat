@echo off
REM Starts the shop at http://127.0.0.1:8000  (admin: /admin). Start MySQL in the XAMPP Control Panel first.
cd /d "%~dp0"
call "%~dp0console.bat" serve --port=8000
pause
