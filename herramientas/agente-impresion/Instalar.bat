@echo off
rem Instala el Agente de Impresion LAN (doble clic). No requiere administrador.
title Instalar Agente de Impresion LAN
powershell -NoProfile -ExecutionPolicy Bypass -Command "Get-ChildItem -LiteralPath '%~dp0' | Unblock-File"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0Instalar.ps1"
echo.
pause
