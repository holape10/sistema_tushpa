' Inicia el agente sin ventana. Pon un acceso directo a este archivo en shell:startup
Set fso = CreateObject("Scripting.FileSystemObject")
carpeta = fso.GetParentFolderName(WScript.ScriptFullName)
Set WshShell = CreateObject("WScript.Shell")
WshShell.Run Chr(34) & carpeta & "\iniciar.bat" & Chr(34), 0
Set WshShell = Nothing
