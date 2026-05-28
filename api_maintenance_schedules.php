<?php
// ================================================================
// MAINTENANCE SCHEDULES API - WITH AUTO OVERDUE UPDATE
// Schedule and manage maintenance tasks for devices
// ================================================================

error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

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

// ✅ FIX: AUTO-UPDATE OVERDUE SCHEDULES
function updateOverdueSchedules() {
    try {
        $db = getDB();
        
        // Update schedules that are past their scheduled_date and still pending/in_progress
        $sql = "UPDATE maintenance_schedules 
                SET status = 'overdue' 
                WHERE scheduled_date < NOW() 
                AND status IN ('pending', 'in_progress')";
        
        $db->query($sql);
        
        $affectedRows = $db->affected_rows;
        if ($affectedRows > 0) {
            logActivity('maintenance', 'WARNING', "Marked $affectedRows schedules as overdue");
        }
        
    } catch (Exception $e) {
        error_log("Failed to update overdue schedules: " . $e->getMessage());
    }
}

try {
    $db = getDB();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'list';
    
    // ================================================================
    // GET - Fetch schedules
    // ================================================================
    if ($method === 'GET') {
        
        // ✅ FIX: Auto-update overdue schedules BEFORE fetching
        updateOverdueSchedules();
        
        if ($action === 'list') {
            // Get all schedules with filters
            $deviceId = $_GET['device_id'] ?? null;
            $status = $_GET['status'] ?? null;
            $priority = $_GET['priority'] ?? null;
            $from_date = $_GET['from_date'] ?? null;
            $to_date = $_GET['to_date'] ?? null;
            
            $sql = "SELECT 
                        ms.*,
                        d.name as device_name,
                        d.ip_address,
                        d.device_type,
                        mtt.task_name as template_task_name
                    FROM maintenance_schedules ms
                    JOIN devices d ON ms.device_id = d.id
                    LEFT JOIN maintenance_task_templates mtt ON ms.task_template_id = mtt.id
                    WHERE 1=1";
            
            if ($deviceId) {
                $deviceId = (int)$deviceId;
                $sql .= " AND ms.device_id = $deviceId";
            }
            
            if ($status) {
                $safeStatus = $db->real_escape_string($status);
                $sql .= " AND ms.status = '$safeStatus'";
            }
            
            if ($priority) {
                $safePriority = $db->real_escape_string($priority);
                $sql .= " AND ms.priority = '$safePriority'";
            }
            
            if ($from_date) {
                $safeFromDate = $db->real_escape_string($from_date);
                $sql .= " AND ms.scheduled_date >= '$safeFromDate'";
            }
            
            if ($to_date) {
                $safeToDate = $db->real_escape_string($to_date);
                $sql .= " AND ms.scheduled_date <= '$safeToDate'";
            }
            
            $sql .= " ORDER BY ms.scheduled_date ASC, ms.priority DESC";
            
            $result = $db->query($sql);
            $schedules = [];
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $schedules[] = [
                        'id' => (int)$row['id'],
                        'device_id' => (int)$row['device_id'],
                        'device_name' => $row['device_name'],
                        'ip_address' => $row['ip_address'],
                        'device_type' => $row['device_type'],
                        'task_template_id' => $row['task_template_id'] ? (int)$row['task_template_id'] : null,
                        'task_name' => $row['task_name'],
                        'task_category' => $row['task_category'],
                        'description' => $row['description'],
                        'scheduled_date' => $row['scheduled_date'],
                        'estimated_duration' => (int)$row['estimated_duration'],
                        'priority' => $row['priority'],
                        'status' => $row['status'], // Will now be 'overdue' if past date
                        'assigned_to' => $row['assigned_to'],
                        'notes' => $row['notes'],
                        'recurring' => (bool)$row['recurring'],
                        'recurring_interval' => $row['recurring_interval'],
                        'created_at' => $row['created_at'],
                        'updated_at' => $row['updated_at']
                    ];
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => $schedules,
                'count' => count($schedules)
            ]);
            
        } elseif ($action === 'upcoming') {
            // Get upcoming schedules (next 7 days by default)
            $days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
            
            $sql = "SELECT 
                        ms.*,
                        d.name as device_name,
                        d.ip_address,
                        d.device_type,
                        DATEDIFF(ms.scheduled_date, NOW()) as days_until
                    FROM maintenance_schedules ms
                    JOIN devices d ON ms.device_id = d.id
                    WHERE ms.scheduled_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL $days DAY)
                    AND ms.status IN ('pending', 'in_progress')
                    ORDER BY ms.scheduled_date ASC";
            
            $result = $db->query($sql);
            $schedules = [];
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $schedules[] = [
                        'id' => (int)$row['id'],
                        'device_id' => (int)$row['device_id'],
                        'device_name' => $row['device_name'],
                        'ip_address' => $row['ip_address'],
                        'device_type' => $row['device_type'],
                        'task_name' => $row['task_name'],
                        'task_category' => $row['task_category'],
                        'scheduled_date' => $row['scheduled_date'],
                        'estimated_duration' => (int)$row['estimated_duration'],
                        'priority' => $row['priority'],
                        'status' => $row['status'],
                        'days_until' => (int)$row['days_until']
                    ];
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => $schedules,
                'count' => count($schedules)
            ]);
            
        } elseif ($action === 'overdue') {
            // Get overdue schedules
            $sql = "SELECT 
                        ms.*,
                        d.name as device_name,
                        d.ip_address,
                        d.device_type,
                        DATEDIFF(NOW(), ms.scheduled_date) as days_overdue
                    FROM maintenance_schedules ms
                    JOIN devices d ON ms.device_id = d.id
                    WHERE ms.status = 'overdue'
                    ORDER BY days_overdue DESC";
            
            $result = $db->query($sql);
            $schedules = [];
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $schedules[] = [
                        'id' => (int)$row['id'],
                        'device_id' => (int)$row['device_id'],
                        'device_name' => $row['device_name'],
                        'ip_address' => $row['ip_address'],
                        'device_type' => $row['device_type'],
                        'task_name' => $row['task_name'],
                        'task_category' => $row['task_category'],
                        'scheduled_date' => $row['scheduled_date'],
                        'estimated_duration' => (int)$row['estimated_duration'],
                        'priority' => $row['priority'],
                        'status' => $row['status'],
                        'days_overdue' => (int)$row['days_overdue']
                    ];
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => $schedules,
                'count' => count($schedules)
            ]);
            
        } elseif ($action === 'get' && isset($_GET['id'])) {
            // Get single schedule
            $id = (int)$_GET['id'];
            
            $sql = "SELECT 
                        ms.*,
                        d.name as device_name,
                        d.ip_address,
                        d.device_type
                    FROM maintenance_schedules ms
                    JOIN devices d ON ms.device_id = d.id
                    WHERE ms.id = $id";
            
            $result = $db->query($sql);
            
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'id' => (int)$row['id'],
                        'device_id' => (int)$row['device_id'],
                        'device_name' => $row['device_name'],
                        'ip_address' => $row['ip_address'],
                        'device_type' => $row['device_type'],
                        'task_template_id' => $row['task_template_id'] ? (int)$row['task_template_id'] : null,
                        'task_name' => $row['task_name'],
                        'task_category' => $row['task_category'],
                        'description' => $row['description'],
                        'scheduled_date' => $row['scheduled_date'],
                        'estimated_duration' => (int)$row['estimated_duration'],
                        'priority' => $row['priority'],
                        'status' => $row['status'],
                        'assigned_to' => $row['assigned_to'],
                        'notes' => $row['notes'],
                        'recurring' => (bool)$row['recurring'],
                        'recurring_interval' => $row['recurring_interval'],
                        'created_at' => $row['created_at'],
                        'updated_at' => $row['updated_at']
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Schedule not found'
                ]);
            }
            
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action'
            ]);
        }
    }
    
    // ================================================================
    // POST - Create new schedule
    // ================================================================
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            $input = $_POST;
        }
        
        $deviceId = (int)($input['device_id'] ?? 0);
        $taskTemplateId = isset($input['task_template_id']) ? (int)$input['task_template_id'] : null;
        $taskName = $db->real_escape_string($input['task_name'] ?? '');
        $taskCategory = $db->real_escape_string($input['task_category'] ?? 'other');
        $description = isset($input['description']) ? $db->real_escape_string($input['description']) : null;
        $scheduledDate = $db->real_escape_string($input['scheduled_date'] ?? '');
        $estimatedDuration = (int)($input['estimated_duration'] ?? 30);
        $priority = $db->real_escape_string($input['priority'] ?? 'medium');
        $status = $db->real_escape_string($input['status'] ?? 'pending');
        $assignedTo = isset($input['assigned_to']) ? $db->real_escape_string($input['assigned_to']) : null;
        $notes = isset($input['notes']) ? $db->real_escape_string($input['notes']) : null;
        $recurring = isset($input['recurring']) ? (int)$input['recurring'] : 0;
        $recurringInterval = isset($input['recurring_interval']) ? $db->real_escape_string($input['recurring_interval']) : null;
        
        if ($deviceId <= 0 || empty($taskName) || empty($scheduledDate)) {
            echo json_encode([
                'success' => false,
                'error' => 'Device ID, task name, and scheduled date are required'
            ]);
            exit;
        }
        
        $taskTemplateIdVal = $taskTemplateId ? $taskTemplateId : 'NULL';
        $descriptionVal = $description ? "'$description'" : 'NULL';
        $assignedToVal = $assignedTo ? "'$assignedTo'" : 'NULL';
        $notesVal = $notes ? "'$notes'" : 'NULL';
        $recurringIntervalVal = $recurringInterval ? "'$recurringInterval'" : 'NULL';
        
        $sql = "INSERT INTO maintenance_schedules 
                (device_id, task_template_id, task_name, task_category, description, 
                 scheduled_date, estimated_duration, priority, status, assigned_to, 
                 notes, recurring, recurring_interval, created_at) 
                VALUES ($deviceId, $taskTemplateIdVal, '$taskName', '$taskCategory', $descriptionVal, 
                        '$scheduledDate', $estimatedDuration, '$priority', '$status', $assignedToVal, 
                        $notesVal, $recurring, $recurringIntervalVal, NOW())";
        
        if ($db->query($sql)) {
            $newId = $db->insert_id;
            
            logActivity('maintenance', 'INFO', "New maintenance scheduled: $taskName for device ID $deviceId");
            
            echo json_encode([
                'success' => true,
                'message' => 'Schedule created successfully',
                'id' => $newId
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to create schedule: ' . $db->error
            ]);
        }
    }
    
    // ================================================================
    // PUT - Update schedule
    // ================================================================
    elseif ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $id = (int)($input['id'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid schedule ID'
            ]);
            exit;
        }
        
        $updates = [];
        
        if (isset($input['task_name'])) {
            $taskName = $db->real_escape_string($input['task_name']);
            $updates[] = "task_name = '$taskName'";
        }
        
        if (isset($input['task_category'])) {
            $taskCategory = $db->real_escape_string($input['task_category']);
            $updates[] = "task_category = '$taskCategory'";
        }
        
        if (isset($input['description'])) {
            $description = $db->real_escape_string($input['description']);
            $updates[] = "description = '$description'";
        }
        
        if (isset($input['scheduled_date'])) {
            $scheduledDate = $db->real_escape_string($input['scheduled_date']);
            $updates[] = "scheduled_date = '$scheduledDate'";
        }
        
        if (isset($input['estimated_duration'])) {
            $estimatedDuration = (int)$input['estimated_duration'];
            $updates[] = "estimated_duration = $estimatedDuration";
        }
        
        if (isset($input['priority'])) {
            $priority = $db->real_escape_string($input['priority']);
            $updates[] = "priority = '$priority'";
        }
        
        if (isset($input['status'])) {
            $status = $db->real_escape_string($input['status']);
            $updates[] = "status = '$status'";
        }
        
        if (isset($input['assigned_to'])) {
            $assignedTo = $db->real_escape_string($input['assigned_to']);
            $updates[] = "assigned_to = '$assignedTo'";
        }
        
        if (isset($input['notes'])) {
            $notes = $db->real_escape_string($input['notes']);
            $updates[] = "notes = '$notes'";
        }
        
        if (isset($input['recurring'])) {
            $recurring = (int)$input['recurring'];
            $updates[] = "recurring = $recurring";
        }
        
        if (isset($input['recurring_interval'])) {
            $recurringInterval = $db->real_escape_string($input['recurring_interval']);
            $updates[] = "recurring_interval = '$recurringInterval'";
        }
        
        if (empty($updates)) {
            echo json_encode([
                'success' => false,
                'error' => 'No fields to update'
            ]);
            exit;
        }
        
        $updates[] = "updated_at = NOW()";
        
        $sql = "UPDATE maintenance_schedules SET " . implode(', ', $updates) . " WHERE id = $id";
        
        if ($db->query($sql)) {
            logActivity('maintenance', 'INFO', "Schedule updated: ID $id");
            
            echo json_encode([
                'success' => true,
                'message' => 'Schedule updated successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to update schedule: ' . $db->error
            ]);
        }
    }
    
    // ================================================================
    // DELETE - Delete schedule
    // ================================================================
    elseif ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid schedule ID'
            ]);
            exit;
        }
        
        $sql = "DELETE FROM maintenance_schedules WHERE id = $id";
        
        if ($db->query($sql)) {
            logActivity('maintenance', 'INFO', "Schedule deleted: ID $id");
            
            echo json_encode([
                'success' => true,
                'message' => 'Schedule deleted successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to delete schedule: ' . $db->error
            ]);
        }
    }
    
} catch (Exception $e) {
    logActivity('maintenance', 'ERROR', "Schedules API error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'details' => $e->getMessage()
    ]);
}
?>