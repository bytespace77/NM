<?php

require_once 'config.php';

// ========================================
// CONFIGURATION - THRESHOLDS
// ========================================
define('CHECK_INTERVAL', 10);  // Check every 5 seconds (lightweight)
define('ENABLE_NETWORK_TRAFFIC', true);  // NEW: Enable network traffic monitoring
define('ENABLE_LATENCY', true);          // NEW: Enable latency monitoring
define('ENABLE_DISK', true);
define('ENABLE_DETAILED_LOGGING', true);
define('ENABLE_SYSTEM_LOGS', true);

// THRESHOLD SETTINGS - Only capture if change exceeds these values
define('CPU_THRESHOLD', 1.0);        
define('RAM_THRESHOLD', 1.0);       
define('NETWORK_THRESHOLD', 5.0);    // NEW: MB/s threshold
define('LATENCY_THRESHOLD', 10.0);   // NEW: ms threshold
define('DISK_THRESHOLD', 0.01);  // LOWERED - Disk changes slowly       
// // PURE THRESHOLD MODE - Only save on actual changes (Boss requirement)
// define('FORCE_CAPTURE_INTERVAL', 0);  // DISABLED
// define('FORCE_CAPTURE_INTERVAL', 60);

// GATEWAY/DNS for latency testing
define('LATENCY_HOST', '1.1.1.1');  // Cloudflare DNS
$GLOBALS['WMIC_AVAILABLE'] = false;
$GLOBALS['last_values'] = [];
$GLOBALS['last_capture_time'] = 0;
$GLOBALS['last_bytes'] = ['sent' => 0, 'received' => 0, 'time' => 0];

function logToSystem($deviceId, $level, $category, $message) {
    if (!ENABLE_SYSTEM_LOGS) return;
    
    try {
        $db = getDB();
        $safeLevel = $db->real_escape_string($level);
        $safeCategory = $db->real_escape_string($category);
        $safeMessage = $db->real_escape_string($message);
        $deviceIdVal = $deviceId ? (int)$deviceId : 'NULL';
        
        $sql = "INSERT INTO system_logs (device_id, log_level, category, message, created_at) 
                VALUES ($deviceIdVal, '$safeLevel', '$safeCategory', '$safeMessage', NOW())";
        
        $db->query($sql);
    } catch (Exception $e) {
        error_log("Failed to write system log: " . $e->getMessage());
    }
}

function detectWMIC() {
    static $checked = false;
    static $available = false;
    
    if ($checked) return $available;
    
    $checked = true;
    exec('wmic os get caption 2>&1', $output, $status);
    $available = ($status === 0 && !empty($output));
    $GLOBALS['WMIC_AVAILABLE'] = $available;
    
    return $available;
}

// ========================================
// EXISTING METRIC FUNCTIONS (CPU, RAM, DISK)
// ========================================
function getCPUUsage() {
    try {
        if (detectWMIC()) {
            exec('wmic cpu get loadpercentage /value 2>&1', $output, $status);
            if ($status === 0 && !empty($output)) {
                foreach ($output as $line) {
                    if (strpos($line, 'LoadPercentage') !== false) {
                        preg_match('/LoadPercentage=(\d+)/', $line, $matches);
                        if (isset($matches[1])) {
                            $cpu = (float)$matches[1];
                            if ($cpu >= 0 && $cpu <= 100) return $cpu;
                        }
                    }
                }
            }
        }
        
        exec('powershell -Command "Get-WmiObject Win32_Processor | Select-Object -ExpandProperty LoadPercentage" 2>&1', $output2, $status2);
        if ($status2 === 0 && !empty($output2) && is_numeric(trim($output2[0]))) {
            $cpu = (float)trim($output2[0]);
            if ($cpu >= 0 && $cpu <= 100) return $cpu;
        }
    } catch (Exception $e) {
        error_log("CPU exception: " . $e->getMessage());
    }
    
    return 0.0;
}

