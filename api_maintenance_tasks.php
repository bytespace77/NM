<?php
// ================================================================
// MAINTENANCE TASK TEMPLATES API
// Manage predefined maintenance tasks (Change CPU, Add RAM, etc.)
// ================================================================

error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
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

try {
    $db = getDB();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'list';
    
    // ================================================================
    // GET - Fetch task templates
    // ================================================================
    if ($method === 'GET') {
        
        if ($action === 'list') {
            // Get all active task templates
            $category = $_GET['category'] ?? null;
            $priority = $_GET['priority'] ?? null;
            
            $sql = "SELECT * FROM maintenance_task_templates WHERE is_active = 1";
            
            if ($category) {
                $safeCategory = $db->real_escape_string($category);
                $sql .= " AND task_category = '$safeCategory'";
            }
            
            if ($priority) {
                $safePriority = $db->real_escape_string($priority);
                $sql .= " AND priority = '$safePriority'";
            }
            
            $sql .= " ORDER BY priority DESC, task_name ASC";
            
            $result = $db->query($sql);
            $tasks = [];
            
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $tasks[] = [
                        'id' => (int)$row['id'],
                        'task_name' => $row['task_name'],
                        'task_category' => $row['task_category'],
                        'description' => $row['description'],
                        'estimated_duration' => (int)$row['estimated_duration'],
                        'priority' => $row['priority'],
                        'is_active' => (bool)$row['is_active'],
                        'created_at' => $row['created_at'],
                        'updated_at' => $row['updated_at']
                    ];
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => $tasks,
                'count' => count($tasks)
            ]);
            
        } elseif ($action === 'get' && isset($_GET['id'])) {
            // Get single task template
            $id = (int)$_GET['id'];
            
            $result = $db->query("SELECT * FROM maintenance_task_templates WHERE id = $id");
            
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'id' => (int)$row['id'],
                        'task_name' => $row['task_name'],
                        'task_category' => $row['task_category'],
                        'description' => $row['description'],
                        'estimated_duration' => (int)$row['estimated_duration'],
                        'priority' => $row['priority'],
                        'is_active' => (bool)$row['is_active'],
                        'created_at' => $row['created_at'],
                        'updated_at' => $row['updated_at']
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Task template not found'
                ]);
            }
            
        } elseif ($action === 'categories') {
            // Get available categories
            echo json_encode([
                'success' => true,
                'data' => [
                    'hardware',
                    'software',
                    'network',
                    'security',
                    'other'
                ]
            ]);
            
        } elseif ($action === 'priorities') {
            // Get available priorities
            echo json_encode([
                'success' => true,
                'data' => [
                    'low',
                    'medium',
                    'high',
                    'critical'
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
    // POST - Create new task template
    // ================================================================
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            $input = $_POST;
        }
        
        $taskName = $db->real_escape_string($input['task_name'] ?? '');
        $taskCategory = $db->real_escape_string($input['task_category'] ?? 'other');
        $description = $db->real_escape_string($input['description'] ?? '');
        $estimatedDuration = isset($input['estimated_duration']) ? (int)$input['estimated_duration'] : 30;
        $priority = $db->real_escape_string($input['priority'] ?? 'medium');
        
        if (empty($taskName)) {
            echo json_encode([
                'success' => false,
                'error' => 'Task name is required'
            ]);
            exit;
        }
        
        $sql = "INSERT INTO maintenance_task_templates 
                (task_name, task_category, description, estimated_duration, priority, created_at) 
                VALUES ('$taskName', '$taskCategory', '$description', $estimatedDuration, '$priority', NOW())";
        
        if ($db->query($sql)) {
            $newId = $db->insert_id;
            
            logActivity('maintenance', 'INFO', "New task template created: $taskName");
            
            echo json_encode([
                'success' => true,
                'message' => 'Task template created successfully',
                'id' => $newId
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to create task template: ' . $db->error
            ]);
        }
    }
    
    // ================================================================
    // PUT - Update task template
    // ================================================================
    elseif ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $id = (int)($input['id'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid task template ID'
            ]);
            exit;
        }
        
        $taskName = $db->real_escape_string($input['task_name'] ?? '');
        $taskCategory = $db->real_escape_string($input['task_category'] ?? 'other');
        $description = $db->real_escape_string($input['description'] ?? '');
        $estimatedDuration = isset($input['estimated_duration']) ? (int)$input['estimated_duration'] : 30;
        $priority = $db->real_escape_string($input['priority'] ?? 'medium');
        $isActive = isset($input['is_active']) ? (int)$input['is_active'] : 1;
        
        $sql = "UPDATE maintenance_task_templates SET 
                task_name = '$taskName',
                task_category = '$taskCategory',
                description = '$description',
                estimated_duration = $estimatedDuration,
                priority = '$priority',
                is_active = $isActive,
                updated_at = NOW()
                WHERE id = $id";
        
        if ($db->query($sql)) {
            logActivity('maintenance', 'INFO', "Task template updated: $taskName");
            
            echo json_encode([
                'success' => true,
                'message' => 'Task template updated successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to update task template: ' . $db->error
            ]);
        }
    }
    
    // ================================================================
    // DELETE - Delete task template
    // ================================================================
    elseif ($method === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid task template ID'
            ]);
            exit;
        }
        
        // Soft delete - mark as inactive
        $sql = "UPDATE maintenance_task_templates SET is_active = 0 WHERE id = $id";
        
        if ($db->query($sql)) {
            logActivity('maintenance', 'INFO', "Task template deleted: ID $id");
            
            echo json_encode([
                'success' => true,
                'message' => 'Task template deleted successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to delete task template: ' . $db->error
            ]);
        }
    }
    
} catch (Exception $e) {
    logActivity('maintenance', 'ERROR', "Task templates API error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'details' => $e->getMessage()
    ]);
}
?>