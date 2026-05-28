<?php
// check_monitoring.php - Diagnostic script
require_once 'config.php';

echo "<h1>🔍 Monitoring System Diagnostic</h1>";
echo "<style>body{font-family:monospace;background:#0a0a0a;color:#fff;padding:20px;}h1{color:#667eea;}h2{color:#10b981;margin-top:30px;}pre{background:#1a1a1a;padding:15px;border-radius:8px;overflow-x:auto;}.good{color:#10b981;}.bad{color:#ef4444;}.warning{color:#f59e0b;}</style>";

$db = getDB();

// Check 1: Database tables exist
echo "<h2>1. Database Tables</h2>";
$tables = ['system_metrics', 'devices', 'system_logs'];
foreach ($tables as $table) {
    $result = $db->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "<span class='good'>✅ $table exists</span><br>";
    } else {
        echo "<span class='bad'>❌ $table missing</span><br>";
    }
}

// Check 2: Recent data in system_metrics
echo "<h2>2. Recent Data (Last 5 Minutes)</h2>";
$result = $db->query("SELECT COUNT(*) as count FROM system_metrics WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
$row = $result->fetch_assoc();
echo "Data points in last 5 minutes: <span class='" . ($row['count'] > 0 ? "good" : "bad") . "'>" . $row['count'] . "</span><br>";

if ($row['count'] == 0) {
    echo "<span class='warning'>⚠️ No recent data! System monitor might not be running!</span><br>";
}

// Check 3: Latest metrics
echo "<h2>3. Latest Metrics</h2>";
$result = $db->query("SELECT * FROM system_metrics ORDER BY recorded_at DESC LIMIT 1");
if ($result && $result->num_rows > 0) {
    $metrics = $result->fetch_assoc();
    echo "<pre>";
    echo "CPU Usage: " . ($metrics['cpu_usage'] ?? 'NULL') . "%\n";
    echo "RAM Usage: " . ($metrics['ram_usage'] ?? 'NULL') . "%\n";
    echo "Temperature: " . ($metrics['temperature'] ?? 'NULL') . "°C\n";
    echo "Disk Usage: " . ($metrics['disk_usage'] ?? 'NULL') . "%\n";
    echo "Recorded at: " . ($metrics['recorded_at'] ?? 'NULL') . "\n";
    echo "</pre>";
} else {
    echo "<span class='bad'>❌ No metrics data found in database!</span><br>";
}

// Check 4: API test
echo "<h2>4. API Test</h2>";
echo "<a href='api_monitoring.php?action=current_metrics' target='_blank' style='color:#667eea;'>🔗 Test API directly</a><br>";

// Check 5: Device info
echo "<h2>5. Device Info</h2>";
$result = $db->query("SELECT * FROM devices ORDER BY id ASC LIMIT 1");
if ($result && $result->num_rows > 0) {
    $device = $result->fetch_assoc();
    echo "<pre>";
    echo "Device ID: " . $device['id'] . "\n";
    echo "Name: " . $device['name'] . "\n";
    echo "IP: " . $device['ip_address'] . "\n";
    echo "Status: " . $device['status'] . "\n";
    echo "</pre>";
} else {
    echo "<span class='bad'>❌ No devices found!</span><br>";
}

// Check 6: System monitor status
echo "<h2>6. System Monitor Status</h2>";
echo "<pre>";
echo "To start system monitor:\n";
echo "1. Open Command Prompt\n";
echo "2. cd C:\\xampp\\htdocs\\network-monitor\n";
echo "3. php system_monitor.php\n\n";
echo "Monitor should run continuously to collect data!\n";
echo "</pre>";

// Check 7: JavaScript console check
echo "<h2>7. Browser Console Check</h2>";
echo "Open monitoring.php, press F12, check Console tab for errors.<br>";
echo "Common errors:<br>";
echo "- Fetch failed<br>";
echo "- Duplicate function definition<br>";
echo "- Cannot read property of undefined<br>";

echo "<h2>✅ Diagnosis Complete</h2>";
echo "<p>If you see <span class='bad'>RED</span> issues above, that's your problem!</p>";
?>