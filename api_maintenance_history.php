<?php
// ================================================================
// MAINTENANCE HISTORY API
// ================================================================

// MUST be first line - buffer all output so stray text can't corrupt JSON
ob_start();

error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';

// Discard any output from config.php (whitespace, warnings, BOM, etc.)
ob_clean();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit();
}

// Safety net - catches PHP fatal errors that bypass try/catch
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Fatal: ' . $error['message'] . ' on line ' . $error['line']]);
    }
});

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
    // Wrap getDB() so connection failure returns JSON instead of die() text
    try {
        $db = getDB();
    } catch (Exception $connEx) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'DB connection failed: ' . $connEx->getMessage()]);
        exit;
    }
    if (!$db || $db->connect_error) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'DB connect error: ' . ($db ? $db->connect_error : 'null')]);
        exit;
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'list';

    // ---- Quick test endpoint: ?action=test ----
    if ($action === 'test') {
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'API reachable', 'time' => date('Y-m-d H:i:s')]);
        exit;
    }
    
    // ================================================================
    // GET - Fetch maintenance history
    // ================================================================
    if ($method === 'GET') {

        // Handle GET delete (PHP dashboard calls GET ?action=delete&id=X)
        if ($action === 'delete' && isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'Invalid ID']);
                exit;
            }
            if ($db->query("DELETE FROM maintenance_history WHERE id = $id")) {
                logActivity('maintenance', 'INFO', "Maintenance history deleted: ID $id");
                echo json_encode(['success' => true, 'message' => 'Deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to delete: ' . $db->error]);
            }

        } elseif ($action === 'list') {
            // Get maintenance history with filters
            $deviceId     = $_GET['device_id']    ?? null;
            $status       = $_GET['status']        ?? null;
            $resultFilter = $_GET['result']        ?? null;  // FIXED: was $result (collided with $db->query result)
            $from_date    = $_GET['from_date']     ?? null;
            $to_date      = $_GET['to_date']       ?? null;
            $limit        = isset($_GET['limit'])  ? (int)$_GET['limit'] : 100;
            $networkRange = $_GET['network_range'] ?? null;
            
            $sql = "SELECT 
                        mh.*,
                        d.name as device_name,
                        d.ip_address,
                        d.device_type,
                        d.network_range,
                        ms.id as schedule_id,
                        ms.task_name as scheduled_task_name
                    FROM maintenance_history mh
                    JOIN devices d ON mh.device_id = d.id
                    LEFT JOIN maintenance_schedules ms ON mh.schedule_id = ms.id
                    WHERE 1=1";
            
            if ($deviceId) {
                $deviceId = (int)$deviceId;
                $sql .= " AND mh.device_id = $deviceId";
            }
            
            if ($status) {
                $safeStatus = $db->real_escape_string($status);
                $sql .= " AND mh.status = '$safeStatus'";
            }
            
            // FIXED: use $resultFilter not $result to avoid collision with $db->query() result below
            if ($resultFilter) {
                $safeResult = $db->real_escape_string($resultFilter);
                $sql .= " AND mh.result = '$safeResult'";
            }
            
            if ($from_date) {
                $safeFromDate = $db->real_escape_string($from_date);
                $sql .= " AND (mh.completed_at >= '$safeFromDate' OR mh.started_at >= '$safeFromDate')";
            }
            
            if ($to_date) {
                $safeToDate = $db->real_escape_string($to_date);
                $sql .= " AND (mh.completed_at <= '$safeToDate' OR mh.started_at <= '$safeToDate')";
            }

            if ($networkRange) {
                $safeNetwork = $db->real_escape_string($networkRange);
                $sql .= " AND d.network_range = '$safeNetwork'";
            }
            
            $sql .= " ORDER BY mh.created_at DESC LIMIT $limit";
            
            // FIXED: renamed to $queryResult — was $result which collided with $resultFilter above
            $queryResult = $db->query($sql);
            $history = [];
            
            if ($queryResult) {
                while ($row = $queryResult->fetch_assoc()) {
                    $partsReplaced = $row['parts_replaced'] ? json_decode($row['parts_replaced'], true) : [];
                    
                    $history[] = [
                        'id' => (int)$row['id'],
                        'device_id' => (int)$row['device_id'],
                        'device_name' => $row['device_name'],
                        'ip_address' => $row['ip_address'],
                        'device_type' => $row['device_type'],
                        'network_range' => $row['network_range'],
                        'schedule_id' => $row['schedule_id'] ? (int)$row['schedule_id'] : null,
                        'task_name' => $row['task_name'],
                        'task_category' => $row['task_category'],
                        'description' => $row['description'],
                        'performed_by' => $row['performed_by'],
                        'started_at' => $row['started_at'],
                        'completed_at' => $row['completed_at'],
                        'duration_minutes' => $row['duration_minutes'] ? (int)$row['duration_minutes'] : null,
                        'status' => $row['status'],
                        'result' => $row['result'],
                        'parts_replaced' => $partsReplaced,
                        'cost' => $row['cost'] ? (float)$row['cost'] : null,
                        'notes' => $row['notes'],
                        'before_photo' => $row['before_photo'],
                        'after_photo' => $row['after_photo'],
                        'created_at' => $row['created_at'],
                        'updated_at' => $row['updated_at']
                    ];
                }
            }
            
            ob_clean();
            echo json_encode([
                'success' => true,
                'data' => $history,
                'count' => count($history)
            ]);
            
        } elseif ($action === 'get' && isset($_GET['id'])) {
            // Get single history record
            $id = (int)$_GET['id'];
            
            $sql = "SELECT 
                        mh.*,
                        d.name as device_name,
                        d.ip_address,
                        d.mac_address,
                        d.device_type
                    FROM maintenance_history mh
                    JOIN devices d ON mh.device_id = d.id
                    WHERE mh.id = $id";
            
            $result = $db->query($sql);
            
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $partsReplaced = $row['parts_replaced'] ? json_decode($row['parts_replaced'], true) : [];
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'id' => (int)$row['id'],
                        'device_id' => (int)$row['device_id'],
                        'device_name' => $row['device_name'],
                        'ip_address' => $row['ip_address'],
                        'mac_address' => $row['mac_address'],
                        'device_type' => $row['device_type'],
                        'schedule_id' => $row['schedule_id'] ? (int)$row['schedule_id'] : null,
                        'task_name' => $row['task_name'],
                        'task_category' => $row['task_category'],
                        'description' => $row['description'],
                        'performed_by' => $row['performed_by'],
                        'started_at' => $row['started_at'],
                        'completed_at' => $row['completed_at'],
                        'duration_minutes' => $row['duration_minutes'] ? (int)$row['duration_minutes'] : null,
                        'status' => $row['status'],
                        'result' => $row['result'],
                        'parts_replaced' => $partsReplaced,
                        'cost' => $row['cost'] ? (float)$row['cost'] : null,
                        'notes' => $row['notes'],
                        'before_photo' => $row['before_photo'],
                        'after_photo' => $row['after_photo'],
                        'created_at' => $row['created_at'],
                        'updated_at' => $row['updated_at']
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'History record not found'
                ]);
            }
            
        } elseif ($action === 'stats') {
            // Get maintenance statistics
            $deviceId = $_GET['device_id'] ?? null;
            $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
            
            $whereClause = "WHERE mh.completed_at >= DATE_SUB(NOW(), INTERVAL $days DAY)";
            if ($deviceId) {
                $deviceId = (int)$deviceId;
                $whereClause .= " AND mh.device_id = $deviceId";
            }
            
            $sql = "SELECT 
                        COUNT(*) as total_maintenance,
                        SUM(CASE WHEN result = 'success' THEN 1 ELSE 0 END) as successful,
                        SUM(CASE WHEN result = 'failed' THEN 1 ELSE 0 END) as failed,
                        SUM(CASE WHEN result = 'partial' THEN 1 ELSE 0 END) as partial,
                        SUM(cost) as total_cost,
                        AVG(duration_minutes) as avg_duration,
                        task_category,
                        COUNT(*) as category_count
                    FROM maintenance_history mh
                    $whereClause
                    GROUP BY task_category
                    WITH ROLLUP";
            
            $result = $db->query($sql);
            $stats = [];
            $total = null;
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    if ($row['task_category'] === null) {
                        // This is the ROLLUP total
                        $total = [
                            'total_maintenance' => (int)$row['total_maintenance'],
                            'successful' => (int)$row['successful'],
                            'failed' => (int)$row['failed'],
                            'partial' => (int)$row['partial'],
                            'total_cost' => $row['total_cost'] ? (float)$row['total_cost'] : 0,
                            'avg_duration' => $row['avg_duration'] ? round((float)$row['avg_duration'], 1) : 0
                        ];
                    } else {
                        $stats[] = [
                            'category' => $row['task_category'],
                            'count' => (int)$row['category_count'],
                            'total_cost' => $row['total_cost'] ? (float)$row['total_cost'] : 0
                        ];
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'by_category' => $stats
                ]
            ]);
            
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action'
            ]);
        }
    }
    
    // ================================================================
    // POST - Create new maintenance history record
    // ================================================================
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            $input = $_POST;
        }
        
        $deviceId     = (int)($input['device_id'] ?? 0);
        $scheduleId   = isset($input['schedule_id']) && $input['schedule_id'] > 0 
                       ? (int)$input['schedule_id'] : 'NULL';
        $taskName     = $db->real_escape_string($input['task_name']     ?? '');
        $taskCategory = $db->real_escape_string($input['task_category'] ?? 'general');
        $description  = $db->real_escape_string($input['description']   ?? '');
        $performedBy  = $db->real_escape_string($input['performed_by']  ?? '');
        $startedAt    = isset($input['started_at'])
                        ? "'" . $db->real_escape_string($input['started_at']) . "'" : 'NOW()';
        $status       = $db->real_escape_string($input['status'] ?? 'completed');
        $notes        = $db->real_escape_string($input['notes'] ?? '');
        $resultVal    = isset($input['result'])
                        ? "'" . $db->real_escape_string($input['result']) . "'" : 'NULL';
        $cost         = (isset($input['cost']) && $input['cost'] !== null) ? (float)$input['cost'] : null;
        $durationMin  = (isset($input['duration_minutes']) && $input['duration_minutes'] !== null)
                        ? (int)$input['duration_minutes'] : null;
        $completedAt  = ($status === 'completed') ? 'NOW()' : 'NULL';
        
        if ($deviceId <= 0 || empty($taskName)) {
            echo json_encode(['success' => false, 'error' => 'Device ID and task name are required']);
            exit;
        }

        $costSql     = $cost       !== null ? $cost       : 'NULL';
        $durationSql = $durationMin !== null ? $durationMin : 'NULL';
        
        $sql = "INSERT INTO maintenance_history 
                (device_id, schedule_id, task_name, task_category, description,
                 performed_by, started_at, completed_at, status, result, notes,
                 cost, duration_minutes, created_at) 
                VALUES ($deviceId, $scheduleId, '$taskName', '$taskCategory', '$description',
                        '$performedBy', $startedAt, $completedAt, '$status', $resultVal, '$notes',
                        $costSql, $durationSql, NOW())";
        
        if ($db->query($sql)) {
            $newId = $db->insert_id;
            
            // Get device name for logging
            $deviceResult = $db->query("SELECT name FROM devices WHERE id = $deviceId");
            $deviceName = $deviceResult->fetch_assoc()['name'] ?? "Device $deviceId";
            
            logActivity('maintenance', 'INFO', "Maintenance started: $taskName on $deviceName", $deviceId);
            
            // If this was from a schedule, update the schedule status
            if ($scheduleId !== 'NULL') {
                $db->query("UPDATE maintenance_schedules SET status='in_progress' WHERE id=$scheduleId");
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Maintenance history created successfully',
                'id' => $newId
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to create maintenance history: ' . $db->error
            ]);
        }
    }
    
    // ================================================================
    // PUT - Update maintenance history
    // ================================================================
    elseif ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $id = (int)($input['id'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid history ID'
            ]);
            exit;
        }
        
        $updates = [];
        
        if (isset($input['completed_at'])) {
            $completedAt = $db->real_escape_string($input['completed_at']);
            $updates[] = "completed_at = '$completedAt'";
        }
        
        if (isset($input['status'])) {
            $status = $db->real_escape_string($input['status']);
            $updates[] = "status = '$status'";
            
            // If marking as completed, auto-set completed_at if not provided
            if ($status === 'completed' && !isset($input['completed_at'])) {
                $updates[] = "completed_at = NOW()";
            }
        }
        
        if (isset($input['result'])) {
            $result = $db->real_escape_string($input['result']);
            $updates[] = "result = '$result'";
        }
        
        if (isset($input['parts_replaced'])) {
            $partsReplaced = json_encode($input['parts_replaced']);
            $safePartsReplaced = $db->real_escape_string($partsReplaced);
            $updates[] = "parts_replaced = '$safePartsReplaced'";
        }
        
        if (isset($input['cost'])) {
            $cost = (float)$input['cost'];
            $updates[] = "cost = $cost";
        }
        
        if (isset($input['notes'])) {
            $notes = $db->real_escape_string($input['notes']);
            $updates[] = "notes = '$notes'";
        }
        
        if (isset($input['performed_by'])) {
            $performedBy = $db->real_escape_string($input['performed_by']);
            $updates[] = "performed_by = '$performedBy'";
        }
        
        if (empty($updates)) {
            echo json_encode([
                'success' => false,
                'error' => 'No fields to update'
            ]);
            exit;
        }
        
        $updates[] = "updated_at = NOW()";
        $sql = "UPDATE maintenance_history SET " . implode(', ', $updates) . " WHERE id = $id";
        
        if ($db->query($sql)) {
            // Get schedule_id to update schedule status if needed
            $historyResult = $db->query("SELECT schedule_id, status, device_id FROM maintenance_history WHERE id = $id");
            $historyRow = $historyResult->fetch_assoc();
            
            if ($historyRow['schedule_id'] && $historyRow['status'] === 'completed') {
                $scheduleId = $historyRow['schedule_id'];
                $db->query("UPDATE maintenance_schedules SET status='completed' WHERE id=$scheduleId");
            }
            
            logActivity('maintenance', 'INFO', "Maintenance history updated: ID $id", $historyRow['device_id']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Maintenance history updated successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to update maintenance history: ' . $db->error
            ]);
        }
    }
    
    // ================================================================
    // DELETE - Delete maintenance history
    // ================================================================
    elseif ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid history ID'
            ]);
            exit;
        }
        
        $sql = "DELETE FROM maintenance_history WHERE id = $id";
        
        if ($db->query($sql)) {
            logActivity('maintenance', 'INFO', "Maintenance history deleted: ID $id");
            
            echo json_encode([
                'success' => true,
                'message' => 'Maintenance history deleted successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to delete maintenance history: ' . $db->error
            ]);
        }
    }
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'error'   => 'Exception: ' . $e->getMessage() . ' (line ' . $e->getLine() . ')'
    ]);
}
?>