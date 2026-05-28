<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'network_monitor');

// Strong Tracing Configuration
define('OFFLINE_GRACE_PERIOD', 300);
define('PING_TIMEOUT', 100); // FASTER
define('PING_RETRIES', 1);
define('ARP_REFRESH_ENABLED', true);
define('HOSTNAME_RESOLUTION_ENABLED', false);
define('AGGRESSIVE_SCAN_ENABLED', true);

// ULTRA FAST: Only scan COMMON IPs (not all 254!)
define('USE_COMMON_IPS_ONLY', true); // ✅ FAST MODE

// Uptime Kuma configuration
define('UPTIME_KUMA_URL', 'http://localhost:3001');
define('UPTIME_KUMA_API_KEY', 'uk1_jcGSXTRyyNWGhdnSQ-gUDPyllWA8GIhb1KR3ahao');

// Set timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

function getLocalIP() {
    exec('ipconfig', $output);
    
    $possibleIPs = [];
    $currentAdapter = '';
    $hasGateway = false;
    
    foreach ($output as $line) {
        if (preg_match('/adapter (.+?):/i', $line, $matches)) {
            $currentAdapter = $matches[1];
            $hasGateway = false;
        }
        
        if (preg_match('/Default Gateway.*?:\s*(\d+\.\d+\.\d+\.\d+)/', $line, $matches)) {
            $hasGateway = true;
        }
        
        if (preg_match('/IPv4 Address.*?:\s*(\d+\.\d+\.\d+\.\d+)/', $line, $matches)) {
            $ip = $matches[1];
            
            if ($ip === '127.0.0.1' || preg_match('/^169\.254/', $ip)) {
                continue;
            }
            
            $priority = 0;
            
            if ($hasGateway) {
                $priority += 100;
            }
            
            if (stripos($currentAdapter, 'vmware') !== false ||
                stripos($currentAdapter, 'virtual') !== false ||
                stripos($currentAdapter, 'vethernet') !== false ||
                stripos($currentAdapter, 'hyper-v') !== false ||
                stripos($currentAdapter, 'wsl') !== false) {
                $priority -= 50;
            }
            
            if (stripos($currentAdapter, 'wi-fi') !== false ||
                stripos($currentAdapter, 'wireless') !== false ||
                stripos($currentAdapter, 'wlan') !== false) {
                $priority += 10;
            }
            
            if (stripos($currentAdapter, 'ethernet') !== false && 
                stripos($currentAdapter, 'vethernet') === false) {
                $priority += 10;
            }
            
            $possibleIPs[] = [
                'ip' => $ip,
                'adapter' => $currentAdapter,
                'priority' => $priority,
                'has_gateway' => $hasGateway
            ];
        }
    }
    
    usort($possibleIPs, function($a, $b) {
        return $b['priority'] - $a['priority'];
    });
    
    if (!empty($possibleIPs)) {
        return $possibleIPs[0]['ip'];
    }
    
    return '127.0.0.1';
}

function getGatewayIP() {
    exec('ipconfig', $output);
    
    foreach ($output as $line) {
        if (preg_match('/Default Gateway.*?:\s*(\d+\.\d+\.\d+\.\d+)/', $line, $matches)) {
            $gateway = $matches[1];
            if ($gateway !== '0.0.0.0' && $gateway !== '') {
                return $gateway;
            }
        }
    }
    
    return null;
}

