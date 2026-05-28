<?php
set_time_limit(0);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '1G'); // ✅ INCREASED from 256M to 1G

// ========================================
// KEEP-ALIVE MONITORING SYSTEM
// ========================================

// PID and Heartbeat files for KEEP-ALIVE monitoring
$runtimeDir = __DIR__ . '/runtime';
if (!is_dir($runtimeDir)) { mkdir($runtimeDir, 0777, true); }
$pidFile = $runtimeDir . '/system_monitor.pid';
$heartbeatFile = $runtimeDir . '/system_monitor_heartbeat.txt';


// Write PID on start
file_put_contents($pidFile, getmypid() . "\n" . date('Y-m-d H:i:s'));

// Update heartbeat function
function updateHeartbeat() {
    global $heartbeatFile;
    file_put_contents($heartbeatFile, time());
}

// Register shutdown function to cleanup
register_shutdown_function(function() use ($pidFile, $heartbeatFile) {
    @unlink($pidFile);
    @unlink($heartbeatFile);
    echo "\n╔════════════════════════════════════════════════════════════════╗\n";
    echo "║        SYSTEM MONITOR STOPPED                                  ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n";
});

require_once 'config.php';

// Global state
$GLOBALS['WMIC_AVAILABLE'] = false;
$GLOBALS['last_values'] = [];
$GLOBALS['last_capture_time'] = 0;

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║        SYSTEM MONITOR - PowerShell Safe (1GB Memory)           ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// ========================================
// LOGGING SYSTEM
// ========================================

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

// ========================================
// WMIC DETECTION
// ========================================

function detectWMIC() {
    static $checked = false;
    static $available = false;
    
    if ($checked) return $available;
    
    $checked = true;
    exec('wmic os get caption 2>&1', $output, $status);
    $available = ($status === 0 && !empty($output));
    $GLOBALS['WMIC_AVAILABLE'] = $available;
    
    echo $available ? "✓ WMIC available\n" : "⚠ WMIC not available, using PowerShell\n";
    
    return $available;
}

// ========================================
// CPU MONITORING
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
            exec('wmic cpu get CurrentClockSpeed /value 2>&1', $output, $status);
            if ($status === 0) {
                foreach ($output as $line) {
                    if (strpos($line, 'CurrentClockSpeed') !== false) {
                        preg_match('/CurrentClockSpeed=(\d+)/', $line, $matches);
                        if (isset($matches[1])) {
                            return round((int)$matches[1] / 1000, 2);
                        }
                    }
                }
            }
        }
        
        exec('powershell -Command "Get-WmiObject Win32_Processor | Select-Object -ExpandProperty CurrentClockSpeed" 2>&1', $output2);
        if (!empty($output2) && is_numeric(trim($output2[0]))) {
            return round((float)trim($output2[0]) / 1000, 2);
        }
    } catch (Exception $e) {
        error_log("CPU frequency exception: " . $e->getMessage());
    }
    
    return 0.0;
}

// ========================================
// RAM MONITORING
// ========================================

function getRAMUsage() {
    try {
        if (detectWMIC()) {
            exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /value 2>&1', $output, $status);
            if ($status === 0) {
                $free = null;
                $total = null;
                
                foreach ($output as $line) {
                    if (strpos($line, 'FreePhysicalMemory') !== false) {
                        preg_match('/FreePhysicalMemory=(\d+)/', $line, $matches);
                        if (isset($matches[1])) $free = (float)$matches[1];
                    }
                    if (strpos($line, 'TotalVisibleMemorySize') !== false) {
                        preg_match('/TotalVisibleMemorySize=(\d+)/', $line, $matches);
                        if (isset($matches[1])) $total = (float)$matches[1];
                    }
                }
                
                if ($total !== null && $free !== null && $total > 0) {
                    $used = $total - $free;
                    $percentage = ($used / $total) * 100;
                    
                    return [
                        'percentage' => round($percentage, 2),
                        'used_gb' => round($used / 1024 / 1024, 2),
                        'total_gb' => round($total / 1024 / 1024, 2)
                    ];
                }
            }
        }
    } catch (Exception $e) {
        error_log("RAM exception: " . $e->getMessage());
    }
    
    return ['percentage' => 0.0, 'used_gb' => 0.0, 'total_gb' => 0.0];
}

