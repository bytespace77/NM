# Network Monitoring

A comprehensive web-based system for monitoring network devices, tracking maintenance history, scheduling repairs, and predicting device failures using AI analytics.

## 📋 Table of Contents
- [Features](#features)
- [System Requirements](#system-requirements)
- [Usage](#usage)
- [API Endpoints](#api-endpoints)
- [Security Best Practices](#security-best-practices)
- [Changelog](#changelog)


---

## ✨ Features

### Core Modules
- **Network Monitor** - Real-time device status monitoring across all networks
- **System Monitor** - Server and infrastructure health tracking
- **Reports** - Generate comprehensive health and analytics reports

### Key Capabilities
- ✅ Real-time device monitoring and alerts
- ✅ Maintenance task tracking and history
- ✅ Network range filtering and organization
- ✅ Multi-status filtering (online/offline/critical)
- ✅ Dark/Light mode support
- ✅ Responsive design (desktop & mobile)
- ✅ RESTful JSON API
- ✅ Role-based access control ready

---

## 💻 System Requirements

### Server
- **PHP**: 7.4 or higher (8.0+ recommended)
- **MySQL/MariaDB**: 5.7 or higher
- **Web Server**: Apache (with mod_rewrite) or Nginx
- **Memory**: 512MB minimum
- **Disk Space**: 1GB for application + database

### Browser
- Chrome/Edge (recommended)
- Firefox
- Safari
- Mobile browsers (iOS Safari, Chrome Mobile)

### Network
- HTTPS recommended for production
- CORS enabled on API endpoints
- Firewall rules for database access

---

## 🚀 Usage

### Accessing the Application
1. Open browser: `http://localhost/network-monitoring-app/`
2. Navigate using left sidebar menu
3. Use filters and search to narrow results
4. Click on devices/records to see details

### Network Monitor
- View all devices in real-time
- Status indicators: 🟢 Online, 🔴 Offline
- Search by device name or IP address
- Filter by network range and status
- Click device for detailed metrics

### Reports
- Generate health summaries
- Export maintenance reports
- AI-powered analytics
- Trend analysis over time

---

## 🔌 API Endpoints

### Maintenance History
```
GET  /api/api_maintenance_history.php?action=list&device_id=1&limit=100
GET  /api/api_maintenance_history.php?action=list&network_range=192.168.0.0/24
GET  /api/api_maintenance_history.php?action=get&id=5
POST /api/api_maintenance_history.php
     {device_id, task_name, task_category, status, performed_by, duration_minutes, description}
PUT  /api/api_maintenance_history.php
     {id, status, result, description}
GET  /api/api_maintenance_history.php?action=delete&id=5
GET  /api/api_maintenance_history.php?action=test  # Health check
```

### Dashboard Stats
```
GET /api/api_dashboard_stats.php
    Returns: {total_devices, online_count, offline_count, critical_count, avg_health_score, overdue_maintenance}
```

### Monitoring
```
GET /api/api_monitoring.php?action=scan_network&network=192.168.0.0/24
GET /api/api_monitoring.php?action=get_device_status&device_id=1
```

### Issue: JavaScript Console Errors
**Solution:**
1. Open F12 Console tab
2. Check for network errors (Network tab)
3. Verify API endpoints respond: check Network tab requests
4. Look for syntax errors in consol

## 🔐 Security Best Practices

1. **Change default credentials** in config.php
2. **Enable HTTPS** in production
3. **Keep PHP and dependencies updated**
4. **Use prepared statements** (already implemented in API)
5. **Limit API access** with authentication
6. **Set proper file permissions** (644 for config, 755 for dirs)
7. **Enable error logging**, disable error display
8. **Regular database backups**
9. **Use strong database passwords**
10. **Implement rate limiting** on API endpoints

---

## Support & Issues

For issues or questions:
1. Check browser Console (F12) for errors
2. Check PHP error logs: `/var/log/php-fpm.log`
3. Check MySQL logs: `/var/log/mysql/error.log`
4. Verify database connectivity
5. Test API endpoints with `?action=test`

## 📅 Changelog

### v1.0.0 (Current)
- ✅ Network monitoring dashboard
- ✅ Device health predictions
- ✅ Maintenance history tracking
- ✅ Schedule management
- ✅ AI analytics
- ✅ Report generation
- ✅ RESTful API

---

**Last Updated:** February 2026  
**Status:** Production Ready
