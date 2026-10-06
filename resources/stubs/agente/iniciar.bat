@echo off
title Agente de impresion
cd /d "%~dp0"
rem Usa el php.exe de esta carpeta; si no hay, el que este instalado en Windows
set PHPEXE=php
if exist "%~dp0php.exe" set PHPEXE="%~dp0php.exe"
:loop
%PHPEXE% "%~dp0worker.php"
timeout /t 2 >nul
goto loop
