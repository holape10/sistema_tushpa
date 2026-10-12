; =====================================================================================
;  Instalador del AGENTE DE IMPRESION de TUSHPA (Inno Setup 6)
;
;  Compilar: doble clic en construir.bat (o abrir este archivo con Inno Setup y "Compile").
;  Sale en deploy\agente\salida\Instalar_Agente_TUSHPA.exe -> súbelo en el panel admin › Agente.
;
;  Qué hace en la PC del cliente:
;   1. Detiene el agente si ya estaba corriendo (también el antiguo holape.bat).
;   2. Copia todo a C:\sistema: PHP portátil, SumatraPDF y el agente (worker.php, iniciar.bat, invisible.vbs).
;      El config.ini existente se conserva (con sus empresas del sistema antiguo).
;   3. Canjea el código de instalación (va en el nombre del .exe: Instalar_Agente_TUSHPA@empresa.tushpa.app@CODIGO.exe)
;      por la clave de la sucursal y la agrega a config.ini. Si el .exe se renombró, pide el código
;      (en el sistema: Impresoras › "Copiar código").
;   4. Lo deja en el Inicio de Windows (arranca solo, sin ventana) y lo inicia ya.
;
;  El PHP portátil se toma de la carpeta RUNTIME (por defecto C:\sistema de esta PC): su php.ini usa
;  extension_dir = "C:/sistema/ext", por eso siempre se instala en C:\sistema.
;  Prueba sin tocar el agente de esta PC:  ISCC /DPRUEBA /DDESTINO=C:\ruta\de\prueba agente_tushpa.iss
; =====================================================================================

#ifndef RUNTIME
  #define RUNTIME "C:\sistema"
#endif
#define STUBS "..\..\resources\stubs\agente"
#ifndef DESTINO
  #define DESTINO "C:\sistema"
#endif