function getNetworkInfo() {
    $ip = getLocalIP();
    $parts = explode('.', $ip);
    $network = $parts[0] . '.' . $parts[1] . '.' . $parts[2];
    $range = $network . '.0/24';
    $gateway = getGatewayIP();
    
    try {
        $db = getDB();
        $safeRange = $db->real_escape_string($range);
        
        $tableExists = $db->query("SHOW TABLES LIKE 'networks'");
        if ($tableExists && $tableExists->num_rows > 0) {
            $db->query("INSERT INTO networks (network_range, last_seen_at) 
                        VALUES ('$safeRange', NOW()) 
                        ON DUPLICATE KEY UPDATE last_seen_at=NOW()");
        }
    } catch (Exception $e) {
    }
    
    return [
        'ip' => $ip,
        'network' => $network,
        'range' => $range,
        'gateway' => $gateway
    ];
}

function getCurrentNetworkRange() {
    $info = getNetworkInfo();
    return $info['range'];
}

function getAllNetworks() {
    $db = getDB();
    
    $tableExists = $db->query("SHOW TABLES LIKE 'networks'");
    if (!$tableExists || $tableExists->num_rows == 0) {
        return [];
    }
    
    $result = $db->query("SELECT * FROM networks ORDER BY last_seen_at DESC");
    
    $networks = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $range = $db->real_escape_string($row['network_range']);
            $stats = $db->query("SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status='online' THEN 1 ELSE 0 END) as online,
                SUM(CASE WHEN status='offline' THEN 1 ELSE 0 END) as offline
                FROM devices WHERE network_range='$range'")->fetch_assoc();
            
            $row['total_devices'] = $stats['total'];
            $row['online_devices'] = $stats['online'];
            $row['offline_devices'] = $stats['offline'];
            
            $networks[] = $row;
        }
    }
    
    return $networks;
}

function updateNetworkDeviceCount($networkRange) {
    $db = getDB();
    $safeRange = $db->real_escape_string($networkRange);
    
    $tableExists = $db->query("SHOW TABLES LIKE 'networks'");
    if (!$tableExists || $tableExists->num_rows == 0) {
        return;
    }
    
    $count = $db->query("SELECT COUNT(*) as total FROM devices WHERE network_range='$safeRange'")->fetch_assoc()['total'];
    $db->query("UPDATE networks SET total_devices=$count WHERE network_range='$safeRange'");
}

function refreshARPTable($networkInfo) {
    if (!ARP_REFRESH_ENABLED) {
        return;
    }
    
    $gateway = $networkInfo['gateway'];
    
    if ($gateway) {
        exec("ping -n 1 -w 50 $gateway > nul 2>&1");
    }
    
    exec("arp -d * > nul 2>&1");
}

function aggressivePing($ip, $retries = PING_RETRIES) {
    exec("ping -n 1 -w " . PING_TIMEOUT . " $ip 2>&1", $output, $status);
    return ($status === 0);
}

