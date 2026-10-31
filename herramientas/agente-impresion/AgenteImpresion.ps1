#Requires -Version 5.1
<#
Agente de impresion local para LAN Import - Export S.A.
Corre un servidor HTTP en localhost para que el navegador (aunque la app
este en la nube) pueda listar e imprimir directamente en las impresoras
instaladas en este equipo. Equivalente casero al IoT Box de Odoo /
QZ Tray, sin depender de software de terceros.

Cada solicitud se procesa en su propio runspace para que un trabajo de
impresion lento o colgado (ej. una impresora virtual que espera un
dialogo) no bloquee al resto de las solicitudes.

Uso:
    powershell -ExecutionPolicy Bypass -File AgenteImpresion.ps1
    powershell -ExecutionPolicy Bypass -File AgenteImpresion.ps1 -Puerto 9898
#>

param(
    [int]$Puerto = 9898
)

Add-Type -AssemblyName System.Drawing

$ErrorActionPreference = 'Stop'
$version = '1.2.0'
$inicio = Get-Date

# El agente corre oculto: el registro va a un archivo para poder revisar por que fallo.
$carpetaLog = Join-Path $env:LOCALAPPDATA 'AgenteImpresionLAN'
New-Item -ItemType Directory -Force -Path $carpetaLog | Out-Null
$archivoLog = Join-Path $carpetaLog 'agente.log'

function Escribir-Log {
    param([string]$Mensaje)
    $linea = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] $Mensaje"
    Write-Host $linea
    try {
        if ((Test-Path $archivoLog) -and (Get-Item $archivoLog).Length -gt 1MB) {
            Move-Item $archivoLog "$archivoLog.1" -Force
        }
        Add-Content -Path $archivoLog -Value $linea -Encoding UTF8
    } catch {
        # Sin log no se detiene el agente.
    }
}

