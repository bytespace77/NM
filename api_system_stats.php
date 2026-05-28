<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

function getSystemStats() {
    $stats = [];
    
    // Detect OS
    $os = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'Windows' : 'Linux';
    $stats['os'] = $os;
    
    if ($os === 'Windows') {
        $stats = array_merge($stats, getWindowsStats());
    } else {
        $stats = array_merge($stats, getLinuxStats());
    }
    
    return $stats;
}

function getWindowsStats() {
    $stats = [];
    
    // CPU Usage
    try {
        $wmi = "wmic cpu get loadpercentage";
        exec($wmi, $output);
        $cpuUsage = isset($output[1]) ? intval(trim($output[1])) : 0;
        $stats['cpu_usage'] = $cpuUsage;
    } catch (Exception $e) {
        $stats['cpu_usage'] = 0;
    }
    
    // Memory Usage
    try {
        exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value', $output);
        $memory = [];
        foreach ($output as $line) {
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $memory[trim($key)] = trim($value);
            }
        }
        
        $totalMemory = isset($memory['TotalVisibleMemorySize']) ? intval($memory['TotalVisibleMemorySize']) : 0;
        $freeMemory = isset($memory['FreePhysicalMemory']) ? intval($memory['FreePhysicalMemory']) : 0;
        $usedMemory = $totalMemory - $freeMemory;
        
        $stats['memory_total'] = round($totalMemory / 1024 / 1024, 2); // GB
        $stats['memory_used'] = round($usedMemory / 1024 / 1024, 2); // GB
        $stats['memory_free'] = round($freeMemory / 1024 / 1024, 2); // GB
        $stats['memory_percent'] = $totalMemory > 0 ? round(($usedMemory / $totalMemory) * 100, 1) : 0;
    } catch (Exception $e) {
        $stats['memory_total'] = 0;
        $stats['memory_used'] = 0;
        $stats['memory_free'] = 0;
        $stats['memory_percent'] = 0;
    }
    
    // Disk Space (C: drive)
    try {
        $totalSpace = disk_total_space("C:");
        $freeSpace = disk_free_space("C:");
        $usedSpace = $totalSpace - $freeSpace;
        
        $stats['disk_total'] = round($totalSpace / 1024 / 1024 / 1024, 2); // GB
        $stats['disk_used'] = round($usedSpace / 1024 / 1024 / 1024, 2); // GB
        $stats['disk_free'] = round($freeSpace / 1024 / 1024 / 1024, 2); // GB
        $stats['disk_percent'] = $totalSpace > 0 ? round(($usedSpace / $totalSpace) * 100, 1) : 0;
    } catch (Exception $e) {
        $stats['disk_total'] = 0;
        $stats['disk_used'] = 0;
        $stats['disk_free'] = 0;
        $stats['disk_percent'] = 0;
    }
    
    // ✅ FIXED: Improved System Uptime (Windows) - Multiple methods
    try {
        $uptime = 0;
        $method = 'unknown';
        
        // Method 1: Try systeminfo command (most reliable)
        exec('systeminfo | findstr /C:"System Boot Time"', $output);
        if (!empty($output)) {
            // Parse: "System Boot Time:          04/02/2026, 08:21:56"
            $line = $output[0];
            if (preg_match('/System Boot Time:\s+(.+)/', $line, $matches)) {
                $bootTimeStr = trim($matches[1]);
                // Try to parse date
                $bootTimestamp = strtotime($bootTimeStr);
                if ($bootTimestamp !== false) {
                    $uptime = time() - $bootTimestamp;
                    $method = 'systeminfo';
                }
            }
        }
        
        // Method 2: WMIC OS LastBootUpTime (fallback)
        if ($uptime == 0) {
            unset($output);
            exec('wmic os get lastbootuptime', $output);
            if (isset($output[1]) && !empty(trim($output[1]))) {
                $bootTime = trim($output[1]);
                // Parse: 20260204082156.500000+480
                if (strlen($bootTime) >= 14) {
                    $year = substr($bootTime, 0, 4);
                    $month = substr($bootTime, 4, 2);
                    $day = substr($bootTime, 6, 2);
                    $hour = substr($bootTime, 8, 2);
                    $minute = substr($bootTime, 10, 2);
                    $second = substr($bootTime, 12, 2);
                    
                    $bootTimestamp = strtotime("$year-$month-$day $hour:$minute:$second");
                    if ($bootTimestamp !== false) {
                        $uptime = time() - $bootTimestamp;
                        $method = 'wmic';
                    }
                }
            }
        }
        
        // Method 3: net statistics workstation (another fallback)
        if ($uptime == 0) {
            unset($output);
            exec('net statistics workstation | findstr /C:"Statistics since"', $output);
            if (!empty($output)) {
                // Parse: "Statistics since 04/02/2026 08:21:56"
                $line = $output[0];
                if (preg_match('/Statistics since\s+(.+)/', $line, $matches)) {
                    $bootTimeStr = trim($matches[1]);
                    $bootTimestamp = strtotime($bootTimeStr);
                    if ($bootTimestamp !== false) {
                        $uptime = time() - $bootTimestamp;
                        $method = 'net_statistics';
                    }
                }
            }
        }
        
        if ($uptime > 0) {
            $stats['uptime_seconds'] = $uptime;
            $stats['uptime_formatted'] = formatUptime($uptime);
            $stats['uptime_method'] = $method;
        } else {
            // Last resort: use PHP start time as approximation
            $stats['uptime_seconds'] = $_SERVER['REQUEST_TIME'] - filemtime(__FILE__);
            $stats['uptime_formatted'] = formatUptime($stats['uptime_seconds']) . ' (approx)';
            $stats['uptime_method'] = 'approximation';
        }
        
    } catch (Exception $e) {
        // Fallback: show server time
        $stats['uptime_seconds'] = 0;
        $stats['uptime_formatted'] = 'Unable to determine';
        $stats['uptime_method'] = 'error';
        $stats['uptime_error'] = $e->getMessage();
    }
    
    // Network Speed (estimate from network interface)
    try {
        exec('netstat -e', $output);
        $stats['network_in'] = 0;
        $stats['network_out'] = 0;
        
        // Parse netstat output for bytes sent/received
        foreach ($output as $line) {
            if (strpos($line, 'Bytes') !== false) {
                $parts = preg_split('/\s+/', trim($line));
                if (count($parts) >= 3) {
                    $stats['network_in'] = isset($parts[1]) ? intval($parts[1]) : 0;
                    $stats['network_out'] = isset($parts[2]) ? intval($parts[2]) : 0;
                }
            }
        }
        
        // Convert to MB
        $stats['network_in_mb'] = round($stats['network_in'] / 1024 / 1024, 2);
        $stats['network_out_mb'] = round($stats['network_out'] / 1024 / 1024, 2);
    } catch (Exception $e) {
        $stats['network_in'] = 0;
        $stats['network_out'] = 0;
        $stats['network_in_mb'] = 0;
        $stats['network_out_mb'] = 0;
    }
    
    // CPU Temperature (Windows - requires third-party tools, so we'll return N/A)
    $stats['cpu_temp'] = 'N/A';
    $stats['gpu_temp'] = 'N/A';
    
    return $stats;
}

