@echo off
rem Compila el instalador del agente de impresion con Inno Setup 6.
rem Usa el PHP portatil de C:\sistema (o la carpeta que pases: construir.bat D:\mi_php_portatil).
cd /d "%~dp0"
set ISCC="%ProgramFiles(x86)%\Inno Setup 6\ISCC.exe"
if not exist %ISCC% set ISCC="%ProgramFiles%\Inno Setup 6\ISCC.exe"
if not exist %ISCC% (
    echo No se encontro Inno Setup 6. Instalalo desde https://jrsoftware.org/isdl.php
    pause
    exit /b 1
)
if "%~1"=="" (%ISCC% agente_tushpa.iss) else (%ISCC% /DRUNTIME="%~1" agente_tushpa.iss)
echo.
echo Listo: salida\Instalar_Agente_TUSHPA.exe
echo Subelo en el panel admin ^> Agente.
pause