function getCPUFrequency() {
    try {
        if (detectWMIC()) {
            exec('wmic cpu get CurrentClockSpeed,MaxClockSpeed /value 2>&1', $output, $status);
            if ($status === 0 && !empty($output)) {
                $current = 0;
                $max = 0;
                foreach ($output as $line) {
                    if (strpos($line, 'CurrentClockSpeed') !== false) {
                        preg_match('/CurrentClockSpeed=(\d+)/', $line, $matches);
                        $current = isset($matches[1]) ? (int)$matches[1] : 0;
                    }
                    if (strpos($line, 'MaxClockSpeed') !== false) {
                        preg_match('/MaxClockSpeed=(\d+)/', $line, $matches);
                        $max = isset($matches[1]) ? (int)$matches[1] : 0;
                    }
                }
                
                if ($current > 0) {
                    return [
                        'current_mhz' => $current,
                        'max_mhz' => $max,
                        'current_ghz' => round($current / 1000, 2),
                        'max_ghz' => round($max / 1000, 2)
                    ];
                }
            }
        }
    } catch (Exception $e) {}
    
    return ['current_mhz' => 0, 'max_mhz' => 0, 'current_ghz' => 0, 'max_ghz' => 0];
}

function getRAMUsage() {
    try {
        if (detectWMIC()) {
            exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /value 2>&1', $output, $status);
            if ($status === 0 && !empty($output)) {
                $total = 0; $free = 0;
                foreach ($output as $line) {
                    if (strpos($line, 'FreePhysicalMemory') !== false) {
                        preg_match('/FreePhysicalMemory=(\d+)/', $line, $matches);
                        $free = isset($matches[1]) ? (int)$matches[1] : 0;
                    }
                    if (strpos($line, 'TotalVisibleMemorySize') !== false) {
                        preg_match('/TotalVisibleMemorySize=(\d+)/', $line, $matches);
                        $total = isset($matches[1]) ? (int)$matches[1] : 0;
                    }
                }
                
                if ($total > 0) {
                    $totalMB = round($total / 1024);
                    $totalGB = round($total / 1024 / 1024, 2);
                    $usedKB = $total - $free;
                    $usedMB = round($usedKB / 1024);
                    $usedGB = round($usedKB / 1024 / 1024, 2);
                    $freeGB = round($free / 1024 / 1024, 2);
                    $usagePercent = round(($usedKB / $total) * 100, 2);
                    
                    return [
                        'usage' => $usagePercent,
                        'total_mb' => $totalMB,
                        'used_mb' => $usedMB,
                        'total_gb' => $totalGB,
                        'used_gb' => $usedGB,
                        'free_gb' => $freeGB
                    ];
                }
            }
        }
        
        exec('powershell -Command "$os = Get-WmiObject Win32_OperatingSystem; $total = $os.TotalVisibleMemorySize; $free = $os.FreePhysicalMemory; Write-Output \"$total,$free\"" 2>&1', $output2, $status2);
        if ($status2 === 0 && !empty($output2)) {
            $parts = explode(',', trim($output2[0]));
            if (count($parts) === 2) {
                $total = (int)$parts[0];
                $free = (int)$parts[1];
                
                if ($total > 0) {
                    $totalMB = round($total / 1024);
                    $totalGB = round($total / 1024 / 1024, 2);
                    $usedKB = $total - $free;
                    $usedMB = round($usedKB / 1024);
                    $usedGB = round($usedKB / 1024 / 1024, 2);
                    $freeGB = round($free / 1024 / 1024, 2);
                    $usagePercent = round(($usedKB / $total) * 100, 2);
                    
                    return [
                        'usage' => $usagePercent,
                        'total_mb' => $totalMB,
                        'used_mb' => $usedMB,
                        'total_gb' => $totalGB,
                        'used_gb' => $usedGB,
                        'free_gb' => $freeGB
                    ];
                }
            }
        }
    } catch (Exception $e) {
        error_log("RAM exception: " . $e->getMessage());
    }
    
    return [
        'usage' => 0,
        'total_mb' => 0,
        'used_mb' => 0,
        'total_gb' => 0,
        'used_gb' => 0,
        'free_gb' => 0
    ];
}

