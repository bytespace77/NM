<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
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

try {
    $db = getDB();
    $input = json_decode(file_get_contents('php://input'), true);
    
    $name = $db->real_escape_string($input['name'] ?? '');
    $ipAddress = $db->real_escape_string($input['ip_address'] ?? '');
    $macAddress = isset($input['mac_address']) && !empty($input['mac_address'])
                  ? "'" . $db->real_escape_string($input['mac_address']) . "'"
                  : 'NULL';
    $deviceType = $db->real_escape_string($input['device_type'] ?? 'computer');
    $location = isset($input['location']) && !empty($input['location'])
               ? "'" . $db->real_escape_string($input['location']) . "'"
               : 'NULL';
    
    if (empty($name) || empty($ipAddress)) {
        echo json_encode([
            'success' => false,
            'error' => 'Name and IP address are required'
        ]);
        exit;
    }
    
    // Check if device already exists
    $checkQuery = "SELECT id FROM devices WHERE ip_address = '$ipAddress'";
    $checkResult = $db->query($checkQuery);
    
    if ($checkResult && $checkResult->num_rows > 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Device with this IP address already exists'
        ]);
        exit;
    }
    
    // Get network range from IP
    $parts = explode('.', $ipAddress);
    if (count($parts) === 4) {
        $networkRange = $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0/24';
    } else {
        $networkRange = '192.168.0.0/24'; // Default
    }
    
    $sql = "INSERT INTO devices 
            (name, ip_address, mac_address, device_type, location, network_range, 
             status, discovery_method, created_at, last_checked_at) 
            VALUES ('$name', '$ipAddress', $macAddress, '$deviceType', $location, 
                    '$networkRange', 'unknown', 'manual', NOW(), NOW())";
    
    if ($db->query($sql)) {
        $newId = $db->insert_id;
        
        logActivity('network', 'INFO', "Device manually added: $name ($ipAddress)", $newId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Device added successfully',
            'id' => $newId
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to add device: ' . $db->error
        ]);
    }
    
} catch (Exception $e) {
    logActivity('network', 'ERROR', "Failed to add device: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'details' => $e->getMessage()
    ]);
}
?>