// ========================================
// NETWORK MONITORING - HYBRID METHOD
// ========================================

function getNetworkTrafficTaskManager() {
    static $adapterName = null;
    static $lastBytes = ['sent' => 0, 'received' => 0, 'time' => 0];
    static $methodUsed = null;
    
    try {
        // Get active adapter name (cache it)
        if ($adapterName === null) {
            exec('powershell -Command "Get-NetAdapter | Where-Object {$_.Status -eq \'Up\' -and $_.Virtual -eq $false} | Select-Object -First 1 -ExpandProperty Name"', $adapterOutput, $adapterStatus);
            
            if ($adapterStatus === 0 && !empty($adapterOutput)) {
                $adapterName = trim(implode('', $adapterOutput));
                echo "✓ Using network adapter: $adapterName\n";
            } else {
                return [
                    'upload_speed' => 0.0, 
                    'download_speed' => 0.0, 
                    'total_speed' => 0.0,
                    'link_speed_mbps' => 0,
                    'utilization_percent' => 0.0
                ];
            }
        }
        
        // Try Get-NetAdapterStatistics first
        $tempFile = sys_get_temp_dir() . '/network_monitor_' . getmypid() . '.ps1';
        
        $psScript = <<<POWERSHELL
\$ErrorActionPreference = 'SilentlyContinue'
\$adapter = Get-NetAdapter -Name '{$adapterName}' -ErrorAction SilentlyContinue
if (\$adapter) {
    \$stats = Get-NetAdapterStatistics -Name '{$adapterName}' -ErrorAction SilentlyContinue
    \$linkSpeed = \$adapter.LinkSpeed
    if (\$stats) {
        \$sent = \$stats.SentBytes
        \$received = \$stats.ReceivedBytes
        Write-Output "\$sent|\$received|\$linkSpeed"
    }
}
POWERSHELL;
        
        file_put_contents($tempFile, $psScript);
        exec("powershell -ExecutionPolicy Bypass -File \"$tempFile\" 2>&1", $output, $status);
        @unlink($tempFile);
        
        if ($status === 0 && !empty($output)) {
            $result = explode('|', trim(implode('', $output)));
            
            if (count($result) >= 3) {
                $currentSent = floatval($result[0]);
                $currentReceived = floatval($result[1]);
                $linkSpeedStr = trim($result[2]);
                
                // Parse link speed
                $linkSpeedMbps = 0;
                if (preg_match('/(\d+(?:\.\d+)?)\s*(Gbps|Mbps)/i', $linkSpeedStr, $matches)) {
                    $speed = floatval($matches[1]);
                    $unit = strtolower($matches[2]);
                    $linkSpeedMbps = ($unit === 'gbps') ? ($speed * 1000) : $speed;
                }
                
                $currentTime = microtime(true);
                
                // Calculate speeds if we have previous data
                if ($lastBytes['time'] > 0) {
                    $timeDiff = $currentTime - $lastBytes['time'];
                    
                    if ($timeDiff > 0) {
                        $sentDiff = $currentSent - $lastBytes['sent'];
                        $receivedDiff = $currentReceived - $lastBytes['received'];
                        
                        // Handle counter resets
                        if ($sentDiff < 0) $sentDiff = $currentSent;
                        if ($receivedDiff < 0) $receivedDiff = $currentReceived;
                        
                        // Bytes per second
                        $bytesSentPerSec = $sentDiff / $timeDiff;
                        $bytesReceivedPerSec = $receivedDiff / $timeDiff;
                        
                        // Convert to MB/s
                        $uploadMBps = $bytesSentPerSec / 1024 / 1024;
                        $downloadMBps = $bytesReceivedPerSec / 1024 / 1024;
                        $totalMBps = $uploadMBps + $downloadMBps;
                        
                        // Network utilization
                        $utilizationPercent = 0.0;
                        if ($linkSpeedMbps > 0) {
                            $totalBps = $bytesSentPerSec + $bytesReceivedPerSec;
                            $linkSpeedBps = $linkSpeedMbps * 1000000 / 8;
                            $utilizationPercent = ($totalBps / $linkSpeedBps) * 100;
                        }
                        
                        // Update last values
                        $lastBytes = [
                            'sent' => $currentSent,
                            'received' => $currentReceived,
                            'time' => $currentTime
                        ];
                        
                        if ($methodUsed !== 'Statistics') {
                            echo "✓ Network method: Get-NetAdapterStatistics (ACCURATE)\n";
                            $methodUsed = 'Statistics';
                        }
                        
                        return [
                            'upload_speed' => round($uploadMBps, 3),
                            'download_speed' => round($downloadMBps, 3),
                            'total_speed' => round($totalMBps, 3),
                            'link_speed_mbps' => round($linkSpeedMbps, 0),
                            'utilization_percent' => round($utilizationPercent, 2)
                        ];
                    }
                }
                
                // First run - store initial values
                $lastBytes = [
                    'sent' => $currentSent,
                    'received' => $currentReceived,
                    'time' => $currentTime
                ];
                
                return [
                    'upload_speed' => 0.0,
                    'download_speed' => 0.0,
                    'total_speed' => 0.0,
                    'link_speed_mbps' => round($linkSpeedMbps, 0),
                    'utilization_percent' => 0.0
                ];
            }
        }
        
        // Fallback to Performance Counter
        echo "⚠ Falling back to Performance Counter method\n";
        
        $tempFile2 = sys_get_temp_dir() . '/network_monitor_pc_' . getmypid() . '.ps1';
        
        $psScript2 = <<<POWERSHELL
\$ErrorActionPreference = 'SilentlyContinue'
\$adapterName = '{$adapterName}'

\$counters = Get-Counter -Counter "\\Network Interface(\$adapterName)\\Bytes Sent/sec","\\Network Interface(\$adapterName)\\Bytes Received/sec","\\Network Interface(\$adapterName)\\Current Bandwidth" -SampleInterval 1 -MaxSamples 1

if (\$counters) {
    \$sent = [math]::Round(\$counters.CounterSamples[0].CookedValue, 2)
    \$received = [math]::Round(\$counters.CounterSamples[1].CookedValue, 2)
    \$bandwidth = [math]::Round(\$counters.CounterSamples[2].CookedValue, 2)
    Write-Output "\$sent|\$received|\$bandwidth"
}
POWERSHELL;
        
        file_put_contents($tempFile2, $psScript2);
        exec("powershell -ExecutionPolicy Bypass -File \"$tempFile2\" 2>&1", $output2, $status2);
        @unlink($tempFile2);
        
        if ($status2 === 0 && !empty($output2)) {
            $result2 = explode('|', trim(implode('', $output2)));
            
            if (count($result2) >= 3) {
                $bytesSentPerSec = floatval($result2[0]);
                $bytesReceivedPerSec = floatval($result2[1]);
                $bandwidthBps = floatval($result2[2]);
                
                // Convert to MB/s
                $uploadMBps = $bytesSentPerSec / 1024 / 1024;
                $downloadMBps = $bytesReceivedPerSec / 1024 / 1024;
                $totalMBps = $uploadMBps + $downloadMBps;
                
                // Link speed in Mbps
                $linkSpeedMbps = $bandwidthBps > 0 ? round($bandwidthBps / 1000000, 0) : 0;
                
                // Network utilization percentage
                $utilizationPercent = 0.0;
                if ($bandwidthBps > 0) {
                    $totalBps = $bytesSentPerSec + $bytesReceivedPerSec;
                    $utilizationPercent = ($totalBps / $bandwidthBps) * 100;
                }
                
                if ($methodUsed !== 'PerfCounter') {
                    echo "✓ Network method: Performance Counter (FALLBACK)\n";
                    $methodUsed = 'PerfCounter';
                }
                
                return [
                    'upload_speed' => round($uploadMBps, 3),
                    'download_speed' => round($downloadMBps, 3),
                    'total_speed' => round($totalMBps, 3),
                    'link_speed_mbps' => $linkSpeedMbps,
                    'utilization_percent' => round($utilizationPercent, 2)
                ];
            }
        }
        
    } catch (Exception $e) {
        error_log("Network traffic exception: " . $e->getMessage());
    }
    
    return [
        'upload_speed' => 0.0, 
        'download_speed' => 0.0, 
        'total_speed' => 0.0,
        'link_speed_mbps' => 0,
        'utilization_percent' => 0.0
    ];
}

