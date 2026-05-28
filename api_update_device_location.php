<?php
// ================================================================
// UPDATE DEVICE LOCATION API
// Save GPS coordinates and address for device tracking
// ================================================================

error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function logActivity($category, $level, $message, $deviceId = null)
{
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

    $deviceId = (int)($input['device_id'] ?? 0);
    $latitude = isset($input['latitude']) ? (float)$input['latitude'] : null;
    $longitude = isset($input['longitude']) ? (float)$input['longitude'] : null;
    $locationAddress = $db->real_escape_string($input['location_address'] ?? '');

    // Validate
    if ($deviceId <= 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid device ID'
        ]);
        exit;
    }

    if ($latitude === null || $longitude === null) {
        echo json_encode([
            'success' => false,
            'error' => 'Latitude and longitude are required'
        ]);
        exit;
    }

    // Check if device exists
    $checkQuery = "SELECT id, name FROM devices WHERE id = $deviceId";
    $checkResult = $db->query($checkQuery);

    if (!$checkResult || $checkResult->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Device not found'
        ]);
        exit;
    }

    $device = $checkResult->fetch_assoc();

    // Update device location
    $sql = "UPDATE devices 
            SET latitude = $latitude,
                longitude = $longitude,
                location = '$locationAddress',
                location_updated_at = NOW()
            WHERE id = $deviceId";

    if ($db->query($sql)) {
        logActivity('device', 'INFO', "Location updated for device: {$device['name']}", $deviceId);

        echo json_encode([
            'success' => true,
            'message' => 'Device location updated successfully',
            'data' => [
                'device_id' => $deviceId,
                'device_name' => $device['name'],
                'latitude' => $latitude,
                'longitude' => $longitude,
                'location_address' => $locationAddress,
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to update location: ' . $db->error
        ]);
    }
} catch (Exception $e) {
    logActivity('device', 'ERROR', "Failed to update device location: " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'details' => $e->getMessage()
    ]);
}
