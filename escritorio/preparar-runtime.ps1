#Requires -Version 5.1
<#
.SYNOPSIS
  Descarga y prepara el runtime que empaqueta el instalador de DENTAL-PRO:
  PHP 8.4 NTS x64 (windows.php.net), PostgreSQL 16 (binarios EDB), cacert.pem
  (curl.se) y vc_redist.x64.exe (Microsoft). Deja todo en escritorio\recursos\runtime.

.DESCRIPTION
  - PHP: se consulta releases.json y se toma la última 8.4 "nts-vs17-x64" (sin fijar
    el número exacto). Se verifica el SHA-256 publicado.
  - PostgreSQL: versión concreta $VersionPostgres (por defecto 16.9-1). Para actualizar,
    mira la lista en https://www.enterprisedb.com/download-postgresql-binaries y pasa
    -VersionPostgres 16.<x>-1. Solo se extraen pgsql\bin, pgsql\lib y pgsql\share.
  - Si cualquier descarga falla, el script termina con error y mensaje claro.

.EXAMPLE
  pwsh escritorio/preparar-runtime.ps1
  pwsh escritorio/preparar-runtime.ps1 -VersionPostgres 16.10-1 -Destino C:\tmp\runtime
#>
[CmdletBinding()]
param(
    [string]$Destino = (Join-Path $PSScriptRoot 'recursos\runtime'),
    [string]$VersionPostgres = '16.9-1',
    [string]$RamaPhp = '8.4',
    [switch]$SinVcRedist,
    [switch]$ConservarDescargas
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
try { [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12 } catch { }

$UserAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) DENTAL-PRO-preparar-runtime'
$Descargas = Join-Path $Destino '..\descargas'
$DirPhp = Join-Path $Destino 'php'
$DirPg = Join-Path $Destino 'pgsql'

function Escribir($texto) { Write-Host "[preparar-runtime] $texto" }
function Fallar($texto) { Write-Error "[preparar-runtime] ERROR: $texto"; exit 1 }

function Descargar([string]$Url, [string]$Archivo, [string]$Que) {
    if (Test-Path $Archivo) { Escribir "$Que ya descargado: $Archivo"; return }
    Escribir "Descargando $Que desde $Url"
    $intentos = 0
    while ($true) {
        $intentos++
        try {
            Invoke-WebRequest -Uri $Url -OutFile "$Archivo.parcial" -UserAgent $UserAgent -Headers @{ 'Accept' = '*/*' } -TimeoutSec 600
            Move-Item -Force "$Archivo.parcial" $Archivo
            return
        } catch {
            Remove-Item -Force "$Archivo.parcial" -ErrorAction SilentlyContinue
            if ($intentos -ge 3) {
                Fallar "No se pudo descargar $Que ($Url) tras $intentos intentos: $($_.Exception.Message)"
            }
            Escribir "Fallo el intento $intentos, reintentando en 10 s..."
            Start-Sleep -Seconds 10
        }
    }
}

function ComprobarSha256([string]$Archivo, [string]$Esperado, [string]$Que) {
    if (-not $Esperado) { Escribir "Sin SHA-256 publicado para $Que; se omite la verificación."; return }
    $real = (Get-FileHash -Algorithm SHA256 $Archivo).Hash.ToLowerInvariant()
    if ($real -ne $Esperado.ToLowerInvariant()) {
        Remove-Item -Force $Archivo -ErrorAction SilentlyContinue
        Fallar "SHA-256 incorrecto para $Que. Esperado $Esperado, obtenido $real. Vuelve a ejecutar el script."
    }
    Escribir "SHA-256 correcto para $Que."
}

New-Item -ItemType Directory -Force -Path $Destino, $Descargas | Out-Null
Add-Type -AssemblyName System.IO.Compression.FileSystem

# ------------------------------------------------------------------ PHP
Escribir "Consultando releases.json de windows.php.net (rama $RamaPhp)..."
try {
    $releases = Invoke-RestMethod -Uri 'https://windows.php.net/downloads/releases/releases.json' -UserAgent $UserAgent -TimeoutSec 120
} catch {
    Fallar "No se pudo leer releases.json: $($_.Exception.Message)"
}
$rama = $releases.$RamaPhp
if (-not $rama) {
    Fallar "releases.json no incluye la rama PHP $RamaPhp (ramas: $(($releases | Get-Member -MemberType NoteProperty).Name -join ', ')). Si pasó a archivo, usa https://windows.php.net/downloads/releases/archives/."
}
$paquetePhp = $rama.'nts-vs17-x64'
if (-not $paquetePhp -or -not $paquetePhp.zip.path) {
    Fallar "La rama $RamaPhp no ofrece el paquete nts-vs17-x64 en releases.json."
}
$versionPhp = $rama.version
$zipPhp = Join-Path $Descargas $paquetePhp.zip.path
Descargar "https://windows.php.net/downloads/releases/$($paquetePhp.zip.path)" $zipPhp "PHP $versionPhp NTS x64"
ComprobarSha256 $zipPhp $paquetePhp.zip.sha256 "PHP $versionPhp"

Escribir "Extrayendo PHP en $DirPhp"
Remove-Item -Recurse -Force $DirPhp -ErrorAction SilentlyContinue
[System.IO.Compression.ZipFile]::ExtractToDirectory($zipPhp, $DirPhp)
if (-not (Test-Path (Join-Path $DirPhp 'php.exe'))) { Fallar "El zip de PHP no contiene php.exe en la raíz." }

# Solo las extensiones que usa la aplicación (ver composer.json y php -m del proyecto).
$extensiones = @('pdo_pgsql', 'pgsql', 'gd', 'mbstring', 'fileinfo', 'curl', 'openssl', 'zip', 'intl', 'sodium', 'exif', 'bz2')
$zend = @('opcache')
$dirExt = Join-Path $DirPhp 'ext'
$conservar = ($extensiones + $zend) | ForEach-Object { "php_$_.dll" }
Get-ChildItem $dirExt -Filter '*.dll' | Where-Object { $conservar -notcontains $_.Name } | Remove-Item -Force
foreach ($ext in $extensiones + $zend) {
    if (-not (Test-Path (Join-Path $dirExt "php_$ext.dll"))) { Fallar "El paquete de PHP no trae ext\php_$ext.dll." }
}
# Restos que no hacen falta en el instalador.
foreach ($sobra in @('php.ini-development', 'php.ini-production', 'phpdbg.exe', 'php-cgi.exe', 'php-win.exe', 'phpdbg.exe', 'dev', 'lib', 'extras', 'snapshot.txt', 'README.md', 'news.txt', 'NEWS.txt', 'install.txt')) {
    Remove-Item -Recurse -Force (Join-Path $DirPhp $sobra) -ErrorAction SilentlyContinue
}

# ------------------------------------------------------------------ cacert.pem
$cacert = Join-Path $DirPhp 'cacert.pem'
Descargar 'https://curl.se/ca/cacert.pem' (Join-Path $Descargas 'cacert.pem') 'cacert.pem (curl.se)'
Descargar 'https://curl.se/ca/cacert.pem.sha256' (Join-Path $Descargas 'cacert.pem.sha256') 'cacert.pem.sha256'
$shaCacert = ((Get-Content (Join-Path $Descargas 'cacert.pem.sha256') -Raw) -split '\s+')[0]
ComprobarSha256 (Join-Path $Descargas 'cacert.pem') $shaCacert 'cacert.pem'
Copy-Item -Force (Join-Path $Descargas 'cacert.pem') $cacert

# ------------------------------------------------------------------ php.ini
# extension_dir y curl.cainfo relativos a la carpeta de php.exe; el lanzador añade
# además un .ini con rutas absolutas vía PHP_INI_SCAN_DIR (ver main.js).
$phpIni = @"
; php.ini de DENTAL-PRO (escritorio). Generado por preparar-runtime.ps1.
[PHP]
extension_dir = "ext"
$(($extensiones | ForEach-Object { "extension=$_" }) -join "`r`n")
zend_extension=opcache

memory_limit = 512M
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 120
max_input_time = 120
max_input_vars = 5000
default_charset = "UTF-8"
date.timezone = America/Lima
variables_order = "EGPCS"
short_open_tag = Off
expose_php = Off
display_errors = Off
display_startup_errors = Off
log_errors = On
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
realpath_cache_size = 4096K
realpath_cache_ttl = 600
output_buffering = 4096
file_uploads = On
allow_url_fopen = On
curl.cainfo = "cacert.pem"
openssl.cafile = "cacert.pem"

[opcache]
opcache.enable = 1
opcache.enable_cli = 0
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 1
opcache.revalidate_freq = 60

[Session]
session.use_strict_mode = 1
session.cookie_httponly = 1
"@
Set-Content -Path (Join-Path $DirPhp 'php.ini') -Value $phpIni -Encoding ascii

# ------------------------------------------------------------------ PostgreSQL
$zipPg = Join-Path $Descargas "postgresql-$VersionPostgres-windows-x64-binaries.zip"
Descargar "https://get.enterprisedb.com/postgresql/postgresql-$VersionPostgres-windows-x64-binaries.zip" $zipPg "PostgreSQL $VersionPostgres (binarios EDB)"

Escribir "Extrayendo bin, lib y share de PostgreSQL en $DirPg"
Remove-Item -Recurse -Force $DirPg -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Force -Path $DirPg | Out-Null
$zip = [System.IO.Compression.ZipFile]::OpenRead($zipPg)
try {
    $extraidos = 0
    foreach ($entrada in $zip.Entries) {
        $ruta = $entrada.FullName -replace '\\', '/'
        if ($ruta -notmatch '^pgsql/(bin|lib|share)/') { continue }
        if ($ruta.EndsWith('/')) { continue }
        # No hacen falta ni las cabeceras de desarrollo ni pgAdmin.
        if ($ruta -match '^pgsql/lib/(pgxs|.*\.lib$)') { continue }
        $destinoArchivo = Join-Path $DirPg ($ruta.Substring(6) -replace '/', '\')
        New-Item -ItemType Directory -Force -Path (Split-Path $destinoArchivo) | Out-Null
        [System.IO.Compression.ZipFileExtensions]::ExtractToFile($entrada, $destinoArchivo, $true)
        $extraidos++
    }
} finally {
    $zip.Dispose()
}
if ($extraidos -eq 0) { Fallar "El zip de PostgreSQL no tiene la estructura pgsql/bin|lib|share esperada." }
Escribir "PostgreSQL: $extraidos archivos extraídos."
foreach ($bin in @('initdb.exe', 'pg_ctl.exe', 'postgres.exe', 'pg_dump.exe', 'pg_restore.exe', 'psql.exe')) {
    if (-not (Test-Path (Join-Path $DirPg "bin\$bin"))) { Fallar "Falta pgsql\bin\$bin en los binarios de PostgreSQL." }
}

# ------------------------------------------------------------------ VC++ Redistributable
if (-not $SinVcRedist) {
    $vc = Join-Path $Destino 'vc_redist.x64.exe'
    Descargar 'https://aka.ms/vs/17/release/vc_redist.x64.exe' (Join-Path $Descargas 'vc_redist.x64.exe') 'Visual C++ Redistributable x64'
    Copy-Item -Force (Join-Path $Descargas 'vc_redist.x64.exe') $vc
}

# ------------------------------------------------------------------ verificación
Escribir "Verificando el runtime..."
$php = Join-Path $DirPhp 'php.exe'
$salidaV = & $php -c (Join-Path $DirPhp 'php.ini') -v 2>&1
if ($LASTEXITCODE -ne 0) { Fallar "php.exe -v falló (código $LASTEXITCODE): $salidaV`nEn un equipo sin Visual C++ Redistributable 2015-2022 x64 esto falla con 0xC0000135." }
Escribir ($salidaV | Select-Object -First 1)

$modulos = (& $php -c (Join-Path $DirPhp 'php.ini') -m 2>&1) -join "`n"
$faltan = @()
foreach ($m in $extensiones + @('Zend OPcache', 'PDO', 'ctype', 'tokenizer', 'xml', 'dom', 'session', 'json', 'bcmath')) {
    if ($modulos -notmatch "(?im)^\s*$([regex]::Escape($m))\s*$") { $faltan += $m }
}
if ($faltan.Count) { Fallar "php -m no lista: $($faltan -join ', ')`n$modulos" }
Escribir "Extensiones PHP correctas."

$salidaPg = & (Join-Path $DirPg 'bin\initdb.exe') --version 2>&1
if ($LASTEXITCODE -ne 0) { Fallar "initdb --version falló: $salidaPg" }
Escribir "$salidaPg"

$resumen = @(
    "PHP: $versionPhp NTS vs17 x64 ($($paquetePhp.zip.path))",
    "PostgreSQL: $VersionPostgres (EDB binaries, solo bin/lib/share)",
    "cacert.pem: $shaCacert",
    "Preparado: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
)
Set-Content -Path (Join-Path $Destino 'VERSIONES.txt') -Value ($resumen -join "`r`n") -Encoding utf8
$resumen | ForEach-Object { Escribir $_ }

if (-not $ConservarDescargas) { Remove-Item -Recurse -Force $Descargas -ErrorAction SilentlyContinue }
Escribir "Runtime listo en $Destino"
