# Agente de Impresion LAN

Servicio local (propio, sin dependencias de terceros) que permite al sistema
LAN Import - Export S.A. -- aunque este alojado en la nube -- imprimir
etiquetas directamente en las impresoras instaladas en este equipo.

Corre un pequeno servidor en `http://localhost:9898/` que el navegador
consulta para listar impresoras y enviar trabajos de impresion. Equivalente
casero al IoT Box de Odoo.

## Requisitos

- Windows 10/11 con PowerShell 5.1 (ya viene instalado de fabrica).
- No requiere instalar ningun programa adicional ni permisos de administrador.
- La impresora ya instalada en Windows (con su driver) en este equipo.

## Instalacion (una sola vez por equipo)

1. En el sistema, ir a **Impresoras** y pulsar **Descargar agente**
   (o copiar esta carpeta al equipo).
2. Descomprimir el ZIP y dar **doble clic en `Instalar.bat`**.
   Si Windows muestra "Windows protegio su PC", elegir *Mas informacion* > *Ejecutar de todas formas*.
3. El agente queda copiado en `%LOCALAPPDATA%\AgenteImpresionLAN` y arranca solo
   cada vez que el usuario inicia sesion en Windows (sin ventana visible).
   La carpeta descargada se puede borrar.
4. Volver a **Impresoras** en el sistema, recargar la pagina, elegir la impresora
   de etiquetas y pulsar **Guardar**.

## Desinstalar

Doble clic en `Desinstalar.bat` (en la carpeta descargada o en
`%LOCALAPPDATA%\AgenteImpresionLAN`).

## Solucion de problemas

- **La pagina de Impresoras dice "No conectado"**: el agente no esta
  corriendo. Volver a ejecutar `Instalar.bat`, o correr manualmente
  `powershell -ExecutionPolicy Bypass -File AgenteImpresion.ps1` para ver el error en pantalla.
- **El navegador bloquea la conexion** (Brave, Chrome): permitir al sitio el acceso a
  la red local / localhost (candado de la barra de direcciones > Configuracion del sitio).
- **El puerto 9898 esta ocupado**: instalar con otro puerto,
  `Instalar.ps1 -Puerto 9899`, y ajustar el puerto en `local-print.js`.
- **La etiqueta sale cortada o pequena**: revisar en Windows el tamano de papel de la
  impresora (Preferencias de impresion > tamano de etiqueta). El agente ajusta la imagen
  a toda la etiqueta y la gira si la orientacion no coincide.
- **Una impresora virtual (Microsoft Print to PDF, XPS) se queda "colgada"**:
  esas impresoras esperan un dialogo para elegir donde guardar el archivo,
  por lo que no sirven para impresion automatica. Usar una impresora fisica
  real (termica o de oficina).
