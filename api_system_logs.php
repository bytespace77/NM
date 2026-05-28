<?php
// ================================================================
// SYSTEM LOGS API
// Retrieve and filter system logs
// ================================================================

error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    $db = getDB();
    $action = $_GET['action'] ?? 'list';
    
    // ================================================================
    // GET - Fetch system logs
    // ================================================================
    if ($action === 'list') {
        // Get logs with filters
        $deviceId = $_GET['device_id'] ?? null;
        $level = $_GET['level'] ?? null;
        $category = $_GET['category'] ?? null;
        $from_date = $_GET['from_date'] ?? null;
        $to_date = $_GET['to_date'] ?? null;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        
        $sql = "SELECT 
                    sl.*,
                    d.name as device_name,
                    d.ip_address
                FROM system_logs sl
                LEFT JOIN devices d ON sl.device_id = d.id
                WHERE 1=1";
        
        if ($deviceId) {
            $deviceId = (int)$deviceId;
            $sql .= " AND sl.device_id = $deviceId";
        }
        
        if ($level) {
            $safeLevel = $db->real_escape_string($level);
            $sql .= " AND sl.log_level = '$safeLevel'";
        }
        
        if ($category) {
            $safeCategory = $db->real_escape_string($category);
            $sql .= " AND sl.category = '$safeCategory'";
        }
        
        if ($from_date) {
            $safeFromDate = $db->real_escape_string($from_date);
            $sql .= " AND sl.created_at >= '$safeFromDate'";
        }
        
        if ($to_date) {
            $safeToDate = $db->real_escape_string($to_date);
            $sql .= " AND sl.created_at <= '$safeToDate'";
        }
        
        $sql .= " ORDER BY sl.created_at DESC LIMIT $limit OFFSET $offset";
        
        $result = $db->query($sql);
        $logs = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $logs[] = [
                    'id' => (int)$row['id'],
                    'device_id' => $row['device_id'] ? (int)$row['device_id'] : null,
                    'device_name' => $row['device_name'],
                    'ip_address' => $row['ip_address'],
                    'log_level' => $row['log_level'],
                    'category' => $row['category'],
                    'message' => $row['message'],
                    'created_at' => $row['created_at']
                ];
            }
        }
        
        // Get total count for pagination
        $countSql = "SELECT COUNT(*) as total FROM system_logs sl WHERE 1=1";
        
        if ($deviceId) {
            $countSql .= " AND sl.device_id = $deviceId";
        }
        if ($level) {
            $countSql .= " AND sl.log_level = '$safeLevel'";
        }
        if ($category) {
            $countSql .= " AND sl.category = '$safeCategory'";
        }
        if ($from_date) {
            $countSql .= " AND sl.created_at >= '$safeFromDate'";
        }
        if ($to_date) {
            $countSql .= " AND sl.created_at <= '$safeToDate'";
        }
        
        $countResult = $db->query($countSql);
        $totalCount = $countResult->fetch_assoc()['total'];
        
        echo json_encode([
            'success' => true,
            'data' => $logs,
            'pagination' => [
                'count' => count($logs),
                'total' => (int)$totalCount,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + count($logs)) < $totalCount
            ]
        ]);
        
    } elseif ($action === 'categories') {
        // Get unique categories
        $result = $db->query("SELECT DISTINCT category FROM system_logs ORDER BY category");
        $categories = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $categories[] = $row['category'];
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => $categories
        ]);
        
    } elseif ($action === 'levels') {
        // Get available log levels
        echo json_encode([
            'success' => true,
            'data' => ['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL']
        ]);
        
    } elseif ($action === 'stats') {
        // Get log statistics (last 24 hours)
        $hours = isset($_GET['hours']) ? (int)$_GET['hours'] : 24;
        
        $sql = "SELECT 
                    log_level,
                    category,
                    COUNT(*) as count
                FROM system_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL $hours HOUR)
                GROUP BY log_level, category
                ORDER BY log_level, category";
        
        $result = $db->query($sql);
        $stats = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $stats[] = [
                    'level' => $row['log_level'],
                    'category' => $row['category'],
                    'count' => (int)$row['count']
                ];
            }
        }
        
        // Get total by level
        $levelSql = "SELECT 
                        log_level,
                        COUNT(*) as count
                     FROM system_logs
                     WHERE created_at >= DATE_SUB(NOW(), INTERVAL $hours HOUR)
                     GROUP BY log_level";
        
        $levelResult = $db->query($levelSql);
        $byLevel = [];
        
        if ($levelResult) {
            while ($row = $levelResult->fetch_assoc()) {
                $byLevel[$row['log_level']] = (int)$row['count'];
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'by_level' => $byLevel,
                'detailed' => $stats,
                'hours' => $hours
            ]
        ]);
        
    } elseif ($action === 'recent') {
        // Get recent logs (last N)
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        
        $sql = "SELECT 
                    sl.*,
                    d.name as device_name,
                    d.ip_address
                FROM system_logs sl
                LEFT JOIN devices d ON sl.device_id = d.id
                ORDER BY sl.created_at DESC
                LIMIT $limit";
        
        $result = $db->query($sql);
        $logs = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $logs[] = [
                    'id' => (int)$row['id'],
                    'device_id' => $row['device_id'] ? (int)$row['device_id'] : null,
                    'device_name' => $row['device_name'],
                    'ip_address' => $row['ip_address'],
                    'log_level' => $row['log_level'],
                    'category' => $row['category'],
                    'message' => $row['message'],
                    'created_at' => $row['created_at']
                ];
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => $logs,
            'count' => count($logs)
        ]);
        
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid action'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'details' => $e->getMessage()
    ]);
}
?>