@echo off
title PostFlow
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0system\start.ps1"
echo.
pause