function getDiskUsage() {
    if (!ENABLE_DISK) return null;
    
    try {
        $drive = 'C:';
        
        // Method 1: Try WMIC first
        if (detectWMIC()) {
            exec("wmic logicaldisk where \"DeviceID='$drive'\" get Size,FreeSpace /value 2>&1", $output, $status);
            if ($status === 0 && !empty($output)) {
                $size = 0; $free = 0;
                foreach ($output as $line) {
                    if (strpos($line, 'FreeSpace') !== false) {
                        preg_match('/FreeSpace=(\d+)/', $line, $matches);
                        $free = isset($matches[1]) ? (int)$matches[1] : 0;
                    }
                    if (strpos($line, 'Size') !== false && strpos($line, 'FreeSpace') === false) {
                        preg_match('/Size=(\d+)/', $line, $matches);
                        $size = isset($matches[1]) ? (int)$matches[1] : 0;
                    }
                }
                
                if ($size > 0) {
                    $totalGB = round($size / 1024 / 1024 / 1024, 2);
                    $freeGB = round($free / 1024 / 1024 / 1024, 2);
                    $usedGB = round(($size - $free) / 1024 / 1024 / 1024, 2);
                    $usagePercent = round((($size - $free) / $size) * 100, 2);
                    
                    return [
                        'usage' => $usagePercent,
                        'total_gb' => $totalGB,
                        'used_gb' => $usedGB,
                        'free_gb' => $freeGB
                    ];
                }
            }
        }
        
        // Method 2: Fallback to PowerShell if WMIC failed
        $cmd = 'powershell -Command "Get-PSDrive C | Select-Object Used,Free | ConvertTo-Json"';
        exec($cmd . ' 2>&1', $psOutput, $psStatus);
        
        if ($psStatus === 0 && !empty($psOutput)) {
            $json = implode('', $psOutput);
            $data = json_decode($json, true);
            
            if ($data && isset($data['Used']) && isset($data['Free'])) {
                $used = (float)$data['Used'];
                $free = (float)$data['Free'];
                $total = $used + $free;
                
                if ($total > 0) {
                    $totalGB = round($total / 1024 / 1024 / 1024, 2);
                    $freeGB = round($free / 1024 / 1024 / 1024, 2);
                    $usedGB = round($used / 1024 / 1024 / 1024, 2);
                    $usagePercent = round(($used / $total) * 100, 2);
                    
                    return [
                        'usage' => $usagePercent,
                        'total_gb' => $totalGB,
                        'used_gb' => $usedGB,
                        'free_gb' => $freeGB
                    ];
                }
            }
        }
        
        // Method 3: Try disk_free_space() PHP function (last resort)
        $totalSpace = disk_total_space($drive);
        $freeSpace = disk_free_space($drive);
        
        if ($totalSpace && $freeSpace) {
            $totalGB = round($totalSpace / 1024 / 1024 / 1024, 2);
            $freeGB = round($freeSpace / 1024 / 1024 / 1024, 2);
            $usedGB = round(($totalSpace - $freeSpace) / 1024 / 1024 / 1024, 2);
            $usagePercent = round((($totalSpace - $freeSpace) / $totalSpace) * 100, 2);
            
            return [
                'usage' => $usagePercent,
                'total_gb' => $totalGB,
                'used_gb' => $usedGB,
                'free_gb' => $freeGB
            ];
        }
        
    } catch (Exception $e) {
        error_log("Disk exception: " . $e->getMessage());
    }
    
    return null;
}

