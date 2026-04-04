@echo off
setlocal

:: 1. Setup environment
if not exist bin mkdir bin
set PHP_BIN=.\bin\php.exe

:: 2. Install if missing
if exist "%PHP_BIN%" goto launch

echo Installing static PHP binary for Windows...
set URL=https://dl.static-php.dev/static-php-cli/windows/spc-max/php-8.4.18-cli-win.zip

curl -L "%URL%" -o bin\php.zip
powershell -Command "Expand-Archive -Path 'bin\php.zip' -DestinationPath 'bin\' -Force"
del bin\php.zip

:launch
echo Starting PHP server on http://localhost:8000
"%PHP_BIN%" -S localhost:8000
