<?php
// ================================================================
// FAST DEVICE LIST API - NO SCANNING!
// Just returns devices from database instantly
// Perfect for dropdowns that need quick device list
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
    
    // Get optional filters
    $networkRange = $_GET['network_range'] ?? null;
    $status = $_GET['status'] ?? null;
    $orderBy = $_GET['order_by'] ?? 'name'; // name, ip, status, last_seen
    
    // ✅ FIXED: Added GPS location fields
    $sql = "SELECT 
                id,
                name,
                ip_address,
                mac_address,
                device_type,
                status,
                last_seen_at,
                network_range,
                location,
                latitude,
                longitude,
                location_updated_at,
                created_at
            FROM devices 
            WHERE 1=1";
    
    if ($networkRange) {
        $safeRange = $db->real_escape_string($networkRange);
        $sql .= " AND network_range = '$safeRange'";
    }
    
    if ($status) {
        $safeStatus = $db->real_escape_string($status);
        $sql .= " AND status = '$safeStatus'";
    }
    
    // Order by
    switch ($orderBy) {
        case 'ip':
            $sql .= " ORDER BY INET_ATON(ip_address)";
            break;
        case 'status':
            $sql .= " ORDER BY status DESC, name ASC";
            break;
        case 'last_seen':
            $sql .= " ORDER BY last_seen_at DESC";
            break;
        default:
            $sql .= " ORDER BY name ASC";
    }
    
    $result = $db->query($sql);
    $devices = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Get vendor from MAC
            $vendor = getVendorFromMac($row['mac_address']);
            
            // Calculate offline minutes
            $lastSeen = new DateTime($row['last_seen_at']);
            $now = new DateTime();
            $offlineMinutes = round(($now->getTimestamp() - $lastSeen->getTimestamp()) / 60);
            
            // Get device icon
            $icon = getDeviceIcon($row['device_type']);
            
            $devices[] = [
                'id' => (int)$row['id'],
                'ip' => $row['ip_address'],
                'mac' => $row['mac_address'],
                'vendor' => $vendor,
                'type' => ucfirst($row['device_type']),
                'icon' => $icon,
                'name' => $row['name'],
                'hostname' => $row['name'],
                'online' => $row['status'] === 'online',
                'last_seen' => $row['last_seen_at'],
                'offline_minutes' => $row['status'] === 'offline' ? (int)$offlineMinutes : 0,
                'network_range' => $row['network_range'],
                'location' => $row['location'],
                // ✅ FIXED: Added GPS fields
                'latitude' => $row['latitude'] ? (float)$row['latitude'] : null,
                'longitude' => $row['longitude'] ? (float)$row['longitude'] : null,
                'location_updated_at' => $row['location_updated_at'],
                'timestamp' => time()
            ];
        }
    }
    
    // Get stats
    $onlineCount = count(array_filter($devices, function($d) { return $d['online']; }));
    $offlineCount = count(array_filter($devices, function($d) { return !$d['online']; }));
    
    echo json_encode([
        'success' => true,
        'devices' => $devices,
        'stats' => [
            'total' => count($devices),
            'online' => $onlineCount,
            'offline' => $offlineCount
        ],
        'cached' => true, // Indicates this is from database, not live scan
        'timestamp' => time()
    ], JSON_THROW_ON_ERROR);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'details' => $e->getMessage()
    ]);
}
?>