// ========================================
// NEW: NETWORK TRAFFIC MONITORING
// ========================================
// ========================================
// NEW: NETWORK TRAFFIC MONITORING (FIXED - Using netstat)
// ========================================
function getNetworkTraffic() {
    if (!ENABLE_NETWORK_TRAFFIC) return null;
    
    try {
        // Method 1: Try netstat -e (most reliable)
        exec('netstat -e 2>&1', $output, $status);
        
        if ($status === 0 && !empty($output)) {
            $bytesSent = 0;
            $bytesReceived = 0;
            
            foreach ($output as $line) {
                // Look for: Bytes           12345678    98765432
                if (preg_match('/Bytes\s+(\d+)\s+(\d+)/', $line, $matches)) {
                    $bytesReceived = (int)$matches[1];
                    $bytesSent = (int)$matches[2];
                    break;
                }
            }
            
            if ($bytesSent > 0 || $bytesReceived > 0) {
                $currentTime = microtime(true);
                
                // Calculate speed if we have previous values
                if ($GLOBALS['last_bytes']['time'] > 0) {
                    $timeDiff = $currentTime - $GLOBALS['last_bytes']['time'];
                    
                    if ($timeDiff > 0) {
                        $sentDiff = $bytesSent - $GLOBALS['last_bytes']['sent'];
                        $receivedDiff = $bytesReceived - $GLOBALS['last_bytes']['received'];
                        
                        // Convert to MB/s
                        $uploadSpeed = round(($sentDiff / $timeDiff) / 1024 / 1024, 2);
                        $downloadSpeed = round(($receivedDiff / $timeDiff) / 1024 / 1024, 2);
                        
                        // Store current values for next calculation
                        $GLOBALS['last_bytes'] = [
                            'sent' => $bytesSent,
                            'received' => $bytesReceived,
                            'time' => $currentTime
                        ];
                        
                        return [
                            'upload_speed' => max(0, $uploadSpeed),      // MB/s
                            'download_speed' => max(0, $downloadSpeed),  // MB/s
                            'total_speed' => max(0, $uploadSpeed + $downloadSpeed)
                        ];
                    }
                }
                
                // First run - just store values
                $GLOBALS['last_bytes'] = [
                    'sent' => $bytesSent,
                    'received' => $bytesReceived,
                    'time' => $currentTime
                ];
                
                return [
                    'upload_speed' => 0,
                    'download_speed' => 0,
                    'total_speed' => 0
                ];
            }
        }
        
    } catch (Exception $e) {
        error_log("Network traffic exception: " . $e->getMessage());
    }
    
    return null;
}


// ========================================
// NEW: LATENCY/PING MONITORING
// ========================================
function getLatency() {
    if (!ENABLE_LATENCY) return null;
    
    try {
        $host = LATENCY_HOST;
        
        // Use ping command (Windows)
        exec("ping -n 1 -w 1000 $host 2>&1", $output, $status);
        
        if ($status === 0 && !empty($output)) {
            foreach ($output as $line) {
                // Look for "time=XXms" or "time<1ms"
                if (preg_match('/time[=<](\d+)ms/i', $line, $matches)) {
                    $latency = (float)$matches[1];
                    
                    return [
                        'latency_ms' => $latency,
                        'host' => $host,
                        'status' => 'online'
                    ];
                }
                // Alternative format: "Average = XXms"
                if (preg_match('/Average\s*=\s*(\d+)ms/i', $line, $matches)) {
                    $latency = (float)$matches[1];
                    
                    return [
                        'latency_ms' => $latency,
                        'host' => $host,
                        'status' => 'online'
                    ];
                }
            }
        }
        
        // If ping failed, mark as offline
        return [
            'latency_ms' => 999,
            'host' => $host,
            'status' => 'offline'
        ];
        
    } catch (Exception $e) {
        error_log("Latency exception: " . $e->getMessage());
        return [
            'latency_ms' => 999,
            'host' => LATENCY_HOST,
            'status' => 'error'
        ];
    }
}

