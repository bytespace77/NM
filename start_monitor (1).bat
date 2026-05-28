@echo off
title Network Monitor - System Monitor Service
color 0A

echo.
echo ========================================
echo  NETWORK MONITOR - SYSTEM MONITOR
echo ========================================
echo.
echo Starting monitoring service...
echo Keep this window open!
echo.
echo Press Ctrl+C to stop monitoring
echo.
echo ========================================
echo.

cd /d "%~dp0"

php system_monitor.php

echo.
echo ========================================
echo  MONITORING STOPPED
echo ========================================
echo.
pause
