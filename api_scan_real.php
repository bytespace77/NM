<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';

set_time_limit(120);
header('Content-Type: application/json');

// Clean any output buffer
if (ob_get_level()) ob_end_clean();
ob_start();

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
    $db = getDB();
    $networkInfo = getNetworkInfo();
    $currentNetworkRange = $networkInfo['range'];
    
    // Log scan start
    logActivity('scan', 'INFO', "Network scan started for range: $currentNetworkRange");
    
    // Scan network
    $arpDevices = scanNetworkFromARP();
    
    logActivity('scan', 'INFO', "ARP scan completed - Found " . count($arpDevices) . " devices");
    
    // GRACE PERIOD
    $gracePeriodSeconds = OFFLINE_GRACE_PERIOD;
    $safeRange = $db->real_escape_string($currentNetworkRange);
    
    $offlineResult = $db->query("UPDATE devices 
                SET status='offline' 
                WHERE network_range='$safeRange' 
                AND last_seen_at < DATE_SUB(NOW(), INTERVAL $gracePeriodSeconds SECOND)");
    
    if ($offlineResult && $db->affected_rows > 0) {
        logActivity('scan', 'WARNING', "Marked " . $db->affected_rows . " devices as offline (grace period: {$gracePeriodSeconds}s)");
    }
    
    // Process each discovered device
    $onlineIPs = [];
    $newDevices = 0;
    $updatedDevices = 0;
    
    foreach ($arpDevices as $device) {
        $ip = $db->real_escape_string($device['ip']);
        $mac = $db->real_escape_string($device['mac']);
        $networkRange = $db->real_escape_string($device['network_range']);
        $hostname = $device['hostname'] ? $db->real_escape_string($device['hostname']) : null;
        
        $onlineIPs[] = $ip;
        $existing = null;
        
        // Try to find by MAC
        if ($mac && $mac !== 'Unknown') {
            $existing = $db->query("SELECT * FROM devices 
                                   WHERE mac_address='$mac' AND network_range='$networkRange'
                                   LIMIT 1");
        }
        
        // If not found by MAC, try by IP
        if (!$existing || $existing->num_rows == 0) {
            $existing = $db->query("SELECT * FROM devices 
                                   WHERE ip_address='$ip' AND network_range='$networkRange'
                                   LIMIT 1");
        }
        
        // If still not found, check for ANY duplicate before inserting
        $duplicateCheck = $db->query("SELECT COUNT(*) as count FROM devices 
                                      WHERE (mac_address='$mac' OR ip_address='$ip') 
                                      AND network_range='$networkRange'");
        
        $hasDuplicate = $duplicateCheck->fetch_assoc()['count'] > 0;
        
        if ($existing && $existing->num_rows > 0) {
            // Device exists - UPDATE
            $row = $existing->fetch_assoc();
            $deviceId = $row['id'];
            
            // Check if status changed
            $wasOffline = $row['status'] === 'offline';
            
            // Delete any other entries with same MAC or IP
            $duplicates = $db->query("SELECT id FROM devices 
                       WHERE id != $deviceId 
                       AND (mac_address='$mac' OR ip_address='$ip')
                       AND network_range='$networkRange'");
            
            if ($duplicates && $duplicates->num_rows > 0) {
                $dupCount = $duplicates->num_rows;
                $db->query("DELETE FROM devices 
                           WHERE id != $deviceId 
                           AND (mac_address='$mac' OR ip_address='$ip')
                           AND network_range='$networkRange'");
                
                logActivity('scan', 'WARNING', "Removed $dupCount duplicate entries for device: $ip ($mac)", $deviceId);
            }
            
            // Re-detect with enhanced method
            $vendor = getVendorFromMac($mac);
            $newType = detectDeviceType($mac, $hostname, $ip);
            
            $updateSQL = "UPDATE devices SET 
                status='online',
                mac_address='$mac',
                ip_address='$ip',
                network_range='$networkRange',
                device_type='$newType',";
            
            if ($hostname) {
                $updateSQL .= " name='$hostname',";
            }
            
            $updateSQL .= " last_checked_at=NOW(),
                last_seen_at=NOW()
                WHERE id=$deviceId";
            
            $db->query($updateSQL);
            $updatedDevices++;
            
            // Log if device came back online
            if ($wasOffline) {
                $deviceName = $hostname ?: $ip;
                logActivity('network', 'INFO', "Device came back ONLINE: $deviceName ($ip)", $deviceId);
            }
            
        } elseif (!$hasDuplicate) {
            // Only insert if NO duplicate exists
            $vendor = getVendorFromMac($mac);
            $type = detectDeviceType($mac, $hostname, $ip);
            
            if ($hostname && $hostname !== 'Unknown') {
                $name = $hostname;
            } else {
                $namePrefix = $vendor !== 'Unknown' ? $vendor : 'Device';
                $lastOctet = substr($ip, strrpos($ip, '.') + 1);
                $name = $namePrefix . '-' . $lastOctet;
            }
            
            $safeName = $db->real_escape_string($name);
            
            // Double-check before insert
            $finalCheck = $db->query("SELECT id FROM devices 
                                      WHERE (mac_address='$mac' OR ip_address='$ip') 
                                      AND network_range='$networkRange'
                                      LIMIT 1");
            
            if (!$finalCheck || $finalCheck->num_rows == 0) {
                $sql = "INSERT INTO devices (
                    name, ip_address, mac_address, network_range, device_type, status, 
                    last_checked_at, last_seen_at, created_at
                ) VALUES (
                    '$safeName', '$ip', '$mac', '$networkRange', '$type', 'online', 
                    NOW(), NOW(), NOW()
                )";
                
                $db->query($sql);
                $newDeviceId = $db->insert_id;
                $newDevices++;
                
                // Log new device discovery
                logActivity('network', 'INFO', "NEW device discovered: $name ($ip) - Type: $type, Vendor: $vendor", $newDeviceId);
            }
        }
    }
    
    // Remove any duplicates that might have slipped through
    $cleanupIPs = $db->query("
        SELECT ip_address, MIN(id) as keep_id, COUNT(*) as count
        FROM devices
        WHERE network_range='$safeRange'
        GROUP BY ip_address
        HAVING COUNT(*) > 1
    ");
    
    $duplicatesRemoved = 0;
    if ($cleanupIPs) {
        while ($cleanup = $cleanupIPs->fetch_assoc()) {
            $cleanIP = $db->real_escape_string($cleanup['ip_address']);
            $keepID = $cleanup['keep_id'];
            $dupCount = $cleanup['count'] - 1;
            
            $db->query("DELETE FROM devices 
                       WHERE ip_address='$cleanIP' 
                       AND network_range='$safeRange'
                       AND id != $keepID");
            
            $duplicatesRemoved += $dupCount;
        }
    }
    
    // Same for MAC addresses
    $cleanupMACs = $db->query("
        SELECT mac_address, MIN(id) as keep_id, COUNT(*) as count
        FROM devices
        WHERE network_range='$safeRange'
        AND mac_address != 'Unknown'
        GROUP BY mac_address
        HAVING COUNT(*) > 1
    ");
    
    if ($cleanupMACs) {
        while ($cleanup = $cleanupMACs->fetch_assoc()) {
            $cleanMAC = $db->real_escape_string($cleanup['mac_address']);
            $keepID = $cleanup['keep_id'];
            $dupCount = $cleanup['count'] - 1;
            
            $db->query("DELETE FROM devices 
                       WHERE mac_address='$cleanMAC' 
                       AND network_range='$safeRange'
                       AND id != $keepID");
            
            $duplicatesRemoved += $dupCount;
        }
    }
    
    if ($duplicatesRemoved > 0) {
        logActivity('scan', 'WARNING', "Cleanup removed $duplicatesRemoved duplicate device entries");
    }
    
    // Update network device count
    updateNetworkDeviceCount($currentNetworkRange);
    
    // STEP 4: Get ALL devices for current network
    $allDevices = $db->query("SELECT * FROM devices 
                              WHERE network_range='$safeRange' 
                              ORDER BY status DESC, last_seen_at DESC");
    
    $formattedDevices = [];
    
    if ($allDevices) {
        while ($device = $allDevices->fetch_assoc()) {
            $vendor = getVendorFromMac($device['mac_address']);
            $type = $device['device_type'];
            
            $lastSeen = new DateTime($device['last_seen_at']);
            $now = new DateTime();
            $offlineMinutes = round($now->getTimestamp() - $lastSeen->getTimestamp()) / 60;
            
            $formattedDevices[] = [
                'id' => $device['id'],
                'ip' => $device['ip_address'],
                'mac' => $device['mac_address'],
                'vendor' => $vendor,
                'type' => ucfirst($type),
                'icon' => getDeviceIcon($type),
                'name' => $device['name'],
                'hostname' => $device['name'],
                'online' => $device['status'] === 'online',
                'last_seen' => $device['last_seen_at'],
                'offline_minutes' => $device['status'] === 'offline' ? round($offlineMinutes) : 0,
                'network_range' => $device['network_range'],
                'timestamp' => time()
            ];
        }
    }
    
    $allNetworks = getAllNetworks();
    
    // Log scan completion
    $onlineCount = count(array_filter($formattedDevices, function($d) { return $d['online']; }));
    $offlineCount = count(array_filter($formattedDevices, function($d) { return !$d['online']; }));
    
    logActivity('scan', 'INFO', "Scan completed - Total: " . count($formattedDevices) . " devices ($onlineCount online, $offlineCount offline, $newDevices new, $updatedDevices updated)");
    
    // Clear output
    ob_end_clean();
    
    // Return JSON
    echo json_encode([
        'success' => true,
        'current_network' => $networkInfo,
        'all_networks' => $allNetworks,
        'devices' => $formattedDevices,
        'stats' => [
            'total' => count($formattedDevices),
            'online' => $onlineCount,
            'offline' => $offlineCount,
            'new_devices' => $newDevices,
            'updated_devices' => $updatedDevices,
            'grace_period' => OFFLINE_GRACE_PERIOD,
            'scan_methods' => [
                'arp_refresh' => ARP_REFRESH_ENABLED,
                'aggressive_scan' => AGGRESSIVE_SCAN_ENABLED,
                'hostname_resolution' => HOSTNAME_RESOLUTION_ENABLED,
                'scan_type' => 'ultra-fast',
                'duplicate_prevention' => 'enabled'
            ]
        ],
        'scan_info' => [
            'devices_discovered' => count($arpDevices),
            'methods_used' => array_unique(array_column($arpDevices, 'method')),
            'scan_time' => time()
        ]
    ], JSON_THROW_ON_ERROR);
    
} catch (Exception $e) {
    ob_end_clean();
    
    logActivity('scan', 'ERROR', "Scan failed: " . $e->getMessage() . " in " . basename($e->getFile()) . " line " . $e->getLine());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
?>