// ========================================
// THRESHOLD LOGIC
// ========================================
function shouldCaptureMetrics($metrics) {
    global $GLOBALS;
    
    $currentTime = time();
    $lastCapture = $GLOBALS['last_capture_time'];
    
    // DISABLED:     // Force capture after interval
    // DISABLED:     if ($currentTime - $lastCapture >= FORCE_CAPTURE_INTERVAL) {
    // DISABLED:         $GLOBALS['last_capture_time'] = $currentTime;
    // DISABLED:         return ['should_capture' => true, 'reason' => 'Forced capture interval reached'];
    // DISABLED:     }
    // DISABLED:     
    // Check CPU threshold
    if (!isset($GLOBALS['last_values']['cpu'])) {
        return ['should_capture' => true, 'reason' => 'First capture'];
    }
    
    $cpuChange = abs($metrics['cpu'] - $GLOBALS['last_values']['cpu']);
    if ($cpuChange >= CPU_THRESHOLD) {
        $GLOBALS['last_capture_time'] = $currentTime;
        return ['should_capture' => true, 'reason' => sprintf('CPU changed by %.1f%%', $cpuChange)];
    }
    
    // Check RAM threshold
    $ramChange = abs($metrics['ram']['usage'] - $GLOBALS['last_values']['ram']);
    if ($ramChange >= RAM_THRESHOLD) {
        $GLOBALS['last_capture_time'] = $currentTime;
        return ['should_capture' => true, 'reason' => sprintf('RAM changed by %.1f%%', $ramChange)];
    }
    
    // Check network traffic threshold
    if ($metrics['network'] && isset($GLOBALS['last_values']['network'])) {
        $networkChange = abs($metrics['network']['total_speed'] - $GLOBALS['last_values']['network']);
        if ($networkChange >= NETWORK_THRESHOLD) {
            $GLOBALS['last_capture_time'] = $currentTime;
            return ['should_capture' => true, 'reason' => sprintf('Network traffic changed by %.2f MB/s', $networkChange)];
        }
    }
    
    // Check latency threshold
    if ($metrics['latency'] && isset($GLOBALS['last_values']['latency'])) {
        $latencyChange = abs($metrics['latency']['latency_ms'] - $GLOBALS['last_values']['latency']);
        if ($latencyChange >= LATENCY_THRESHOLD) {
            $GLOBALS['last_capture_time'] = $currentTime;
            return ['should_capture' => true, 'reason' => sprintf('Latency changed by %.0f ms', $latencyChange)];
        }
    }
    
    // Check disk threshold
    if ($metrics['disk'] && isset($GLOBALS['last_values']['disk'])) {
        $diskChange = abs($metrics['disk']['usage'] - $GLOBALS['last_values']['disk']);
        if ($diskChange >= DISK_THRESHOLD) {
            $GLOBALS['last_capture_time'] = $currentTime;
            return ['should_capture' => true, 'reason' => sprintf('Disk usage changed by %.1f%%', $diskChange)];
        }
    }
    
    return ['should_capture' => false, 'reason' => 'No significant changes'];
}

function updateLastValues($metrics) {
    global $GLOBALS;
    
    $GLOBALS['last_values']['cpu'] = $metrics['cpu'];
    $GLOBALS['last_values']['ram'] = $metrics['ram']['usage'];
    
    if ($metrics['network']) {
        $GLOBALS['last_values']['network'] = $metrics['network']['total_speed'];
    }
    
    if ($metrics['latency']) {
        $GLOBALS['last_values']['latency'] = $metrics['latency']['latency_ms'];
    }
    
    if ($metrics['disk']) {
        $GLOBALS['last_values']['disk'] = $metrics['disk']['usage'];
    }
}

function checkMonitoringTables() {
    try {
        $db = getDB();
        
        $tables = ['system_metrics', 'devices', 'system_logs'];
        $allExist = true;
        
        foreach ($tables as $table) {
            $result = $db->query("SHOW TABLES LIKE '$table'");
            if (!$result || $result->num_rows == 0) {
                echo "❌ Table '$table' not found!\n";
                $allExist = false;
            }
        }
        
        return $allExist;
    } catch (Exception $e) {
        echo "❌ Database error: " . $e->getMessage() . "\n";
        return false;
    }
}

