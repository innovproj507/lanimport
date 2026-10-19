#Requires -Version 5.1
<#
Instala el Agente de Impresion LAN para que arranque automaticamente
cada vez que este usuario inicia sesion en Windows (sin ventana visible).

Usa una tarea programada; si Windows no la permite (sin permisos de
administrador), deja un acceso directo en la carpeta Inicio del usuario.

Uso:
    powershell -ExecutionPolicy Bypass -File Instalar.ps1
#>

param(
    [int]$Puerto = 9898
)

$ErrorActionPreference = 'Stop'
$nombreTarea = 'AgenteImpresionLAN'
$accesoInicio = Join-Path ([Environment]::GetFolderPath('Startup')) 'Agente de Impresion LAN.lnk'

if (-not (Test-Path (Join-Path $PSScriptRoot 'AgenteImpresion.ps1'))) {
    Write-Host "No se encontro AgenteImpresion.ps1 junto a este instalador." -ForegroundColor Red
    exit 1
}

# El agente se copia a una carpeta fija del usuario: sigue funcionando aunque se borre
# la carpeta descargada (Descargas, escritorio...).
$carpetaAgente = Join-Path $env:LOCALAPPDATA 'AgenteImpresionLAN'
New-Item -ItemType Directory -Force -Path $carpetaAgente | Out-Null
foreach ($archivo in 'AgenteImpresion.ps1', 'Desinstalar.ps1', 'Desinstalar.bat', 'LEEME.md') {
    $origen = Join-Path $PSScriptRoot $archivo
    if ((Test-Path $origen) -and ((Resolve-Path $origen).Path -ne (Join-Path $carpetaAgente $archivo))) {
        Copy-Item $origen $carpetaAgente -Force
    }
}
# Archivos bajados de internet traen la marca "bloqueado" de Windows.
Get-ChildItem $carpetaAgente | Unblock-File -ErrorAction SilentlyContinue

$scriptAgente = Join-Path $carpetaAgente 'AgenteImpresion.ps1'
$argumentos = "-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File `"$scriptAgente`" -Puerto $Puerto"

# Detener una version anterior que este corriendo (para cargar la nueva).
Get-CimInstance Win32_Process -Filter "Name='powershell.exe'" |
    Where-Object { $_.CommandLine -like '*AgenteImpresion.ps1*' } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }

$tareaExistente = Get-ScheduledTask -TaskName $nombreTarea -ErrorAction SilentlyContinue

if ($tareaExistente) {
    Write-Host "Ya existe una instalacion previa, se actualizara."
    Unregister-ScheduledTask -TaskName $nombreTarea -Confirm:$false -ErrorAction SilentlyContinue
}

$modo = 'tarea'

try {
    $accion = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument $argumentos
    $disparador = New-ScheduledTaskTrigger -AtLogOn -User $env:USERNAME
    $configuracion = New-ScheduledTaskSettingsSet `
        -AllowStartIfOnBatteries `
        -DontStopIfGoingOnBatteries `
        -StartWhenAvailable `
        -ExecutionTimeLimit ([TimeSpan]::Zero)

    Register-ScheduledTask -TaskName $nombreTarea `
        -Action $accion `
        -Trigger $disparador `
        -Settings $configuracion `
        -Description 'Agente local de impresion para el sistema LAN Import - Export S.A. (permite imprimir etiquetas desde el navegador).' `
        | Out-Null
} catch {
    # Sin permisos para tareas programadas: acceso directo en la carpeta Inicio del usuario.
    $modo = 'inicio'
    $shell = New-Object -ComObject WScript.Shell
    $acceso = $shell.CreateShortcut($accesoInicio)
    $acceso.TargetPath = 'powershell.exe'
    $acceso.Arguments = $argumentos
    $acceso.WorkingDirectory = $carpetaAgente
    $acceso.WindowStyle = 7
    $acceso.Description = 'Agente de Impresion LAN'
    $acceso.Save()
}

if ($modo -eq 'tarea') {
    Write-Host "Instalado como tarea programada. Arrancara automaticamente al iniciar sesion." -ForegroundColor Green
    Write-Host "Iniciandolo ahora..."
    Start-ScheduledTask -TaskName $nombreTarea
} else {
    Write-Host "Instalado en la carpeta Inicio del usuario. Arrancara automaticamente al iniciar sesion." -ForegroundColor Green
    Write-Host "Iniciandolo ahora..."
    Start-Process -FilePath 'powershell.exe' -ArgumentList $argumentos -WindowStyle Hidden
}

Start-Sleep -Seconds 3

try {
    $estado = Invoke-RestMethod -Uri "http://localhost:$Puerto/estado" -TimeoutSec 5
    Write-Host "Agente corriendo correctamente (version $($estado.version))." -ForegroundColor Green
    Write-Host "Abre el sistema en el navegador, ve a Impresoras y elige las impresoras de este equipo."
} catch {
    Write-Host "El agente no respondio todavia. Puede tardar unos segundos, o revisa que el puerto $Puerto este libre." -ForegroundColor Yellow
}
