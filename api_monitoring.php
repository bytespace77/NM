<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$action = $_GET['action'] ?? '';
$db = getDB();

// ========================================
// LOGGING FUNCTION
// ========================================
function logActivity($category, $level, $message, $deviceId = null) {
    try {
        $db = getDB();
        $safeCategory = $db->real_escape_string($category);
        $safeLevel = $db->real_escape_string($level);
        $safeMessage = $db->real_escape_string($message);
        $deviceIdVal = $deviceId ? (int)$deviceId : 'NULL';
        
        $sql = "INSERT INTO system_logs (device_id, log_level, category, message, created_at) 
                VALUES ($deviceIdVal, '$safeLevel', '$safeCategory', '$safeMessage', NOW())";
        
        $db->query($sql);
    } catch (Exception $e) {
        error_log("Failed to write activity log: " . $e->getMessage());
    }
}

// ========================================
// HELPER FUNCTIONS
// ========================================
function getLocalhostDeviceId() {
    global $db;
    
    $localIP = getLocalIP();
    $safeIP = $db->real_escape_string($localIP);
    
    $result = $db->query("SELECT id FROM devices WHERE ip_address='$safeIP' LIMIT 1");
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['id'];
    }
    
    return 1;
}

try {
    switch ($action) {
        
        // ========================================
        // Get current system metrics
        // ========================================
        case 'current_metrics':
            $result = $db->query("SELECT * FROM system_metrics 
                ORDER BY recorded_at DESC 
                LIMIT 1");
            
            if ($result && $result->num_rows > 0) {
                $metrics = $result->fetch_assoc();
                
                echo json_encode([
                    'success' => true,
                    'metrics' => $metrics
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'No metrics found'
                ]);
            }
            break;
        
        // ========================================
        // ✅ FIXED: Get historical metrics with SIMPLE query
        // ========================================
        case 'historical_metrics':
            $dateFilter = $_GET['date_filter'] ?? 'today';
            
            // Build WHERE clause
            switch ($dateFilter) {
                case 'today':
                    $whereClause = "WHERE DATE(recorded_at) = CURDATE()";
                    break;
                case '7days':
                    $whereClause = "WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    break;
                case '30days':
                    $whereClause = "WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                    break;
                default:
                    $whereClause = "WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
                    break;
            }
            
            // ✅ SIMPLE QUERY - Just get all data, we'll downsample in PHP
            $query = "SELECT 
                recorded_at,
                cpu_usage,
                ram_usage,
                network_total_speed,
                latency_ms,
                disk_usage,
                temperature
            FROM system_metrics 
            $whereClause
            ORDER BY recorded_at ASC";
            
            $result = $db->query($query);
            
            if (!$result) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Query failed: ' . $db->error,
                    'query' => $query
                ]);
                break;
            }
            
            // Get all data
            $allData = [];
            while ($row = $result->fetch_assoc()) {
                $allData[] = $row;
            }
            
            $totalCount = count($allData);
            
            // ✅ SMART DOWNSAMPLING - Keep every Nth record
            $maxPoints = 200;
            $sampledData = [];
            
            if ($totalCount <= $maxPoints) {
                // Use all data
                $sampledData = $allData;
            } else {
                // Calculate interval
                $interval = ceil($totalCount / $maxPoints);
                
                // Take every Nth record
                for ($i = 0; $i < $totalCount; $i += $interval) {
                    $sampledData[] = $allData[$i];
                }
                
                // Always include last record
                if (end($sampledData) !== end($allData)) {
                    $sampledData[] = end($allData);
                }
            }
            
            // Build response arrays
            $labels = [];
            $cpu = [];
            $ram = [];
            $network = [];
            $latency = [];
            $disk = [];
            $temperature = [];
            
            foreach ($sampledData as $row) {
                // Format time based on date range
                if ($dateFilter === 'today') {
                    $time = date('H:i', strtotime($row['recorded_at']));
                } elseif ($dateFilter === '7days') {
                    $time = date('m/d H:i', strtotime($row['recorded_at']));
                } else {
                    $time = date('m/d H:i', strtotime($row['recorded_at']));
                }
                
                $labels[] = $time;
                $cpu[] = round((float)$row['cpu_usage'], 1);
                $ram[] = round((float)$row['ram_usage'], 1);
                $network[] = round((float)$row['network_total_speed'], 2);
                $latency[] = $row['latency_ms'] ? round((float)$row['latency_ms'], 0) : null;
                $disk[] = round((float)$row['disk_usage'], 1);
                $temperature[] = $row['temperature'] ? round((float)$row['temperature'], 1) : null;
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'labels' => $labels,
                    'cpu' => $cpu,
                    'ram' => $ram,
                    'network' => $network,
                    'latency' => $latency,
                    'disk' => $disk,
                    'temperature' => $temperature
                ],
                'count' => count($labels),
                'total_records' => $totalCount,
                'downsampled' => $totalCount > count($labels),
                'date_filter' => $dateFilter
            ]);
            break;
        
        // ========================================
        // Get active alerts
        // ========================================
        case 'active_alerts':
            $limit = (int)($_GET['limit'] ?? 20);
            
            $result = $db->query("SELECT 
                    ah.*,
                    d.name as device_name,
                    d.ip_address
                FROM alert_history ah
                LEFT JOIN devices d ON ah.device_id = d.id
                WHERE ah.acknowledged = 0
                ORDER BY ah.created_at DESC
                LIMIT $limit");
            
            $alerts = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $alerts[] = $row;
                }
            }
            
            echo json_encode([
                'success' => true,
                'alerts' => $alerts,
                'count' => count($alerts)
            ]);
            break;
        
        // ========================================
        // Acknowledge alert
        // ========================================
        case 'acknowledge_alert':
            $alertId = (int)$_POST['alert_id'];
            $acknowledgedBy = $db->real_escape_string($_POST['acknowledged_by'] ?? 'Admin');
            
            $result = $db->query("UPDATE alert_history 
                SET acknowledged = 1, 
                    acknowledged_by = '$acknowledgedBy',
                    acknowledged_at = NOW()
                WHERE id = $alertId");
            
            echo json_encode([
                'success' => (bool)$result,
                'affected_rows' => $db->affected_rows
            ]);
            break;
        
        // ========================================
        // Get system logs
        // ========================================
        case 'system_logs':
            $limit = (int)($_GET['limit'] ?? 50);
            $level = $_GET['level'] ?? '';
            $category = $_GET['category'] ?? '';
            
            $where = [];
            if ($level) {
                $safeLevel = $db->real_escape_string($level);
                $where[] = "log_level='$safeLevel'";
            }
            if ($category) {
                $safeCategory = $db->real_escape_string($category);
                $where[] = "category='$safeCategory'";
            }
            
            $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            
            $result = $db->query("SELECT 
                    sl.*,
                    d.name as device_name,
                    d.ip_address as device_ip
                FROM system_logs sl
                LEFT JOIN devices d ON sl.device_id = d.id
                $whereClause
                ORDER BY sl.created_at DESC
                LIMIT $limit");
            
            $logs = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $logs[] = $row;
                }
            }
            
            echo json_encode([
                'success' => true,
                'logs' => $logs,
                'count' => count($logs)
            ]);
            break;
        
        // ========================================
        // Export logs to CSV
        // ========================================
        case 'export_logs_csv':
            $limit = (int)($_GET['limit'] ?? 1000);
            $level = $_GET['level'] ?? '';
            $category = $_GET['category'] ?? '';
            
            $where = [];
            if ($level) {
                $safeLevel = $db->real_escape_string($level);
                $where[] = "log_level='$safeLevel'";
            }
            if ($category) {
                $safeCategory = $db->real_escape_string($category);
                $where[] = "category='$safeCategory'";
            }
            
            $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            
            $result = $db->query("SELECT 
                    sl.*,
                    d.name as device_name,
                    d.ip_address as device_ip
                FROM system_logs sl
                LEFT JOIN devices d ON sl.device_id = d.id
                $whereClause
                ORDER BY sl.created_at DESC
                LIMIT $limit");
            
            $logs = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $logs[] = $row;
                }
            }
            
            echo json_encode([
                'success' => true,
                'logs' => $logs
            ]);
            break;
        
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Unknown action',
                'available_actions' => [
                    'current_metrics',
                    'historical_metrics',
                    'active_alerts',
                    'acknowledge_alert',
                    'system_logs',
                    'export_logs_csv'
                ]
            ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>