<?php
// api_monitoring.php - FIXED VERSION with Network + Latency
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';

header('Content-Type: application/json');

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

try {
    switch ($action) {
        
        // ========================================
        // Get current system metrics (FIXED)
        // ========================================
        case 'current_metrics':
            $deviceId = (int)($_GET['device_id'] ?? getLocalhostDeviceId());
            
            $result = $db->query("SELECT * FROM system_metrics 
                WHERE device_id=$deviceId 
                ORDER BY recorded_at DESC 
                LIMIT 1");
            
            if ($result && $result->num_rows > 0) {
                $metrics = $result->fetch_assoc();
                
                // Get device info
                $device = $db->query("SELECT name, ip_address FROM devices WHERE id=$deviceId")->fetch_assoc();
                
                echo json_encode([
                    'success' => true,
                    'device' => $device,
                    'metrics' => $metrics,
                    'timestamp' => time()
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'No metrics found'
                ]);
            }
            break;
        
        // ========================================
        // Get historical metrics for charts (FIXED)
        // ========================================
        case 'historical_metrics':
            $deviceId = (int)($_GET['device_id'] ?? getLocalhostDeviceId());
            $hours = (int)($_GET['hours'] ?? 24);
            
            $result = $db->query("SELECT 
                cpu_usage,
                ram_usage,
                network_upload_speed,
                network_download_speed,
                network_total_speed,
                latency_ms,
                latency_host,
                disk_usage,
                temperature,
                DATE_FORMAT(recorded_at, '%H:%i') as time_label,
                UNIX_TIMESTAMP(recorded_at) as timestamp
                FROM system_metrics 
                WHERE device_id=$deviceId 
                AND recorded_at >= DATE_SUB(NOW(), INTERVAL $hours HOUR)
                ORDER BY recorded_at ASC");
            
            // FIXED: Added network and latency arrays
            $data = [
                'labels' => [],
                'cpu' => [],
                'ram' => [],
                'network_upload' => [],
                'network_download' => [],
                'network_total' => [],
                'latency' => [],
                'disk' => [],
                'temperature' => []
            ];
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $data['labels'][] = $row['time_label'];
                    $data['cpu'][] = (float)$row['cpu_usage'];
                    $data['ram'][] = (float)$row['ram_usage'];
                    $data['network_upload'][] = $row['network_upload_speed'] ? (float)$row['network_upload_speed'] : 0;
                    $data['network_download'][] = $row['network_download_speed'] ? (float)$row['network_download_speed'] : 0;
                    $data['network_total'][] = $row['network_total_speed'] ? (float)$row['network_total_speed'] : 0;
                    $data['latency'][] = $row['latency_ms'] ? (float)$row['latency_ms'] : null;
                    $data['disk'][] = $row['disk_usage'] ? (float)$row['disk_usage'] : null;
                    $data['temperature'][] = $row['temperature'] ? (float)$row['temperature'] : null;
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => $data,
                'device_id' => $deviceId,
                'hours' => $hours
            ]);
            break;
        
        // ========================================
        // Export logs to CSV
        // ========================================
        case 'export_logs_csv':
            $level = $_GET['level'] ?? null;
            $category = $_GET['category'] ?? null;
            $limit = (int)($_GET['limit'] ?? 1000);
            
            $sql = "SELECT 
                sl.created_at,
                sl.log_level,
                sl.category,
                sl.message,
                COALESCE(d.name, 'System') as device_name,
                COALESCE(d.ip_address, 'N/A') as device_ip
                FROM system_logs sl
                LEFT JOIN devices d ON sl.device_id = d.id
                WHERE 1=1";
            
            if ($level) {
                $safeLevel = $db->real_escape_string($level);
                $sql .= " AND sl.log_level = '$safeLevel'";
            }
            
            if ($category) {
                $safeCategory = $db->real_escape_string($category);
                $sql .= " AND sl.category = '$safeCategory'";
            }
            
            $sql .= " ORDER BY sl.created_at DESC LIMIT $limit";
            
            $result = $db->query($sql);
            
            $logs = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $logs[] = $row;
                }
            }
            
            // Log export activity
            logActivity('monitoring', 'INFO', "Logs exported to CSV (" . count($logs) . " entries)");
            
            echo json_encode([
                'success' => true,
                'logs' => $logs,
                'count' => count($logs),
                'exported_at' => date('Y-m-d H:i:s')
            ]);
            break;
        
        // ========================================
        // Export metrics to CSV
        // ========================================
        case 'export_metrics_csv':
            $deviceId = (int)($_GET['device_id'] ?? getLocalhostDeviceId());
            $hours = (int)($_GET['hours'] ?? 24);
            
            $result = $db->query("SELECT 
                recorded_at,
                cpu_usage,
                cpu_frequency,
                ram_usage,
                ram_used_gb,
                ram_total_gb,
                network_upload_speed,
                network_download_speed,
                network_total_speed,
                latency_ms,
                temperature,
                disk_usage,
                disk_used_gb,
                disk_total_gb
                FROM system_metrics 
                WHERE device_id=$deviceId 
                AND recorded_at >= DATE_SUB(NOW(), INTERVAL $hours HOUR)
                ORDER BY recorded_at ASC");
            
            $metrics = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $metrics[] = $row;
                }
            }
            
            // Log export activity
            logActivity('monitoring', 'INFO', "Metrics exported to CSV (" . count($metrics) . " data points)", $deviceId);
            
            echo json_encode([
                'success' => true,
                'metrics' => $metrics,
                'count' => count($metrics),
                'exported_at' => date('Y-m-d H:i:s')
            ]);
            break;
        
        // ========================================
        // Get device statistics
        // ========================================
        case 'device_stats':
            $deviceId = (int)($_GET['device_id'] ?? getLocalhostDeviceId());
            
            $stats = $db->query("SELECT * FROM device_statistics WHERE device_id=$deviceId")->fetch_assoc();
            
            if ($stats) {
                echo json_encode([
                    'success' => true,
                    'stats' => $stats
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'No statistics found'
                ]);
            }
            break;
        
        // ========================================
        // Get active alerts
        // ========================================
        case 'active_alerts':
            $limit = (int)($_GET['limit'] ?? 10);
            
            $result = $db->query("SELECT 
                ah.*,
                d.name as device_name,
                d.ip_address
                FROM alert_history ah
                JOIN devices d ON ah.device_id = d.id
                WHERE ah.acknowledged = FALSE
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
            $alertId = (int)($_POST['alert_id'] ?? 0);
            $acknowledgedBy = $_POST['acknowledged_by'] ?? 'User';
            
            if ($alertId > 0) {
                $safeBy = $db->real_escape_string($acknowledgedBy);
                
                // Get alert details before acknowledging
                $alertInfo = $db->query("SELECT ah.*, d.name FROM alert_history ah 
                                        LEFT JOIN devices d ON ah.device_id = d.id 
                                        WHERE ah.id = $alertId")->fetch_assoc();
                
                $db->query("UPDATE alert_history 
                    SET acknowledged = TRUE, 
                        acknowledged_at = NOW(),
                        acknowledged_by = '$safeBy'
                    WHERE id = $alertId");
                
                // Log alert acknowledgment
                if ($alertInfo) {
                    $deviceName = $alertInfo['name'] ?? 'Unknown';
                    logActivity('alert', 'INFO', "Alert acknowledged by $safeBy: {$alertInfo['message']}", $alertInfo['device_id']);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Alert acknowledged'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid alert ID'
                ]);
            }
            break;
        
        // ========================================
        // Get system logs
        // ========================================
        case 'system_logs':
            $limit = (int)($_GET['limit'] ?? 50);
            $level = $_GET['level'] ?? null;
            $category = $_GET['category'] ?? null;
            
            $sql = "SELECT 
                sl.*,
                d.name as device_name,
                d.ip_address as device_ip
                FROM system_logs sl
                LEFT JOIN devices d ON sl.device_id = d.id
                WHERE 1=1";
            
            if ($level) {
                $safeLevel = $db->real_escape_string($level);
                $sql .= " AND sl.log_level = '$safeLevel'";
            }
            
            if ($category) {
                $safeCategory = $db->real_escape_string($category);
                $sql .= " AND sl.category = '$safeCategory'";
            }
            
            $sql .= " ORDER BY sl.created_at DESC LIMIT $limit";
            
            $result = $db->query($sql);
            
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
        // Default - API info
        // ========================================
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action',
                'available_actions' => [
                    'current_metrics' => 'Get current system metrics',
                    'historical_metrics' => 'Get historical data for charts',
                    'export_logs_csv' => 'Export system logs to CSV',
                    'export_metrics_csv' => 'Export metrics to CSV',
                    'device_stats' => 'Get device statistics',
                    'active_alerts' => 'Get unacknowledged alerts',
                    'acknowledge_alert' => 'Mark alert as acknowledged',
                    'system_logs' => 'Get system logs'
                ]
            ]);
            break;
    }
    
} catch (Exception $e) {
    logActivity('system', 'ERROR', "API error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

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
?>