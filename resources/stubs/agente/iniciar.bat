@echo off
title Agente de impresion
cd /d "%~dp0"
rem Usa el php.exe de esta carpeta; si no hay, el que este instalado en Windows
set PHPEXE=php
if exist "%~dp0php.exe" set PHPEXE="%~dp0php.exe"
rem Impresion sin internet: la caja del navegador manda los tickets a esta PC (solo 127.0.0.1)
if exist "%~dp0local.php" start "" /b %PHPEXE% -S 127.0.0.1:17777 "%~dp0local.php"
:loop
%PHPEXE% "%~dp0worker.php"
timeout /t 2 >nul
goto loop
