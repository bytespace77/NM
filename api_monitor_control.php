<?php

use PhpOffice\PhpWord\Escaper\Xml;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Css;
use Soap\Sdl;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$action = $_GET['action'] ?? 'status';

// ================================================================
// heck if monitor is running (heartbeat only)
// ================================================================
function isMonitorRunning()
{
    $heartbeatFile = __DIR__ . '/runtime/system_monitor_heartbeat.txt';

    if (!file_exists($heartbeatFile)) {
        return false;
    }

    // Read heartbeat CONTENT (timestamp)
    $heartbeatContent = @file_get_contents($heartbeatFile);
    if ($heartbeatContent === false) {
        return false;
    }

    $heartbeatTime = (int)trim($heartbeatContent);
    $heartbeatAge = time() - $heartbeatTime;

    // Monitor is alive if heartbeat is less than 30 seconds old
    return ($heartbeatAge < 30);
}

// ================================================================
// START MONITOR 
// ================================================================
function findPhpBinary()
{
    $binary = PHP_BINARY;
    if (stripos($binary, 'php') === false || stripos($binary, 'httpd') !== false) {
        $xamppRoot = dirname(dirname(dirname($binary)));
        $derived   = $xamppRoot . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'php.exe';
        if (file_exists($derived)) return $derived;
    }
    if (stripos($binary, 'php') !== false && file_exists($binary)) return $binary;
    $common = ['C:\\xampp\\php\\php.exe', 'C:\\wamp\\bin\\php\\php.exe', 'C:\\wamp64\\bin\\php\\php.exe', 'C:\\laragon\\bin\\php\\php.exe', '/usr/bin/php', '/usr/local/bin/php'];
    foreach ($common as $p) {
        if (file_exists($p)) return $p;
    }
    return null;
}

function startMonitor()
{
    $phpPath    = findPhpBinary();
    $scriptPath = __DIR__ . '/system_monitor.php';

    if (!$phpPath || !file_exists($phpPath)) {
        return ['success' => false, 'error' => 'PHP executable not found. PHP_BINARY=' . PHP_BINARY];
    }
    if (!file_exists($scriptPath)) {
        return ['success' => false, 'error' => 'system_monitor.php not found'];
    }

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // ✅ wscript.exe VBS launcher — most reliable on XAMPP Windows
        $vbsFile    = sys_get_temp_dir() . '\\start_monitor_' . time() . '.vbs';
        $vbsContent = 'Set objShell = CreateObject("WScript.Shell")' . "\n";
        $vbsContent .= 'objShell.Run Chr(34) & "' . $phpPath . '" & Chr(34) & " " & Chr(34) & "' . $scriptPath . '" & Chr(34), 0, False' . "\n";
        file_put_contents($vbsFile, $vbsContent);
        pclose(popen('"C:\\Windows\\System32\\wscript.exe" "' . $vbsFile . '"', 'r'));
    } else {
        exec('nohup "' . $phpPath . '" "' . $scriptPath . '" > /dev/null 2>&1 &');
    }
    // Wait for monitor to initialize
    sleep(5);

    // Check up to 10 times
    $attempts = 0;
    while ($attempts < 10) {
        if (isMonitorRunning()) {
            return ['success' => true, 'message' => 'Monitor started successfully', 'php_path' => $phpPath];
        }
        sleep(1);
        $attempts++;
    }

    // Check PID file as fallback
    $pidFile = __DIR__ . '/runtime/system_monitor.pid';
    if (file_exists($pidFile)) {
        return ['success' => true, 'message' => 'Monitor started (PID file found)', 'php_path' => $phpPath];
    }

    return ['success' => false, 'error' => 'Monitor started but heartbeat not detected. Check system_monitor.php manually.'];
}

// ================================================================
// STOP MONITOR
// ================================================================
function stopMonitor()
{
    $pidFile = __DIR__ . '/runtime/system_monitor.pid';
    $heartbeatFile = __DIR__ . '/runtime/system_monitor_heartbeat.txt';

    if (!file_exists($pidFile)) {
        return ['success' => false, 'error' => 'Monitor is not running (no PID file)'];
    }

    $pidContent = file_get_contents($pidFile);
    $pid = (int)explode("\n", $pidContent)[0];

    if ($pid > 0) {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec("taskkill /PID $pid /F 2>&1", $output, $status);
        } else {
            exec("kill $pid 2>&1", $output, $status);
        }
    }

    // Cleanup files
    @unlink($pidFile);
    @unlink($heartbeatFile);

    return ['success' => true, 'message' => 'Monitor stopped'];
}

// ================================================================
// RESTART MONITOR
// ================================================================
function restartMonitor()
{
    stopMonitor();
    sleep(5);
    return startMonitor();
}

// ================================================================
// GET MONITOR STATUS
// ================================================================
function getMonitorStatus()
{
    $running       = isMonitorRunning();
    $heartbeatFile = __DIR__ . '/runtime/system_monitor_heartbeat.txt';
    $pidFile       = __DIR__ . '/runtime/system_monitor.pid';

    $status = [
        'success' => true,
        'running' => $running,
        'heartbeat_age' => null,
        'pid' => null,
        'uptime' => null,
    ];

    if (file_exists($heartbeatFile)) {
        $heartbeatTime      = (int)trim(file_get_contents($heartbeatFile));
        $status['heartbeat_age'] = time() - $heartbeatTime;
        $status['last_heartbeat'] = date('Y-m-d H:i:s', $heartbeatTime);
    }

    if (file_exists($pidFile)) {
        $pidContent    = file_get_contents($pidFile);
        $parts         = explode("\n", $pidContent);
        $status['pid'] = (int)$parts[0];
        if (isset($parts[1])) {
            $status['started_at'] = trim($parts[1]);
        }
    }

    $status['server_time'] = date('Y-m-d H:i:s');
    $status['php_binary']  = PHP_BINARY;

    return $status;
}

// ================================================================
// HANDLE ACTIONS
// ================================================================

try {
    switch ($action) {
        case 'status':
            echo json_encode(getMonitorStatus());
            break;

        case 'start':
            echo json_encode(startMonitor());
            break;

        case 'stop':
            echo json_encode(stopMonitor());
            break;

        case 'restart':
            echo json_encode(restartMonitor());
            break;

        case 'check':
            $status = getMonitorStatus();

            // Auto-restart if not running
            if (!$status['running']) {
                $result = startMonitor();
                $status['auto_restarted']   = $result['success'];
                $status['restart_message']  = $result['message'] ?? $result['error'];
            }

            echo json_encode($status);
            break;

        default:
            echo json_encode([
                'error'             => 'Invalid action',
                'available_actions' => ['status', 'start', 'stop', 'restart', 'check']
            ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Exception: ' . $e->getMessage()
    ]);
}
                                                                                                                                                          