[Setup]
AppId={{8F3C2A51-6B7E-4D2A-9C1E-TUSHPA-AGENTE}
AppName=Agente de impresión TUSHPA
AppVersion=2.0
AppPublisher=HOLAPE E.I.R.L.
DefaultDirName={#DESTINO}
DisableDirPage=yes
DisableProgramGroupPage=yes
DisableReadyPage=yes
UsePreviousAppDir=no
OutputDir=salida
OutputBaseFilename=Instalar_Agente_TUSHPA
Compression=lzma2/max
SolidCompression=yes
#ifdef PRUEBA
PrivilegesRequired=lowest
#else
PrivilegesRequired=admin
#endif
WizardStyle=modern
SetupIconFile=..\..\public\imagenes\favicon.ico
UninstallDisplayName=Agente de impresión TUSHPA
UninstallDisplayIcon={app}\php.exe
CloseApplications=no
#ifdef PRUEBA
Uninstallable=no
#endif

[Languages]
Name: "es"; MessagesFile: "compiler:Languages\Spanish.isl"

[Messages]
es.WelcomeLabel2=Se instalará el agente que imprime los tickets, comandas y comprobantes de tu sistema TUSHPA.%n%nQueda funcionando solo, sin ventanas, y arranca cada vez que prendes la PC.

[InstallDelete]
#ifndef PRUEBA
; Accesos directos del agente antiguo: si quedaran, el agente arrancaría dos veces
Type: files; Name: "{userstartup}\Agente Impresion HolaPE.lnk"
Type: files; Name: "{commonstartup}\Agente Impresion HolaPE.lnk"
#endif

[Files]
; PHP portátil y SumatraPDF (sin la configuración ni los registros de esta PC)
Source: "{#RUNTIME}\*"; DestDir: "{app}"; Excludes: "config.ini,*.log,unins*,worker.php,iniciar.bat,invisible.vbs,holape.bat,LEEME.txt"; Flags: ignoreversion recursesubdirs createallsubdirs
; El agente (siempre la versión del sistema)
Source: "{#STUBS}\worker.php"; DestDir: "{app}"; Flags: ignoreversion
Source: "{#STUBS}\iniciar.bat"; DestDir: "{app}"; Flags: ignoreversion
Source: "{#STUBS}\invisible.vbs"; DestDir: "{app}"; Flags: ignoreversion
Source: "{#STUBS}\LEEME.txt"; DestDir: "{app}"; Flags: ignoreversion
; Configuración: solo si la PC no tiene una (se conservan sus empresas)
Source: "{#STUBS}\config.ini"; DestDir: "{app}"; Flags: onlyifdoesntexist uninsneveruninstall

[Icons]
#ifndef PRUEBA
Name: "{commonstartup}\Agente de impresion TUSHPA"; Filename: "{app}\invisible.vbs"; WorkingDir: "{app}"
#endif

[UninstallRun]
Filename: "powershell.exe"; Parameters: "-NoProfile -ExecutionPolicy Bypass -Command ""Get-CimInstance Win32_Process | Where-Object {{ $_.CommandLine -like '*worker.php*' -or $_.CommandLine -like '*iniciar.bat*' } | ForEach-Object {{ Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }"""; Flags: runhidden; RunOnceId: "DetenerAgente"

[Code]
var
  PaginaCodigo: TInputQueryWizardPage;
  Servidor, Codigo: string;

{ "empresa.tushpa.app@ABCDEFGH23" -> Servidor y Codigo. Acepta lo que agrega el navegador: " (1)" }
function Separar(Texto: string): Boolean;
var
  P, I: Integer;
  Resto: string;
begin
  Result := False;
  Texto := Trim(Texto);
  P := Pos('@', Texto);
  if P = 0 then Exit;
  Servidor := LowerCase(Trim(Copy(Texto, 1, P - 1)));
  Resto := Copy(Texto, P + 1, Length(Texto));
  Codigo := '';
  for I := 1 to Length(Resto) do
  begin
    if ((Resto[I] >= 'A') and (Resto[I] <= 'Z')) or ((Resto[I] >= 'a') and (Resto[I] <= 'z')) or ((Resto[I] >= '0') and (Resto[I] <= '9')) then
      Codigo := Codigo + Resto[I]
    else
      Break;
  end;
  Codigo := Uppercase(Codigo);
  Result := (Servidor <> '') and (Length(Codigo) = 10);
end;

{ Del nombre del instalador: Instalar_Agente_TUSHPA@empresa.tushpa.app@CODIGO.exe }
procedure LeerNombreDelInstalador();
var
  Nombre: string;
  P: Integer;
begin
  Nombre := ExtractFileName(ExpandConstant('{srcexe}'));
  P := Pos('@', Nombre);
  if P > 0 then
    Separar(Copy(Nombre, P + 1, Length(Nombre)));
end;

procedure InitializeWizard();
begin
  LeerNombreDelInstalador();
  PaginaCodigo := CreateInputQueryPage(wpWelcome, 'Código de instalación',
    'Conecta el agente con tu sistema',
    'Escribe el código que aparece en tu sistema TUSHPA, en Impresoras › "Copiar código". Tiene la forma empresa.tushpa.app@ABCDE23456');
  PaginaCodigo.Add('Código:', False);
  if (Servidor <> '') and (Codigo <> '') then
    PaginaCodigo.Values[0] := Servidor + '@' + Codigo;
end;

{ Si el código venía en el nombre del .exe, no se pregunta nada }
function ShouldSkipPage(PageID: Integer): Boolean;
begin
  Result := (PageID = PaginaCodigo.ID) and (Servidor <> '') and (Length(Codigo) = 10);
end;

function NextButtonClick(CurPageID: Integer): Boolean;
begin
  Result := True;
  if CurPageID = PaginaCodigo.ID then
  begin
    if not Separar(PaginaCodigo.Values[0]) then
    begin
      MsgBox('El código no es válido. Cópialo de tu sistema: Impresoras › "Copiar código".', mbError, MB_OK);
      Result := False;
    end;
  end;
end;

{ Detiene el agente que esté corriendo (nuevo o antiguo) para poder reemplazar sus archivos }
procedure DetenerAgente();
var
  R: Integer;
begin
#ifndef PRUEBA
  Exec('powershell.exe', '-NoProfile -ExecutionPolicy Bypass -Command "Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -like ''*worker.php*'' -or $_.CommandLine -like ''*iniciar.bat*'' -or $_.CommandLine -like ''*holape.bat*'' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }"',
    '', SW_HIDE, ewWaitUntilTerminated, R);
#endif
end;

function PrepareToInstall(var NeedsRestart: Boolean): String;
begin
  DetenerAgente();
  Result := '';
end;

{ Agrega (o reemplaza) el bloque de la empresa en config.ini. Conserva las demás empresas }
procedure AgregarAConfig(Bloque: string);
var
  Archivo, Seccion, Linea, Nuevo: string;
  Lineas: TArrayOfString;
  I, P: Integer;
  Saltando: Boolean;
begin
  Archivo := ExpandConstant('{app}\config.ini');
  P := Pos(#13, Bloque);
  if P = 0 then P := Pos(#10, Bloque);
  Seccion := Trim(Copy(Bloque, 1, P - 1));
  Nuevo := '';
  Saltando := False;
  if LoadStringsFromFile(Archivo, Lineas) then
    for I := 0 to GetArrayLength(Lineas) - 1 do
    begin
      Linea := Trim(Lineas[I]);
      if Linea = '__SISTEMAS__' then Continue;
      if (Length(Linea) > 0) and (Linea[1] = '[') then
        Saltando := CompareText(Linea, Seccion) = 0;
      if not Saltando then
        Nuevo := Nuevo + Lineas[I] + #13#10;
    end;
  SaveStringToFile(Archivo, Nuevo + #13#10 + Bloque, False);
end;

{ Pide la clave al sistema con el código (https; si el sitio aún no tiene https, http) }
function DescargarClave(var Bloque: AnsiString): Boolean;
var
  Url: string;
  Intento: Integer;
begin
  Result := False;
  for Intento := 1 to 2 do
  begin
    if Intento = 1 then Url := 'https://' + Servidor + '/api/agente/instalar/' + Codigo
    else Url := 'http://' + Servidor + '/api/agente/instalar/' + Codigo;
    try
      DownloadTemporaryFile(Url, 'clave_agente.txt', '', nil);
      if LoadStringFromFile(ExpandConstant('{tmp}\clave_agente.txt'), Bloque) and (Pos('token', Bloque) > 0) then
      begin
        Result := True;
        Exit;
      end;
    except
      Log('No se pudo descargar la clave de ' + Url + ': ' + GetExceptionMessage);
    end;
  end;
end;

procedure CurStepChanged(CurStep: TSetupStep);
var
  Bloque: AnsiString;
  R: Integer;
begin
  if CurStep <> ssPostInstall then Exit;
  if DescargarClave(Bloque) then
    AgregarAConfig(Bloque)
  else
    MsgBox('El agente se instaló, pero no se pudo conectar con ' + Servidor + ' para traer la clave.' + #13#10 +
      'Revisa el internet y vuelve a ejecutar el instalador, o en el sistema usa Impresoras › "Solo la clave" y pega el bloque en C:\sistema\config.ini.',
      mbInformation, MB_OK);
#ifndef PRUEBA
  Exec('wscript.exe', '"' + ExpandConstant('{app}\invisible.vbs') + '"', ExpandConstant('{app}'), SW_HIDE, ewNoWait, R);
#endif
end;
