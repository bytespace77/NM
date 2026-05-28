<?php
// ================================================================
// AI PREDICTION GRAPHS API - FIXED VERSION
// ================================================================

error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $db = getDB();
    $deviceId = $_GET['device_id'] ?? null;
    $networkRange = $_GET['network_range'] ?? null;
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
    
    if ($deviceId) {
        // ================================================================
        // SINGLE DEVICE GRAPH DATA
        // ================================================================
        $deviceId = (int)$deviceId;
        
        // ✅ Get device info with current health
        $deviceSql = "SELECT 
                        d.*,
                        dhm.health_score,
                        dhm.risk_level,
                        dhm.predicted_failure_date,
                        dhm.confidence_level,
                        dhm.last_updated as last_prediction_time
                      FROM devices d
                      LEFT JOIN device_health_metrics dhm ON d.id = dhm.device_id
                      WHERE d.id = $deviceId
                      LIMIT 1";
        
        $deviceResult = $db->query($deviceSql);
        $deviceInfo = null;
        
        if ($deviceResult && $deviceResult->num_rows > 0) {
            $row = $deviceResult->fetch_assoc();
            $deviceInfo = [
                'current_health' => $row['health_score'] ? round((float)$row['health_score'], 1) : 0,
                'current_risk' => $row['risk_level'] ?? 'UNKNOWN',
                'predicted_failure_date' => $row['predicted_failure_date'],
                'confidence' => $row['confidence_level'] ? round((float)$row['confidence_level'], 1) : 0,
                'last_prediction_time' => $row['last_prediction_time'],
                'is_online' => $row['status'] === 'online', // ✅ Use 'status' column instead
            ];
        } else {
            // Device not found - return empty data
            echo json_encode([
                'success' => true,
                'device_info' => [
                    'current_health' => 0,
                    'current_risk' => 'UNKNOWN',
                    'predicted_failure_date' => null,
                    'confidence' => 0,
                    'last_prediction_time' => null,
                    'is_online' => false,
                ],
                'health_history' => [],
                'risk_history' => [],
                'maintenance_events' => [],
            ]);
            exit;
        }
        
        // ✅ Get health score history (for line graph)
        // Uses device_health_history table for proper historical data
        $healthHistorySql = "SELECT 
                                snapshot_date as date,
                                health_score
                            FROM device_health_history
                            WHERE device_id = $deviceId
                            AND snapshot_date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
                            ORDER BY snapshot_date ASC";
        
        $healthResult = $db->query($healthHistorySql);
        $healthHistory = [];
        
        if ($healthResult && $healthResult->num_rows > 0) {
            while ($row = $healthResult->fetch_assoc()) {
                $healthHistory[] = [
                    'date' => $row['date'],
                    'health_score' => round((float)$row['health_score'], 1),
                ];
            }
        } else {
            // ✅ FALLBACK: If no history, use current value
            if ($deviceInfo['current_health'] > 0) {
                $healthHistory[] = [
                    'date' => date('Y-m-d'),
                    'health_score' => $deviceInfo['current_health'],
                ];
            }
        }
        
        // ✅ Get risk level history (for distribution chart)
        $riskHistorySql = "SELECT 
                            risk_level,
                            COUNT(*) as count
                           FROM device_health_history
                           WHERE device_id = $deviceId
                           AND snapshot_date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
                           GROUP BY risk_level";
        
        $riskResult = $db->query($riskHistorySql);
        $riskHistory = [];
        
        if ($riskResult && $riskResult->num_rows > 0) {
            while ($row = $riskResult->fetch_assoc()) {
                $riskHistory[] = [
                    'risk_level' => $row['risk_level'],
                    'count' => (int)$row['count'],
                ];
            }
        } else {
            // ✅ FALLBACK: If no history, use current value
            if ($deviceInfo['current_risk'] != 'UNKNOWN') {
                $riskHistory[] = [
                    'risk_level' => $deviceInfo['current_risk'],
                    'count' => 1,
                ];
            }
        }
        
        // ✅ Get maintenance events timeline
        $maintenanceSql = "SELECT 
                            DATE(completed_at) as date,
                            task_name,
                            result,
                            cost
                           FROM maintenance_history
                           WHERE device_id = $deviceId
                           AND completed_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
                           AND status = 'completed'
                           ORDER BY completed_at DESC
                           LIMIT 10";
        
        $maintenanceResult = $db->query($maintenanceSql);
        $maintenanceEvents = [];
        
        if ($maintenanceResult) {
            while ($row = $maintenanceResult->fetch_assoc()) {
                $maintenanceEvents[] = [
                    'date' => $row['date'],
                    'task_name' => $row['task_name'],
                    'result' => $row['result'] ?? 'unknown',
                    'cost' => $row['cost'] ? (float)$row['cost'] : null,
                ];
            }
        }
        
        // ✅ Return data in format Flutter expects
        echo json_encode([
            'success' => true,
            'device_info' => $deviceInfo,
            'health_history' => $healthHistory, // ✅ For line chart
            'risk_history' => $riskHistory,     // ✅ For distribution
            'maintenance_events' => $maintenanceEvents, // ✅ For timeline
        ], JSON_PRETTY_PRINT);
        
    } else {
        // ================================================================
        // NETWORK-WIDE TREND DATA
        // ================================================================
        
        $sql = "SELECT 
                    DATE(dhm.last_updated) as date,
                    d.network_range,
                    AVG(dhm.health_score) as avg_health,
                    COUNT(CASE WHEN dhm.risk_level = 'CRITICAL' THEN 1 END) as critical_count,
                    COUNT(CASE WHEN dhm.risk_level = 'HIGH' THEN 1 END) as high_count,
                    COUNT(CASE WHEN dhm.risk_level = 'MEDIUM' THEN 1 END) as medium_count,
                    COUNT(CASE WHEN dhm.risk_level = 'LOW' THEN 1 END) as low_count,
                    COUNT(DISTINCT dhm.device_id) as device_count
                FROM device_health_metrics dhm
                JOIN devices d ON dhm.device_id = d.id
                WHERE dhm.last_updated >= DATE_SUB(NOW(), INTERVAL $days DAY)";
        
        if ($networkRange) {
            $safeRange = $db->real_escape_string($networkRange);
            $sql .= " AND d.network_range = '$safeRange'";
        }
        
        $sql .= " GROUP BY DATE(dhm.last_updated), d.network_range
                  ORDER BY date ASC, d.network_range ASC";
        
        $result = $db->query($sql);
        $trendData = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $trendData[] = [
                    'date' => $row['date'],
                    'network_range' => $row['network_range'],
                    'avg_health' => round((float)$row['avg_health'], 1),
                    'critical_count' => (int)$row['critical_count'],
                    'high_count' => (int)$row['high_count'],
                    'medium_count' => (int)$row['medium_count'],
                    'low_count' => (int)$row['low_count'],
                    'device_count' => (int)$row['device_count'],
                ];
            }
        }
        
        echo json_encode([
            'success' => true,
            'trend_data' => $trendData,
            'days' => $days,
        ], JSON_PRETTY_PRINT);
    }
    
} catch (Exception $e) {
    error_log("AI graph API error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage(),
    ]);
}
?>