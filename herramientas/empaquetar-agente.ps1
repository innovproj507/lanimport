#Requires -Version 5.1
<#
Empaqueta el Agente de Impresion LAN (herramientas/agente-impresion) en
public/descargas/AgenteImpresionLAN.zip, el archivo que se descarga desde
la pantalla Impresoras del sistema. Correr despues de cambiar el agente.

Uso (desde la raiz del proyecto):
    powershell -ExecutionPolicy Bypass -File herramientas/empaquetar-agente.ps1
#>

$ErrorActionPreference = 'Stop'
$raiz = Split-Path $PSScriptRoot -Parent
$origen = Join-Path $PSScriptRoot 'agente-impresion'
$destino = Join-Path $raiz 'public\descargas'
$zip = Join-Path $destino 'AgenteImpresionLAN.zip'

New-Item -ItemType Directory -Force -Path $destino | Out-Null

# El ZIP lleva una carpeta "AgenteImpresionLAN" con los archivos adentro.
$temporal = Join-Path $env:TEMP ('AgenteImpresionLAN-' + [guid]::NewGuid().ToString('N'))
$carpeta = Join-Path $temporal 'AgenteImpresionLAN'
New-Item -ItemType Directory -Force -Path $carpeta | Out-Null

try {
    Copy-Item (Join-Path $origen '*') $carpeta
    if (Test-Path $zip) { Remove-Item $zip -Force }
    Compress-Archive -Path $carpeta -DestinationPath $zip
} finally {
    Remove-Item $temporal -Recurse -Force -ErrorAction SilentlyContinue
}

Write-Host "Listo: $zip ($((Get-Item $zip).Length) bytes)" -ForegroundColor Green
