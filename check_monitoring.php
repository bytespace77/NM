<?php
/**
 * System Diagnostic & Health Check
 * Checks database, monitoring status, and system health
 */

require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');

echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔍 System Diagnostic - Network Monitor</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Consolas', 'Monaco', monospace;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: rgba(0,0,0,0.8);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        
        h1 {
            font-size: 32px;
            margin-bottom: 10px;
            text-align: center;
            background: linear-gradient(90deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .subtitle {
            text-align: center;
            color: #aaa;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        h2 {
            color: #10b981;
            margin-top: 30px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #10b981;
            font-size: 20px;
        }
        
        pre {
            background: #1a1a1a;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            border-left: 4px solid #667eea;
            margin: 10px 0;
        }
        
        .good { color: #10b981; font-weight: bold; }
        .bad { color: #ef4444; font-weight: bold; }
        .warning { color: #f59e0b; font-weight: bold; }
        .info { color: #3b82f6; font-weight: bold; }
        
        .status-box {
            background: #1a1a1a;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 4px solid #667eea;
        }
        
        .metric {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #333;
        }
        
        .metric:last-child {
            border-bottom: none;
        }
        
        .metric-label {
            color: #aaa;
        }
        
        .metric-value {
            font-weight: bold;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .timestamp {
            text-align: center;
            color: #666;
            margin-top: 30px;
            font-size: 12px;
        }
        
        .icon {
            font-size: 20px;
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 System Diagnostic</h1>
        <div class="subtitle">Network Monitor - Health Check</div>
HTML;

$db = getDB();
$issues = [];
$warnings = [];
$info = [];

// ========================================
// 1. DATABASE TABLES CHECK
// ========================================
echo "<h2><span class='icon'>🗄️</span> 1. Database Tables</h2>";
echo "<div class='status-box'>";

$required_tables = [
    'devices' => 'Network devices table',
    'system_metrics' => 'System monitoring metrics',
    'system_logs' => 'Activity and event logs',
    'alert_rules' => 'Alert configuration rules',
    'alert_history' => 'Alert history and acknowledgments',
    'device_statistics' => 'Device statistics (24h averages)',
    'networks' => 'Discovered networks'
];

$missing_tables = [];

foreach ($required_tables as $table => $description) {
    $result = $db->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "<div class='metric'>";
        echo "<span class='metric-label'>$table</span>";
        echo "<span class='metric-value good'>✓ EXISTS</span>";
        echo "</div>";
    } else {
        echo "<div class='metric'>";
        echo "<span class='metric-label'>$table</span>";
        echo "<span class='metric-value bad'>✗ MISSING</span>";
        echo "</div>";
        $missing_tables[] = $table;
        $issues[] = "Missing table: $table ($description)";
    }
}

echo "</div>";

if (!empty($missing_tables)) {
    echo "<div class='status-box'>";
    echo "<span class='bad'>⚠️ CRITICAL: Missing tables detected!</span><br>";
    echo "Please import the SQL file: <code>network_monitor__1_.sql</code>";
    echo "</div>";
}

// ========================================
// 2. MONITORING STATUS
// ========================================
echo "<h2><span class='icon'>📊</span> 2. Monitoring Status</h2>";
echo "<div class='status-box'>";

// Check recent data (last 5 minutes)
$result = $db->query("SELECT COUNT(*) as count, MAX(recorded_at) as latest 
                      FROM system_metrics 
                      WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
$monitoring = $result->fetch_assoc();

echo "<div class='metric'>";
echo "<span class='metric-label'>Data points (last 5 min)</span>";
if ($monitoring['count'] > 0) {
    echo "<span class='metric-value good'>{$monitoring['count']} points</span>";
} else {
    echo "<span class='metric-value bad'>0 points</span>";
    $issues[] = "No recent monitoring data - system_monitor.php may not be running";
}
echo "</div>";

echo "<div class='metric'>";
echo "<span class='metric-label'>Latest data timestamp</span>";
if ($monitoring['latest']) {
    $latest_time = new DateTime($monitoring['latest']);
    $now = new DateTime();
    $diff = $now->getTimestamp() - $latest_time->getTimestamp();
    
    if ($diff < 60) {
        echo "<span class='metric-value good'>{$monitoring['latest']} (Live ✓)</span>";
    } elseif ($diff < 300) {
        echo "<span class='metric-value warning'>{$monitoring['latest']} ({$diff}s ago)</span>";
        $warnings[] = "Last data is {$diff} seconds old";
    } else {
        echo "<span class='metric-value bad'>{$monitoring['latest']} ({$diff}s ago)</span>";
        $issues[] = "Monitoring appears stale or stopped";
    }
} else {
    echo "<span class='metric-value bad'>No data available</span>";
    $issues[] = "No metrics data in database";
}
echo "</div>";

// Check data for last 24 hours
$result24h = $db->query("SELECT COUNT(*) as count FROM system_metrics 
                         WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$data24h = $result24h->fetch_assoc();

echo "<div class='metric'>";
echo "<span class='metric-label'>Data points (24 hours)</span>";
if ($data24h['count'] > 0) {
    echo "<span class='metric-value good'>{$data24h['count']} points</span>";
    $info[] = "{$data24h['count']} data points collected in last 24 hours";
} else {
    echo "<span class='metric-value warning'>No historical data</span>";
    $warnings[] = "No historical data - charts will be empty";
}
echo "</div>";

echo "</div>";

// ========================================
// 3. LATEST METRICS
// ========================================
echo "<h2><span class='icon'>⚡</span> 3. Latest Metrics</h2>";

$result = $db->query("SELECT * FROM system_metrics ORDER BY recorded_at DESC LIMIT 1");
if ($result && $result->num_rows > 0) {
    $metrics = $result->fetch_assoc();
    echo "<div class='status-box'>";
    echo "<pre>";
    echo "📊 CPU Usage:        " . ($metrics['cpu_usage'] ?? 'NULL') . "%\n";
    echo "🧠 RAM Usage:        " . ($metrics['ram_usage'] ?? 'NULL') . "%\n";
    echo "🌡️  Temperature:      " . ($metrics['temperature'] ?? 'N/A') . "°C\n";
    echo "💾 Disk Usage:       " . ($metrics['disk_usage'] ?? 'NULL') . "%\n";
    echo "📡 Network Upload:   " . ($metrics['network_upload_speed'] ?? '0') . " MB/s\n";
    echo "📥 Network Download: " . ($metrics['network_download_speed'] ?? '0') . " MB/s\n";
    echo "🏓 Latency:          " . ($metrics['latency_ms'] ?? 'NULL') . " ms\n";
    echo "⏰ Recorded:         " . ($metrics['recorded_at'] ?? 'NULL');
    echo "</pre>";
    echo "</div>";
} else {
    echo "<div class='status-box'>";
    echo "<span class='bad'>⚠️ No metrics data found in database!</span><br>";
    echo "This means <code>system_monitor.php</code> is not running or has never been started.";
    echo "</div>";
}

// ========================================
// 4. DEVICE INFO
// ========================================
echo "<h2><span class='icon'>🖥️</span> 4. Monitored Devices</h2>";
echo "<div class='status-box'>";

$devices = $db->query("SELECT COUNT(*) as total, 
                       SUM(CASE WHEN status='online' THEN 1 ELSE 0 END) as online,
                       SUM(CASE WHEN status='offline' THEN 1 ELSE 0 END) as offline
                       FROM devices");
$device_stats = $devices->fetch_assoc();

echo "<div class='metric'>";
echo "<span class='metric-label'>Total Devices</span>";
echo "<span class='metric-value info'>{$device_stats['total']}</span>";
echo "</div>";

echo "<div class='metric'>";
echo "<span class='metric-label'>Online Devices</span>";
echo "<span class='metric-value good'>{$device_stats['online']}</span>";
echo "</div>";

echo "<div class='metric'>";
echo "<span class='metric-label'>Offline Devices</span>";
echo "<span class='metric-value warning'>{$device_stats['offline']}</span>";
echo "</div>";

echo "</div>";

// Show first device
$first_device = $db->query("SELECT * FROM devices ORDER BY id ASC LIMIT 1");
if ($first_device && $first_device->num_rows > 0) {
    $device = $first_device->fetch_assoc();
    echo "<div class='status-box'>";
    echo "<strong>Sample Device:</strong><br>";
    echo "<pre>";
    echo "ID:     {$device['id']}\n";
    echo "Name:   {$device['name']}\n";
    echo "IP:     {$device['ip_address']}\n";
    echo "MAC:    {$device['mac_address']}\n";
    echo "Type:   {$device['device_type']}\n";
    echo "Status: {$device['status']}";
    echo "</pre>";
    echo "</div>";
}

// ========================================
// 5. ALERT STATUS
// ========================================
echo "<h2><span class='icon'>🚨</span> 5. Alert System</h2>";
echo "<div class='status-box'>";

$alert_rules = $db->query("SELECT COUNT(*) as total, 
                           SUM(CASE WHEN enabled=1 THEN 1 ELSE 0 END) as enabled
                           FROM alert_rules");
$alert_stats = $alert_rules->fetch_assoc();

echo "<div class='metric'>";
echo "<span class='metric-label'>Total Alert Rules</span>";
echo "<span class='metric-value'>{$alert_stats['total']}</span>";
echo "</div>";

echo "<div class='metric'>";
echo "<span class='metric-label'>Enabled Rules</span>";
echo "<span class='metric-value good'>{$alert_stats['enabled']}</span>";
echo "</div>";

$active_alerts = $db->query("SELECT COUNT(*) as count FROM alert_history WHERE acknowledged = FALSE");
$active_count = $active_alerts->fetch_assoc()['count'];

echo "<div class='metric'>";
echo "<span class='metric-label'>Active Alerts</span>";
if ($active_count > 0) {
    echo "<span class='metric-value warning'>{$active_count} unacknowledged</span>";
} else {
    echo "<span class='metric-value good'>None</span>";
}
echo "</div>";

echo "</div>";

// ========================================
// 6. SYSTEM HEALTH
// ========================================
echo "<h2><span class='icon'>💚</span> 6. Overall System Health</h2>";
echo "<div class='status-box'>";

$health_status = 'healthy';
if (!empty($issues)) {
    $health_status = 'critical';
} elseif (!empty($warnings)) {
    $health_status = 'warning';
}

echo "<div style='text-align: center; padding: 20px;'>";
if ($health_status === 'healthy') {
    echo "<div style='font-size: 48px;'>✅</div>";
    echo "<div style='font-size: 24px; color: #10b981; margin-top: 10px;'>SYSTEM HEALTHY</div>";
    echo "<div style='color: #666; margin-top: 10px;'>All checks passed successfully</div>";
} elseif ($health_status === 'warning') {
    echo "<div style='font-size: 48px;'>⚠️</div>";
    echo "<div style='font-size: 24px; color: #f59e0b; margin-top: 10px;'>WARNINGS DETECTED</div>";
    echo "<div style='color: #666; margin-top: 10px;'>System functional but needs attention</div>";
} else {
    echo "<div style='font-size: 48px;'>❌</div>";
    echo "<div style='font-size: 24px; color: #ef4444; margin-top: 10px;'>CRITICAL ISSUES</div>";
    echo "<div style='color: #666; margin-top: 10px;'>System requires immediate attention</div>";
}
echo "</div>";

// Display issues
if (!empty($issues)) {
    echo "<h3 style='color: #ef4444; margin-top: 20px;'>🔴 Critical Issues:</h3>";
    echo "<ul style='margin-left: 20px; color: #ef4444;'>";
    foreach ($issues as $issue) {
        echo "<li>$issue</li>";
    }
    echo "</ul>";
}

// Display warnings
if (!empty($warnings)) {
    echo "<h3 style='color: #f59e0b; margin-top: 20px;'>⚠️ Warnings:</h3>";
    echo "<ul style='margin-left: 20px; color: #f59e0b;'>";
    foreach ($warnings as $warning) {
        echo "<li>$warning</li>";
    }
    echo "</ul>";
}

// Display info
if (!empty($info)) {
    echo "<h3 style='color: #3b82f6; margin-top: 20px;'>ℹ️ Information:</h3>";
    echo "<ul style='margin-left: 20px; color: #3b82f6;'>";
    foreach ($info as $item) {
        echo "<li>$item</li>";
    }
    echo "</ul>";
}

echo "</div>";

// ========================================
// 7. QUICK ACTIONS
// ========================================
echo "<h2><span class='icon'>⚡</span> 7. Quick Actions</h2>";
echo "<div class='action-buttons'>";
echo "<a href='monitoring.php' class='btn btn-primary'>📊 View Monitoring Dashboard</a>";
echo "<a href='networks.php' class='btn btn-success'>🌐 Network Scanner</a>";
echo "<a href='index.php' class='btn btn-primary'>🏠 Main Dashboard</a>";
echo "<a href='api_monitoring.php?action=health_check' class='btn btn-primary' target='_blank'>🔍 API Health Check</a>";
echo "</div>";

// ========================================
// 8. INSTRUCTIONS
// ========================================
echo "<h2><span class='icon'>📖</span> 8. Getting Started</h2>";
echo "<div class='status-box'>";

if (!empty($issues)) {
    echo "<h3 style='color: #ef4444;'>⚠️ Action Required:</h3>";
    
    if (in_array(true, array_map(fn($i) => strpos($i, 'Missing table') !== false, $issues))) {
        echo "<ol style='margin-left: 20px;'>";
        echo "<li>Import the database schema: <code>network_monitor__1_.sql</code></li>";
        echo "<li>Restart Apache in XAMPP</li>";
        echo "<li>Refresh this page</li>";
        echo "</ol>";
    }
    
    if (in_array(true, array_map(fn($i) => strpos($i, 'monitoring data') !== false, $issues))) {
        echo "<h4>Start the System Monitor:</h4>";
        echo "<ol style='margin-left: 20px;'>";
        echo "<li>Open Command Prompt</li>";
        echo "<li><code>cd C:\\xampp\\htdocs\\network-monitor</code></li>";
        echo "<li><code>php system_monitor.php</code></li>";
        echo "<li>Keep the window open</li>";
        echo "</ol>";
    }
} else {
    echo "<div style='color: #10b981;'>";
    echo "<strong>✅ System is ready to use!</strong><br><br>";
    echo "Your monitoring system is properly configured and running.<br>";
    echo "Use the buttons above to access different features.";
    echo "</div>";
}

echo "</div>";

// ========================================
// FOOTER
// ========================================
echo "<div class='timestamp'>";
echo "Diagnostic completed at: " . date('Y-m-d H:i:s') . " | ";
echo "Server: " . gethostname() . " | ";
echo "PHP: " . phpversion();
echo "</div>";

echo "</div>"; // container
echo "</body></html>";
?>