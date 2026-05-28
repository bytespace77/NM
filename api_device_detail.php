<?php
// Buffer MUST be first — catches any stray output from config.php or getDB()
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';

// Now safe to set JSON header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    ob_end_clean();
    exit;
}

// ── Get DB — if it fails, return JSON error instead of die() text ──
function getDBSafe()
{
    try {
        $db = getDB();
        if (!$db || $db->connect_error) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'DB connection failed: ' . ($db->connect_error ?? 'unknown')]);
            exit;
        }
        return $db;
    } catch (Exception $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'DB error: ' . $e->getMessage()]);
        exit;
    } catch (Error $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'DB fatal: ' . $e->getMessage()]);
        exit;
    }
}

$db     = getDBSafe();
$action = $_GET['action'] ?? $_POST['action'] ?? 'get_full';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

// ── Helper: safe COUNT ────────────────────────────────────────────
function safeCount($db, $sql)
{
    $r = @$db->query($sql);
    if (!$r) return 0;
    $row = $r->fetch_assoc();
    return (int)($row['c'] ?? 0);
}

// ── Helper: table exists ──────────────────────────────────────────
function tableExists($db, $name)
{
    $r = $db->query("SHOW TABLES LIKE '$name'");
    return $r && $r->num_rows > 0;
}

// ── Auto-create required tables ───────────────────────────────────
$db->query("CREATE TABLE IF NOT EXISTS device_flags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT NOT NULL,
    flag_type VARCHAR(50) NOT NULL DEFAULT 'custom',
    severity VARCHAR(20) DEFAULT 'medium',
    title VARCHAR(255) NOT NULL,
    description TEXT,
    created_by VARCHAR(100) DEFAULT 'system',
    resolved TINYINT(1) DEFAULT 0,
    resolved_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT NOW(),
    updated_at DATETIME DEFAULT NOW() ON UPDATE NOW(),
    KEY idx_device (device_id),
    KEY idx_resolved (resolved)
)");

$db->query("CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id INT DEFAULT NULL,
    log_level VARCHAR(20) DEFAULT 'INFO',
    category VARCHAR(50) DEFAULT 'system',
    message TEXT,
    created_at DATETIME DEFAULT NOW(),
    KEY idx_device (device_id),
    KEY idx_created (created_at)
)");

$db->query("CREATE TABLE IF NOT EXISTS device_health_metrics (
    device_id INT PRIMARY KEY,
    health_score FLOAT DEFAULT 100,
    risk_level VARCHAR(20) DEFAULT 'LOW',
    predicted_failure_date DATE DEFAULT NULL,
    days_until_failure INT DEFAULT 365,
    confidence_level FLOAT DEFAULT 50,
    factors TEXT,
    last_updated DATETIME DEFAULT NOW() ON UPDATE NOW()
)");
// Add missing columns if table already existed without them
// Patch missing columns on existing device_health_metrics table
$_dhmPatch = [
    "days_until_failure INT DEFAULT 365",
    "confidence_level FLOAT DEFAULT 50",
    "predicted_failure_date DATE DEFAULT NULL",
    "risk_level VARCHAR(20) DEFAULT 'LOW'",
    "factors TEXT",
    "health_score FLOAT DEFAULT 100"
];
foreach ($_dhmPatch as $_patchCol) {
    $_patchName = explode(' ', $_patchCol)[0];
    $_patchChk = $db->query("SHOW COLUMNS FROM device_health_metrics LIKE '$_patchName'");
    if (!$_patchChk || $_patchChk->num_rows === 0) {
        $db->query("ALTER TABLE device_health_metrics ADD COLUMN $_patchCol");
    }
}


// ── Ensure optional columns on devices table ──────────────────────
foreach (['offline_reason VARCHAR(255) DEFAULT NULL', 'offline_since DATETIME DEFAULT NULL'] as $colDef) {
    $colName = explode(' ', $colDef)[0];
    $chk = $db->query("SHOW COLUMNS FROM devices LIKE '$colName'");
    if (!$chk || $chk->num_rows === 0) {
        $db->query("ALTER TABLE devices ADD COLUMN $colDef");
    }
}

