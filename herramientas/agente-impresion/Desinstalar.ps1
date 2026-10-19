#Requires -Version 5.1
<#
Detiene y desinstala el Agente de Impresion LAN de este equipo
(tarea programada y/o acceso directo en la carpeta Inicio).

Uso:
    powershell -ExecutionPolicy Bypass -File Desinstalar.ps1
#>

$ErrorActionPreference = 'Stop'
$nombreTarea = 'AgenteImpresionLAN'
$accesoInicio = Join-Path ([Environment]::GetFolderPath('Startup')) 'Agente de Impresion LAN.lnk'
$habia = $false

$tarea = Get-ScheduledTask -TaskName $nombreTarea -ErrorAction SilentlyContinue

if ($tarea) {
    Stop-ScheduledTask -TaskName $nombreTarea -ErrorAction SilentlyContinue
    Unregister-ScheduledTask -TaskName $nombreTarea -Confirm:$false
    $habia = $true
}

if (Test-Path $accesoInicio) {
    Remove-Item $accesoInicio -Force
    $habia = $true
}

Get-CimInstance Win32_Process -Filter "Name='powershell.exe'" |
    Where-Object { $_.CommandLine -like '*AgenteImpresion.ps1*' } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue; $habia = $true }

$carpetaAgente = Join-Path $env:LOCALAPPDATA 'AgenteImpresionLAN'

if (Test-Path $carpetaAgente) {
    Remove-Item $carpetaAgente -Recurse -Force -ErrorAction SilentlyContinue
    $habia = $true
}

if ($habia) {
    Write-Host "Agente de impresion desinstalado." -ForegroundColor Green
} else {
    Write-Host "El agente no esta instalado en este equipo."
}