function getOrCreateLocalhostDevice() {
    try {
        $db = getDB();
        $localIP = getLocalIP();
        $networkInfo = getNetworkInfo();
        $networkRange = $networkInfo['range'];
        
        $safeIP = $db->real_escape_string($localIP);
        $safeRange = $db->real_escape_string($networkRange);
        
        $existing = $db->query("SELECT id FROM devices WHERE ip_address='$safeIP' AND network_range='$safeRange' LIMIT 1");
        
        if ($existing && $existing->num_rows > 0) {
            return $existing->fetch_assoc()['id'];
        }
        
        $hostname = gethostname();
        $safeName = $db->real_escape_string($hostname ?: "Localhost-Monitor");
        $mac = getMacFromIP($localIP);
        $safeMac = $db->real_escape_string($mac);
        
        $db->query("INSERT INTO devices (name, ip_address, mac_address, network_range, device_type, status, created_at, last_checked_at, last_seen_at) 
                   VALUES ('$safeName', '$safeIP', '$safeMac', '$safeRange', 'computer', 'online', NOW(), NOW(), NOW())");
        
        $deviceId = $db->insert_id;
        logToSystem($deviceId, 'INFO', 'monitoring', "Network-aware monitoring started for device: $safeName ($safeIP)");
        
        return $deviceId;
    } catch (Exception $e) {
        error_log("Get/Create device exception: " . $e->getMessage());
        return 0;
    }
}

function storeMetrics($deviceId, $metrics) {
    try {
        $db = getDB();
        
        $cpuVal = isset($metrics['cpu']) && $metrics['cpu'] > 0 ? $metrics['cpu'] : 0;
        $cpuFreqVal = isset($metrics['cpu_freq']['current_ghz']) && $metrics['cpu_freq']['current_ghz'] > 0 ? $metrics['cpu_freq']['current_ghz'] : 'NULL';
        $ramVal = isset($metrics['ram']['usage']) && $metrics['ram']['usage'] > 0 ? $metrics['ram']['usage'] : 0;
        $ramUsedMB = isset($metrics['ram']['used_mb']) && $metrics['ram']['used_mb'] > 0 ? $metrics['ram']['used_mb'] : 0;
        $ramTotalMB = isset($metrics['ram']['total_mb']) && $metrics['ram']['total_mb'] > 0 ? $metrics['ram']['total_mb'] : 0;
        $ramUsedGB = isset($metrics['ram']['used_gb']) && $metrics['ram']['used_gb'] > 0 ? $metrics['ram']['used_gb'] : 'NULL';
        $ramTotalGB = isset($metrics['ram']['total_gb']) && $metrics['ram']['total_gb'] > 0 ? $metrics['ram']['total_gb'] : 'NULL';
        
        // NEW: Network traffic metrics
        $uploadSpeed = 'NULL';
        $downloadSpeed = 'NULL';
        $totalSpeed = 'NULL';
        if ($metrics['network'] !== null && is_array($metrics['network'])) {
            $uploadSpeed = $metrics['network']['upload_speed'];
            $downloadSpeed = $metrics['network']['download_speed'];
            $totalSpeed = $metrics['network']['total_speed'];
        }
        
        // NEW: Latency metrics
        $latencyMs = 'NULL';
        $latencyHost = 'NULL';
        if ($metrics['latency'] !== null && is_array($metrics['latency'])) {
            $latencyMs = $metrics['latency']['latency_ms'];
            $latencyHost = "'" . $db->real_escape_string($metrics['latency']['host']) . "'";
        }
        
        $diskUsageVal = 'NULL';
        $diskUsedGB = 'NULL';
        $diskTotalGB = 'NULL';
        
        if ($metrics['disk'] !== null && is_array($metrics['disk'])) {
            $diskUsageVal = $metrics['disk']['usage'];
            $diskUsedGB = $metrics['disk']['used_gb'];
            $diskTotalGB = $metrics['disk']['total_gb'];
        }
        
        // UPDATED SQL with new columns
        $sql = "INSERT INTO system_metrics (
            device_id, cpu_usage, cpu_frequency, ram_usage, ram_used_mb, ram_total_mb, 
            ram_used_gb, ram_total_gb, network_upload_speed, network_download_speed, 
            network_total_speed, latency_ms, latency_host, disk_usage, disk_used_gb, 
            disk_total_gb, recorded_at
        ) VALUES (
            $deviceId, $cpuVal, $cpuFreqVal, $ramVal, $ramUsedMB, $ramTotalMB, 
            $ramUsedGB, $ramTotalGB, $uploadSpeed, $downloadSpeed, $totalSpeed, 
            $latencyMs, $latencyHost, $diskUsageVal, $diskUsedGB, $diskTotalGB, NOW()
        )";
        
        $success = $db->query($sql);
        
        if (!$success) {
            $error = $db->error;
            error_log("SQL Error: " . $error);
            echo "❌ Database error: $error\n";
        }
        
        return $success;
    } catch (Exception $e) {
        error_log("Store metrics exception: " . $e->getMessage());
        echo "❌ Exception: " . $e->getMessage() . "\n";
        logToSystem($deviceId, 'ERROR', 'monitoring', "Failed to store metrics: " . $e->getMessage());
        return false;
    }
}