// ── Route action ──────────────────────────────────────────────────
try {
    switch ($action) {

        // ── get_full ──────────────────────────────────────────────
        case 'get_full':
            $deviceId = (int)($_GET['device_id'] ?? $input['device_id'] ?? 0);
            if (!$deviceId) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'No device ID provided']);
                exit;
            }

            // Core device + health metrics (LEFT JOIN safe — table now exists)
            $devRes = $db->query("
                SELECT d.*,
                    dhm.health_score, dhm.risk_level, dhm.predicted_failure_date,
                    dhm.days_until_failure, dhm.confidence_level, dhm.factors,
                    dhm.last_updated AS health_updated
                FROM devices d
                LEFT JOIN device_health_metrics dhm ON d.id = dhm.device_id
                WHERE d.id = $deviceId
            ");

            if (!$devRes) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Device query failed: ' . $db->error]);
                exit;
            }

            $dev = $devRes->fetch_assoc();
            if (!$dev) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Device not found (id=' . $deviceId . ')']);
                exit;
            }

            // Decode factors JSON
            $factors = [];
            if (!empty($dev['factors'])) {
                $decoded = json_decode($dev['factors'], true);
                if (is_array($decoded)) $factors = $decoded;
            }

            // Active flags
            $flags = [];
            $r = $db->query("SELECT * FROM device_flags WHERE device_id=$deviceId AND resolved=0 ORDER BY created_at DESC");
            if ($r) while ($row = $r->fetch_assoc()) $flags[] = $row;

            // Resolved flags (last 30d)
            $resolvedFlags = [];
            $r = $db->query("SELECT * FROM device_flags WHERE device_id=$deviceId AND resolved=1 AND resolved_at > DATE_SUB(NOW(),INTERVAL 30 DAY) ORDER BY resolved_at DESC LIMIT 10");
            if ($r) while ($row = $r->fetch_assoc()) $resolvedFlags[] = $row;

            // Logs (last 100)
            $logs = [];
            $r = $db->query("SELECT * FROM system_logs WHERE device_id=$deviceId ORDER BY created_at DESC LIMIT 100");
            if ($r) while ($row = $r->fetch_assoc()) $logs[] = $row;

            // Maintenance history
            $maintenance = [];
            if (tableExists($db, 'maintenance_history')) {
                $r = $db->query("SELECT * FROM maintenance_history WHERE device_id=$deviceId ORDER BY completed_at DESC LIMIT 20");
                if ($r) while ($row = $r->fetch_assoc()) $maintenance[] = $row;
            }

            // Performance metrics
            $metrics      = [];
            $hasPerfTable = tableExists($db, 'performance_metrics');
            if ($hasPerfTable) {
                $r = $db->query("SELECT * FROM performance_metrics WHERE device_id=$deviceId ORDER BY recorded_at DESC LIMIT 50");
                if ($r) while ($row = $r->fetch_assoc()) $metrics[] = $row;
            }

            // Offline reason detection
            $offlineReason = null;
            if ($dev['status'] !== 'online') {
                $offlineReason = detectOfflineReason($dev, $logs);
            }

            // Stats
            $errorCount    = safeCount($db, "SELECT COUNT(*) c FROM system_logs WHERE device_id=$deviceId AND log_level IN ('ERROR','CRITICAL') AND created_at > DATE_SUB(NOW(),INTERVAL 60 DAY)");
            $criticalCount = safeCount($db, "SELECT COUNT(*) c FROM system_logs WHERE device_id=$deviceId AND log_level='CRITICAL' AND created_at > DATE_SUB(NOW(),INTERVAL 60 DAY)");
            $warnCount     = safeCount($db, "SELECT COUNT(*) c FROM system_logs WHERE device_id=$deviceId AND log_level='WARNING' AND created_at > DATE_SUB(NOW(),INTERVAL 60 DAY)");
            $offlineEvents = safeCount($db, "SELECT COUNT(*) c FROM system_logs WHERE device_id=$deviceId AND (message LIKE '%offline%' OR message LIKE '%unreachable%') AND created_at > DATE_SUB(NOW(),INTERVAL 30 DAY)");
            $totalChecks   = safeCount($db, "SELECT COUNT(*) c FROM system_logs WHERE device_id=$deviceId AND created_at > DATE_SUB(NOW(),INTERVAL 30 DAY)");
            $uptimePct     = $totalChecks > 0 ? round((1 - $offlineEvents / $totalChecks) * 100, 1) : null;

            // Perf averages
            $perfAvg = null;
            if ($hasPerfTable) {
                $r = $db->query("SELECT AVG(cpu_usage) avg_cpu, AVG(ram_usage) avg_ram, AVG(latency) avg_latency, AVG(disk_usage) avg_disk FROM performance_metrics WHERE device_id=$deviceId AND recorded_at > DATE_SUB(NOW(),INTERVAL 7 DAY)");
                if ($r) $perfAvg = $r->fetch_assoc();
            }

            ob_end_clean();
            echo json_encode([
                'success'        => true,
                'device'         => $dev,
                'factors'        => $factors,
                'flags'          => $flags,
                'resolved_flags' => $resolvedFlags,
                'logs'           => $logs,
                'maintenance'    => $maintenance,
                'metrics'        => $metrics,
                'offline_reason' => $offlineReason,
                'stats'          => [
                    'error_count'    => $errorCount,
                    'critical_count' => $criticalCount,
                    'warn_count'     => $warnCount,
                    'uptime_pct'     => $uptimePct,
                    'offline_events' => $offlineEvents,
                    'active_flags'   => count($flags),
                ],
                'perf_avg' => $perfAvg,
            ]);
            break;

        // ── add_flag ──────────────────────────────────────────────
        case 'add_flag':
            $deviceId  = (int)($input['device_id'] ?? 0);
            $flagType  = $db->real_escape_string($input['flag_type']    ?? 'custom');
            $severity  = $db->real_escape_string($input['severity']     ?? 'medium');
            $title     = $db->real_escape_string(trim($input['title']   ?? ''));
            $desc      = $db->real_escape_string(trim($input['description'] ?? ''));
            $createdBy = $db->real_escape_string(trim($input['created_by']  ?? 'user'));
            if (!$deviceId || !$title) {
                echo json_encode(['success' => false, 'error' => 'Device ID and title required']);
                exit;
            }
            $db->query("INSERT INTO device_flags (device_id,flag_type,severity,title,description,created_by,created_at) VALUES ($deviceId,'$flagType','$severity','$title','$desc','$createdBy',NOW())");
            $db->query("INSERT INTO system_logs (device_id,log_level,category,message,created_at) VALUES ($deviceId,'WARNING','flag','Flag added: $title',NOW())");
            ob_end_clean();
            echo json_encode(['success' => true, 'id' => $db->insert_id, 'message' => 'Flag added']);
            break;

        // ── resolve_flag ──────────────────────────────────────────
        case 'resolve_flag':
            $flagId  = (int)($input['flag_id'] ?? 0);
            if (!$flagId) {
                echo json_encode(['success' => false, 'error' => 'No flag ID']);
                exit;
            }
            $r    = $db->query("SELECT device_id,title FROM device_flags WHERE id=$flagId");
            $flag = $r ? $r->fetch_assoc() : null;
            $db->query("UPDATE device_flags SET resolved=1, resolved_at=NOW() WHERE id=$flagId");
            if ($flag) $db->query("INSERT INTO system_logs (device_id,log_level,category,message,created_at) VALUES ({$flag['device_id']},'INFO','flag','Flag resolved: {$flag['title']}',NOW())");
            ob_end_clean();
            echo json_encode(['success' => true, 'message' => 'Flag resolved']);
            break;

        // ── delete_flag ───────────────────────────────────────────
        case 'delete_flag':
            $flagId = (int)($input['flag_id'] ?? 0);
            if (!$flagId) {
                echo json_encode(['success' => false, 'error' => 'No flag ID']);
                exit;
            }
            $db->query("DELETE FROM device_flags WHERE id=$flagId");
            ob_end_clean();
            echo json_encode(['success' => true, 'message' => 'Flag deleted']);
            break;

        // ── set_offline_reason ────────────────────────────────────
        case 'set_offline_reason':
            $deviceId = (int)($input['device_id'] ?? 0);
            $reason   = $db->real_escape_string(trim($input['reason'] ?? ''));
            if (!$deviceId) {
                echo json_encode(['success' => false, 'error' => 'No device ID']);
                exit;
            }
            $db->query("UPDATE devices SET offline_reason='$reason' WHERE id=$deviceId");
            ob_end_clean();
            echo json_encode(['success' => true, 'message' => 'Offline reason updated']);
            break;

        // ── add_log ───────────────────────────────────────────────
        case 'add_log':
            $deviceId = (int)($input['device_id'] ?? 0);
            $level    = $db->real_escape_string($input['level']    ?? 'INFO');
            $category = $db->real_escape_string($input['category'] ?? 'manual');
            $message  = $db->real_escape_string(trim($input['message'] ?? ''));
            if (!$deviceId || !$message) {
                echo json_encode(['success' => false, 'error' => 'Device ID and message required']);
                exit;
            }
            $db->query("INSERT INTO system_logs (device_id,log_level,category,message,created_at) VALUES ($deviceId,'$level','$category','$message',NOW())");
            ob_end_clean();
            echo json_encode(['success' => true, 'message' => 'Log entry added']);
            break;

        // ── get_logs ──────────────────────────────────────────────
        case 'get_logs':
            $deviceId = (int)($_GET['device_id'] ?? 0);
            $level    = $db->real_escape_string($_GET['level']  ?? '');
            $limit    = min(500, max(10, (int)($_GET['limit']   ?? 100)));
            $search   = $db->real_escape_string($_GET['search'] ?? '');
            if (!$deviceId) {
                echo json_encode(['success' => false, 'error' => 'No device ID']);
                exit;
            }
            $where = "WHERE device_id=$deviceId";
            if ($level)  $where .= " AND log_level='$level'";
            if ($search) $where .= " AND message LIKE '%$search%'";
            $r    = $db->query("SELECT * FROM system_logs $where ORDER BY created_at DESC LIMIT $limit");
            $logs = [];
            if ($r) while ($row = $r->fetch_assoc()) $logs[] = $row;
            ob_end_clean();
            echo json_encode(['success' => true, 'logs' => $logs, 'total' => count($logs)]);
            break;

        // ── check_online — ARP-based connectivity check ──────────
        case 'ping_now':
            $deviceId = (int)($input['device_id'] ?? 0);
            if (!$deviceId) {
                echo json_encode(['success' => false, 'error' => 'No device ID']);
                exit;
            }
            $r   = $db->query("SELECT ip_address,name FROM devices WHERE id=$deviceId");
            $dev = $r ? $r->fetch_assoc() : null;
            if (!$dev) {
                echo json_encode(['success' => false, 'error' => 'Device not found']);
                exit;
            }

            $ip    = $dev['ip_address'];
            $isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
            $online = false;
            $mac    = '';
            $method = '';

            // ── ARP lookup: most reliable on LAN, no privileges needed ──
            $t = microtime(true);
            exec(($isWin ? "arp -a $ip" : "arp -n $ip") . " 2>&1", $arpOut);
            $arpStr = strtolower(implode(' ', $arpOut));
            if (preg_match('/([0-9a-f]{2}[:\-]){5}[0-9a-f]{2}/i', $arpStr, $macMatch)) {
                $online = true;
                $mac    = strtoupper(str_replace('-', ':', $macMatch[0]));
                $method = 'ARP';
            }

            // ── Fallback: scan full ARP table for this IP ───────────
            if (!$online) {
                $arpAll = [];
                exec("arp -a 2>&1", $arpAll);
                foreach ($arpAll as $line) {
                    if (strpos($line, $ip) !== false) {
                        if (preg_match('/([0-9a-f]{2}[:\-]){5}[0-9a-f]{2}/i', $line, $macMatch2)) {
                            $online = true;
                            $mac    = strtoupper(str_replace('-', ':', $macMatch2[0]));
                            $method = 'ARP table scan';
                        }
                    }
                }
            }
            $ms = round((microtime(true) - $t) * 1000, 1);

            // ── Update DB and log ────────────────────────────────────
            if ($online) {
                // Also update MAC if we got one and it was unknown
                $macSafe = $db->real_escape_string($mac);
                $db->query("UPDATE devices SET status='online', last_seen_at=NOW(), offline_reason=NULL"
                    . ($mac ? ", mac_address='$macSafe'" : "") . " WHERE id=$deviceId");
                $db->query("INSERT INTO system_logs (device_id,log_level,category,message,created_at)
                    VALUES ($deviceId,'INFO','arp','ARP check: online via $method" . ($mac ? " MAC $mac" : "") . "',NOW())");
            } else {
                $reason = 'Not found in ARP table — device may be offline, powered off, or not recently active on the network.';
                $reasonSafe = $db->real_escape_string($reason);
                $db->query("UPDATE devices SET status='offline', offline_reason='$reasonSafe' WHERE id=$deviceId");
                $db->query("INSERT INTO system_logs (device_id,log_level,category,message,created_at)
                    VALUES ($deviceId,'WARNING','arp','ARP check: not found in table',NOW())");
            }

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'online'  => $online,
                'mac'     => $mac,
                'method'  => $method ?: 'ARP',
                'ms'      => $ms,
                'message' => $online ? "Online — found in ARP table" . ($mac ? " ($mac)" : "") : 'Not found in ARP table',
                'offline_reason' => $online ? null : 'Not found in ARP table',
            ]);
            break;

        default:
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
    }
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Error $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Fatal error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' line ' . $e->getLine()]);
}

