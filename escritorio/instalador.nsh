; Fragmento NSIS incluido por electron-builder (nsis.include).
; PHP y PostgreSQL para Windows necesitan el runtime de Visual C++ 2015-2022 (x64).
; preparar-runtime.ps1 deja vc_redist.x64.exe en resources\runtime; aquí se instala
; en silencio solo si el equipo no lo tiene todavía.

!macro customInstall
  SetRegView 64
  ReadRegDWORD $0 HKLM "SOFTWARE\Microsoft\VisualStudio\14.0\VC\Runtimes\x64" "Installed"
  SetRegView lastused
  ${If} $0 != 1
    IfFileExists "$INSTDIR\resources\runtime\vc_redist.x64.exe" 0 +4
      DetailPrint "Instalando Microsoft Visual C++ Redistributable (x64)..."
      ExecWait '"$INSTDIR\resources\runtime\vc_redist.x64.exe" /install /quiet /norestart' $1
      DetailPrint "vc_redist terminó con código $1"
  ${EndIf}
!macroend

!macro customUnInstall
  ; Los datos de la clínica (%LOCALAPPDATA%\DENTAL-PRO) se conservan a propósito.
  ; Si el usuario quiere borrarlos, se le pregunta.
  ${IfNot} ${Silent}
    MessageBox MB_YESNO|MB_ICONQUESTION|MB_DEFBUTTON2 "¿Quieres borrar también la base de datos y los archivos de la clínica?$\r$\n($LOCALAPPDATA\DENTAL-PRO)$\r$\n$\r$\nSi eliges NO, podrás reinstalar DENTAL-PRO más adelante y recuperar todo." IDNO +2
      RMDir /r "$LOCALAPPDATA\DENTAL-PRO"
  ${EndIf}
!macroend
