@echo off
REM Runs:  console.bat <command>   e.g.  console.bat migrate   |  console.bat serve  |  console.bat doctor
REM Finds PHP automatically (PATH, then XAMPP folders). No need to edit PATH.
setlocal
set "PHPEXE="
where php >nul 2>nul && set "PHPEXE=php"
if not defined PHPEXE (
  for %%D in ("%XAMPP_HOME%" "D:\xampp" "C:\xampp" "E:\xampp" "F:\xampp" "C:\Program Files\xampp") do (
    if not defined PHPEXE if exist "%%~D\php\php.exe" set "PHPEXE=%%~D\php\php.exe"
  )
)
if not defined PHPEXE (
  echo.
  echo PHP was not found. Install XAMPP, or set the path once, for example:
  echo     set XAMPP_HOME=D:\xampp
  echo and run this file again.
  exit /b 1
)
"%PHPEXE%" "%~dp0bin\console" %*
exit /b %ERRORLEVEL%