// ========================================
// LATENCY MONITORING
// ========================================

function getLatency() {
    $host = LATENCY_HOST;
    
    try {
        $startTime = microtime(true);
        exec("ping -n 1 -w 1000 $host 2>&1", $output, $status);
        $endTime = microtime(true);
        
        if ($status === 0) {
            foreach ($output as $line) {
                if (preg_match('/time[=<](\d+)ms/i', $line, $matches)) {
                    return [
                        'latency_ms' => (float)$matches[1],
                        'latency_host' => $host
                    ];
                }
            }
            
            $latency = round(($endTime - $startTime) * 1000, 1);
            if ($latency < 5000) {
                return [
                    'latency_ms' => $latency,
                    'latency_host' => $host
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Latency exception: " . $e->getMessage());
    }
    
    return ['latency_ms' => null, 'latency_host' => $host];
}

// ========================================
// DISK MONITORING
// ========================================

function getDiskUsage() {
    try {
        if (detectWMIC()) {
            exec('wmic logicaldisk where "DeviceID=\'C:\'" get FreeSpace,Size /value 2>&1', $output, $status);
            if ($status === 0) {
                $free = null;
                $total = null;
                
                foreach ($output as $line) {
                    if (strpos($line, 'FreeSpace') !== false) {
                        preg_match('/FreeSpace=(\d+)/', $line, $matches);
                        if (isset($matches[1])) $free = (float)$matches[1];
                    }
                    if (strpos($line, 'Size') !== false && strpos($line, 'FreeSpace') === false) {
                        preg_match('/Size=(\d+)/', $line, $matches);
                        if (isset($matches[1])) $total = (float)$matches[1];
                    }
                }
                
                if ($total !== null && $free !== null && $total > 0) {
                    $used = $total - $free;
                    $percentage = ($used / $total) * 100;
                    
                    return [
                        'percentage' => round($percentage, 2),
                        'used_gb' => round($used / 1024 / 1024 / 1024, 2),
                        'total_gb' => round($total / 1024 / 1024 / 1024, 2)
                    ];
                }
            }
        }
    } catch (Exception $e) {
        error_log("Disk exception: " . $e->getMessage());
    }
    
    return ['percentage' => 0.0, 'used_gb' => 0.0, 'total_gb' => 0.0];
}

// ========================================
// TEMPERATURE MONITORING - MULTI-METHOD
// ========================================

function getTemperatureLibreHardware() {
    static $available = null;
    
    if ($available === false) return null;
    
    try {
        exec('powershell -Command "Get-WmiObject -Namespace root/LibreHardwareMonitor -Class Sensor -ErrorAction SilentlyContinue | Where-Object {$_.SensorType -eq \'Temperature\' -and ($_.Name -like \'*Package*\' -or $_.Name -like \'*CPU*\')} | Select-Object -First 1 -ExpandProperty Value" 2>&1', $output, $status);
        
        if ($status === 0 && !empty($output) && is_numeric(trim($output[0]))) {
            $temp = (float)trim($output[0]);
            if ($temp > 0 && $temp < 150) {
                if ($available === null) {
                    echo "✓ Temperature: LibreHardwareMonitor\n";
                }
                $available = true;
                return round($temp, 1);
            }
        }
    } catch (Exception $e) {
        // Silent fail
    }
    
    $available = false;
    return null;
}

function getTemperatureOpenHardware() {
    static $available = null;
    
    if ($available === false) return null;
    
    try {
        exec('powershell -Command "Get-WmiObject -Namespace root/OpenHardwareMonitor -Class Sensor -ErrorAction SilentlyContinue | Where-Object {$_.SensorType -eq \'Temperature\' -and $_.Name -like \'*CPU*\'} | Select-Object -First 1 -ExpandProperty Value" 2>&1', $output, $status);
        
        if ($status === 0 && !empty($output) && is_numeric(trim($output[0]))) {
            $temp = (float)trim($output[0]);
            if ($temp > 0 && $temp < 150) {
                if ($available === null) {
                    echo "✓ Temperature: OpenHardwareMonitor\n";
                }
                $available = true;
                return round($temp, 1);
            }
        }
    } catch (Exception $e) {
        // Silent fail
    }
    
    $available = false;
    return null;
}

function getTemperatureWMIC() {
    static $available = null;
    
    if ($available === false) return null;
    
    try {     
        exec('wmic /namespace:\\\\root\\wmi PATH MSAcpi_ThermalZoneTemperature get CurrentTemperature /value 2>&1', $output, $status);
        
        if ($status === 0) {
            foreach ($output as $line) {
                if (strpos($line, 'CurrentTemperature') !== false) {
                    preg_match('/CurrentTemperature=(\d+)/', $line, $matches);
                    if (isset($matches[1])) {
                        $kelvin = (int)$matches[1];
                        $celsius = ($kelvin / 10) - 273.15;
                        if ($celsius > 0 && $celsius < 150) {
                            if ($available === null) {
                                echo "✓ Temperature: WMIC/ACPI\n";
                            }
                            $available = true;
                            return round($celsius, 1);
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Silent fail
    }
    
    $available = false;
    return null;
}

function getTemperature() {
    static $method = null;
    static $noTempWarningShown = false;
    
    if ($method === 'libre') {
        $temp = getTemperatureLibreHardware();
        if ($temp !== null) return $temp;
        $method = null;
    } elseif ($method === 'open') {
        $temp = getTemperatureOpenHardware();
        if ($temp !== null) return $temp;
        $method = null;
    } elseif ($method === 'wmic') {
        $temp = getTemperatureWMIC();
        if ($temp !== null) return $temp;
        $method = null;
    }
    
    $temp = getTemperatureLibreHardware();
    if ($temp !== null) {
        $method = 'libre';
        return $temp;
    }
    
    $temp = getTemperatureOpenHardware();
    if ($temp !== null) {
        $method = 'open';
        return $temp;
    }
    
    $temp = getTemperatureWMIC();
    if ($temp !== null) {
        $method = 'wmic';
        return $temp;
    }
    
    if (!$noTempWarningShown) {
        echo "⚠ Temperature: Not available (install LibreHardwareMonitor)\n";
        $noTempWarningShown = true;
    }
    
    return null;
}

// ========================================
// DATABASE FUNCTIONS
// ========================================

function initializeDatabase() {
    $db = getDB();
    
    // Check if system_metrics has device_id column
    $result = $db->query("SHOW COLUMNS FROM system_metrics LIKE 'device_id'");
    $hasDeviceId = ($result && $result->num_rows > 0);
    
    if ($hasDeviceId) {
        echo "⚠ Removing device_id foreign key constraint...\n";
        $db->query("ALTER TABLE system_metrics DROP FOREIGN KEY IF EXISTS system_metrics_ibfk_1");
        $db->query("ALTER TABLE system_metrics DROP COLUMN device_id");
        echo "✓ device_id removed\n";
    }
    
    $db->query("CREATE TABLE IF NOT EXISTS system_metrics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cpu_usage DECIMAL(5,2),
        cpu_frequency DECIMAL(5,2),
        ram_usage DECIMAL(5,2),
        ram_used_gb DECIMAL(10,2),
        ram_total_gb DECIMAL(10,2),
        network_upload_speed DECIMAL(10,3),
        network_download_speed DECIMAL(10,3),
        network_total_speed DECIMAL(10,3),
        network_link_speed INT DEFAULT 0,
        network_utilization DECIMAL(5,2) DEFAULT 0.00,
        latency_ms DECIMAL(10,2),
        latency_host VARCHAR(255),
        disk_usage DECIMAL(5,2),
        disk_used_gb DECIMAL(10,2),
        disk_total_gb DECIMAL(10,2),
        temperature DECIMAL(5,2),
        recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_recorded_at (recorded_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    echo "✓ Database tables verified\n\n";
}

function shouldCaptureMetrics($currentMetrics) {
    static $lastMetrics = null;
    
    if ($lastMetrics === null) {
        $lastMetrics = $currentMetrics;
        return true;
    }
    
    $cpuChanged = abs($currentMetrics['cpu_usage'] - $lastMetrics['cpu_usage']) >= CPU_THRESHOLD;
    $ramChanged = abs($currentMetrics['ram_usage'] - $lastMetrics['ram_usage']) >= RAM_THRESHOLD;
    $networkChanged = abs($currentMetrics['network_total_speed'] - $lastMetrics['network_total_speed']) >= NETWORK_THRESHOLD;
    $latencyChanged = abs(($currentMetrics['latency_ms'] ?? 0) - ($lastMetrics['latency_ms'] ?? 0)) >= LATENCY_THRESHOLD;
    
    if ($cpuChanged || $ramChanged || $networkChanged || $latencyChanged) {
        $lastMetrics = $currentMetrics;
        return true;
    }
    
    $timeSinceLastCapture = time() - $GLOBALS['last_capture_time'];
    if ($timeSinceLastCapture >= 60) {
        $lastMetrics = $currentMetrics;
        $GLOBALS['last_capture_time'] = time();
        return true;
    }
    
    return false;
}

function saveMetrics($metrics) {
    try {
        $db = getDB();
        
        $cpu = $db->real_escape_string($metrics['cpu_usage']);
        $cpuFreq = $db->real_escape_string($metrics['cpu_frequency']);
        $ramUsage = $db->real_escape_string($metrics['ram_usage']);
        $ramUsedGB = $db->real_escape_string($metrics['ram_used_gb']);
        $ramTotalGB = $db->real_escape_string($metrics['ram_total_gb']);
        $networkUp = $db->real_escape_string($metrics['network_upload_speed']);
        $networkDown = $db->real_escape_string($metrics['network_download_speed']);
        $networkTotal = $db->real_escape_string($metrics['network_total_speed']);
        $linkSpeed = $db->real_escape_string($metrics['network_link_speed']);
        $utilization = $db->real_escape_string($metrics['network_utilization']);
        $latency = $metrics['latency_ms'] !== null ? $db->real_escape_string($metrics['latency_ms']) : 'NULL';
        $latencyHost = $metrics['latency_host'] ? "'" . $db->real_escape_string($metrics['latency_host']) . "'" : 'NULL';
        $diskUsage = $db->real_escape_string($metrics['disk_usage']);
        $diskUsedGB = $db->real_escape_string($metrics['disk_used_gb']);
        $diskTotalGB = $db->real_escape_string($metrics['disk_total_gb']);
        $temp = $metrics['temperature'] !== null ? $db->real_escape_string($metrics['temperature']) : 'NULL';
        
        $sql = "INSERT INTO system_metrics (
            cpu_usage, cpu_frequency, ram_usage, ram_used_gb, ram_total_gb,
            network_upload_speed, network_download_speed, network_total_speed,
            network_link_speed, network_utilization,
            latency_ms, latency_host,
            disk_usage, disk_used_gb, disk_total_gb,
            temperature, recorded_at
        ) VALUES (
            '$cpu', '$cpuFreq', '$ramUsage', '$ramUsedGB', '$ramTotalGB',
            '$networkUp', '$networkDown', '$networkTotal',
            '$linkSpeed', '$utilization',
            $latency, $latencyHost,
            '$diskUsage', '$diskUsedGB', '$diskTotalGB',
            $temp, NOW()
        )";
        
        if ($db->query($sql)) {
            return true;
        } else {
            error_log("Database error: " . $db->error);
            return false;
        }
    } catch (Exception $e) {
        error_log("Save metrics exception: " . $e->getMessage());
        return false;
    }
}

// ========================================
// LOG CLEANUP 
// ========================================

function cleanupOldLogs() {
    static $lastCleanup = 0;
    
    // Only run cleanup once per hour
    $now = time();
    if ($now - $lastCleanup < 3600) {
        return;
    }
    
    $lastCleanup = $now;
    
    try {
        $db = getDB();
        
        // Delete logs older than 2 days
        $result = $db->query("DELETE FROM system_logs 
                              WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 DAY)");
        
        if ($result) {
            $deletedCount = $db->affected_rows;
            if ($deletedCount > 0) {
                echo "  🗑️  Cleaned up $deletedCount old log entries (>2 days)\n";
                logToSystem(null, 'INFO', 'maintenance', "Cleaned up $deletedCount old log entries");
            }
        }
    } catch (Exception $e) {
        error_log("Log cleanup exception: " . $e->getMessage());
    }
}

// ========================================
// MAIN MONITORING LOOP
// ========================================

function monitorSystem() {
    initializeDatabase();
    
    echo "Starting monitoring loop...\n";
    echo "PID: " . getmypid() . "\n";
    echo "Memory Limit: " . ini_get('memory_limit') . "\n";
    echo "Update interval: " . CHECK_INTERVAL . " seconds\n";
    echo "Network method: HYBRID (Get-NetAdapterStatistics + Performance Counter)\n";
    echo "Temperature: MULTI-METHOD (LibreHardware/OpenHardware/WMIC)\n";
    echo "Log retention: 2 days (auto-cleanup enabled)\n";
    echo "Press Ctrl+C to stop\n\n";
    
    logToSystem(null, 'INFO', 'monitoring', 'System monitoring started successfully.');
    
    // Run initial cleanup
    cleanupOldLogs();
    
    $iteration = 0;
    $consecutiveErrors = 0;
    
    while (true) {
        try {
            $iteration++;
            $startTime = microtime(true);
            
            // Update heartbeat EVERY iteration 
            updateHeartbeat();
            
            // Run cleanup periodically (every hour)
            if ($iteration % (3600 / CHECK_INTERVAL) == 0) {
                cleanupOldLogs();
            }
            
            echo "[" . date('Y-m-d H:i:s') . "] Iteration #$iteration (PID: " . getmypid() . ")\n";
            
            // ✅ MEMORY USAGE TRACKING
            $memUsed = round(memory_get_usage() / 1024 / 1024, 2);
            $memPeak = round(memory_get_peak_usage() / 1024 / 1024, 2);
            echo "  Memory: {$memUsed}MB / Peak: {$memPeak}MB\n";
            
            // Collect all metrics
            $cpu = getCPUUsage();
            $cpuFreq = getCPUFrequency();
            $ram = getRAMUsage();
            $network = getNetworkTrafficTaskManager();
            $latency = ENABLE_LATENCY ? getLatency() : ['latency_ms' => null, 'latency_host' => null];
            $disk = ENABLE_DISK ? getDiskUsage() : ['percentage' => 0.0, 'used_gb' => 0.0, 'total_gb' => 0.0];
            $temp = getTemperature();
            
            $metrics = [
                'cpu_usage' => $cpu,
                'cpu_frequency' => $cpuFreq,
                'ram_usage' => $ram['percentage'],
                'ram_used_gb' => $ram['used_gb'],
                'ram_total_gb' => $ram['total_gb'],
                'network_upload_speed' => $network['upload_speed'],
                'network_download_speed' => $network['download_speed'],
                'network_total_speed' => $network['total_speed'],
                'network_link_speed' => $network['link_speed_mbps'],
                'network_utilization' => $network['utilization_percent'],
                'latency_ms' => $latency['latency_ms'],
                'latency_host' => $latency['latency_host'],
                'disk_usage' => $disk['percentage'],
                'disk_used_gb' => $disk['used_gb'],
                'disk_total_gb' => $disk['total_gb'],
                'temperature' => $temp
            ];
            
            // Display metrics
            echo sprintf("  CPU: %.1f%% @ %.2f GHz\n", $cpu, $cpuFreq);
            echo sprintf("  RAM: %.1f%% (%.2f GB / %.2f GB)\n", $ram['percentage'], $ram['used_gb'], $ram['total_gb']);
            echo sprintf("  Network: %.3f MB/s (↑%.3f ↓%.3f) | Link: %d Mbps | Util: %.2f%%\n", 
                $network['total_speed'], $network['upload_speed'], $network['download_speed'],
                $network['link_speed_mbps'], $network['utilization_percent']);
            
            if ($latency['latency_ms'] !== null) {
                echo sprintf("  Latency: %.1f ms (%s)\n", $latency['latency_ms'], $latency['latency_host']);
            }
            
            if (ENABLE_DISK) {
                echo sprintf("  Disk: %.2f%% (%.2f GB / %.2f GB)\n", $disk['percentage'], $disk['used_gb'], $disk['total_gb']);
            }
            
            if ($temp !== null) {
                echo sprintf("  Temperature: %.1f°C\n", $temp);
            }
            
            // Save metrics if needed
            if (shouldCaptureMetrics($metrics)) {
                if (saveMetrics($metrics)) {
                    echo "  ✓ Metrics saved to database\n";
                    $consecutiveErrors = 0;
                } else {
                    echo "  ✗ Failed to save metrics\n";
                    $consecutiveErrors++;
                }
            } else {
                echo "  ⊘ Skipped (no significant changes)\n";
            }
            
            $endTime = microtime(true);
            $elapsed = $endTime - $startTime;
            
            echo sprintf("  Execution time: %.2f seconds\n", $elapsed);
            echo sprintf("  Heartbeat updated: %s\n\n", date('H:i:s'));
            
            // ✅ MEMORY WARNING
            if ($memPeak > 800) {
                echo "  ⚠️  WARNING: Memory usage approaching limit! ({$memPeak}MB / 1024MB)\n\n";
                logToSystem(null, 'WARNING', 'monitoring', "High memory usage: {$memPeak}MB");
            }
            
        } catch (Exception $e) {
            echo "ERROR in iteration: " . $e->getMessage() . "\n\n";
            logToSystem(null, 'ERROR', 'monitoring', 'System monitoring iteration error: ' . $e->getMessage());
            $consecutiveErrors++;
            
            if ($consecutiveErrors >= 10) {
                logToSystem(null, 'CRITICAL', 'monitoring', 'System monitor experiencing repeated errors!');
                echo "  ⚠ CRITICAL: {$consecutiveErrors} consecutive errors - but still running!\n\n";
                $consecutiveErrors = 0;
            }
        }
        
        sleep(CHECK_INTERVAL);
    }
}

// ========================================
// START MONITORING
// ========================================

try {
    monitorSystem();
} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    logToSystem(null, 'CRITICAL', 'monitoring', 'System monitoring crashed: ' . $e->getMessage());
    exit(1);
}
?>