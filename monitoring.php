<?php
require_once 'config.php';

$db = getDB();
$required_tables = ['system_metrics', 'system_logs', 'alert_rules', 'alert_history', 'device_statistics'];
$missing_tables = [];

foreach ($required_tables as $table) {
    $result = $db->query("SHOW TABLES LIKE '$table'");
    if (!$result || $result->num_rows == 0) {
        $missing_tables[] = $table;
    }
}

$tablesExist = empty($missing_tables);

// Check how many data points we have
$dataPointCount = 0;
if ($tablesExist) {
    $countResult = $db->query("SELECT COUNT(*) as count FROM system_metrics WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    if ($countResult) {
        $dataPointCount = $countResult->fetch_assoc()['count'];
    }
}

$yourIP = getLocalIP();
$parts = explode('.', $yourIP);
$networkRange = $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0/24';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Monitoring - Network Monitor</title>
    <link rel="icon" href="data:image/svg+xml,
        <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'>
        <text y='1.0em' font-size='85'>📊</text>
        </svg>">
    
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <!-- jsPDF for PDF export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --bg-primary: #000000;
            --bg-secondary: #0f0f0f;
            --bg-tertiary: #0a0a0a;
            --border-color: #1a1a1a;
            --border-hover: #333;
            --text-primary: #ffffff;
            --text-secondary: #666;
            --text-tertiary: #444;
        }

        body.light-mode {
            --bg-primary: #ffffff;
            --bg-secondary: #f5f5f5;
            --bg-tertiary: #fafafa;
            --border-color: #e0e0e0;
            --border-hover: #d0d0d0;
            --text-primary: #000000;
            --text-secondary: #666666;
            --text-tertiary: #999999;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            overflow-x: hidden;
            transition: background 0.3s ease, color 0.3s ease;
        }

        /* Theme Toggle Button - Hides on scroll */
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1001;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            padding: 12px 16px;
            border-radius: 8px;
            cursor: pointer;
            color: var(--text-primary);
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            transition: all 0.3s ease;
            opacity: 1;
            transform: translateY(0);
        }

        .theme-toggle.hidden {
            opacity: 0;
            transform: translateY(-20px);
            pointer-events: none;
        }

        .theme-toggle:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }

        .theme-toggle.hidden:hover {
            transform: translateY(-20px);
        }

        .theme-toggle span {
            font-size: 13px;
            display: none;
        }

        @media (min-width: 768px) {
            .theme-toggle span {
                display: inline;
            }
        }

        .app-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 320px;
            background: var(--bg-primary);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 100;
            transition: all 0.3s ease;
        }

        .sidebar-header {
            padding: 24px;
            border-bottom: 1px solid var(--border-color);
        }

        .sidebar-header h1 {
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .sidebar-header p {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .sidebar-stats {
            padding: 16px 24px;
            background: var(--bg-tertiary);
            border-bottom: 1px solid var(--border-color);
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .sidebar-stat {
            text-align: center;
        }

        .sidebar-stat-value {
            font-size: 24px;
            font-weight: 800;
            display: block;
        }

        .sidebar-stat-value.cpu { color: #667eea; }
        .sidebar-stat-value.ram { color: #10b981; }
        .sidebar-stat-value.network { color: #10b981; }
        .sidebar-stat-value.disk { color: #ef4444; }

        .sidebar-stat-label {
            font-size: 10px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 4px;
            display: block;
        }

        .sidebar-menu {
            flex: 1;
            padding: 16px;
            overflow-y: auto;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 8px;
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.3s ease;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
        }

        .menu-item:hover {
            background: var(--bg-secondary);
        }

        .menu-item.active {
            background: #667eea;
            color: white;
        }

        .menu-item i {
            font-size: 18px;
        }

        .sidebar-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border-color);
            font-size: 12px;
            color: var(--text-tertiary);
        }

        .main-content {
            flex: 1;
            margin-left: 320px;
            padding: 40px;
        }

        .header {
            margin-bottom: 32px;
        }

        .header h1 {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .header p {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .stat-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .stat-card-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-card-icon.cpu { background: rgba(102, 126, 234, 0.1); color: #667eea; }
        .stat-card-icon.ram { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .stat-card-icon.temp { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .stat-card-icon.disk { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

        .stat-card-value {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 8px;
        }

        
        /* Sparkline Styles */
        .stat-card.updating {
            animation: cardPulse 0.5s ease;
        }
        
        @keyframes cardPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.01); box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3); }
        }
        
        .stat-card-body {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-top: 12px;
        }
        
        .stat-card-main {
            flex: 1;
        }
        
        .stat-card-sparkline {
            width: 100px;
            height: 40px;
            flex-shrink: 0;
        }
        
        .stat-card-trend {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 4px;
            margin-top: 4px;
        }
        
        .trend-arrow { font-size: 12px; }
        .trend-up { color: #ef4444; background: rgba(239, 68, 68, 0.1); }
        .trend-down { color: #10b981; background: rgba(16, 185, 129, 0.1); }
        .trend-stable { color: #666; background: rgba(102, 102, 102, 0.1); }


        .stat-card-label {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .chart-container {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 32px;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-container h2 {
            font-size: 18px;
            font-weight: 700;
        }

        .chart-actions {
            display: flex;
            gap: 8px;
        }

        .chart-wrapper {
            height: 300px;
            position: relative;
        }

        .alerts-section {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 32px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-header h2 {
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-item {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .alert-item.WARNING {
            border-left: 4px solid #f59e0b;
        }

        .alert-item.CRITICAL {
            border-left: 4px solid #ef4444;
        }

        .alert-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            margin-right: 8px;
        }

        .alert-badge.WARNING {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .alert-badge.CRITICAL {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .log-filters {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
        }

        .log-filters select {
            padding: 10px 14px;
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
        }

        .logs-container {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            max-height: 400px;
            overflow-y: auto;
        }

        .log-item {
            padding: 14px 18px;
            border-bottom: 1px solid var(--border-color);
            font-family: 'Courier New', monospace;
            font-size: 13px;
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .log-item:hover {
            background: var(--bg-secondary);
        }

        .log-item:last-child {
            border-bottom: none;
        }

        .log-time {
            color: var(--text-tertiary);
            min-width: 140px;
        }

        .log-level {
            min-width: 80px;
            font-weight: 700;
        }

        .log-level.INFO { color: #667eea; }
        .log-level.WARNING { color: #f59e0b; }
        .log-level.ERROR { color: #f97316; }
        .log-level.CRITICAL { color: #ef4444; }

        .log-category {
            color: #667eea;
            min-width: 100px;
        }

        .log-message {
            flex: 1;
            color: var(--text-secondary);
        }

        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn:hover {
            background: var(--bg-secondary);
            transform: translateY(-1px);
        }

        .btn-success {
            background: #10b981;
            color: white;
            border: none;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-export {
            background: #667eea;
            color: white;
            border: none;
        }

        .btn-export:hover {
            background: #5568d3;
        }

        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
            min-width: 300px;
            z-index: 1000;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateX(400px); }
            to { transform: translateX(0); }
        }

        .no-temperature-notice {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 32px;
        }

        .no-temperature-notice h3 {
            color: #f59e0b;
            margin-bottom: 12px;
            font-size: 16px;
        }

        .no-temperature-notice p {
            margin-bottom: 8px;
            font-size: 14px;
        }

        .no-temperature-notice ul {
            margin-left: 20px;
            font-size: 14px;
        }

        .no-temperature-notice a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .data-notice {
            background: rgba(102, 126, 234, 0.1);
            border: 1px solid #667eea;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .data-notice i {
            font-size: 24px;
            color: #667eea;
        }

        .data-notice-content h4 {
            color: #667eea;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .data-notice-content p {
            font-size: 13px;
            color: var(--text-secondary);
        }
        .chart-actions {
        display: flex;
        gap: 8px;
        }

        .btn-export {
            background: #667eea;
            color: white;
            border: none;
        }

        .btn-export:hover {
            background: #5568d3;
        }
        .stat-card-icon.network {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()" id="themeToggle">
        <i class="bi bi-moon-fill" id="themeIcon"></i>
        <span id="themeText">Dark</span>
    </button>

    <div class="app-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h1>📊 System Monitor</h1>
                <p>Real-time Performance</p>
            </div>

            <div class="sidebar-stats">
                <div class="sidebar-stat">
                    <span class="sidebar-stat-value cpu" id="sidebarCPU">--</span>
                    <span class="sidebar-stat-label">CPU</span>
                </div>
                <div class="sidebar-stat">
                    <span class="sidebar-stat-value ram" id="sidebarRAM">--</span>
                    <span class="sidebar-stat-label">RAM</span>
                </div>
                <div class="sidebar-stat">
                    <span class="sidebar-stat-value network" id="sidebarNetwork">--</span>
                    <span class="sidebar-stat-label">Network</span>
                </div>
                <div class="sidebar-stat">
                    <span class="sidebar-stat-value disk" id="sidebarDisk">--</span>
                    <span class="sidebar-stat-label">Disk</span>
                </div>
            </div>

            <div class="sidebar-menu">
                <h3 style="font-size: 11px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 14px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                    <i class="bi bi-compass-fill" style="font-size: 13px; color: #667eea;"></i>
                    Dashboard Navigation
                </h3>
                <a href="index.php" class="menu-item">
                    <i class="bi bi-grid"></i>
                    <span>Dashboard</span>
                </a>
                <a href="monitoring.php" class="menu-item active">
                    <i class="bi bi-activity"></i>
                    <span>System Monitoring</span>
                </a>
            </div>

            <div class="sidebar-footer">
                <div>Network: <?php echo $networkRange; ?></div>
                <div>Your IP: <?php echo $yourIP; ?></div>
                <?php if ($dataPointCount > 0): ?>
                <div style="margin-top: 8px; color: #10b981;">
                    📊 Data Points: <?php echo $dataPointCount; ?>
                </div>
                <?php endif; ?>
                <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border-color); font-size: 11px; color: var(--text-tertiary);">
                    <b>Developed by Bytespace Teams</b><br>
                </div>
            </div>
        </div>

        <div class="main-content">
            <?php if (!$tablesExist): ?>
                <div class="no-temperature-notice" style="background: rgba(239, 68, 68, 0.1); border-color: #ef4444;">
                    <h3 style="color: #ef4444;">⚠️ Monitoring Tables Not Found</h3>
                    <p>The following tables are missing: <?php echo implode(', ', $missing_tables); ?></p>
                    <p>Please run system_monitor.php in web mode or CLI mode to create the required tables.</p>
                </div>
            <?php else: ?>
                <div class="header">
                    <h1>System Monitoring</h1>
                    <p>Real-time monitoring of system performance and health</p>
                </div>

                <?php if ($dataPointCount < 10): ?>
                <div class="data-notice">
                    <i class="bi bi-info-circle"></i>
                    <div class="data-notice-content">
                        <h4>📊 Building Historical Data</h4>
                        <p>Only <?php echo $dataPointCount; ?> data points collected. Chart will fill up as system_monitor.php runs (60-second intervals). Expected full graph in ~24 hours.</p>
                    </div>
                </div>
                <?php endif; ?>

                <div class="stats-grid">
                    <div class="stat-card" id="cpuCard">
                        <div class="stat-card-header">
                            <div class="stat-card-icon cpu">
                                <i class="bi bi-cpu"></i>
                            </div>
                            <div style="flex: 1;">
                                <div class="stat-card-label">CPU Usage</div>
                                <div class="stat-card-trend trend-stable" id="cpuTrend">
                                    <span class="trend-arrow">→</span> <span>0.0%</span>
                                </div>
                            </div>
                        </div>
                        <div class="stat-card-body">
                            <div class="stat-card-main">
                                <div class="stat-card-value" id="cpuValue">--</div>
                                <div class="stat-card-label" id="cpuDetail">--</div>
                            </div>
                            <canvas class="stat-card-sparkline" id="cpuSparkline" width="200" height="80"></canvas>
                        </div>
                    </div>

                    <div class="stat-card" id="ramCard">
                        <div class="stat-card-header">
                            <div class="stat-card-icon ram">
                                <i class="bi bi-memory"></i>
                            </div>
                            <div style="flex: 1;">
                                <div class="stat-card-label">RAM Usage</div>
                                <div class="stat-card-trend trend-stable" id="ramTrend">
                                    <span class="trend-arrow">→</span> <span>0.0%</span>
                                </div>
                            </div>
                        </div>
                        <div class="stat-card-body">
                            <div class="stat-card-main">
                                <div class="stat-card-value" id="ramValue">--</div>
                                <div class="stat-card-label" id="ramDetail">--</div>
                            </div>
                            <canvas class="stat-card-sparkline" id="ramSparkline" width="200" height="80"></canvas>
                        </div>
                    </div>

                   <!-- Network Traffic + Latency Card -->
                    <div class="stat-card" id="networkLatencyCard">
                        <div class="stat-card-header">
                            <div class="stat-card-icon network">
                                 <i class="bi bi-activity"></i>
                            </div>
                        <div>
                        <div class="stat-card-label">Network + Latency</div>
                           <div class="stat-card-trend" id="networkTrend">
                                  <span class="trend-arrow">→</span>
                                   <span class="trend-value">0 MB/s</span>
                             </div>
                            </div>
                        </div>
                        <div class="stat-card-body">
                            <div class="stat-card-main">
                                <div class="stat-card-value" id="networkValue">-- MB/s</div>
                                <div class="stat-card-label" id="latencyDetail">Ping: -- ms</div>
                            </div>
                            <canvas class="stat-card-sparkline" id="network-sparkline" width="200" height="80"></canvas>
                        </div>
                    </div>


                    <div class="stat-card" id="diskCard">
                        <div class="stat-card-header">
                            <div class="stat-card-icon disk">
                                <i class="bi bi-hdd"></i>
                            </div>
                            <div style="flex: 1;">
                                <div class="stat-card-label">Disk Usage</div>
                                <div class="stat-card-trend trend-stable" id="diskTrend">
                                    <span class="trend-arrow">→</span> <span>0.0%</span>
                                </div>
                            </div>
                        </div>
                        <div class="stat-card-body">
                            <div class="stat-card-main">
                                <div class="stat-card-value" id="diskValue">--</div>
                                <div class="stat-card-label" id="diskDetail">--</div>
                            </div>
                            <canvas class="stat-card-sparkline" id="diskSparkline" width="200" height="80"></canvas>
                        </div>
                    </div>
                </div>

                <div id="noTempNotice"></div>

                <div class="chart-container">
                    <div class="chart-header">
                        <h2>📈 Performance History (24 Hours)</h2>
                        <div class="chart-actions">
                            <button class="btn btn-export" onclick="exportToPDF()">
                                <i class="bi bi-file-pdf"></i>
                                Export PDF
                            </button>
                            <button class="btn btn-export" onclick="exportToCSV()">
                                <i class="bi bi-file-spreadsheet"></i>
                                Export CSV
                            </button>
                        </div>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>

                <div class="alerts-section">
                    <div class="section-header">
                        <h2>
                            <i class="bi bi-exclamation-triangle"></i> Active Alerts (<span id="alertCount">0</span>)
                        </h2>
                        <button class="btn" onclick="loadActiveAlerts()">
                            <i class="bi bi-arrow-clockwise"></i>
                            Refresh
                        </button>
                    </div>
                    <div id="alertsContainer"></div>
                </div>

                <div class="alerts-section">
                    <div class="section-header">
                        <h2>
                            <i class="bi bi-file-earmark-text"></i> System Logs
                        </h2>
                        <div class="chart-actions">
                            <button class="btn btn-export" onclick="exportLogsToCSV()">
                                <i class="bi bi-file-spreadsheet"></i>
                                Export CSV
                            </button>
                            <button class="btn" onclick="loadLogs()">
                                <i class="bi bi-arrow-clockwise"></i>
                                Refresh
                            </button>
                        </div>
                    </div>
                    <div class="log-filters">
                        <select id="logLevelFilter" onchange="loadLogs()">
                            <option value="">All Levels</option>
                            <option value="INFO">INFO</option>
                            <option value="WARNING">WARNING</option>
                            <option value="ERROR">ERROR</option>
                            <option value="CRITICAL">CRITICAL</option>
                        </select>
                        <select id="logCategoryFilter" onchange="loadLogs()">
                            <option value="">All Categories</option>
                            <option value="system">System</option>
                            <option value="network">Network</option>
                            <option value="scan">Scan</option>
                            <option value="alert">Alert</option>
                            <option value="monitoring">Monitoring</option>
                        </select>
                    </div>
                    <div class="logs-container" id="logsContainer">
                        <div style="text-align: center; padding: 40px; color: var(--text-tertiary);">
                            <p>Loading logs...</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="toastContainer"></div>

    <script>
        const API_URL = 'api_monitoring.php';

        // ========================================
        // SPARKLINE FUNCTIONALITY  
        // ========================================
        const sparklineData = {
            cpu: [],
            ram: [],
            temp: [],
            disk: [],
            network: []
        };
        
        const MAX_SPARKLINE_POINTS = 20;
        
        function drawSparkline(canvasId, data, color, fillColor) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return;
            
            const ctx = canvas.getContext('2d');
            const width = canvas.width;
            const height = canvas.height;
            
            ctx.clearRect(0, 0, width, height);
            
            if (data.length < 2) return;
            
            const max = Math.max(...data);
            const min = Math.min(...data);
            const range = max - min || 1;
            
            ctx.beginPath();
            ctx.strokeStyle = color;
            ctx.lineWidth = 2.5;
            ctx.lineJoin = 'round';
            ctx.lineCap = 'round';
            
            data.forEach((value, index) => {
                const x = (index / (data.length - 1)) * width;
                const y = height - ((value - min) / range) * (height - 10) - 5;
                
                if (index === 0) ctx.moveTo(x, y);
                else ctx.lineTo(x, y);
            });
            
            ctx.stroke();
            
            ctx.lineTo(width, height);
            ctx.lineTo(0, height);
            ctx.closePath();
            ctx.fillStyle = fillColor;
            ctx.fill();
        }
        
        function updateSparklineData(metric, value) {
            if (value === null || value === undefined || isNaN(value)) return;
            
            sparklineData[metric].push(parseFloat(value));
            
            if (sparklineData[metric].length > MAX_SPARKLINE_POINTS) {
                sparklineData[metric].shift();
            }
        }
        
        function updateTrend(metric, trendId, cardId) {
            const data = sparklineData[metric];
            const trendEl = document.getElementById(trendId);
            if (!trendEl || data.length < 5) {
                if (trendEl) {
                    trendEl.innerHTML = '<span class="trend-arrow">→</span> <span>N/A</span>';
                    trendEl.className = 'stat-card-trend trend-stable';
                }
                return;
            }
            
            const current = data[data.length - 1];
            const previous = data[Math.max(0, data.length - 6)];
            const change = current - previous;
            const changePercent = previous !== 0 ? (change / previous * 100).toFixed(1) : 0;
            
            let arrow, className, sign;
            if (Math.abs(change) > 0.5) {
                if (change > 0) {
                    arrow = '↑';
                    className = 'trend-up';
                    sign = '+';
                } else {
                    arrow = '↓';
                    className = 'trend-down';
                    sign = '';
                }
            } else {
                arrow = '→';
                className = 'trend-stable';
                sign = '';
            }
            
            trendEl.innerHTML = `<span class="trend-arrow">${arrow}</span> <span>${sign}${Math.abs(changePercent)}%</span>`;
            trendEl.className = 'stat-card-trend ' + className;
            
            const card = document.getElementById(cardId);
            if (card) {
                card.classList.add('updating');
                setTimeout(() => card.classList.remove('updating'), 500);
            }
        }
        let performanceChart = null;
        let refreshInterval = null;
        let hasTemperatureSensor = false;
        let chartData = null; // Store for export
        
        document.addEventListener('DOMContentLoaded', function() {
            loadTheme();
            initializeMonitoring();
            refreshInterval = setInterval(autoRefresh, 10000);
        });
        
        function toggleTheme() {
            const body = document.body;
            const themeIcon = document.getElementById('themeIcon');
            const themeText = document.getElementById('themeText');
            
            if (body.classList.contains('light-mode')) {
                body.classList.remove('light-mode');
                themeIcon.className = 'bi bi-moon-fill';
                themeText.textContent = 'Dark';
                localStorage.setItem('theme', 'dark');
            } else {
                body.classList.add('light-mode');
                themeIcon.className = 'bi bi-sun-fill';
                themeText.textContent = 'Light';
                localStorage.setItem('theme', 'light');
            }
            
            if (performanceChart) {
                loadHistoricalData();
            }
        }

        function loadTheme() {
            const savedTheme = localStorage.getItem('theme');
            const body = document.body;
            const themeIcon = document.getElementById('themeIcon');
            const themeText = document.getElementById('themeText');
            
            if (savedTheme === 'light') {
                body.classList.add('light-mode');
                if (themeIcon) themeIcon.className = 'bi bi-sun-fill';
                if (themeText) themeText.textContent = 'Light';
            } else {
                if (themeIcon) themeIcon.className = 'bi bi-moon-fill';
                if (themeText) themeText.textContent = 'Dark';
            }
        }
        
        async function initializeMonitoring() {
            await loadCurrentMetrics();
            await loadHistoricalData();
            await loadActiveAlerts();
            await loadLogs();
        }
        
        async function loadCurrentMetrics() {
            try {
                const response = await fetch(`${API_URL}?action=current_metrics`);
                const data = await response.json();
                
                if (data.success) {
                    const metrics = data.metrics;
                    
                // CPU with frequency
                const cpuVal = metrics.cpu_usage ? parseFloat(metrics.cpu_usage).toFixed(1) + '%' : 'N/A';
                const cpuFreq = metrics.cpu_frequency ? parseFloat(metrics.cpu_frequency).toFixed(2) : 0;
                const cpuDetail = cpuFreq > 0 ? `@ ${cpuFreq} GHz` : 'Frequency N/A';

                document.getElementById('cpuValue').textContent = cpuVal;
                document.getElementById('cpuDetail').textContent = cpuDetail;
                
                // Update CPU sparkline
                const cpuNum = metrics.cpu_usage ? parseFloat(metrics.cpu_usage) : null;
                if (cpuNum !== null) {
                    updateSparklineData('cpu', cpuNum);
                    drawSparkline('cpuSparkline', sparklineData.cpu, '#667eea', 'rgba(102, 126, 234, 0.15)');
                    updateTrend('cpu', 'cpuTrend', 'cpuCard');
                }

                // RAM with GB details
                const ramVal = metrics.ram_usage ? parseFloat(metrics.ram_usage).toFixed(1) + '%' : 'N/A';
                const ramUsedGB = metrics.ram_used_gb ? parseFloat(metrics.ram_used_gb).toFixed(1) : 0;
                const ramTotalGB = metrics.ram_total_gb ? parseFloat(metrics.ram_total_gb).toFixed(1) : 0;
                const ramDetail = ramTotalGB > 0 ? `${ramUsedGB} GB / ${ramTotalGB} GB` : 'Details N/A';

                document.getElementById('ramValue').textContent = ramVal;
                document.getElementById('ramDetail').textContent = ramDetail;
                
                // Update RAM sparkline
                const ramNum = metrics.ram_usage ? parseFloat(metrics.ram_usage) : null;
                if (ramNum !== null) {
                    updateSparklineData('ram', ramNum);
                    drawSparkline('ramSparkline', sparklineData.ram, '#10b981', 'rgba(16, 185, 129, 0.15)');
                    updateTrend('ram', 'ramTrend', 'ramCard');
                }

                // Network Traffic + Latency
                if (metrics.network_upload_speed !== undefined && metrics.network_upload_speed !== null) {
                    const networkUpload = parseFloat(metrics.network_upload_speed).toFixed(2);
                    const networkDownload = parseFloat(metrics.network_download_speed || 0).toFixed(2);
                    const networkTotal = parseFloat(metrics.network_total_speed || 0).toFixed(2);
                    const latency = metrics.latency_ms ? parseFloat(metrics.latency_ms).toFixed(0) : 'N/A';

                    document.getElementById('networkValue').textContent = networkTotal + ' MB/s';
                    document.getElementById('latencyDetail').textContent = `Ping: ${latency} ms | ↑${networkUpload} ↓${networkDownload} MB/s`;
                    document.getElementById('sidebarNetwork').textContent = networkTotal + ' MB/s';

                    // Update Network sparkline
                    updateSparklineData('network', parseFloat(networkTotal));
                    drawSparkline('network-sparkline', sparklineData.network, '#10b981', 'rgba(16, 185, 129, 0.15)');
                    updateTrend('network', 'networkTrend', 'networkLatencyCard');
                } else {
                    document.getElementById('networkValue').textContent = '0 MB/s';
                    document.getElementById('sidebarNetwork').textContent = '-- MB/s';
                    document.getElementById('latencyDetail').textContent = 'Ping: -- ms | Collecting data...';
                }

                // Disk with GB details
                const diskVal = metrics.disk_usage ? parseFloat(metrics.disk_usage).toFixed(1) + '%' : 'N/A';
                const diskUsedGB = metrics.disk_used_gb ? parseFloat(metrics.disk_used_gb).toFixed(1) : 0;
                const diskTotalGB = metrics.disk_total_gb ? parseFloat(metrics.disk_total_gb).toFixed(1) : 0;
                const diskDetail = diskTotalGB > 0 ? `${diskUsedGB} GB / ${diskTotalGB} GB` : 'Details N/A';

                document.getElementById('diskValue').textContent = diskVal;
                document.getElementById('diskDetail').textContent = diskDetail;
                
                // Update Disk sparkline
                const diskNum = metrics.disk_usage ? parseFloat(metrics.disk_usage) : null;
                if (diskNum !== null) {
                    updateSparklineData('disk', diskNum);
                    drawSparkline('diskSparkline', sparklineData.disk, '#ef4444', 'rgba(239, 68, 68, 0.15)');
                    updateTrend('disk', 'diskTrend', 'diskCard');
                }
                // Update sidebar stats
                document.getElementById('sidebarCPU').textContent = cpuVal;
                document.getElementById('sidebarRAM').textContent = ramVal;
                // Sidebar network updated after network metrics
                document.getElementById('sidebarDisk').textContent = diskVal;
                    
                    if (!metrics.temperature || metrics.temperature === null) {
                        if (!hasTemperatureSensor) {
                            hasTemperatureSensor = false;
                            showNoTemperatureNotice();
                        }
                    } else {
                        hasTemperatureSensor = true;
                        hideNoTemperatureNotice();
                    }
                }
            } catch (error) {
                console.error('Error loading current metrics:', error);
            }
        }
        
        function showNoTemperatureNotice() {
            const notice = document.getElementById('noTempNotice');
            if (notice.innerHTML) return;
            
            notice.innerHTML = `
                <div class="no-temperature-notice">
                    <h3>🌡️ Temperature Sensor Not Detected</h3>
                    <p>Your system doesn't appear to have temperature sensors accessible via WMI. To enable temperature monitoring:</p>
                </div>
            `;
        }
        
        function hideNoTemperatureNotice() {
            const notice = document.getElementById('noTempNotice');
            notice.innerHTML = '';
        }
        
        async function loadHistoricalData() {
            try {
                const response = await fetch(`${API_URL}?action=historical_metrics&hours=24`);
                const data = await response.json();
                
                if (data.success) {
                    chartData = data.data; // Store for export
                    createPerformanceChart(data.data);
                }
            } catch (error) {
                console.error('Error loading historical data:', error);
            }
        }
        
        function createPerformanceChart(data) {
            const ctx = document.getElementById('performanceChart');
            
            if (performanceChart) {
                performanceChart.destroy();
            }
            
            const isDark = !document.body.classList.contains('light-mode');
            const textColor = isDark ? '#666' : '#999';
            const gridColor = isDark ? '#1a1a1a' : '#e0e0e0';
            
            const datasets = [
                {
                    label: 'CPU Usage (%)',
                    data: data.cpu,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    spanGaps: true // Connect sparse data points
                },
                {
                    label: 'RAM Usage (%)',
                    data: data.ram,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    spanGaps: true
                }
            ];
            
            if (data.temperature && data.temperature.some(t => t !== null)) {
                datasets.push({
                    label: 'Temperature (°C)',
                    data: data.temperature,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    yAxisID: 'y1',
                    spanGaps: true
                });
            }
            
            if (data.disk && data.disk.some(d => d !== null)) {
                datasets.push({
                    label: 'Disk Usage (%)',
                    data: data.disk,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true,
                    spanGaps: true
                });
            }
            
            performanceChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: { 
                                color: textColor, 
                                font: { family: 'Montserrat', weight: 600 } 
                            }
                        },
                        tooltip: {
                            backgroundColor: isDark ? '#1a1a1a' : '#ffffff',
                            titleColor: isDark ? '#ffffff' : '#000000',
                            bodyColor: isDark ? '#666' : '#666',
                            borderColor: isDark ? '#333' : '#e0e0e0',
                            borderWidth: 1
                        }
                    },
                    scales: {
                        x: {
                            ticks: { color: textColor, font: { family: 'Montserrat' } },
                            grid: { color: gridColor }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            ticks: { color: textColor, font: { family: 'Montserrat' } },
                            grid: { color: gridColor },
                            beginAtZero: true,
                            max: 100
                        },
                        y1: {
                            type: 'linear',
                            display: datasets.length > 2,
                            position: 'right',
                            ticks: { color: textColor, font: { family: 'Montserrat' } },
                            grid: { drawOnChartArea: false },
                            beginAtZero: true
                        }
                    }
                }
            });
        }
        
        // Export to PDF
        function exportToPDF() {
            if (!chartData) {
                alert('No data to export. Please wait for data to load.');
                return;
            }

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            // Title
            doc.setFontSize(20);
            doc.setTextColor(40);
            doc.text('System Monitoring Report', 14, 22);
            
            // Subtitle
            doc.setFontSize(11);
            doc.setTextColor(100);
            const now = new Date();
            const dateStr = now.toLocaleString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            doc.text(`Generated: ${dateStr}`, 14, 32);
            doc.text(`Period: Last 24 Hours`, 14, 38);
            doc.text(`Data Points: ${chartData.labels.length}`, 14, 44);
            
            // Prepare table data
            const tableData = [];
            for (let i = 0; i < chartData.labels.length; i++) {
                tableData.push([
                    chartData.labels[i],
                    chartData.cpu[i] ? chartData.cpu[i].toFixed(1) + '%' : 'N/A',
                    chartData.ram[i] ? chartData.ram[i].toFixed(1) + '%' : 'N/A',
                    chartData.temperature[i] ? chartData.temperature[i].toFixed(1) + '°C' : 'N/A',
                    chartData.disk[i] ? chartData.disk[i].toFixed(1) + '%' : 'N/A'
                ]);
            }
            
            // Create table
            doc.autoTable({
                startY: 50,
                head: [['Time', 'CPU', 'RAM', 'Temperature', 'Disk']],
                body: tableData,
                theme: 'grid',
                headStyles: {
                    fillColor: [102, 126, 234],
                    textColor: 255,
                    fontStyle: 'bold',
                    fontSize: 9
                },
                bodyStyles: {
                    fontSize: 8
                },
                alternateRowStyles: {
                    fillColor: [245, 245, 245]
                }
            });
            
            // Save PDF
            const filename = `system-monitoring-${now.getTime()}.pdf`;
            doc.save(filename);
            
            showToast('PDF exported successfully!', 'INFO');
        }

        // Export to CSV
        function exportToCSV() {
            if (!chartData) {
                alert('No data to export. Please wait for data to load.');
                return;
            }

            let csv = 'Time,CPU Usage (%),RAM Usage (%),Temperature (°C),Disk Usage (%)\n';
            
            for (let i = 0; i < chartData.labels.length; i++) {
                const row = [
                    chartData.labels[i],
                    chartData.cpu[i] || '',
                    chartData.ram[i] || '',
                    chartData.temperature[i] || '',
                    chartData.disk[i] || ''
                ];
                csv += row.join(',') + '\n';
            }
            
            // Create download link
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            
            const now = new Date();
            const filename = `system-monitoring-${now.getTime()}.csv`;
            
            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            link.style.visibility = 'hidden';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            showToast('CSV exported successfully!', 'INFO');
        }
        
        async function loadActiveAlerts() {
            try {
                const response = await fetch(`${API_URL}?action=active_alerts&limit=20`);
                const data = await response.json();
                
                if (data.success) {
                    displayAlerts(data.alerts);
                    document.getElementById('alertCount').textContent = data.count;
                }
            } catch (error) {
                console.error('Error loading alerts:', error);
            }
        }
        
        function displayAlerts(alerts) {
            const container = document.getElementById('alertsContainer');
            
            if (alerts.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; padding: 40px; color: var(--text-tertiary);">
                        <i class="bi bi-check-circle" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
                        No active alerts - All systems normal
                    </div>
                `;
                return;
            }
            
            container.innerHTML = alerts.map(alert => `
                <div class="alert-item ${alert.alert_level}">
                    <div class="alert-content">
                        <div class="alert-header">
                            <span class="alert-badge ${alert.alert_level}">${alert.alert_level}</span>
                            <strong>${alert.device_name}</strong>
                            <span>(${alert.ip_address})</span>
                        </div>
                        <div class="alert-message">${alert.message}</div>
                        <div class="alert-time">${formatTimestamp(alert.created_at)}</div>
                    </div>
                    <div>
                        <button class="btn btn-success" onclick="acknowledgeAlert(${alert.id})">
                            <i class="bi bi-check2"></i> Acknowledge
                        </button>
                    </div>
                </div>
            `).join('');
        }
        
        async function acknowledgeAlert(alertId) {
            try {
                const formData = new FormData();
                formData.append('alert_id', alertId);
                formData.append('acknowledged_by', 'Admin');
                
                const response = await fetch(`${API_URL}?action=acknowledge_alert`, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast('Alert acknowledged', 'INFO');
                    await loadActiveAlerts();
                }
            } catch (error) {
                console.error('Error acknowledging alert:', error);
            }
        }
        
        async function loadLogs() {
            const level = document.getElementById('logLevelFilter').value;
            const category = document.getElementById('logCategoryFilter').value;
            
            try {
                let url = `${API_URL}?action=system_logs&limit=50`;
                if (level) url += `&level=${level}`;
                if (category) url += `&category=${category}`;
                
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success) {
                    displayLogs(data.logs);
                }
            } catch (error) {
                console.error('Error loading logs:', error);
            }
        }
        
        function displayLogs(logs) {
            const container = document.getElementById('logsContainer');
            
            if (logs.length === 0) {
                container.innerHTML = `
                    <div style="text-align: center; padding: 40px; color: var(--text-tertiary);">
                        No logs found
                    </div>
                `;
                return;
            }
            
            container.innerHTML = logs.map(log => `
                <div class="log-item">
                    <span class="log-time">${formatTimestamp(log.created_at)}</span>
                    <span class="log-level ${log.log_level}">[${log.log_level}]</span>
                    <span class="log-category">${log.category}</span>
                    <span class="log-message">${log.message}</span>
                </div>
            `).join('');
        }
        
        function showToast(message, level = 'INFO') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${level}`;
            toast.innerHTML = `
                <div style="font-weight: 700; margin-bottom: 6px;">${level}</div>
                <div>${message}</div>
            `;
            
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 5000);
        }
        
        async function autoRefresh() {
            await loadCurrentMetrics();
            await loadHistoricalData();
            await loadActiveAlerts();
        }
        
        function formatTimestamp(timestamp) {
            return new Date(timestamp).toLocaleString('en-MY', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            });
        }
        // Export logs to CSV function
        async function exportLogsToCSV() {
            const level = document.getElementById('logLevelFilter').value;
            const category = document.getElementById('logCategoryFilter').value;
            
            try {
                let url = `${API_URL}?action=export_logs_csv&limit=1000`;
                if (level) url += `&level=${level}`;
                if (category) url += `&category=${category}`;
                
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success && data.logs) {
                    // Create CSV content
                    let csv = 'Timestamp,Level,Category,Device,IP Address,Message\n';
                    
                    data.logs.forEach(log => {
                        const timestamp = log.created_at || '';
                        const level = log.log_level || '';
                        const category = log.category || '';
                        const device = (log.device_name || 'System').replace(/,/g, ';');
                        const ip = log.device_ip || 'N/A';
                        const message = (log.message || '').replace(/,/g, ';').replace(/"/g, '""');
                        
                        csv += `"${timestamp}","${level}","${category}","${device}","${ip}","${message}"\n`;
                    });
                    
                    // Create download link
                    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    const url = URL.createObjectURL(blob);
                    
                    const now = new Date();
                    const filename = `system-logs-${now.getTime()}.csv`;
                    
                    link.setAttribute('href', url);
                    link.setAttribute('download', filename);
                    link.style.visibility = 'hidden';
                    
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    showToast(`Exported ${data.logs.length} log entries to CSV`, 'INFO');
                } else {
                    showToast('No logs to export', 'WARNING');
                }
            } catch (error) {
                console.error('Error exporting logs:', error);
                showToast('Failed to export logs', 'ERROR');
            }
        }

        // Fix refresh issue - add cache busting
        async function loadCurrentMetrics() {
            try {
                const response = await fetch(`${API_URL}?action=current_metrics&t=${Date.now()}`);
                const data = await response.json();
                
                if (data.success) {
                    const metrics = data.metrics;
                    
                    // CPU
                    const cpuVal = metrics.cpu_usage ? parseFloat(metrics.cpu_usage).toFixed(1) + '%' : 'N/A';
                    const cpuFreq = metrics.cpu_frequency ? parseFloat(metrics.cpu_frequency).toFixed(2) : 0;
                    const cpuDetail = cpuFreq > 0 ? `@ ${cpuFreq} GHz` : 'Frequency N/A';
                    
                    document.getElementById('cpuValue').textContent = cpuVal;
                    document.getElementById('cpuDetail').textContent = cpuDetail;
                    document.getElementById('sidebarCPU').textContent = cpuVal;
                    
                    // Update CPU sparkline
                    const cpuNum = metrics.cpu_usage ? parseFloat(metrics.cpu_usage) : null;
                    if (cpuNum !== null) {
                        updateSparklineData('cpu', cpuNum);
                        drawSparkline('cpuSparkline', sparklineData.cpu, '#667eea', 'rgba(102, 126, 234, 0.15)');
                        updateTrend('cpu', 'cpuTrend', 'cpuCard');
                    }
                    
                    // RAM
                    const ramVal = metrics.ram_usage ? parseFloat(metrics.ram_usage).toFixed(1) + '%' : 'N/A';
                    const ramUsedGB = metrics.ram_used_gb ? parseFloat(metrics.ram_used_gb).toFixed(1) : 0;
                    const ramTotalGB = metrics.ram_total_gb ? parseFloat(metrics.ram_total_gb).toFixed(1) : 0;
                    const ramDetail = ramTotalGB > 0 ? `${ramUsedGB} GB / ${ramTotalGB} GB` : 'Details N/A';
                    
                    document.getElementById('ramValue').textContent = ramVal;
                    document.getElementById('ramDetail').textContent = ramDetail;
                    document.getElementById('sidebarRAM').textContent = ramVal;
                    
                    // Update RAM sparkline
                    const ramNum = metrics.ram_usage ? parseFloat(metrics.ram_usage) : null;
                    if (ramNum !== null) {
                        updateSparklineData('ram', ramNum);
                        drawSparkline('ramSparkline', sparklineData.ram, '#10b981', 'rgba(16, 185, 129, 0.15)');
                        updateTrend('ram', 'ramTrend', 'ramCard');
                    }
                    
                    // NETWORK TRAFFIC + LATENCY (NEW!)
                    if (metrics.network_upload_speed !== null && metrics.network_upload_speed !== undefined) {
                        const networkUpload = parseFloat(metrics.network_upload_speed || 0).toFixed(2);
                        const networkDownload = parseFloat(metrics.network_download_speed || 0).toFixed(2);
                        const networkTotal = parseFloat(metrics.network_total_speed || 0).toFixed(2);
                        const latency = metrics.latency_ms ? parseFloat(metrics.latency_ms).toFixed(0) : 'N/A';
                        
                        document.getElementById('networkValue').textContent = networkTotal + ' MB/s';
                        document.getElementById('latencyDetail').textContent = `Ping: ${latency} ms | ↑${networkUpload} ↓${networkDownload} MB/s`;
                        document.getElementById('sidebarNetwork').textContent = networkTotal + ' MB/s';
                        
                        // Update Network sparkline
                        updateSparklineData('network', parseFloat(networkTotal));
                        drawSparkline('network-sparkline', sparklineData.network, '#10b981', 'rgba(16, 185, 129, 0.15)');
                        updateTrend('network', 'networkTrend', 'networkLatencyCard');
                    } else {
                        document.getElementById('networkValue').textContent = '-- MB/s';
                        document.getElementById('latencyDetail').textContent = 'Ping: -- ms | Collecting data...';
                    }
                        document.getElementById('sidebarNetwork').textContent = '-- MB/s';
                    
                    // TEMPERATURE (keep for backward compatibility, but can be hidden)
                    const tempVal = metrics.temperature ? parseFloat(metrics.temperature).toFixed(1) + '°C' : 'N/A';
                    const tempDetail = metrics.temperature ? 'CPU Temperature' : 'No sensor detected';
                    
                    if (document.getElementById('tempValue')) {
                        document.getElementById('tempValue').textContent = tempVal;
                        document.getElementById('tempDetail').textContent = tempDetail;
                        // Sidebar network updated after network metrics
                        
                        // Update Temp sparkline
                        const tempNum = metrics.temperature ? parseFloat(metrics.temperature) : null;
                        if (tempNum !== null) {
                            updateSparklineData('temp', tempNum);
                            drawSparkline('tempSparkline', sparklineData.temp, '#f59e0b', 'rgba(245, 158, 11, 0.15)');
                            updateTrend('temp', 'tempTrend', 'tempCard');
                        }
                    }
                    
                    // DISK
                    const diskVal = metrics.disk_usage ? parseFloat(metrics.disk_usage).toFixed(1) + '%' : 'N/A';
                    const diskUsedGB = metrics.disk_used_gb ? parseFloat(metrics.disk_used_gb).toFixed(1) : 0;
                    const diskTotalGB = metrics.disk_total_gb ? parseFloat(metrics.disk_total_gb).toFixed(1) : 0;
                    const diskDetail = diskTotalGB > 0 ? `${diskUsedGB} GB / ${diskTotalGB} GB` : 'Details N/A';
                    
                    document.getElementById('diskValue').textContent = diskVal;
                    document.getElementById('diskDetail').textContent = diskDetail;
                    document.getElementById('sidebarDisk').textContent = diskVal;
                    
                    // Update Disk sparkline
                    const diskNum = metrics.disk_usage ? parseFloat(metrics.disk_usage) : null;
                    if (diskNum !== null) {
                        updateSparklineData('disk', diskNum);
                        drawSparkline('diskSparkline', sparklineData.disk, '#ef4444', 'rgba(239, 68, 68, 0.15)');
                        updateTrend('disk', 'diskTrend', 'diskCard');
                    }
                    
                    // Temperature sensor notice
                    if (!metrics.temperature || metrics.temperature === null) {
                        if (!hasTemperatureSensor) {
                            hasTemperatureSensor = false;
                            showNoTemperatureName();
                        }
                    } else {
                        hasTemperatureSensor = true;
                        hideNoTemperatureNotice();
                    }
                }
            } catch (error) {
                console.error('Error loading current metrics:', error);
            }
        }

        // Also update loadHistoricalData to prevent caching
        async function loadHistoricalData() {
            try {
                // Add timestamp to prevent caching
                const response = await fetch(`${API_URL}?action=historical_metrics&hours=24&t=${Date.now()}`);
                const data = await response.json();
                
                if (data.success) {
                    chartData = data.data;
                    createPerformanceChart(data.data);
                }
            } catch (error) {
                console.error('Error loading historical data:', error);
            }
        }
    </script>
</body>
</html>