// WEB TEST MODE
if (php_sapi_name() !== 'cli') {
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n   ENHANCED MONITOR v2.0 - TEST\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    echo "🔍 Threshold Configuration:\n";
    echo "   CPU Threshold: " . CPU_THRESHOLD . "%\n";
    echo "   RAM Threshold: " . RAM_THRESHOLD . "%\n";
    echo "   Network Threshold: " . NETWORK_THRESHOLD . " MB/s\n";
    echo "   Latency Threshold: " . LATENCY_THRESHOLD . " ms\n";
    echo "   Disk Threshold: " . DISK_THRESHOLD . "%\n";
    echo "   Force Capture: DISABLED (Pure threshold mode)\n\n";
    
    if (!checkMonitoringTables()) exit(1);
    
    detectWMIC();
    $deviceId = getOrCreateLocalhostDevice();
    
    $metrics = [
        'cpu' => getCPUUsage(),
        'cpu_freq' => getCPUFrequency(),
        'ram' => getRAMUsage(),
        'network' => getNetworkTraffic(),
        'latency' => getLatency(),
        'disk' => getDiskUsage()
    ];
    
    echo "📊 Current Metrics:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "   CPU: " . $metrics['cpu'] . "% @ " . $metrics['cpu_freq']['current_ghz'] . " GHz\n";
    echo "   RAM: " . $metrics['ram']['usage'] . "% (" . $metrics['ram']['used_gb'] . " GB / " . $metrics['ram']['total_gb'] . " GB)\n";
    
    if ($metrics['network']) {
        echo "   Network: ↑ " . $metrics['network']['upload_speed'] . " MB/s | ↓ " . $metrics['network']['download_speed'] . " MB/s\n";
    }
    
    if ($metrics['latency']) {
        echo "   Latency: " . $metrics['latency']['latency_ms'] . " ms (to " . $metrics['latency']['host'] . ")\n";
    }
    
    if ($metrics['disk']) {
        echo "   Disk: " . $metrics['disk']['usage'] . "% (" . $metrics['disk']['used_gb'] . " GB / " . $metrics['disk']['total_gb'] . " GB)\n";
    }
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    echo "💾 Testing threshold logic...\n";
    $decision = shouldCaptureMetrics($metrics);
    echo "Decision: " . ($decision['should_capture'] ? '✅ CAPTURE' : '⏭️  SKIP') . " - " . $decision['reason'] . "\n\n";
    
    echo storeMetrics($deviceId, $metrics) ? "✅ Metrics stored successfully!\n" : "❌ Failed to store metrics\n";
    exit(0);
}