// ── Offline reason detection ──────────────────────────────────────
function detectOfflineReason($dev, $logs)
{
    if (!empty($dev['offline_reason'])) {
        return ['code' => 'stored', 'reason' => $dev['offline_reason'], 'icon' => '📋'];
    }
    foreach ($logs as $log) {
        $msg = strtolower($log['message'] ?? '');
        if (strpos($msg, 'timeout')     !== false) return ['code' => 'timeout',    'reason' => 'Connection timeout — device not responding to ping',       'icon' => '⏱️'];
        if (strpos($msg, 'unreachable') !== false) return ['code' => 'unreachable', 'reason' => 'Host unreachable — possibly powered off or disconnected',  'icon' => '🔌'];
        if (strpos($msg, 'refused')     !== false) return ['code' => 'refused',    'reason' => 'Connection refused — device up but blocking connections',  'icon' => '🚫'];
        if (strpos($msg, 'port closed') !== false) return ['code' => 'port',       'reason' => 'Required ports closed — service may be stopped',          'icon' => '🔒'];
        if (strpos($msg, 'dns')         !== false) return ['code' => 'dns',        'reason' => 'DNS resolution failure — hostname cannot be resolved',     'icon' => '🌐'];
    }
    if (!empty($dev['last_seen_at'])) {
        $mins  = round((time() - strtotime($dev['last_seen_at'])) / 60);
        $hours = round($mins / 60, 1);
        if ($mins  < 10) return ['code' => 'recent', 'reason' => "Last seen {$mins}m ago — may be temporarily unreachable",           'icon' => '⚡'];
        if ($mins  < 60) return ['code' => 'short', 'reason' => "Offline for {$mins} minutes — network interruption or sleep mode", 'icon' => '😴'];
        if ($hours < 24) return ['code' => 'hours', 'reason' => "Offline for {$hours}h — check device power and network cable",     'icon' => '🔌'];
        if ($hours < 72) return ['code' => 'days',  'reason' => "Offline for " . round($hours / 24, 1) . " days — likely powered off",     'icon' => '🖥️'];
        return                  ['code' => 'long',  'reason' => "Offline for " . round($hours / 24) . " days — may be decommissioned",    'icon' => '❓'];
    }
    return ['code' => 'unknown', 'reason' => 'Offline — reason unknown. Run a manual ping to diagnose.', 'icon' => '❓'];
}