# Codigo que corre dentro de cada runspace para atender una solicitud.
# Debe ser autosuficiente: no puede depender de funciones/variables del
# script principal, solo de lo que se le pase explicitamente.
$manejarSolicitud = {
    param($context, $version, $inicio, $archivoLog)

    Add-Type -AssemblyName System.Drawing

    function Agregar-EncabezadosCors {
        param($Response)
        $Response.Headers.Add('Access-Control-Allow-Origin', '*')
        $Response.Headers.Add('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
        $Response.Headers.Add('Access-Control-Allow-Headers', 'Content-Type')
        $Response.Headers.Add('Access-Control-Allow-Private-Network', 'true')
    }

    function Enviar-Json {
        param($Response, [int]$StatusCode, $Objeto)
        $json = $Objeto | ConvertTo-Json -Depth 5 -Compress
        $bytes = [System.Text.Encoding]::UTF8.GetBytes($json)
        $Response.StatusCode = $StatusCode
        $Response.ContentType = 'application/json; charset=utf-8'
        Agregar-EncabezadosCors -Response $Response
        $Response.ContentLength64 = $bytes.Length
        $Response.OutputStream.Write($bytes, 0, $bytes.Length)
        $Response.OutputStream.Close()
    }

    function Obtener-Impresoras {
        return [System.Drawing.Printing.PrinterSettings]::InstalledPrinters | ForEach-Object { $_.ToString() }
    }

    function Imprimir-Imagen {
        param([string]$NombreImpresora, [string]$Url, [string]$Base64)

        $impresorasDisponibles = Obtener-Impresoras

        if ($impresorasDisponibles -notcontains $NombreImpresora) {
            throw "La impresora '$NombreImpresora' no esta instalada en este equipo."
        }

        $tempFile = [System.IO.Path]::GetTempFileName() + '.png'

        try {
            if ($Base64) {
                # El navegador ya tenia la imagen: no hace falta bajarla otra vez de internet.
                [System.IO.File]::WriteAllBytes($tempFile, [Convert]::FromBase64String($Base64))
            } else {
                # Windows viejos usan TLS 1.0 por defecto y el servidor HTTPS lo rechaza.
                [Net.ServicePointManager]::SecurityProtocol = [Net.ServicePointManager]::SecurityProtocol -bor [Net.SecurityProtocolType]::Tls12
                Invoke-WebRequest -Uri $Url -OutFile $tempFile -UseBasicParsing -TimeoutSec 15
            }

            $imagen = [System.Drawing.Image]::FromFile($tempFile)

            $doc = New-Object System.Drawing.Printing.PrintDocument
            $doc.PrinterSettings.PrinterName = $NombreImpresora
            $doc.DocumentName = 'Etiqueta LAN'
            # Sin la ventana "Imprimiendo..." (el agente corre oculto).
            $doc.PrintController = New-Object System.Drawing.Printing.StandardPrintController
            # Toda la etiqueta, no solo lo que queda dentro de los margenes de 1" de Windows
            # (en una etiqueta de 100x70 mm eso dejaba un area de 49x19 mm).
            $doc.OriginAtMargins = $false
            $doc.DefaultPageSettings.Margins = New-Object System.Drawing.Printing.Margins(0, 0, 0, 0)

            # Si la etiqueta del papel esta vertical y la imagen horizontal (o al reves), se gira.
            $area = $doc.DefaultPageSettings.PrintableArea
            if (($imagen.Width -gt $imagen.Height) -ne ($area.Width -gt $area.Height)) {
                $imagen.RotateFlip([System.Drawing.RotateFlipType]::Rotate90FlipNone)
            }

            $handler = {
                param($sender, $e)
                # Con OriginAtMargins = false el origen ya es la esquina imprimible del papel.
                $area = $e.PageSettings.PrintableArea
                $escala = [Math]::Min($area.Width / $imagen.Width, $area.Height / $imagen.Height)
                $anchoFinal = [int]($imagen.Width * $escala)
                $altoFinal = [int]($imagen.Height * $escala)
                $x = [int](($area.Width - $anchoFinal) / 2)
                $y = [int](($area.Height - $altoFinal) / 2)
                $e.Graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::NearestNeighbor
                $e.Graphics.DrawImage($imagen, $x, $y, $anchoFinal, $altoFinal)
                $e.HasMorePages = $false
            }

            $doc.add_PrintPage($handler)
            $doc.Print()
            $imagen.Dispose()
        } finally {
            if (Test-Path $tempFile) {
                Remove-Item $tempFile -Force -ErrorAction SilentlyContinue
            }
        }
    }

    $request = $context.Request
    $response = $context.Response

    try {
        if ($request.HttpMethod -eq 'OPTIONS') {
            Agregar-EncabezadosCors -Response $response
            $response.StatusCode = 204
            $response.OutputStream.Close()
            return
        }

        switch ($request.Url.AbsolutePath) {
            '/estado' {
                Enviar-Json -Response $response -StatusCode 200 -Objeto @{ ok = $true; version = $version; desde = $inicio.ToString('yyyy-MM-dd HH:mm:ss') }
            }
            '/impresoras' {
                Enviar-Json -Response $response -StatusCode 200 -Objeto @{ impresoras = @(Obtener-Impresoras) }
            }
            '/imprimir-imagen' {
                if ($request.HttpMethod -ne 'POST') {
                    Enviar-Json -Response $response -StatusCode 405 -Objeto @{ error = 'Metodo no permitido.' }
                    return
                }

                $reader = New-Object System.IO.StreamReader($request.InputStream, $request.ContentEncoding)
                $cuerpo = $reader.ReadToEnd() | ConvertFrom-Json
                $reader.Close()

                Imprimir-Imagen -NombreImpresora $cuerpo.impresora -Url $cuerpo.url -Base64 $cuerpo.imagen

                Enviar-Json -Response $response -StatusCode 200 -Objeto @{ ok = $true }
            }
            default {
                Enviar-Json -Response $response -StatusCode 404 -Objeto @{ error = 'Ruta no encontrada.' }
            }
        }
    } catch {
        try {
            Add-Content -Path $archivoLog -Value "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] Error en $($request.Url.AbsolutePath): $($_.Exception.Message)" -Encoding UTF8
        } catch {
        }
        try {
            Enviar-Json -Response $response -StatusCode 500 -Objeto @{ error = $_.Exception.Message }
        } catch {
            # La respuesta ya pudo haberse cerrado; nada mas que hacer.
        }
    }
}

function Iniciar-Listener {
    $nuevo = New-Object System.Net.HttpListener
    $nuevo.Prefixes.Add("http://localhost:$Puerto/")
    $nuevo.Start()
    return $nuevo
}

try {
    $listener = Iniciar-Listener
} catch {
    Escribir-Log "No se pudo iniciar el servidor en el puerto $Puerto. ¿Ya hay otra instancia corriendo? Error: $($_.Exception.Message)"
    exit 1
}

$runspacePool = [runspacefactory]::CreateRunspacePool(1, 10)
$runspacePool.Open()
$tareasActivas = New-Object System.Collections.Generic.List[object]

Escribir-Log "Agente de impresion LAN corriendo en http://localhost:$Puerto/  (version $version)"
Escribir-Log "Impresoras detectadas: $([System.Drawing.Printing.PrinterSettings]::InstalledPrinters -join ', ')"
Escribir-Log 'Presiona Ctrl+C para detener.'

# Antes, cualquier error del listener (p.ej. el navegador cortaba una peticion, o Windows volvia
# de suspension) salia del bucle y el agente se cerraba: "No se pudo conectar" hasta que la tarea
# programada lo volvia a abrir. Ahora el error se registra y el listener se vuelve a crear.
try {
    while ($true) {
        try {
            if ($null -eq $listener -or -not $listener.IsListening) {
                Escribir-Log 'Reiniciando el servidor...'
                try { if ($listener) { $listener.Close() } } catch { }
                $listener = Iniciar-Listener
                Escribir-Log 'Servidor reiniciado.'
            }

            # Espera con pausas para notar si el listener murio (GetContext() se quedaria colgado).
            $espera = $listener.GetContextAsync()
            while (-not $espera.Wait(5000)) {
                if (-not $listener.IsListening) {
                    throw 'El servidor dejo de escuchar.'
                }
            }
            $context = $espera.Result

            $ps = [powershell]::Create()
            $ps.RunspacePool = $runspacePool
            [void]$ps.AddScript($manejarSolicitud).AddArgument($context).AddArgument($version).AddArgument($inicio).AddArgument($archivoLog)
            $handle = $ps.BeginInvoke()
            $tareasActivas.Add(@{ Powershell = $ps; Handle = $handle })

            # Limpiar tareas ya terminadas para no acumular memoria.
            $tareasActivas.RemoveAll([Predicate[object]] {
                param($tarea)
                if ($tarea.Handle.IsCompleted) {
                    $tarea.Powershell.EndInvoke($tarea.Handle) | Out-Null
                    $tarea.Powershell.Dispose()
                    return $true
                }
                return $false
            }) | Out-Null
        } catch {
            Escribir-Log "Error del servidor: $($_.Exception.Message)"
            try { $listener.Close() } catch { }
            $listener = $null
            Start-Sleep -Seconds 2
        }
    }
} finally {
    try { $listener.Close() } catch { }
    $runspacePool.Close()
    $runspacePool.Dispose()
}
