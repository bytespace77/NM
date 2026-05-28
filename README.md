# Bytespace Server Monitoring Setup Guide

> **Maintained by:** Bytespace Sdn Bhd  
> **Alert emails:** support@bytespace.asia | protools77@gmail.com  
> **From:** bytespace950@gmail.com  

---

## Table of Contents

1. [Overview](#overview)
2. [Servers & Devices](#servers--devices)
3. [Cenviro (Windows Server 2016)](#cenviro-windows-server-2016)
4. [MnR Jetson (Ubuntu)](#mnr-jetson-ubuntu)
5. [GuardHouse PC 1 & 2 (Windows 11)](#guardhouse-pc-1--2-windows-11)
6. [KSB (Red Hat Enterprise Linux 8)](#ksb-red-hat-enterprise-linux-8)
7. [KPK (Ubuntu 20.04)](#kpk-ubuntu-2004)
8. [Common Commands](#common-commands)
9. [Troubleshooting](#troubleshooting)

---

## Overview

Each site runs the following monitoring stack:

| Component | Purpose |
|-----------|---------|
| **cpu-monitor** | Email alert when CPU/RAM > 90% with top processes |
| **Glances** | Web dashboard for real-time CPU/RAM/Disk/Network |
| **Uptime Kuma** | Ping/HTTP monitoring for services and devices |
| **PM2** | Process manager — keeps all services running |
| **wildfly-watchdog** | Cenviro only — monitors WildFly service |
| **monthly-report** | Cenviro only — sends Excel report on 1st of month |

---

## Servers & Devices

| Site | OS | IP | Services |
|------|----|----|---------|
| Cenviro | Windows Server 2016 | 172.33.65.44 | cpu-monitor, wildfly-watchdog, Glances, Uptime Kuma, monthly-report |
| MnR Jetson | Ubuntu | 192.168.0.214 | cpu-monitor, Glances |
| GuardHouse PC 1 | Windows 11 | — | cpu-monitor |
| GuardHouse PC 2 | Windows 11 | — | cpu-monitor |
| KSB Jetson | RHEL 8 | 192.168.100.200 | Uptime Kuma |
| KPK Jetson | Ubuntu 20.04 | 172.31.15.232 | cpu-monitor, Glances |

---

## Cenviro (Windows Server 2016)

**IP:** `172.33.65.44`  
**Access:** RDP

### Installed Stack
| Service | Port | URL |
|---------|------|-----|
| Uptime Kuma | 3001 | http://172.33.65.44:3001 |
| Glances | 61208 | http://172.33.65.44:61208 |

### Scripts Location
| Script | Purpose |
|--------|---------|
| `C:\cpu-monitor.ps1` | CPU/RAM monitor |
| `C:\cpu-monitor.js` | PM2 wrapper for cpu-monitor |
| `C:\wildfly-watchdog.ps1` | WildFly down detection + email |
| `C:\wildfly-watchdog.js` | PM2 wrapper for wildfly-watchdog |
| `C:\glances.js` | PM2 wrapper for Glances |
| `C:\monthly-report.py` | Monthly Excel report generator |
| `C:\monitoring-logs\cpu-ram-alerts.csv` | CPU/RAM alert log |
| `C:\monitoring-logs\wildfly-alerts.csv` | WildFly event log |

### PM2 Services
```powershell
pm2 list
pm2 logs cpu-monitor --lines 20
pm2 logs wildfly-watchdog --lines 20
pm2 restart cpu-monitor
pm2 restart wildfly-watchdog
pm2 restart glances
```

### WildFly
```powershell
# Check WildFly status
Get-Process java -ErrorAction SilentlyContinue

# Start WildFly manually
Start-Process "cmd.exe" -ArgumentList "/c C:\wildfly-22.0.0.Final\wildfly-22.0.0.Final\bin\standalone.bat" -WindowStyle Hidden

# Stop WildFly
Stop-Process -Name java -Force

# Stop auto-restart temporarily
pm2 stop wildfly-watchdog

# Resume auto-restart
pm2 start wildfly-watchdog
```

### Monthly Report
```powershell
# Generate current month report
python C:\monthly-report.py

# Generate specific month
python C:\monthly-report.py 2026-03
```

### Fresh Install (Windows Server)
```powershell
# 1. Install Node.js from https://nodejs.org (v20+)
# 2. Install Git from https://git-scm.com
# 3. Install Python from https://python.org (v3.11+)

# 4. Set execution policy
Set-ExecutionPolicy -Scope CurrentUser -ExecutionPolicy RemoteSigned -Force

# 5. Install PM2
npm install -g pm2
npm install -g pm2-windows-startup
pm2-windows-startup install

# 6. Install Python packages
pip install openpyxl glances[web]

# 7. Start services
pm2 start "C:\cpu-monitor.js" --name cpu-monitor
pm2 start "C:\wildfly-watchdog.js" --name wildfly-watchdog
pm2 start "C:\glances.js" --name glances
pm2 start "C:\uptime-kuma\server\server.js" --name uptime-kuma --interpreter node
pm2 save

# 8. Schedule monthly report
schtasks /create /tn "CenviroMonthlyReport" /tr "python C:\monthly-report.py" /sc monthly /d 1 /st 08:00 /ru SYSTEM /f
```

---

## MnR Jetson (Ubuntu)

**IP:** `192.168.0.214`  
**Access:** `ssh vms@192.168.0.214`

### Installed Stack
| Service | Port | URL |
|---------|------|-----|
| Glances | 61208 | http://192.168.0.214:61208 |

### Scripts Location
| Script | Purpose |
|--------|---------|
| `/home/vms/cpu-monitor.sh` | CPU/RAM monitor + email |
| `/home/vms/monitoring-logs/cpu-ram-alerts.csv` | Alert log |

### PM2 Commands
```bash
pm2 list
pm2 logs cpu-monitor --lines 20
pm2 restart cpu-monitor
```

### Glances
```bash
sudo systemctl status glances
sudo systemctl restart glances
```

### Fresh Install (Ubuntu)
```bash
# 1. Install dependencies
sudo apt-get update -y
sudo apt-get install -y python3 python3-pip curl git

# 2. Install Glances
pip3 install glances[web] --break-system-packages

# 3. Install Node.js 20
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y nodejs

# 4. Install PM2
sudo npm install -g pm2

# 5. Create monitoring folder
mkdir -p /home/vms/monitoring-logs

# 6. Start services
pm2 start /home/vms/cpu-monitor.sh --name cpu-monitor --interpreter bash
pm2 startup systemd -u vms --hp /home/vms
pm2 save

# 7. Glances systemd service
sudo systemctl enable glances
sudo systemctl start glances
```

---

## GuardHouse PC 1 & 2 (Windows 11)

**Access:** Physical / AnyDesk  
**npm path:** `C:\npm\pm2.cmd`

### Scripts Location
| Script | Purpose |
|--------|---------|
| `C:\cpu-monitor.ps1` | CPU/RAM monitor |
| `C:\cpu-monitor.js` | PM2 wrapper |
| `C:\monitoring-logs\cpu-ram-alerts.csv` | Alert log |

### PM2 Commands
```powershell
& "C:\npm\pm2.cmd" list
& "C:\npm\pm2.cmd" logs cpu-monitor --lines 20
& "C:\npm\pm2.cmd" restart cpu-monitor
```

### Fresh Install (Windows 11)
```powershell
# 1. Install Node.js from https://nodejs.org (v20+)
# 2. Install Python from https://python.org (v3.11+)

# 3. Set execution policy
Set-ExecutionPolicy -Scope CurrentUser -ExecutionPolicy RemoteSigned -Force

# 4. Set npm prefix
npm config set prefix "C:\npm"
$env:PATH += ";C:\npm"
[System.Environment]::SetEnvironmentVariable("PATH", $env:PATH, "Machine")

# 5. Install PM2
npm install -g pm2

# 6. Start services
& "C:\npm\pm2.cmd" start "C:\cpu-monitor.js" --name cpu-monitor
& "C:\npm\pm2.cmd" save

# 7. Startup via Task Scheduler
$action = New-ScheduledTaskAction -Execute "C:\npm\pm2.cmd" -Argument "resurrect"
$trigger = New-ScheduledTaskTrigger -AtStartup
$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest
Register-ScheduledTask -TaskName "PM2-Startup" -Action $action -Trigger $trigger -Principal $principal -Force
```

---

## KSB (Red Hat Enterprise Linux 8)

**IP:** `192.168.100.200`  
**Access:** TigerVNC → `192.168.100.200:5901` (Password: `24SafeG&`)  
**SSH:** `ssh admin@192.168.100.200`

### Installed Stack
| Service | Port | URL |
|---------|------|-----|
| Uptime Kuma | 3001 | http://192.168.100.200:3001 |

### PM2 Commands
```bash
pm2 list
pm2 logs uptime-kuma --lines 20
pm2 restart uptime-kuma
```

### Uptime Kuma Monitors (KSB)
| Name | Type | Target |
|------|------|--------|
| KSB Jetson | Ping | 192.168.100.200 |
| KSB Visitor Kiosk | Ping | 192.168.100.150 |
| KSB Adam Controller | Ping | 192.168.100.101 |
| KSB Access Point | Ping | 192.168.100.100 |
| KSB Antenna Gate1 IN | Ping | 192.168.100.201 |
| KSB Antenna Gate2 IN | Ping | 192.168.100.202 |
| KSB Antenna Gate3 OUT | Ping | 192.168.100.211 |
| KSB Antenna Gate4 OUT | Ping | 192.168.100.212 |
| KPK Jetson | Ping | 172.31.15.232 |
| KPK Kiosk | Ping | 172.31.15.239 |
| KPK SafeG | HTTP | http://172.31.15.232:8080 |
| KPK LPR | HTTPS | https://172.31.15.232:8443 |

### Fresh Install (RHEL 8)
```bash
# 1. Disable broken repos
sudo dnf config-manager --set-disabled pgdg13

# 2. Install git
sudo dnf install -y git

# 3. Install Node.js 20
curl -fsSL https://rpm.nodesource.com/setup_20.x | sudo bash -
sudo dnf install -y nodejs

# 4. Install PM2
sudo npm install -g pm2

# 5. Clone and setup Uptime Kuma
cd /home/admin
git clone https://github.com/louislam/uptime-kuma.git
cd uptime-kuma
npm run setup

# 6. Start
pm2 start server/server.js --name uptime-kuma
pm2 startup
pm2 save
```

---

## KPK (Ubuntu 20.04)

**IP:** `172.31.15.232`  
**Access:** AnyDesk → `1525713930` (Password: `admin101`)  
**SSH:** `ssh jetsonkpk@172.31.15.232`

### Installed Stack
| Service | Port | URL |
|---------|------|-----|
| Glances | 61208 | http://172.31.15.232:61208 |

### Scripts Location
| Script | Purpose |
|--------|---------|
| `/home/jetsonkpk/cpu-monitor.sh` | CPU/RAM monitor + email |
| `/home/jetsonkpk/monitoring-logs/cpu-ram-alerts.csv` | Alert log |

### PM2 Commands
```bash
pm2 list
pm2 logs cpu-monitor --lines 20
pm2 restart cpu-monitor
```

### Uptime Kuma Monitors (KPK)
| Name | Type | Target |
|------|------|--------|
| KPK Jetson | Ping | 172.31.15.232 |
| KPK Kiosk | Ping | 172.31.15.239 |
| KPK ALPR Cam 1 | Ping | 172.31.15.233 |
| KPK ALPR Cam 2 | Ping | 172.31.15.234 |
| KPK Antenna Gate1 | Ping | 172.31.15.235 |
| KPK Antenna Gate2 | Ping | 172.31.15.236 |
| KPK Access Point | Ping | 172.31.15.237 |
| KPK ICPDAS Controller | Ping | 172.31.15.238 |
| KSB Jetson | Ping | 192.168.100.200 |
| KPK SafeG | HTTP | http://172.31.15.232:8080 |
| KPK LPR | HTTPS | https://172.31.15.232:8443 |

### Fresh Install (Ubuntu 20.04)
```bash
# 1. Install dependencies
sudo apt-get update -y
sudo apt-get install -y python3 python3-pip curl git

# 2. Install Glances
pip3 install glances[web] --break-system-packages 2>/dev/null || pip3 install glances[web]

# 3. Install Node.js 20
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y nodejs

# 4. Install PM2
sudo npm install -g pm2

# 5. Create monitoring folder
mkdir -p /home/jetsonkpk/monitoring-logs

# 6. Start services
pm2 start /home/jetsonkpk/cpu-monitor.sh --name cpu-monitor --interpreter bash
pm2 startup systemd -u jetsonkpk --hp /home/jetsonkpk
pm2 save

# 7. Glances systemd service
sudo systemctl enable glances
sudo systemctl start glances
```

---

## Common Commands

### Check all PM2 services
```bash
# Linux
pm2 list
pm2 logs --lines 20

# Windows
pm2 list
# or
& "C:\npm\pm2.cmd" list
```

### Check recent CPU alerts
```bash
# Linux
tail -20 /home/<user>/monitoring-logs/cpu-ram-alerts.csv

# Windows
Get-Content C:\monitoring-logs\cpu-ram-alerts.csv | Select-Object -Last 10
```

### Test email manually
```bash
# Linux (Python SSL)
python3 /tmp/test-email.py

# Windows (.NET)
$smtp = New-Object System.Net.Mail.SmtpClient("smtp.gmail.com", 587)
$smtp.EnableSsl = $true
$smtp.Credentials = New-Object System.Net.NetworkCredential("bytespace950@gmail.com", "rppt gxvh wrpq ahzm")
[System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12
$msg = New-Object System.Net.Mail.MailMessage("bytespace950@gmail.com", "support@bytespace.asia", "Test", "Test")
$smtp.Send($msg)
```

### Stress test CPU
```bash
# Linux
nohup bash -c 'for i in $(seq 1 $(nproc)); do while true; do :; done & done' &

# Stop
pkill -f "while true"

# Windows
1..8 | ForEach-Object {
    Start-Process powershell -ArgumentList "-NoProfile -WindowStyle Hidden -Command `"while(`$true){ `$x=1; 1..10000000 | %{ `$x = `$x * 1.0000001 } }`"" -WindowStyle Hidden
}
# Stop
Get-Process powershell | Where-Object { $_.Id -ne $PID } | Stop-Process -Force
```

---

## Troubleshooting

### PM2 not found (Windows)
```powershell
# Find npm prefix
npm config get prefix

# Run pm2 directly
& "<npm-prefix>\pm2.cmd" list
```

### Email not sending (Linux)
```bash
# Use SSL port 465 instead of 587
# In cpu-monitor.sh, use SMTP_SSL not SMTP+starttls
python3 -c "
import smtplib, ssl
context = ssl.create_default_context()
with smtplib.SMTP_SSL('smtp.gmail.com', 465, context=context) as s:
    s.login('bytespace950@gmail.com', 'rppt gxvh wrpq ahzm')
    print('Connected OK')
"
```

### Email not sending (Windows)
```powershell
# Use Python instead of Send-MailMessage
python -c "
import smtplib, ssl
from email.mime.text import MIMEText
msg = MIMEText('Test')
msg['Subject'] = 'Test'
msg['From'] = 'bytespace950@gmail.com'
msg['To'] = 'support@bytespace.asia'
context = ssl.create_default_context()
with smtplib.SMTP_SSL('smtp.gmail.com', 465, context=context) as s:
    s.login('bytespace950@gmail.com', 'rppt gxvh wrpq ahzm')
    s.send_message(msg)
    print('Sent!')
"
```

### Uptime Kuma not accessible
```bash
pm2 restart uptime-kuma
pm2 logs uptime-kuma --lines 20
```

### Glances not accessible
```bash
# Linux
sudo systemctl restart glances
sudo systemctl status glances

# Windows
pm2 restart glances
```

### apt/dnf broken repo
```bash
# Ubuntu - fix broken sources
sudo apt-get update --fix-missing

# RHEL - disable broken repo
sudo dnf config-manager --set-disabled <repo-name>
```

---

## Email Configuration

| Setting | Value |
|---------|-------|
| SMTP Server | smtp.gmail.com |
| Port (Windows) | 587 (TLS) |
| Port (Linux) | 465 (SSL) |
| From | bytespace950@gmail.com |
| To | support@bytespace.asia |
| CC | protools77@gmail.com |
| App Password | rppt gxvh wrpq ahzm |

---

*Last updated: April 2026 — Bytespace Sdn Bhd*
