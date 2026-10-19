@echo off
rem Desinstala el Agente de Impresion LAN (doble clic).
title Desinstalar Agente de Impresion LAN
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0Desinstalar.ps1"
echo.
pause