function isUptimeKumaRunning() {
    $ch = curl_init(UPTIME_KUMA_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($httpCode >= 200 && $httpCode < 400);
}

function pingDevice($ip) {
    $startTime = microtime(true);
    $online = aggressivePing($ip);
    $endTime = microtime(true);
    $responseTime = round(($endTime - $startTime) * 1000);
    
    return [
        'online' => $online,
        'response_time' => $online ? $responseTime : null,
        'checked_at' => date('Y-m-d H:i:s')
    ];
}

function getDeviceHostname($ip) {
    return null;
}

function getVendorFromMac($mac) {
    $oui = strtoupper(substr(str_replace(':', '', $mac), 0, 6));
    
    $vendors = [
        'DCCF96' => 'Xiaomi Phone', '34CE00' => 'Xiaomi Phone', '2CCF67' => 'Xiaomi Phone',
        'E454E8' => 'Xiaomi Phone', 'F8A45F' => 'Xiaomi Phone', '64095F' => 'Xiaomi Phone',
        '28F076' => 'Apple iPhone', '5CF9DD' => 'Apple iPhone', 'A4C639' => 'Apple iPhone',
        'F0F002' => 'Apple iPhone', '3C2EFF' => 'Apple iPhone', 'DC2B2A' => 'Apple iPhone',
        '2C3AE8' => 'Samsung Phone', '5C497D' => 'Samsung Phone', 'B0E235' => 'Samsung Phone',
        '18D071' => 'Samsung Phone', 'E8E5D6' => 'Samsung Phone', '6C2F2C' => 'Samsung Phone',
        '001632' => 'Samsung Phone', '0012FB' => 'Samsung Phone', '001377' => 'Samsung Phone',
        '0000F0' => 'Samsung Phone', '0007AB' => 'Samsung Phone', '001247' => 'Samsung Phone',
        '8CB0E9' => 'Samsung Phone', 'C8418A' => 'Samsung Phone', '72460F' => 'Samsung Phone',
        'A25884' => 'Samsung Phone',
        'AC3743' => 'Huawei Phone', 'D0C857' => 'Huawei Phone', '0C37DC' => 'Huawei Phone',
        '442133' => 'Huawei Phone', '9C28EF' => 'Huawei Phone',
        'D023DB' => 'Oppo Phone', '30F7C5' => 'Oppo Phone', '84A466' => 'Oppo Phone',
        '582E9E' => 'Vivo Phone', '3C9872' => 'Vivo Phone',
        '0CB319' => 'Realme Phone', '6CEA3A' => 'Realme Phone',
        '8C8590' => 'OnePlus Phone', 'AC37E5' => 'OnePlus Phone',
        
        '001A2B' => 'Dell Laptop', '0050B6' => 'Dell Laptop', '18FEB5' => 'Dell Laptop',
        '48A472' => 'HP Laptop', '002655' => 'HP Laptop', '94E979' => 'HP Laptop',
        '001D0F' => 'ASUS Laptop', '2C56DC' => 'ASUS Laptop', '04D4C4' => 'ASUS Laptop',
        '0024E8' => 'Lenovo Laptop', '8CDC3E' => 'Lenovo Laptop', '309C23' => 'Lenovo Laptop',
        '9C5C8E' => 'Acer Laptop', 'E0469A' => 'Acer Laptop', 'F80113' => 'Acer Laptop',
        '00155D' => 'Microsoft Surface', '985FD3' => 'Microsoft Surface',
        '701407' => 'Toshiba Laptop', '001560' => 'Toshiba Laptop',
        
        '3CA854' => 'Apple MacBook', 'F0DBE2' => 'Apple MacBook', 'F0CBA1' => 'Apple MacBook',
        '38F9D3' => 'Apple MacBook', '8863DF' => 'Apple MacBook',
        
        '54EAA8' => 'Apple iPad', '9803D8' => 'Apple iPad', 'E056F0' => 'Apple iPad',
        '643150' => 'Samsung Tablet', '7C6193' => 'Samsung Tablet',
        'C87E75' => 'Huawei Tablet',
        
        '0050B6' => 'HP Printer', '002264' => 'HP Printer', 'C8D3FF' => 'HP Printer',
        '0004AC' => 'Canon Printer', '0026AB' => 'Canon Printer',
        '0000F0' => 'Epson Printer', '00A053' => 'Epson Printer',
        '00E036' => 'Brother Printer',
        
        'DCA632' => 'TP-Link Router', '74E9BF' => 'TP-Link Router', '14EBB6' => 'TP-Link Router',
        'B046FC' => 'TP-Link Router', '50C7BF' => 'TP-Link Router',
        '001D0F' => 'ASUS Router', '04D4C4' => 'ASUS Router',
        '086698' => 'D-Link Router', '1CBDB9' => 'D-Link Router',
        'B0B98A' => 'Netgear Router', 'A040A0' => 'Netgear Router',
        'E8DE27' => 'Linksys Router',
        
        'B827EB' => 'Raspberry Pi', 'DCA632' => 'Smart Device',
        '54AF97' => 'Smart TV', '74DADA' => 'Smart Device',
        
        '0026BB' => 'Nintendo Switch', 'B8AE6E' => 'Nintendo Switch',
        '002444' => 'Sony PlayStation', '7CBD28' => 'Sony PlayStation',
        '98E8FA' => 'Microsoft Xbox', '00095B' => 'Microsoft Xbox',
        
        'D8C80C' => 'Intel NIC', '3460F9' => 'Intel NIC', '0023EB' => 'Intel NIC',
    ];
    
    return $vendors[$oui] ?? 'Unknown';
}

function getTTL($ip) {
    return null;
}

function detectOSFromTTL($ttl) {
    return 'Unknown';
}

function detectDeviceType($mac, $hostname = null, $ip = null) {
    $vendor = getVendorFromMac($mac);
    $vendorLower = strtolower($vendor);
    
    if (strpos($vendorLower, 'phone') !== false) return 'phone';
    if (strpos($vendorLower, 'iphone') !== false) return 'phone';
    if (strpos($vendorLower, 'xiaomi') !== false) return 'phone';
    if (strpos($vendorLower, 'samsung phone') !== false) return 'phone';
    if (strpos($vendorLower, 'huawei phone') !== false) return 'phone';
    if (strpos($vendorLower, 'oppo') !== false) return 'phone';
    if (strpos($vendorLower, 'vivo') !== false) return 'phone';
    if (strpos($vendorLower, 'realme') !== false) return 'phone';
    if (strpos($vendorLower, 'oneplus') !== false) return 'phone';
    
    if (strpos($vendorLower, 'ipad') !== false) return 'tablet';
    if (strpos($vendorLower, 'tablet') !== false) return 'tablet';
    
    if (strpos($vendorLower, 'laptop') !== false) return 'laptop';
    if (strpos($vendorLower, 'macbook') !== false) return 'laptop';
    if (strpos($vendorLower, 'surface') !== false) return 'laptop';
    if (strpos($vendorLower, 'dell') !== false) return 'laptop';
    if (strpos($vendorLower, 'lenovo') !== false) return 'laptop';
    if (strpos($vendorLower, 'asus') !== false && strpos($vendorLower, 'router') === false) return 'laptop';
    if (strpos($vendorLower, 'acer') !== false) return 'laptop';
    if (strpos($vendorLower, 'toshiba') !== false) return 'laptop';
    if (strpos($vendorLower, 'apple') !== false) return 'laptop';
    
    if (strpos($vendorLower, 'printer') !== false) return 'printer';
    if (strpos($vendorLower, 'canon') !== false) return 'printer';
    if (strpos($vendorLower, 'epson') !== false) return 'printer';
    if (strpos($vendorLower, 'brother') !== false) return 'printer';
    if (strpos($vendorLower, 'hp') !== false && strpos($vendorLower, 'laptop') === false) return 'printer';
    
    if (strpos($vendorLower, 'router') !== false) return 'router';
    if (strpos($vendorLower, 'tp-link') !== false) return 'router';
    if (strpos($vendorLower, 'd-link') !== false) return 'router';
    if (strpos($vendorLower, 'netgear') !== false) return 'router';
    if (strpos($vendorLower, 'linksys') !== false) return 'router';
    
    if (strpos($vendorLower, 'raspberry') !== false) return 'server';
    if (strpos($vendorLower, 'smart tv') !== false) return 'other';
    if (strpos($vendorLower, 'nintendo') !== false) return 'other';
    if (strpos($vendorLower, 'playstation') !== false) return 'other';
    if (strpos($vendorLower, 'xbox') !== false) return 'other';
    
    return 'computer';
}

function getDeviceIcon($type) {
    $icons = [
        'phone' => '📱',
        'laptop' => '💻',
        'computer' => '🖥️',
        'tablet' => '📱',
        'server' => '🖥️',
        'printer' => '🖨️',
        'router' => '📡',
        'switch' => '🔌',
        'other' => '📟'
    ];
    return $icons[$type] ?? '📟';
}

// Common IPs only!
function scanNetworkFromARP() {
    $networkInfo = getNetworkInfo();
    $yourIP = $networkInfo['ip'];
    $network = $networkInfo['network'];
    $networkRange = $networkInfo['range'];
    
    refreshARPTable($networkInfo);
    
    $devices = [];
    $foundMACs = [];
    
    // Read initial ARP
    exec('arp -a', $arpOutput);
    
    foreach ($arpOutput as $line) {
        if (preg_match('/(\d+\.\d+\.\d+\.\d+)\s+([0-9a-f\-]{17})\s+dynamic/i', $line, $matches)) {
            $ip = $matches[1];
            $mac = strtoupper(str_replace('-', ':', $matches[2]));
            
            if (strpos($ip, $network) === 0 && 
                !preg_match('/\.(1|255)$/', $ip) && 
                $ip !== $yourIP) {
                
                $devices[] = [
                    'ip' => $ip,
                    'mac' => $mac,
                    'hostname' => null,
                    'ttl' => null,
                    'online' => true,
                    'network_range' => $networkRange,
                    'discovered_at' => date('Y-m-d H:i:s'),
                    'method' => 'ARP'
                ];
                
                $foundMACs[$mac] = true;
            }
        }
    }
    
    if (AGGRESSIVE_SCAN_ENABLED && USE_COMMON_IPS_ONLY) {
    $commonIPs = range(1, 25);
    sleep(5);
    $commonIPs = range(26, 90);
    sleep(5);
    $commonIPs = range(91, 155);
    sleep(5);
    $commonIPs = range(156, 180);
    sleep(5);
    $commonIPs = range(181, 254);
        
        // Ping all common IPs quickly
        foreach ($commonIPs as $lastOctet) {
            $testIP = $network . '.' . $lastOctet;
            
            if ($testIP !== $yourIP) {
                exec("ping -n 1 -w 80 $testIP > nul 2>&1");
            }
        }
        
        // Wait for ARP to populate
        sleep(5);
        
        // Read ARP again
        exec('arp -a', $newArp);
        
        foreach ($newArp as $line) {
            if (preg_match('/(\d+\.\d+\.\d+\.\d+)\s+([0-9a-f\-]{17})\s+dynamic/i', $line, $matches)) {
                $ip = $matches[1];
                $mac = strtoupper(str_replace('-', ':', $matches[2]));
                
                if (strpos($ip, $network) === 0 && 
                    !preg_match('/\.(1|255)$/', $ip) && 
                    $ip !== $yourIP &&
                    !isset($foundMACs[$mac])) {
                    
                    $devices[] = [
                        'ip' => $ip,
                        'mac' => $mac,
                        'hostname' => null,
                        'ttl' => null,
                        'online' => true,
                        'network_range' => $networkRange,
                        'discovered_at' => date('Y-m-d H:i:s'),
                        'method' => 'Ultra-Fast'
                    ];
                    
                    $foundMACs[$mac] = true;
                }
            }
        }
    }
    
    return $devices;
}

function createUptimeKumaMonitor($name, $ip) {
    $url = UPTIME_KUMA_URL . '/api/add';
    
    $data = [
        'type' => 'ping',
        'name' => $name,
        'hostname' => $ip,
        'interval' => 60,
        'retryInterval' => 60,
        'maxretries' => 3,
        'active' => true
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: ' . UPTIME_KUMA_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        $result = json_decode($response, true);
        return $result['monitorID'] ?? true;
    }
    
    return false;
}

function deleteUptimeKumaMonitor($monitorId) {
    if (!$monitorId) return false;
    
    $url = UPTIME_KUMA_URL . '/api/monitor/' . $monitorId;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . UPTIME_KUMA_API_KEY
    ]);
    
    curl_exec($ch);
    curl_close($ch);
    
    return true;
}

function getMacFromIP($ip) {
    exec("arp -a $ip", $output);
    foreach ($output as $line) {
        if (preg_match('/([0-9a-f\-]{17})/i', $line, $matches)) {
            return strtoupper(str_replace('-', ':', $matches[1]));
        }
    }
    
    exec("ping -n 1 -w 100 $ip > nul 2>&1");
    sleep(1);
    
    exec("arp -a $ip", $output2);
    foreach ($output2 as $line) {
        if (preg_match('/([0-9a-f\-]{17})/i', $line, $matches)) {
            return strtoupper(str_replace('-', ':', $matches[1]));
        }
    }
    
    return 'Unknown';
}

function scanNetworkRealTime() {
    return scanNetworkFromARP();
}

function quickPing($ip) {
    return aggressivePing($ip, 1);
}
?>