function getLinuxStats() {
    $stats = [];
    
    // CPU Usage
    try {
        $load = sys_getloadavg();
        $cpuCount = 1;
        
        // Get CPU count
        if (file_exists('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor/m', $cpuinfo, $matches);
            $cpuCount = count($matches[0]);
        }
        
        $stats['cpu_usage'] = round(($load[0] / $cpuCount) * 100, 1);
        $stats['cpu_count'] = $cpuCount;
    } catch (Exception $e) {
        $stats['cpu_usage'] = 0;
        $stats['cpu_count'] = 1;
    }
    
    // Memory Usage
    try {
        $meminfo = file_get_contents('/proc/meminfo');
        preg_match('/MemTotal:\s+(\d+)/', $meminfo, $totalMatch);
        preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $availMatch);
        
        $totalMemory = isset($totalMatch[1]) ? intval($totalMatch[1]) : 0;
        $availMemory = isset($availMatch[1]) ? intval($availMatch[1]) : 0;
        $usedMemory = $totalMemory - $availMemory;
        
        $stats['memory_total'] = round($totalMemory / 1024 / 1024, 2); // GB
        $stats['memory_used'] = round($usedMemory / 1024 / 1024, 2); // GB
        $stats['memory_free'] = round($availMemory / 1024 / 1024, 2); // GB
        $stats['memory_percent'] = $totalMemory > 0 ? round(($usedMemory / $totalMemory) * 100, 1) : 0;
    } catch (Exception $e) {
        $stats['memory_total'] = 0;
        $stats['memory_used'] = 0;
        $stats['memory_free'] = 0;
        $stats['memory_percent'] = 0;
    }
    
    // Disk Space
    try {
        $totalSpace = disk_total_space("/");
        $freeSpace = disk_free_space("/");
        $usedSpace = $totalSpace - $freeSpace;
        
        $stats['disk_total'] = round($totalSpace / 1024 / 1024 / 1024, 2); // GB
        $stats['disk_used'] = round($usedSpace / 1024 / 1024 / 1024, 2); // GB
        $stats['disk_free'] = round($freeSpace / 1024 / 1024 / 1024, 2); // GB
        $stats['disk_percent'] = $totalSpace > 0 ? round(($usedSpace / $totalSpace) * 100, 1) : 0;
    } catch (Exception $e) {
        $stats['disk_total'] = 0;
        $stats['disk_used'] = 0;
        $stats['disk_free'] = 0;
        $stats['disk_percent'] = 0;
    }
    
    // ✅ FIXED: Uptime (Linux - this should work fine)
    try {
        $uptime = file_get_contents('/proc/uptime');
        $uptimeSeconds = intval(explode(' ', $uptime)[0]);
        $stats['uptime_seconds'] = $uptimeSeconds;
        $stats['uptime_formatted'] = formatUptime($uptimeSeconds);
        $stats['uptime_method'] = 'proc_uptime';
    } catch (Exception $e) {
        $stats['uptime_seconds'] = 0;
        $stats['uptime_formatted'] = 'Unable to determine';
        $stats['uptime_method'] = 'error';
    }
    
    // Network Stats
    try {
        $netdev = file_get_contents('/proc/net/dev');
        $lines = explode("\n", $netdev);
        $totalIn = 0;
        $totalOut = 0;
        
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false && strpos($line, 'lo:') === false) {
                $parts = preg_split('/\s+/', trim($line));
                if (count($parts) >= 10) {
                    $totalIn += intval($parts[1]);
                    $totalOut += intval($parts[9]);
                }
            }
        }
        
        $stats['network_in'] = $totalIn;
        $stats['network_out'] = $totalOut;
        $stats['network_in_mb'] = round($totalIn / 1024 / 1024, 2);
        $stats['network_out_mb'] = round($totalOut / 1024 / 1024, 2);
    } catch (Exception $e) {
        $stats['network_in'] = 0;
        $stats['network_out'] = 0;
        $stats['network_in_mb'] = 0;
        $stats['network_out_mb'] = 0;
    }
    
    // CPU Temperature (Linux)
    try {
        $temp = @file_get_contents('/sys/class/thermal/thermal_zone0/temp');
        $stats['cpu_temp'] = $temp ? round(intval($temp) / 1000, 1) . '°C' : 'N/A';
    } catch (Exception $e) {
        $stats['cpu_temp'] = 'N/A';
    }
    
    $stats['gpu_temp'] = 'N/A';
    
    return $stats;
}

function formatUptime($seconds) {
    if ($seconds <= 0) {
        return '0 minutes';
    }
    
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    $parts = [];
    if ($days > 0) $parts[] = "$days day" . ($days != 1 ? 's' : '');
    if ($hours > 0) $parts[] = "$hours hour" . ($hours != 1 ? 's' : '');
    if ($minutes > 0 || empty($parts)) $parts[] = "$minutes min" . ($minutes != 1 ? 's' : '');
    
    return implode(', ', $parts);
}

// Main execution
try {
    $stats = getSystemStats();
    $stats['success'] = true;
    $stats['timestamp'] = time();
    $stats['server_time'] = date('Y-m-d H:i:s');
    
    echo json_encode($stats, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>