// CLI MODE - THRESHOLD-BASED MONITORING
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "   ENHANCED SYSTEM MONITOR v2.0\n";
echo "   Network Traffic + Latency Monitoring\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
echo "⚙️  Configuration:\n";
echo "   Check Interval: " . CHECK_INTERVAL . " seconds (lightweight)\n";
echo "   CPU Threshold: " . CPU_THRESHOLD . "%\n";
echo "   RAM Threshold: " . RAM_THRESHOLD . "%\n";
echo "   Network Threshold: " . NETWORK_THRESHOLD . " MB/s\n";
echo "   Latency Threshold: " . LATENCY_THRESHOLD . " ms\n";
echo "   Latency Host: " . LATENCY_HOST . "\n";
echo "   Disk Threshold: " . DISK_THRESHOLD . "%\n";
echo "   Force Capture: DISABLED (Pure threshold mode)\n\n";

if (!checkMonitoringTables()) exit(1);
echo "✅ All monitoring tables verified\n\n";

detectWMIC();
$deviceId = getOrCreateLocalhostDevice();
$loop = 0;
$captures = 0;
$skips = 0;

logToSystem($deviceId, 'INFO', 'system', "Enhanced monitoring started - Network Traffic + Latency enabled");

echo "🚀 Monitoring started...\n\n";

while (true) {
    $loop++;
    $loopStart = microtime(true);
    
    $metrics = [
        'cpu' => getCPUUsage(),
        'cpu_freq' => getCPUFrequency(),
        'ram' => getRAMUsage(),
        'network' => getNetworkTraffic(),
        'latency' => getLatency(),
        'disk' => getDiskUsage()
    ];
    
    // Check if we should capture
    $decision = shouldCaptureMetrics($metrics);
    
    if ($decision['should_capture']) {
        $captures++;
        echo "Check #$loop [" . date('H:i:s') . "] - ✅ CAPTURED\n";
        echo "   CPU: " . $metrics['cpu'] . "% | RAM: " . $metrics['ram']['usage'] . "%";
        
        if ($metrics['network']) {
            echo " | Net: ↑" . $metrics['network']['upload_speed'] . " ↓" . $metrics['network']['download_speed'] . " MB/s";
        }
        
        if ($metrics['latency']) {
            echo " | Ping: " . $metrics['latency']['latency_ms'] . "ms";
        }
        
        echo "\n";
        echo "   Reason: " . $decision['reason'] . "\n";
        
        if (storeMetrics($deviceId, $metrics)) {
            updateLastValues($metrics);
            
            if (ENABLE_DETAILED_LOGGING) {
                logToSystem($deviceId, 'INFO', 'monitoring', "Metrics captured - " . $decision['reason']);
            }
        } else {
            echo "   ❌ Failed to store\n";
        }
        echo "\n";
    } else {
        $skips++;
        if ($loop % 12 == 0) { // Show skip status every minute
            echo "Check #$loop [" . date('H:i:s') . "] - ⏭️  Skipped (no significant change) - CPU: " . $metrics['cpu'] . "%, RAM: " . $metrics['ram']['usage'] . "%";
            
            if ($metrics['network']) {
                echo ", Net: " . $metrics['network']['total_speed'] . " MB/s";
            }
            
            if ($metrics['latency']) {
                echo ", Ping: " . $metrics['latency']['latency_ms'] . "ms";
            }
            
            echo "\n";
            echo "   Stats: " . $captures . " captured, " . $skips . " skipped (" . round(($skips/($captures+$skips))*100, 1) . "% resource savings)\n\n";
        }
    }
    
    $elapsed = microtime(true) - $loopStart;
    $sleep = max(0, CHECK_INTERVAL - $elapsed);
    
    sleep((int)$sleep);
}
?>