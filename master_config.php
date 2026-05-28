<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once 'config.php';
date_default_timezone_set('Asia/Kuala_Lumpur');

// ============================================================
// API HANDLER — all form actions handled here
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['api'])) {
    // Clean any output buffer to ensure pure JSON
    if (ob_get_level()) ob_end_clean();
    ob_start();
    header('Content-Type: application/json');
    $db = getDB();
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $action ?: ($input['action'] ?? '');

    try {
        // ── Ensure system_settings table exists ────────────────────
        $db->query("CREATE TABLE IF NOT EXISTS system_settings (`key` VARCHAR(100) PRIMARY KEY, `value` TEXT, updated_at DATETIME DEFAULT NOW() ON UPDATE NOW())");

        switch ($action) {

            // ── Update device type ──────────────────────────────────
            case 'update_device_type':
                $id   = (int)($input['id'] ?? 0);
                $type = $db->real_escape_string($input['device_type'] ?? '');
                $allowed = ['computer','server','laptop','router','switch','printer','phone','tablet','camera','other'];
                if (!$id || !in_array($type, $allowed)) { echo json_encode(['success'=>false,'error'=>'Invalid type']); exit; }
                $db->query("UPDATE devices SET device_type='$type' WHERE id=$id");
                echo json_encode(['success'=>true,'message'=>"Device type updated to $type"]);
                break;

            // ── Update device MAC ───────────────────────────────────
            case 'update_device_mac':
                $id  = (int)($input['id'] ?? 0);
                $mac = $db->real_escape_string(strtoupper(trim($input['mac'] ?? '')));
                if (!$id) { echo json_encode(['success'=>false,'error'=>'No device ID']); exit; }
                $macVal = $mac ? "'$mac'" : "NULL";
                $db->query("UPDATE devices SET mac_address=$macVal WHERE id=$id");
                echo json_encode(['success'=>true,'message'=>'MAC address updated']);
                break;

            // ── Update device location ──────────────────────────────
            case 'update_device_location':
                $id  = (int)($input['id'] ?? 0);
                $loc = $db->real_escape_string(trim($input['location'] ?? ''));
                if (!$id) { echo json_encode(['success'=>false,'error'=>'No device ID']); exit; }
                $locVal = $loc ? "'$loc'" : "NULL";
                $db->query("UPDATE devices SET location=$locVal WHERE id=$id");
                echo json_encode(['success'=>true,'message'=>'Location updated']);
                break;

            // ── Update device IP address ────────────────────────────
            case 'update_device_ip':
                $id    = (int)($input['id'] ?? 0);
                $newIp = $db->real_escape_string(trim($input['ip'] ?? ''));
                if (!$id || !$newIp) { echo json_encode(['success'=>false,'error'=>'Missing id or ip']); exit; }
                if (!filter_var($newIp, FILTER_VALIDATE_IP)) { echo json_encode(['success'=>false,'error'=>'Invalid IP address']); exit; }
                $conflict = $db->query("SELECT id FROM devices WHERE ip_address='$newIp' AND id!=$id");
                if ($conflict && $conflict->num_rows > 0) { echo json_encode(['success'=>false,'error'=>"IP $newIp is already assigned to another device"]); exit; }
                $parts = explode('.', $newIp);
                $newNet = $parts[0].'.'.$parts[1].'.'.$parts[2].'.0/24';
                $db->query("UPDATE devices SET ip_address='$newIp', network_range='$newNet' WHERE id=$id");
                $db->query("INSERT INTO system_logs (device_id, log_level, category, message, created_at) VALUES ($id,'INFO','device','IP address changed to $newIp',NOW())");
                echo json_encode(['success'=>true,'message'=>"IP updated to $newIp"]);
                break;

            // ── Bulk rename by pattern ──────────────────────────────
            case 'bulk_rename':
                $ids     = array_map('intval', $input['ids'] ?? []);
                $pattern = $db->real_escape_string(trim($input['pattern'] ?? ''));
                $start   = (int)($input['start_num'] ?? 1);
                if (!$ids || !$pattern) { echo json_encode(['success'=>false,'error'=>'No devices or pattern']); exit; }
                $renamed = 0;
                foreach ($ids as $i => $id) {
                    $num  = $start + $i;
                    $name = $db->real_escape_string(str_replace('{N}', $num, $pattern));
                    $db->query("UPDATE devices SET name='$name' WHERE id=$id");
                    $renamed++;
                }
                echo json_encode(['success'=>true,'message'=>"Renamed $renamed devices"]);
                break;

            // ── Bulk delete ─────────────────────────────────────────
            case 'bulk_delete':
                $ids = array_map('intval', $input['ids'] ?? []);
                if (!$ids) { echo json_encode(['success'=>false,'error'=>'No device IDs']); exit; }
                $idList = implode(',', $ids);
                $db->query("DELETE FROM devices WHERE id IN ($idList)");
                echo json_encode(['success'=>true,'message'=>count($ids).' devices deleted']);
                break;

            // ── Bulk change type ────────────────────────────────────
            case 'bulk_change_type':
                $ids  = array_map('intval', $input['ids'] ?? []);
                $type = $db->real_escape_string($input['device_type'] ?? '');
                if (!$ids || !$type) { echo json_encode(['success'=>false,'error'=>'Missing ids or type']); exit; }
                $idList = implode(',', $ids);
                $db->query("UPDATE devices SET device_type='$type' WHERE id IN ($idList)");
                echo json_encode(['success'=>true,'message'=>count($ids)." devices set to $type"]);
                break;

            // ── Add network range ───────────────────────────────────
            case 'add_network':
                $range = $db->real_escape_string(trim($input['network_range'] ?? ''));
                $label = $db->real_escape_string(trim($input['label'] ?? ''));
                if (!$range) { echo json_encode(['success'=>false,'error'=>'No network range']); exit; }
                $tableCheck = $db->query("SHOW TABLES LIKE 'networks'");
                if ($tableCheck && $tableCheck->num_rows > 0) {
                    $db->query("INSERT IGNORE INTO networks (network_range, label, last_seen_at) VALUES ('$range','$label',NOW())");
                }
                echo json_encode(['success'=>true,'message'=>"Network $range added"]);
                break;

            // ── Delete network range ────────────────────────────────
            case 'delete_network':
                $range = $db->real_escape_string(trim($input['network_range'] ?? ''));
                if (!$range) { echo json_encode(['success'=>false,'error'=>'No range']); exit; }
                $devCount = $db->query("SELECT COUNT(*) c FROM devices WHERE network_range='$range'")->fetch_assoc()['c'];
                if ($devCount > 0 && !($input['force'] ?? false)) {
                    echo json_encode(['success'=>false,'error'=>"Network has $devCount devices. Pass force:true to delete anyway."]);
                    exit;
                }
                $tableCheck = $db->query("SHOW TABLES LIKE 'networks'");
                if ($tableCheck && $tableCheck->num_rows > 0) {
                    $db->query("DELETE FROM networks WHERE network_range='$range'");
                }
                echo json_encode(['success'=>true,'message'=>"Network $range removed"]);
                break;

            // ── Export devices CSV ──────────────────────────────────
            case 'export_devices_csv':
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="devices_'.date('Ymd_His').'.csv"');
                $out = fopen('php://output','w');
                fputcsv($out, ['ID','Name','IP Address','MAC Address','Type','Status','Network Range','Location','Last Seen','Created']);
                $res = $db->query("SELECT id,name,ip_address,mac_address,device_type,status,network_range,location,last_seen_at,created_at FROM devices ORDER BY name");
                while ($r = $res->fetch_assoc()) fputcsv($out, $r);
                fclose($out);
                exit;

            // ── DB cleanup: remove stale devices ───────────────────
            case 'db_cleanup_stale':
                $days = max(1, (int)($input['days'] ?? 30));
                $res = $db->query("SELECT id,name,ip_address FROM devices WHERE last_seen_at < DATE_SUB(NOW(), INTERVAL $days DAY) AND status='offline'");
                $stale = [];
                while ($r = $res->fetch_assoc()) $stale[] = $r;
                if ($input['confirm'] ?? false) {
                    $db->query("DELETE FROM devices WHERE last_seen_at < DATE_SUB(NOW(), INTERVAL $days DAY) AND status='offline'");
                    echo json_encode(['success'=>true,'message'=>count($stale)." stale devices removed",'deleted'=>$stale]);
                } else {
                    echo json_encode(['success'=>true,'preview'=>true,'count'=>count($stale),'devices'=>$stale,'message'=>count($stale)." devices would be removed (offline > $days days)"]);
                }
                break;

            // ── DB reset offline counts ─────────────────────────────
            case 'db_reset_offline':
                $db->query("UPDATE devices SET status='offline' WHERE last_seen_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
                $count = $db->affected_rows;
                echo json_encode(['success'=>true,'message'=>"Reset $count offline device statuses"]);
                break;

            // ── DB stats ────────────────────────────────────────────
            case 'db_stats':
                $tables = ['devices','device_health_metrics','maintenance_history','maintenance_schedules','system_logs','predictive_alerts','networks'];
                $stats  = [];
                foreach ($tables as $tbl) {
                    $check = $db->query("SHOW TABLES LIKE '$tbl'");
                    if (!$check || $check->num_rows === 0) { $stats[$tbl] = ['rows'=>0,'size_mb'=>0,'exists'=>false]; continue; }
                    $cnt  = $db->query("SELECT COUNT(*) c FROM `$tbl`")->fetch_assoc()['c'];
                    $size = $db->query("SELECT ROUND(data_length/1024/1024,3) s FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='$tbl'")->fetch_assoc()['s'] ?? 0;
                    $stats[$tbl] = ['rows'=>$cnt,'size_mb'=>(float)$size,'exists'=>true];
                }
                $totalSize = $db->query("SELECT ROUND(SUM(data_length+index_length)/1024/1024,2) s FROM information_schema.tables WHERE table_schema=DATABASE()")->fetch_assoc()['s'];
                echo json_encode(['success'=>true,'tables'=>$stats,'total_size_mb'=>(float)$totalSize,'db_name'=>DB_NAME]);
                break;

            // ── Clear old system logs ───────────────────────────────
            case 'clear_system_logs':
                $days = max(1, (int)($input['days'] ?? 7));
                $db->query("DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL $days DAY)");
                $deleted = $db->affected_rows;
                echo json_encode(['success'=>true,'message'=>"Cleared $deleted log entries older than $days days"]);
                break;

            // ── Reset all AI predictions ────────────────────────────
            case 'reset_predictions':
                $hasDHM2 = $db->query("SHOW TABLES LIKE 'device_health_metrics'")->num_rows > 0;
                if ($hasDHM2) $db->query("TRUNCATE TABLE device_health_metrics");
                $check = $db->query("SHOW TABLES LIKE 'predictive_alerts'");
                if ($check && $check->num_rows > 0) $db->query("DELETE FROM predictive_alerts WHERE acknowledged=0");
                echo json_encode(['success'=>true,'message'=>'All prediction data cleared. Run predictions to recalculate.']);
                break;

            // ── Trigger bulk predict ────────────────────────────────
            case 'trigger_bulk_predict':
                $res    = $db->query("SELECT id FROM devices ORDER BY id");
                $ids    = [];
                while ($r = $res->fetch_assoc()) $ids[] = $r['id'];
                $done   = 0; $errors = 0;
                foreach ($ids as $did) {
                    try {
                        // Call prediction logic inline (simplified fast version)
                        $dev = $db->query("SELECT * FROM devices WHERE id=$did")->fetch_assoc();
                        if (!$dev) continue;
                        $errCount = $db->query("SELECT COUNT(*) c FROM system_logs WHERE device_id=$did AND log_level IN ('ERROR','CRITICAL') AND created_at > DATE_SUB(NOW(),INTERVAL 60 DAY)")->fetch_assoc()['c'];
                        $maintCount = 0;
                        $mCheck = $db->query("SHOW TABLES LIKE 'maintenance_history'");
                        if ($mCheck && $mCheck->num_rows > 0) $maintCount = $db->query("SELECT COUNT(*) c FROM maintenance_history WHERE device_id=$did")->fetch_assoc()['c'];
                        $online = ($dev['status'] === 'online');
                        $health = 100;
                        if (!$online) $health -= 30;
                        $health -= min(40, $errCount * 5);
                        if ($maintCount === 0) $health -= 20;
                        $health = max(0, min(100, $health));
                        $risk = $health >= 80 ? 'LOW' : ($health >= 60 ? 'MEDIUM' : ($health >= 40 ? 'HIGH' : 'CRITICAL'));
                        $predictDays = $health >= 80 ? 180 : ($health >= 60 ? 90 : ($health >= 40 ? 45 : 14));
                        $failDate = date('Y-m-d', strtotime("+$predictDays days"));
                        $factors = json_encode(['is_online'=>$online,'error_count'=>$errCount,'maintenance_count'=>$maintCount,'bulk_prediction'=>true]);
                        $db->query("CREATE TABLE IF NOT EXISTS device_health_metrics (device_id INT PRIMARY KEY, health_score FLOAT, risk_level VARCHAR(20), predicted_failure_date DATE, days_until_failure INT, confidence_level FLOAT, factors TEXT, last_updated DATETIME)");
                        $db->query("INSERT INTO device_health_metrics (device_id,health_score,risk_level,predicted_failure_date,days_until_failure,confidence_level,factors,last_updated)
                            VALUES ($did,$health,'$risk','$failDate',$predictDays,70.0,'$factors',NOW())
                            ON DUPLICATE KEY UPDATE health_score=$health,risk_level='$risk',predicted_failure_date='$failDate',days_until_failure=$predictDays,confidence_level=70.0,factors='$factors',last_updated=NOW()");
                        $done++;
                    } catch(Exception $e) { $errors++; }
                }
                echo json_encode(['success'=>true,'message'=>"Bulk prediction done: $done devices updated, $errors errors",'done'=>$done,'errors'=>$errors]);
                break;

            // ── System health check ─────────────────────────────────
            case 'get_system_health':
                $checks = [];
                // DB connection
                $checks['database'] = ['ok'=>true,'msg'=>'Connected to '.DB_NAME];
                // Tables
                $reqTables = ['devices','system_logs','device_health_metrics','maintenance_schedules','maintenance_history'];
                foreach ($reqTables as $t) {
                    $r = $db->query("SHOW TABLES LIKE '$t'");
                    $checks['table_'.$t] = ['ok'=>($r && $r->num_rows>0), 'msg'=>($r && $r->num_rows>0) ? "Table exists" : "MISSING"];
                }
                // Monitor process (check recent data)
                $recentData = $db->query("SELECT COUNT(*) c FROM system_logs WHERE created_at > DATE_SUB(NOW(),INTERVAL 5 MINUTE)")->fetch_assoc()['c'];
                $checks['monitor_active'] = ['ok'=>$recentData>0,'msg'=>$recentData>0?"Monitor active ($recentData recent events)":'No recent monitor activity'];
                // Stale devices
                $staleCount = $db->query("SELECT COUNT(*) c FROM devices WHERE last_seen_at < DATE_SUB(NOW(),INTERVAL 1 HOUR) AND status='online'")->fetch_assoc()['c'];
                $checks['stale_devices'] = ['ok'=>$staleCount===0||$staleCount<5,'msg'=>"$staleCount devices marked online but not seen in 1h"];
                // AI predictions coverage
                $devTotal = $db->query("SELECT COUNT(*) c FROM devices")->fetch_assoc()['c'];
                $devPred  = $db->query("SELECT COUNT(*) c FROM device_health_metrics")->fetch_assoc()['c'];
                $pct = $devTotal > 0 ? round($devPred/$devTotal*100) : 0;
                $checks['ai_coverage'] = ['ok'=>$pct>=50,'msg'=>"$devPred/$devTotal devices have predictions ($pct%)"];
                // Disk space (basic)
                $free = disk_free_space('.');
                $total = disk_total_space('.');
                $freeGb = round($free/1073741824,1);
                $usePct = round((1-$free/$total)*100);
                $checks['disk_space'] = ['ok'=>$usePct<90,'msg'=>"{$freeGb}GB free ({$usePct}% used)"];
                $allOk = !in_array(false, array_column($checks,'ok'));
                echo json_encode(['success'=>true,'all_ok'=>$allOk,'checks'=>$checks,'timestamp'=>date('Y-m-d H:i:s')]);
                break;

            // ── Get IP map for a network range ─────────────────────
            case 'get_ip_map':
                $range = $db->real_escape_string($input['network_range'] ?? '');
                if (!$range) { echo json_encode(['success'=>false,'error'=>'No range']); exit; }
                $prefix = preg_replace('/\.0\/24$|\/24$/', '', $range);

                $result = $db->query("SELECT ip_address, name, device_type, status, mac_address, last_seen_at FROM devices WHERE network_range LIKE '$prefix%' OR ip_address LIKE '$prefix.%' ORDER BY INET_ATON(ip_address)");
                $assigned = [];
                while ($row = $result->fetch_assoc()) {
                    $parts = explode('.', $row['ip_address']);
                    $last = (int)end($parts);
                    $assigned[$last] = $row;
                }
                $map = [];
                for ($i = 1; $i <= 254; $i++) {
                    $map[] = [
                        'octet'   => $i,
                        'ip'      => $prefix . '.' . $i,
                        'assigned'=> isset($assigned[$i]),
                        'device'  => $assigned[$i] ?? null,
                    ];
                }
                echo json_encode(['success'=>true, 'map'=>$map, 'prefix'=>$prefix,
                    'total'=>254, 'assigned'=>count($assigned), 'free'=>254-count($assigned)]);
                break;

            // ── Get all network ranges ──────────────────────────────
            case 'get_networks':
                $res = $db->query("SELECT DISTINCT network_range FROM devices WHERE network_range != '' ORDER BY network_range");
                $nets = [];
                while ($r = $res->fetch_assoc()) $nets[] = $r['network_range'];
                // Also add from networks table
                $r2 = $db->query("SELECT network_range FROM networks ORDER BY network_range");
                if ($r2) while ($r = $r2->fetch_assoc()) {
                    $n = $r['network_range'];
                    if (!in_array($n, $nets)) $nets[] = $n;
                }
                echo json_encode(['success'=>true,'networks'=>$nets]);
                break;

            // ── Rename device ───────────────────────────────────────
            case 'rename_device':
                $id   = (int)($input['id'] ?? 0);
                $name = $db->real_escape_string(trim($input['name'] ?? ''));
                if (!$id || !$name) { echo json_encode(['success'=>false,'error'=>'Missing id or name']); exit; }
                $db->query("UPDATE devices SET name='$name' WHERE id=$id");
                echo json_encode(['success'=>true, 'message'=>"Device renamed to '$name'"]);
                break;

            // ── Add device manually ─────────────────────────────────
            case 'add_device':
                $name    = $db->real_escape_string(trim($input['name'] ?? ''));
                $ip      = $db->real_escape_string(trim($input['ip'] ?? ''));
                $type    = $db->real_escape_string($input['device_type'] ?? 'computer');
                $mac     = $db->real_escape_string(trim($input['mac'] ?? ''));
                $location= $db->real_escape_string(trim($input['location'] ?? ''));
                $notes   = $db->real_escape_string(trim($input['notes'] ?? ''));

                if (!$name || !$ip) { echo json_encode(['success'=>false,'error'=>'Name and IP required']); exit; }

                // Extract network range from IP
                $parts = explode('.', $ip);
                $netRange = $parts[0].'.'.$parts[1].'.'.$parts[2].'.0/24';

                $check = $db->query("SELECT id FROM devices WHERE ip_address='$ip'");
                if ($check && $check->num_rows > 0) {
                    echo json_encode(['success'=>false,'error'=>"IP $ip already assigned"]);
                    exit;
                }

                $macVal  = $mac  ? "'$mac'"  : "NULL";
                $locVal  = $location ? "'$location'" : "NULL";
                $noteVal = $notes ? "'$notes'" : "NULL";

                $db->query("INSERT INTO devices (name, ip_address, mac_address, device_type, status, network_range, location, discovery_method, created_at, last_checked_at)
                    VALUES ('$name','$ip',$macVal,'$type','offline','$netRange',$locVal,'manual',NOW(),NOW())");

                $newId = $db->insert_id;
                // Log
                $db->query("INSERT INTO system_logs (device_id, log_level, category, message, created_at)
                    VALUES ($newId,'INFO','device','Device manually added: $name ($ip)',NOW())");

                echo json_encode(['success'=>true,'id'=>$newId,'message'=>"Device $name added at $ip"]);
                break;

            // ── Update global system settings ───────────────────────
            case 'save_settings':
                $settings = $input['settings'] ?? [];
                $configPath = __DIR__ . '/config.php';

                // ── Map of setting keys → define() name + type ──────
                $configMap = [
                    'check_interval'          => ['const'=>'CHECK_INTERVAL',              'type'=>'int'],
                    'ping_timeout'            => ['const'=>'PING_TIMEOUT',                'type'=>'int'],
                    'ping_retries'            => ['const'=>'PING_RETRIES',                'type'=>'int'],
                    'offline_grace'           => ['const'=>'OFFLINE_GRACE_PERIOD',        'type'=>'int'],
                    'latency_host'            => ['const'=>'LATENCY_HOST',                'type'=>'string'],
                    'cpu_threshold'           => ['const'=>'CPU_THRESHOLD',               'type'=>'float'],
                    'ram_threshold'           => ['const'=>'RAM_THRESHOLD',               'type'=>'float'],
                    'network_threshold'       => ['const'=>'NETWORK_THRESHOLD',           'type'=>'float'],
                    'latency_threshold'       => ['const'=>'LATENCY_THRESHOLD',           'type'=>'float'],
                    'disk_threshold'          => ['const'=>'DISK_THRESHOLD',              'type'=>'float'],
                    'enable_network_traffic'  => ['const'=>'ENABLE_NETWORK_TRAFFIC',      'type'=>'bool'],
                    'enable_latency'          => ['const'=>'ENABLE_LATENCY',              'type'=>'bool'],
                    'enable_disk'             => ['const'=>'ENABLE_DISK',                 'type'=>'bool'],
                    'enable_detailed_logging' => ['const'=>'ENABLE_DETAILED_LOGGING',     'type'=>'bool'],
                    'enable_system_logs'      => ['const'=>'ENABLE_SYSTEM_LOGS',          'type'=>'bool'],
                    'arp_refresh'             => ['const'=>'ARP_REFRESH_ENABLED',         'type'=>'bool'],
                    'aggressive_scan'         => ['const'=>'AGGRESSIVE_SCAN_ENABLED',     'type'=>'bool'],
                    'use_common_ips'          => ['const'=>'USE_COMMON_IPS_ONLY',         'type'=>'bool'],
                    'hostname_resolution'     => ['const'=>'HOSTNAME_RESOLUTION_ENABLED', 'type'=>'bool'],
                ];

                // ── Check file is writable ───────────────────────────
                if (!is_writable($configPath)) {
                    echo json_encode(['success'=>false,'error'=>'config.php is not writable. Check file permissions (chmod 664 config.php on Linux, or uncheck Read-only on Windows).']);
                    exit;
                }

                // ── Backup config.php before touching it ────────────
                $backupPath = $configPath . '.bak';
                copy($configPath, $backupPath);

                // ── Read current config.php content ─────────────────
                $content = file_get_contents($configPath);
                if ($content === false) {
                    echo json_encode(['success'=>false,'error'=>'Could not read config.php']);
                    exit;
                }

                $changed = [];

                foreach ($settings as $key => $rawVal) {
                    if (!isset($configMap[$key])) continue;

                    $constName = $configMap[$key]['const'];
                    $type      = $configMap[$key]['type'];

                    // Cast value to correct PHP type
                    if ($type === 'int')    $phpVal = (string)(int)$rawVal;
                    elseif ($type === 'float') $phpVal = rtrim(rtrim(number_format((float)$rawVal, 4, '.', ''), '0'), '.');
                    elseif ($type === 'bool')  $phpVal = ($rawVal === '1' || $rawVal === 'true' || $rawVal === true) ? 'true' : 'false';
                    else                       $phpVal = "'" . addslashes($rawVal) . "'"; // string — wrap in quotes

                    // Replace define('CONST_NAME', oldValue) with new value
                    // Handles: int, float, bool (unquoted), string (quoted)
                    $pattern     = "/define\('{$constName}',\s*[^)]+\)/";
                    $replacement = "define('{$constName}', {$phpVal})";

                    $newContent = preg_replace($pattern, $replacement, $content, 1, $count);

                    if ($count > 0) {
                        $content = $newContent;
                        $changed[] = "{$constName} = {$phpVal}";
                    }
                }

                // ── Atomic write: write to temp file, then rename ────
                $tmpPath = $configPath . '.tmp';
                $written = file_put_contents($tmpPath, $content, LOCK_EX);

                if ($written === false) {
                    echo json_encode(['success'=>false,'error'=>'Failed to write temp file. Check directory permissions.']);
                    exit;
                }

                if (!rename($tmpPath, $configPath)) {
                    // Fallback: direct write
                    file_put_contents($configPath, $content, LOCK_EX);
                }

                $changedCount = count($changed);
                echo json_encode([
                    'success' => true,
                    'message' => "config.php updated — {$changedCount} setting(s) changed. New values are live immediately.",
                    'changed' => $changed,
                    'backup'  => 'config.php.bak created'
                ]);
                break;

            // ── Get settings ────────────────────────────────────────
            case 'get_settings':
                // Read directly from live PHP constants — always reflects config.php on disk
                echo json_encode(['success'=>true,'settings'=>[
                    'check_interval'          => CHECK_INTERVAL,
                    'ping_timeout'            => PING_TIMEOUT,
                    'ping_retries'            => PING_RETRIES,
                    'offline_grace'           => OFFLINE_GRACE_PERIOD,
                    'latency_host'            => LATENCY_HOST,
                    'cpu_threshold'           => CPU_THRESHOLD,
                    'ram_threshold'           => RAM_THRESHOLD,
                    'network_threshold'       => NETWORK_THRESHOLD,
                    'latency_threshold'       => LATENCY_THRESHOLD,
                    'disk_threshold'          => DISK_THRESHOLD,
                    'enable_network_traffic'  => ENABLE_NETWORK_TRAFFIC  ? '1' : '0',
                    'enable_latency'          => ENABLE_LATENCY          ? '1' : '0',
                    'enable_disk'             => ENABLE_DISK             ? '1' : '0',
                    'enable_detailed_logging' => ENABLE_DETAILED_LOGGING ? '1' : '0',
                    'enable_system_logs'      => ENABLE_SYSTEM_LOGS      ? '1' : '0',
                    'arp_refresh'             => ARP_REFRESH_ENABLED         ? '1' : '0',
                    'aggressive_scan'         => AGGRESSIVE_SCAN_ENABLED     ? '1' : '0',
                    'use_common_ips'          => USE_COMMON_IPS_ONLY         ? '1' : '0',
                    'hostname_resolution'     => HOSTNAME_RESOLUTION_ENABLED ? '1' : '0',
                ]]);
                break;

            // ── Delete device ───────────────────────────────────────
            case 'delete_device':
                $id = (int)($input['id'] ?? 0);
                if (!$id) { echo json_encode(['success'=>false,'error'=>'No ID']); exit; }
                $devRes = $db->query("SELECT name,ip_address FROM devices WHERE id=$id");
                $dev = $devRes ? $devRes->fetch_assoc() : null;
                if (!$dev) { echo json_encode(['success'=>false,'error'=>'Device not found']); exit; }
                $db->query("DELETE FROM devices WHERE id=$id");
                echo json_encode(['success'=>true,'message'=>"Deleted {$dev['name']} ({$dev['ip_address']})"]);
                break;

            // ── Get dashboard overview stats ────────────────────────
            case 'get_overview':
                $safeQuery = function($db, $sql) { $r = $db->query($sql); return ($r && $row = $r->fetch_assoc()) ? (int)($row['c'] ?? 0) : 0; };
                $totalDevices    = $safeQuery($db, "SELECT COUNT(*) c FROM devices");
                $onlineDevices   = $safeQuery($db, "SELECT COUNT(*) c FROM devices WHERE status='online'");
                $totalNetworks   = $safeQuery($db, "SELECT COUNT(DISTINCT network_range) c FROM devices WHERE network_range!=''");
                $hasDHM          = $db->query("SHOW TABLES LIKE 'device_health_metrics'")->num_rows > 0;
                $criticalDevices = $hasDHM ? $safeQuery($db, "SELECT COUNT(*) c FROM device_health_metrics WHERE risk_level='CRITICAL'") : 0;
                $hasSched        = $db->query("SHOW TABLES LIKE 'maintenance_schedules'")->num_rows > 0;
                $pendingSchedules= $hasSched ? $safeQuery($db, "SELECT COUNT(*) c FROM maintenance_schedules WHERE status='pending'") : 0;
                $hasLogs         = $db->query("SHOW TABLES LIKE 'system_logs'")->num_rows > 0;
                $recentLogs      = $hasLogs ? $safeQuery($db, "SELECT COUNT(*) c FROM system_logs WHERE created_at > DATE_SUB(NOW(),INTERVAL 24 HOUR)") : 0;
                echo json_encode(['success'=>true,'stats'=>[
                    'total_devices'=>$totalDevices,
                    'online_devices'=>$onlineDevices,
                    'offline_devices'=>$totalDevices-$onlineDevices,
                    'total_networks'=>$totalNetworks,
                    'critical_devices'=>$criticalDevices,
                    'pending_schedules'=>$pendingSchedules,
                    'recent_logs'=>$recentLogs,
                ]]);
                break;

            default:
                echo json_encode(['success'=>false,'error'=>'Unknown action: '.$action]);
        }
    } catch (Exception $e) {
        ob_end_clean();
        echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    }
    ob_end_flush();
    exit;
}

$db = getDB();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Master Config — SafeG</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='1.0em' font-size='85'>⚙️</text></svg>">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{--bg-primary:#000;--bg-secondary:#0f0f0f;--bg-tertiary:#0a0a0a;--border-color:#1a1a1a;--border-hover:#333;--text-primary:#fff;--text-secondary:#888;--text-muted:#555;--accent:#667eea;--green:#10b981;--red:#ef4444;--yellow:#f59e0b;--orange:#f97316;}
body.light-mode{--bg-primary:#f5f5f5;--bg-secondary:#fff;--bg-tertiary:#f0f0f0;--border-color:#e0e0e0;--border-hover:#ccc;--text-primary:#111;--text-secondary:#666;--text-muted:#aaa;}
body{font-family:'Montserrat',sans-serif;background:var(--bg-primary);color:var(--text-primary);min-height:100vh;}
/* Sidebar */
.sidebar{position:fixed;left:0;top:0;width:220px;height:100vh;background:var(--bg-secondary);border-right:1px solid var(--border-color);display:flex;flex-direction:column;z-index:100;overflow-y:auto;}
.sidebar-brand{padding:18px 16px;border-bottom:1px solid var(--border-color);}
.sidebar-brand h2{font-size:13px;font-weight:700;}
.sidebar-brand p{font-size:10px;color:var(--text-secondary);margin-top:2px;}
.nav-group-label{font-size:9px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:1.5px;padding:12px 14px 4px;}
.nav-item{display:flex;align-items:center;gap:9px;padding:8px 12px;border-radius:6px;text-decoration:none;color:var(--text-secondary);font-size:12px;font-weight:500;margin:1px 6px;transition:all .15s;}
.nav-item:hover{background:var(--bg-tertiary);color:var(--text-primary);}
.nav-item.active{background:rgba(102,126,234,.1);color:var(--accent);border:1px solid rgba(102,126,234,.2);}
.nav-item i{font-size:13px;width:16px;text-align:center;}
/* Main */
.main{margin-left:220px;display:flex;flex-direction:column;min-height:100vh;}
.topbar{position:sticky;top:0;z-index:50;background:var(--bg-primary);border-bottom:1px solid var(--border-color);padding:16px 24px;display:flex;align-items:center;justify-content:space-between;}
.topbar-left h2{font-size:18px;font-weight:800;}
.topbar-left p{font-size:11px;color:var(--text-secondary);margin-top:2px;}
.topbar-right{display:flex;align-items:center;gap:8px;}
/* Tabs */
.tabs-bar{display:flex;gap:2px;padding:0 24px;border-bottom:1px solid var(--border-color);background:var(--bg-primary);overflow-x:auto;}
.tab-btn{display:flex;align-items:center;gap:7px;padding:12px 16px;border:none;background:transparent;color:var(--text-secondary);cursor:pointer;font-family:'Montserrat',sans-serif;font-size:12px;font-weight:600;border-bottom:2px solid transparent;transition:all .15s;white-space:nowrap;}
.tab-btn:hover{color:var(--text-primary);}
.tab-btn.active{color:var(--accent);border-bottom-color:var(--accent);}
.tab-btn i{font-size:13px;}
.tab-panel{display:none;padding:24px;}
.tab-panel.active{display:block;}
/* Buttons */
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:6px;font-family:'Montserrat',sans-serif;font-size:12px;font-weight:600;cursor:pointer;border:none;transition:all .15s;text-decoration:none;}
.btn-accent{background:var(--accent);color:#fff;} .btn-accent:hover{opacity:.85;}
.btn-success{background:var(--green);color:#fff;} .btn-success:hover{opacity:.85;}
.btn-danger{background:var(--red);color:#fff;} .btn-danger:hover{opacity:.85;}
.btn-ghost{background:transparent;border:1px solid var(--border-color);color:var(--text-secondary);} .btn-ghost:hover{border-color:var(--border-hover);color:var(--text-primary);}
.btn-sm{padding:6px 10px;font-size:11px;}
.icon-btn{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:6px;padding:7px 10px;cursor:pointer;color:var(--text-primary);font-size:14px;}
/* Overview stats */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;margin-bottom:22px;}
.stat-card{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;padding:16px;position:relative;overflow:hidden;}
.stat-num{font-size:32px;font-weight:800;line-height:1;margin-bottom:4px;}
.stat-label{font-size:10px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.4px;}
/* Section card */
.section{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:8px;margin-bottom:18px;overflow:hidden;}
.section-head{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border-color);}
.section-head h3{font-size:13px;font-weight:700;display:flex;align-items:center;gap:8px;}
.section-body{padding:18px;}
/* Table */
.tbl-wrap{overflow-x:auto;}
table.tbl{width:100%;border-collapse:collapse;}
table.tbl thead th{font-size:10px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.5px;padding:10px 14px;border-bottom:1px solid var(--border-color);text-align:left;white-space:nowrap;}
table.tbl tbody td{padding:10px 14px;border-bottom:1px solid var(--border-color);font-size:12px;vertical-align:middle;}
table.tbl tbody tr:last-child td{border-bottom:none;}
table.tbl tbody tr:hover{background:var(--bg-tertiary);}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;}
.b-online{background:rgba(16,185,129,.12);color:var(--green);}
.b-offline{background:rgba(239,68,68,.1);color:var(--red);}
.b-ok{background:rgba(102,126,234,.1);color:var(--accent);}
.b-miss{background:rgba(239,68,68,.1);color:var(--red);}
/* Form */
.form-group{margin-bottom:14px;}
.form-label{display:block;font-size:10px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.4px;margin-bottom:5px;}
.form-control{width:100%;background:var(--bg-tertiary);border:1px solid var(--border-color);color:var(--text-primary);padding:9px 12px;border-radius:6px;font-family:'Montserrat',sans-serif;font-size:12px;transition:border-color .15s;}
.form-control:focus{outline:none;border-color:var(--accent);}
.form-control option{background:var(--bg-secondary);}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
/* Settings grid */
.settings-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
@media(max-width:700px){.settings-grid{grid-template-columns:1fr;}}
.settings-section-title{font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;padding-bottom:6px;border-bottom:1px solid var(--border-color);}
.toggle-row{display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--border-color);}
.toggle-row:last-child{border-bottom:none;}
.toggle-label{font-size:12px;font-weight:500;}
.toggle-desc{font-size:10px;color:var(--text-secondary);margin-top:1px;}
.toggle-switch{position:relative;width:40px;height:22px;cursor:pointer;flex-shrink:0;}
.toggle-switch input{opacity:0;width:0;height:0;}
.toggle-slider{position:absolute;inset:0;background:var(--border-hover);border-radius:22px;transition:.3s;}
.toggle-slider:before{position:absolute;content:'';height:16px;width:16px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s;}
input:checked+.toggle-slider{background:var(--accent);}
input:checked+.toggle-slider:before{transform:translateX(18px);}
/* IP Map */
.ip-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(50px,1fr));gap:3px;}
.ip-cell{aspect-ratio:1;border-radius:5px;display:flex;flex-direction:column;align-items:center;justify-content:center;font-size:10px;font-weight:700;cursor:pointer;transition:all .12s;border:1px solid transparent;position:relative;}
.ip-cell.free{background:rgba(16,185,129,.08);border-color:rgba(16,185,129,.2);color:var(--green);}
.ip-cell.free:hover{background:rgba(16,185,129,.2);border-color:var(--green);transform:scale(1.12);z-index:2;}
.ip-cell.assigned{background:rgba(102,126,234,.1);border-color:rgba(102,126,234,.25);color:var(--accent);}
.ip-cell.assigned:hover{background:rgba(102,126,234,.2);border-color:var(--accent);transform:scale(1.12);z-index:2;}
.ip-cell.online-cell{background:rgba(16,185,129,.12);border-color:rgba(16,185,129,.35);color:var(--green);}
.ip-cell.offline-cell{background:rgba(239,68,68,.07);border-color:rgba(239,68,68,.2);color:var(--red);}
.ip-cell-tip{position:absolute;bottom:calc(100% + 5px);left:50%;transform:translateX(-50%);background:#1a1a1a;border:1px solid #333;border-radius:5px;padding:5px 8px;font-size:9px;white-space:nowrap;z-index:10;pointer-events:none;opacity:0;transition:opacity .12s;}
.ip-cell:hover .ip-cell-tip{opacity:1;}
.cell-dot{width:4px;height:4px;border-radius:50%;margin-top:1px;}
.progress-wrap{background:var(--bg-tertiary);border-radius:3px;height:5px;overflow:hidden;margin-top:8px;}
.progress-fill{height:100%;border-radius:3px;background:linear-gradient(90deg,var(--accent),#764ba2);transition:width .5s;}
/* Search */
.search-wrap{position:relative;display:inline-block;}
.search-wrap i{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:12px;}
.search-input{background:var(--bg-tertiary);border:1px solid var(--border-color);color:var(--text-primary);padding:8px 12px 8px 30px;border-radius:6px;font-family:'Montserrat',sans-serif;font-size:12px;width:220px;}
.search-input:focus{outline:none;border-color:var(--accent);}
/* Modal */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:500;display:none;align-items:center;justify-content:center;padding:20px;}
.modal-overlay.open{display:flex;}
.modal{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:10px;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;animation:mIn .15s ease;}
@keyframes mIn{from{opacity:0;transform:scale(.96)}to{opacity:1;transform:scale(1)}}
.modal-head{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border-color);}
.modal-head h3{font-size:14px;font-weight:700;}
.modal-close{background:none;border:none;color:var(--text-secondary);cursor:pointer;font-size:20px;line-height:1;}
.modal-body{padding:20px;}
.modal-foot{display:flex;justify-content:flex-end;gap:8px;padding:14px 20px;border-top:1px solid var(--border-color);}
/* Toast */
.toast-area{position:fixed;bottom:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:6px;}
.toast{background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:7px;padding:12px 16px;min-width:240px;display:flex;align-items:center;gap:10px;font-size:12px;font-weight:500;animation:tIn .2s ease;box-shadow:0 8px 24px rgba(0,0,0,.5);}
@keyframes tIn{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:none}}
.toast.success{border-color:var(--green);} .toast.error{border-color:var(--red);}
/* Health checks */
.hc-row{display:flex;align-items:flex-start;gap:10px;padding:10px 0;border-bottom:1px solid var(--border-color);}
.hc-row:last-child{border-bottom:none;}
.hc-icon{font-size:16px;flex-shrink:0;margin-top:1px;}
.hc-label{font-size:12px;font-weight:600;}
.hc-msg{font-size:11px;color:var(--text-secondary);margin-top:2px;}
/* Info box */
.info-box{background:rgba(102,126,234,.06);border:1px solid rgba(102,126,234,.15);border-radius:6px;padding:12px 14px;font-size:11px;color:var(--text-secondary);}
/* Spinner */
.spinner{width:28px;height:28px;border:2px solid var(--border-color);border-top-color:var(--accent);border-radius:50%;animation:sp .7s linear infinite;margin:30px auto;}
@keyframes sp{to{transform:rotate(360deg)}}
.empty{text-align:center;padding:40px;color:var(--text-secondary);}
.empty i{font-size:36px;display:block;margin-bottom:10px;opacity:.2;}
/* Inline edit */
.ie{display:flex;align-items:center;gap:6px;}
.ie input{background:var(--bg-tertiary);border:1px solid var(--border-color);color:var(--text-primary);padding:4px 8px;border-radius:5px;font-family:'Montserrat',sans-serif;font-size:12px;flex:1;min-width:120px;}
.ie input:focus{outline:none;border-color:var(--accent);}
::-webkit-scrollbar{width:4px;height:4px;} ::-webkit-scrollbar-track{background:transparent;} ::-webkit-scrollbar-thumb{background:var(--border-hover);border-radius:2px;}
</style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
  <div class="sidebar-brand"><h2>🌐 Network Monitor</h2><p>SafeG Monitoring System</p></div>
  <nav style="padding:8px 0;">
    <div class="nav-group-label">Monitoring</div>
    <a href="index.php"                   class="nav-item"><i class="bi bi-diagram-3-fill"></i>Network Monitor</a>
    <a href="monitoring.php"              class="nav-item"><i class="bi bi-bar-chart-fill"></i>System Monitor</a>
    <a href="ai_dashboard.php"            class="nav-item"><i class="bi bi-robot"></i>AI Predictions</a>
    <a href="device_health_dashboard.php" class="nav-item"><i class="bi bi-heart-pulse-fill"></i>Device Health</a>
    <a href="predictive_fault.php"        class="nav-item"><i class="bi bi-lightning-charge-fill"></i>Predictive Fault</a>
    <div class="nav-group-label">Maintenance</div>
    <a href="maintenance_history.php"     class="nav-item"><i class="bi bi-clock-history"></i>Maintenance History</a>
    <a href="maintenance_schedules.php"   class="nav-item"><i class="bi bi-calendar-check-fill"></i>Schedules</a>
    <a href="maintenance_reports.php"     class="nav-item"><i class="bi bi-file-earmark-text-fill"></i>Reports</a>
    <div class="nav-group-label">System</div>
    <a href="master_config.php"           class="nav-item active"><i class="bi bi-gear-fill"></i>Master Config</a>
  </nav>
</aside>

<!-- Main -->
<div class="main">

  <!-- Topbar -->
  <div class="topbar">
    <div class="topbar-left">
      <h2>⚙️ Master Configuration</h2>
      <p>IP Management · Device Settings · System Config</p>
    </div>
    <div class="topbar-right">
      <button class="btn btn-success btn-sm" onclick="openAddModal()"><i class="bi bi-plus-lg"></i> Add Device</button>
      <button class="icon-btn" onclick="toggleTheme()"><i class="bi bi-moon-fill" id="themeIcon"></i></button>
    </div>
  </div>

  <!-- Tabs -->
  <div class="tabs-bar">
    <button class="tab-btn active" id="tab-overview"  onclick="switchTab('overview')"><i class="bi bi-grid-fill"></i>Overview</button>
    <button class="tab-btn"        id="tab-ip_map"    onclick="switchTab('ip_map')"><i class="bi bi-grid-3x3-gap-fill"></i>IP Map</button>
    <button class="tab-btn"        id="tab-devices"   onclick="switchTab('devices')"><i class="bi bi-hdd-network-fill"></i>Devices</button>
    <button class="tab-btn"        id="tab-networks"  onclick="switchTab('networks')"><i class="bi bi-globe2"></i>Networks</button>
    <button class="tab-btn"        id="tab-tools"     onclick="switchTab('tools')"><i class="bi bi-tools"></i>DB Tools</button>
    <button class="tab-btn"        id="tab-health"    onclick="switchTab('health')"><i class="bi bi-activity"></i>System Health</button>
    <button class="tab-btn"        id="tab-settings"  onclick="switchTab('settings')"><i class="bi bi-sliders"></i>Settings</button>
  </div>

  <!-- TAB: Overview -->
  <div class="tab-panel active" id="panel-overview">
    <div class="stats-grid">
      <div class="stat-card"><div class="stat-num" id="ov-total"    style="color:var(--accent)">—</div><div class="stat-label">Total Devices</div></div>
      <div class="stat-card"><div class="stat-num" id="ov-online"   style="color:var(--green)">—</div><div class="stat-label">Online Now</div></div>
      <div class="stat-card"><div class="stat-num" id="ov-offline"  style="color:var(--red)">—</div><div class="stat-label">Offline</div></div>
      <div class="stat-card"><div class="stat-num" id="ov-networks" style="color:var(--accent)">—</div><div class="stat-label">Networks</div></div>
      <div class="stat-card"><div class="stat-num" id="ov-critical" style="color:var(--red)">—</div><div class="stat-label">Critical Risk</div></div>
      <div class="stat-card"><div class="stat-num" id="ov-sched"    style="color:var(--yellow)">—</div><div class="stat-label">Pending Schedules</div></div>
      <div class="stat-card"><div class="stat-num" id="ov-logs"     style="color:var(--orange)">—</div><div class="stat-label">Logs (24h)</div></div>
    </div>

    <div class="section">
      <div class="section-head"><h3><i class="bi bi-lightning-charge-fill" style="color:var(--accent)"></i> Quick Actions</h3></div>
      <div class="section-body" style="display:flex;gap:8px;flex-wrap:wrap;">
        <button class="btn btn-accent btn-sm" onclick="switchTab('ip_map')"><i class="bi bi-grid-3x3-gap-fill"></i>IP Map</button>
        <button class="btn btn-success btn-sm" onclick="openAddModal()"><i class="bi bi-plus-circle-fill"></i>Add Device</button>
        <button class="btn btn-ghost btn-sm" onclick="switchTab('devices')"><i class="bi bi-pencil-fill"></i>Manage Devices</button>
        <button class="btn btn-ghost btn-sm" onclick="switchTab('settings')"><i class="bi bi-sliders"></i>Settings</button>
        <a href="index.php" class="btn btn-ghost btn-sm"><i class="bi bi-diagram-3-fill"></i>Network Monitor</a>
      </div>
    </div>

    <div class="section">
      <div class="section-head"><h3><i class="bi bi-info-circle-fill" style="color:var(--accent)"></i> System Info</h3></div>
      <div class="section-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;">
          <div><div class="form-label">PHP Version</div><div style="font-size:13px;font-weight:600;"><?= phpversion() ?></div></div>
          <div><div class="form-label">Server Time (MYT)</div><div style="font-size:13px;font-weight:600;" id="serverTime">—</div></div>
          <div><div class="form-label">Database</div><div style="font-size:13px;font-weight:600;"><?= DB_NAME ?> @ <?= DB_HOST ?></div></div>
          <div><div class="form-label">Check Interval</div><div style="font-size:13px;font-weight:600;"><?= CHECK_INTERVAL ?>s</div></div>
          <div><div class="form-label">Timezone</div><div style="font-size:13px;font-weight:600;">Asia/Kuala_Lumpur (MYT)</div></div>
          <div><div class="form-label">Offline Grace</div><div style="font-size:13px;font-weight:600;"><?= OFFLINE_GRACE_PERIOD ?>s</div></div>
        </div>
      </div>
    </div>
  </div>

  <!-- TAB: IP Map -->
  <div class="tab-panel" id="panel-ip_map">
    <div class="section">
      <div class="section-head">
        <h3><i class="bi bi-grid-3x3-gap-fill" style="color:var(--accent)"></i> IP Address Map</h3>
        <button class="btn btn-success btn-sm" onclick="openAddModal()"><i class="bi bi-plus"></i>Add Device</button>
      </div>
      <div class="section-body">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
          <label class="form-label" style="margin:0;">Network</label>
          <select id="networkSelect" class="form-control" style="width:220px;" onchange="loadIPMap()">
            <option value="">Loading…</option>
          </select>
          <button class="btn btn-ghost btn-sm" onclick="loadIPMap()"><i class="bi bi-arrow-clockwise"></i></button>
        </div>

        <div style="display:flex;gap:18px;margin-bottom:12px;flex-wrap:wrap;font-size:11px;font-weight:600;">
          <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--accent);margin-right:4px;"></span><span id="statAssigned">0</span> Assigned</span>
          <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--green);margin-right:4px;"></span><span id="statOnline">0</span> Online</span>
          <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--red);margin-right:4px;"></span><span id="statOffline">0</span> Offline</span>
          <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:rgba(16,185,129,.2);margin-right:4px;border:1px solid rgba(16,185,129,.4);"></span><span id="statFree">0</span> Free</span>
        </div>
        <div class="progress-wrap" style="margin-bottom:16px;"><div class="progress-fill" id="ipProgress" style="width:0%"></div></div>

        <div id="ipMapGrid" class="ip-grid"><div class="spinner"></div></div>
        <div style="margin-top:14px;font-size:11px;color:var(--text-muted);">
          💡 Click <span style="color:var(--green)">green</span> (free) to add a device. Click <span style="color:var(--accent)">blue/red</span> to view or rename.
        </div>
      </div>
    </div>
  </div>

  <!-- TAB: Devices -->
  <div class="tab-panel" id="panel-devices">
    <div class="section">
      <div class="section-head">
        <h3><i class="bi bi-hdd-network-fill" style="color:var(--accent)"></i> All Devices</h3>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
          <div class="search-wrap"><i class="bi bi-search"></i><input type="text" class="search-input" id="deviceSearch" placeholder="Search…" oninput="filterDevices(this.value)"></div>
          <select id="deviceNetFilter" class="form-control" style="width:160px;" onchange="filterDevices()">
            <option value="">All Networks</option>
          </select>
          <button class="btn btn-success btn-sm" onclick="openAddModal()"><i class="bi bi-plus"></i>Add</button>
        </div>
      </div>
      <div class="tbl-wrap">
        <table class="tbl">
          <thead><tr>
            <th><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)" style="cursor:pointer;"></th>
            <th>#</th><th>Device Name</th><th>IP Address</th><th>Type</th>
            <th>MAC</th><th>Location</th><th>Network</th><th>Status</th><th>Last Seen</th><th>Actions</th>
          </tr></thead>
          <tbody id="deviceTableBody"><tr><td colspan="11" style="text-align:center;padding:30px;"><div class="spinner"></div></td></tr></tbody>
        </table>
      </div>
    </div>
    <!-- Bulk Actions (shown when items selected) -->
    <div class="section" id="bulkActionsCard" style="display:none;">
      <div class="section-head"><h3><i class="bi bi-check2-square" style="color:var(--accent)"></i> Bulk Actions <span id="bulkCountLabel" style="color:var(--text-secondary);font-weight:400;font-size:11px;"></span></h3></div>
      <div class="section-body" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <div>
          <div class="form-label">Rename Pattern (use {N})</div>
          <div style="display:flex;gap:6px;">
            <input type="text" class="form-control" id="bulkRenamePattern" placeholder="PC-Floor2-{N}" style="width:180px;">
            <input type="number" class="form-control" id="bulkRenameStart" value="1" style="width:60px;">
            <button class="btn btn-accent btn-sm" onclick="bulkRename()"><i class="bi bi-pencil-fill"></i>Rename</button>
          </div>
        </div>
        <div>
          <div class="form-label">Set Device Type</div>
          <div style="display:flex;gap:6px;">
            <select class="form-control" id="bulkType" style="width:140px;">
              <option value="computer">🖥️ Computer</option><option value="server">🗄️ Server</option>
              <option value="laptop">💻 Laptop</option><option value="router">📡 Router</option>
              <option value="switch">🔌 Switch</option><option value="printer">🖨️ Printer</option>
              <option value="phone">📱 Phone</option><option value="camera">📷 Camera</option>
              <option value="other">📟 Other</option>
            </select>
            <button class="btn btn-accent btn-sm" onclick="bulkChangeType()"><i class="bi bi-tag-fill"></i>Set</button>
          </div>
        </div>
        <button class="btn btn-danger btn-sm" onclick="bulkDelete()"><i class="bi bi-trash-fill"></i>Delete Selected</button>
      </div>
    </div>
  </div>

  <!-- TAB: Networks -->
  <div class="tab-panel" id="panel-networks">
    <div class="section">
      <div class="section-head">
        <h3><i class="bi bi-globe2" style="color:var(--accent)"></i> Network Ranges</h3>
        <button class="btn btn-success btn-sm" onclick="document.getElementById('addNetModal').classList.add('open')"><i class="bi bi-plus"></i>Add Network</button>
      </div>
      <div class="tbl-wrap">
        <table class="tbl">
          <thead><tr><th>Network Range</th><th>Devices</th><th>Online</th><th>Offline</th><th>Actions</th></tr></thead>
          <tbody id="networksTableBody"><tr><td colspan="5" style="text-align:center;padding:30px;"><div class="spinner"></div></td></tr></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- TAB: DB Tools -->
  <div class="tab-panel" id="panel-tools">
    <!-- DB Stats -->
    <div class="section">
      <div class="section-head">
        <h3><i class="bi bi-database-fill" style="color:var(--accent)"></i> Database Statistics</h3>
        <button class="btn btn-ghost btn-sm" onclick="loadDbStats()"><i class="bi bi-arrow-clockwise"></i>Refresh</button>
      </div>
      <div class="tbl-wrap">
        <table class="tbl">
          <thead><tr><th>Table</th><th>Rows</th><th>Size (MB)</th><th>Status</th></tr></thead>
          <tbody id="dbStatsBody"><tr><td colspan="4" style="text-align:center;padding:20px;color:var(--text-muted);">Click Refresh to load</td></tr></tbody>
        </table>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
      <!-- Stale Cleanup -->
      <div class="section">
        <div class="section-head"><h3><i class="bi bi-trash3-fill" style="color:var(--red)"></i> Remove Stale Devices</h3></div>
        <div class="section-body">
          <div class="form-group">
            <label class="form-label">Offline longer than (days)</label>
            <input type="number" class="form-control" id="staleDays" value="30" min="1" style="width:100px;">
          </div>
          <div id="stalePreview" style="display:none;margin-bottom:10px;padding:10px;background:rgba(239,68,68,.07);border:1px solid rgba(239,68,68,.2);border-radius:6px;font-size:11px;"></div>
          <div style="display:flex;gap:6px;">
            <button class="btn btn-ghost btn-sm" onclick="previewStale()"><i class="bi bi-eye-fill"></i>Preview</button>
            <button class="btn btn-danger btn-sm" onclick="runStaleCleanup()"><i class="bi bi-trash-fill"></i>Delete Stale</button>
          </div>
        </div>
      </div>
      <!-- Log Cleanup -->
      <div class="section">
        <div class="section-head"><h3><i class="bi bi-journal-x" style="color:var(--yellow)"></i> Clear System Logs</h3></div>
        <div class="section-body">
          <div class="form-group">
            <label class="form-label">Delete logs older than (days)</label>
            <input type="number" class="form-control" id="logDays" value="7" min="1" style="width:100px;">
          </div>
          <p style="font-size:11px;color:var(--text-muted);margin-bottom:12px;">Permanently removes old entries from system_logs table.</p>
          <button class="btn btn-danger btn-sm" onclick="clearLogs()"><i class="bi bi-trash-fill"></i>Clear Logs</button>
        </div>
      </div>
      <!-- AI Tools -->
      <div class="section">
        <div class="section-head"><h3><i class="bi bi-robot" style="color:var(--accent)"></i> AI Prediction Tools</h3></div>
        <div class="section-body">
          <p style="font-size:11px;color:var(--text-muted);margin-bottom:12px;">Bulk prediction uses a fast algorithm (status + errors + maintenance). For accuracy, use per-device deep analysis in AI Dashboard.</p>
          <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <button class="btn btn-accent btn-sm" onclick="triggerBulkPredict()"><i class="bi bi-lightning-fill"></i>Bulk Predict All</button>
            <button class="btn btn-danger btn-sm" onclick="resetPredictions()"><i class="bi bi-arrow-counterclockwise"></i>Reset All</button>
          </div>
          <div id="bulkPredictResult" style="margin-top:10px;display:none;font-size:11px;"></div>
        </div>
      </div>
      <!-- Export/Import -->
      <div class="section">
        <div class="section-head"><h3><i class="bi bi-arrow-left-right" style="color:var(--green)"></i> Export / Import</h3></div>
        <div class="section-body">
          <div style="margin-bottom:12px;">
            <a href="master_config.php?action=export_devices_csv" class="btn btn-success btn-sm"><i class="bi bi-download"></i>Export CSV</a>
          </div>
          <div class="form-group" style="margin:0;">
            <label class="form-label">Import from CSV</label>
            <input type="file" id="importFile" accept=".csv" class="form-control" style="padding:6px;">
            <p style="font-size:10px;color:var(--text-muted);margin-top:4px;">Required cols: Name, IP Address, Type, Location</p>
            <button class="btn btn-ghost btn-sm" style="margin-top:8px;" onclick="importCSV()"><i class="bi bi-upload"></i>Import</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- TAB: System Health -->
  <div class="tab-panel" id="panel-health">
    <div class="section">
      <div class="section-head">
        <h3><i class="bi bi-activity" style="color:var(--green)"></i> System Health Check</h3>
        <button class="btn btn-accent btn-sm" onclick="runHealthCheck()"><i class="bi bi-play-fill"></i>Run Check</button>
      </div>
      <div class="section-body" id="healthCheckBody">
        <div class="empty"><i class="bi bi-activity"></i><p>Click "Run Check" to check system health</p></div>
      </div>
    </div>
  </div>

  <!-- TAB: Settings -->
  <div class="tab-panel" id="panel-settings">
    <div class="section">
      <div class="section-head">
        <h3><i class="bi bi-sliders" style="color:var(--accent)"></i> System Settings</h3>
        <button class="btn btn-success btn-sm" onclick="saveSettings()"><i class="bi bi-save-fill"></i>Save All</button>
      </div>
      <div class="section-body">
        <div class="settings-grid">
          <!-- Monitoring -->
          <div>
            <div class="settings-section-title">⚡ Monitoring</div>
            <div class="form-group"><label class="form-label">Check Interval (seconds)</label><input type="number" class="form-control" id="cfg-check_interval" value="<?= CHECK_INTERVAL ?>" min="5" max="300"></div>
            <div class="form-group"><label class="form-label">Ping Timeout (ms)</label><input type="number" class="form-control" id="cfg-ping_timeout" value="<?= PING_TIMEOUT ?>" min="50" max="5000"></div>
            <div class="form-group"><label class="form-label">Ping Retries</label><input type="number" class="form-control" id="cfg-ping_retries" value="<?= PING_RETRIES ?>" min="1" max="10"></div>
            <div class="form-group"><label class="form-label">Offline Grace Period (seconds)</label><input type="number" class="form-control" id="cfg-offline_grace" value="<?= OFFLINE_GRACE_PERIOD ?>" min="30" max="3600"></div>
            <div class="form-group"><label class="form-label">Latency Check Host</label><input type="text" class="form-control" id="cfg-latency_host" value="<?= LATENCY_HOST ?>"></div>
          </div>
          <!-- Thresholds -->
          <div>
            <div class="settings-section-title">📊 Alert Thresholds</div>
            <div class="form-group"><label class="form-label">CPU Change (%)</label><input type="number" step="0.1" class="form-control" id="cfg-cpu_threshold" value="<?= CPU_THRESHOLD ?>"></div>
            <div class="form-group"><label class="form-label">RAM Change (%)</label><input type="number" step="0.1" class="form-control" id="cfg-ram_threshold" value="<?= RAM_THRESHOLD ?>"></div>
            <div class="form-group"><label class="form-label">Network Traffic (MB/s)</label><input type="number" step="0.1" class="form-control" id="cfg-network_threshold" value="<?= NETWORK_THRESHOLD ?>"></div>
            <div class="form-group"><label class="form-label">Latency Threshold (ms)</label><input type="number" step="0.1" class="form-control" id="cfg-latency_threshold" value="<?= LATENCY_THRESHOLD ?>"></div>
            <div class="form-group"><label class="form-label">Disk Change (%)</label><input type="number" step="0.01" class="form-control" id="cfg-disk_threshold" value="<?= DISK_THRESHOLD ?>"></div>
          </div>
          <!-- Features -->
          <div>
            <div class="settings-section-title">🔧 Features</div>
            <div class="toggle-row"><div><div class="toggle-label">Network Traffic Monitoring</div><div class="toggle-desc">Track inbound/outbound bandwidth</div></div><label class="toggle-switch"><input type="checkbox" id="cfg-enable_network_traffic" <?= ENABLE_NETWORK_TRAFFIC?'checked':'' ?>><span class="toggle-slider"></span></label></div>
            <div class="toggle-row"><div><div class="toggle-label">Latency Monitoring</div><div class="toggle-desc">Track ping latency to external host</div></div><label class="toggle-switch"><input type="checkbox" id="cfg-enable_latency" <?= ENABLE_LATENCY?'checked':'' ?>><span class="toggle-slider"></span></label></div>
            <div class="toggle-row"><div><div class="toggle-label">Disk Usage Monitoring</div><div class="toggle-desc">Track disk read/write changes</div></div><label class="toggle-switch"><input type="checkbox" id="cfg-enable_disk" <?= ENABLE_DISK?'checked':'' ?>><span class="toggle-slider"></span></label></div>
            <div class="toggle-row"><div><div class="toggle-label">Detailed Logging</div><div class="toggle-desc">Log all events to system_logs table</div></div><label class="toggle-switch"><input type="checkbox" id="cfg-enable_detailed_logging" <?= ENABLE_DETAILED_LOGGING?'checked':'' ?>><span class="toggle-slider"></span></label></div>
            <div class="toggle-row"><div><div class="toggle-label">System Logs</div><div class="toggle-desc">Enable system event logs</div></div><label class="toggle-switch"><input type="checkbox" id="cfg-enable_system_logs" <?= ENABLE_SYSTEM_LOGS?'checked':'' ?>><span class="toggle-slider"></span></label></div>
          </div>
          <!-- Scan Settings -->
          <div>
            <div class="settings-section-title">🔍 Scan Settings</div>
            <div class="toggle-row"><div><div class="toggle-label">ARP Table Refresh</div><div class="toggle-desc">Refresh ARP before scanning</div></div><label class="toggle-switch"><input type="checkbox" id="cfg-arp_refresh" <?= ARP_REFRESH_ENABLED?'checked':'' ?>><span class="toggle-slider"></span></label></div>
            <div class="toggle-row"><div><div class="toggle-label">Aggressive Scan</div><div class="toggle-desc">Ping all IPs to populate ARP table</div></div><label class="toggle-switch"><input type="checkbox" id="cfg-aggressive_scan" <?= AGGRESSIVE_SCAN_ENABLED?'checked':'' ?>><span class="toggle-slider"></span></label></div>
            <div class="toggle-row"><div><div class="toggle-label">Common IPs Only</div><div class="toggle-desc">Scan .1–.254 range in batches</div></div><label class="toggle-switch"><input type="checkbox" id="cfg-use_common_ips" <?= USE_COMMON_IPS_ONLY?'checked':'' ?>><span class="toggle-slider"></span></label></div>
            <div class="toggle-row"><div><div class="toggle-label">Hostname Resolution</div><div class="toggle-desc">Resolve hostnames via DNS/NetBIOS</div></div><label class="toggle-switch"><input type="checkbox" id="cfg-hostname_resolution" <?= HOSTNAME_RESOLUTION_ENABLED?'checked':'' ?>><span class="toggle-slider"></span></label></div>
            <div style="margin-top:14px;" class="settings-section-title">🤖 AI Prediction</div>
            <div class="form-group"><label class="form-label">Error Window — Full Analysis (days)</label><input type="number" class="form-control" id="cfg-ai_full_days" value="60" min="7" max="365"></div>
            <div class="form-group"><label class="form-label">Error Window — Deep Analysis (days)</label><input type="number" class="form-control" id="cfg-ai_deep_days" value="90" min="14" max="365"></div>
            <div class="form-group"><label class="form-label">Quick Check Cache (minutes)</label><input type="number" class="form-control" id="cfg-ai_cache_min" value="60" min="5" max="1440"></div>
          </div>
        </div>
        <div class="info-box" style="margin-top:16px;"><i class="bi bi-info-circle" style="color:var(--accent);margin-right:6px;"></i>Settings are saved to the <code>system_settings</code> database table. PHP constants in <code>config.php</code> are the live defaults — changes here are persisted in DB and can be read at runtime.</div>
      </div>
    </div>
  </div>

</div><!-- /main -->

<!-- Modals -->
<!-- Add Device -->
<div class="modal-overlay" id="addModal">
  <div class="modal">
    <div class="modal-head"><h3><i class="bi bi-plus-circle-fill" style="color:var(--green);margin-right:6px;"></i>Add Device</h3><button class="modal-close" onclick="closeAddModal()">×</button></div>
    <div class="modal-body">
      <div class="form-row">
        <div class="form-group"><label class="form-label">Device Name *</label><input type="text" class="form-control" id="add-name" placeholder="e.g. Server-HQ-01"></div>
        <div class="form-group"><label class="form-label">IP Address *</label><input type="text" class="form-control" id="add-ip" placeholder="192.168.1.50"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Device Type</label><select class="form-control" id="add-type"><option value="computer">🖥️ Computer</option><option value="server">🗄️ Server</option><option value="laptop">💻 Laptop</option><option value="router">📡 Router</option><option value="switch">🔌 Switch</option><option value="printer">🖨️ Printer</option><option value="phone">📱 Phone</option><option value="camera">📷 Camera</option><option value="other">📟 Other</option></select></div>
        <div class="form-group"><label class="form-label">MAC Address</label><input type="text" class="form-control" id="add-mac" placeholder="AA:BB:CC:DD:EE:FF (optional)"></div>
      </div>
      <div class="form-group"><label class="form-label">Location / Room</label><input type="text" class="form-control" id="add-location" placeholder="e.g. Server Room B, Floor 2"></div>
      <div class="form-group"><label class="form-label">Notes</label><textarea class="form-control" id="add-notes" rows="2" placeholder="Optional notes…" style="resize:vertical;"></textarea></div>
    </div>
    <div class="modal-foot"><button class="btn btn-ghost btn-sm" onclick="closeAddModal()">Cancel</button><button class="btn btn-success btn-sm" onclick="submitAddDevice()"><i class="bi bi-plus-circle-fill"></i>Add Device</button></div>
  </div>
</div>

<!-- IP Cell Detail -->
<div class="modal-overlay" id="ipDetailModal">
  <div class="modal" style="max-width:400px;">
    <div class="modal-head"><h3 id="ipDetailTitle">IP Details</h3><button class="modal-close" onclick="closeIPDetail()">×</button></div>
    <div class="modal-body" id="ipDetailBody"></div>
    <div class="modal-foot" id="ipDetailFooter"></div>
  </div>
</div>

<!-- Add Network -->
<div class="modal-overlay" id="addNetModal">
  <div class="modal" style="max-width:400px;">
    <div class="modal-head"><h3><i class="bi bi-globe-americas" style="color:var(--green);margin-right:6px;"></i>Add Network Range</h3><button class="modal-close" onclick="document.getElementById('addNetModal').classList.remove('open')">×</button></div>
    <div class="modal-body">
      <div class="form-group"><label class="form-label">Network Range *</label><input type="text" class="form-control" id="net-range" placeholder="e.g. 192.168.2.0/24"></div>
      <div class="form-group"><label class="form-label">Label</label><input type="text" class="form-control" id="net-label" placeholder="e.g. Office Floor 3"></div>
    </div>
    <div class="modal-foot"><button class="btn btn-ghost btn-sm" onclick="document.getElementById('addNetModal').classList.remove('open')">Cancel</button><button class="btn btn-success btn-sm" onclick="submitAddNetwork()"><i class="bi bi-plus-circle-fill"></i>Add Network</button></div>
  </div>
</div>

<!-- Edit Device -->
<div class="modal-overlay" id="editDevModal">
  <div class="modal">
    <div class="modal-head"><h3><i class="bi bi-pencil-square" style="color:var(--accent);margin-right:6px;"></i>Edit Device</h3><button class="modal-close" onclick="document.getElementById('editDevModal').classList.remove('open')">×</button></div>
    <div class="modal-body">
      <input type="hidden" id="edit-id">
      <div class="form-row">
        <div class="form-group"><label class="form-label">Device Name *</label><input type="text" class="form-control" id="edit-name"></div>
        <div class="form-group"><label class="form-label">IP Address *</label><input type="text" class="form-control" id="edit-ip"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Device Type</label><select class="form-control" id="edit-type"><option value="computer">🖥️ Computer</option><option value="server">🗄️ Server</option><option value="laptop">💻 Laptop</option><option value="router">📡 Router</option><option value="switch">🔌 Switch</option><option value="printer">🖨️ Printer</option><option value="phone">📱 Phone</option><option value="tablet">📱 Tablet</option><option value="camera">📷 Camera</option><option value="other">📟 Other</option></select></div>
        <div class="form-group"><label class="form-label">MAC Address</label><input type="text" class="form-control" id="edit-mac" placeholder="AA:BB:CC:DD:EE:FF"></div>
      </div>
      <div class="form-group"><label class="form-label">Location</label><input type="text" class="form-control" id="edit-location" placeholder="e.g. Server Room B, Floor 2"></div>
    </div>
    <div class="modal-foot"><button class="btn btn-ghost btn-sm" onclick="document.getElementById('editDevModal').classList.remove('open')">Cancel</button><button class="btn btn-accent btn-sm" onclick="submitEditDevice()"><i class="bi bi-check-circle-fill"></i>Save Changes</button></div>
  </div>
</div>

<div class="toast-area" id="toastArea"></div>

<script>
const TYPE_ICONS={computer:'🖥️',server:'🗄️',laptop:'💻',router:'📡',switch:'🔌',printer:'🖨️',phone:'📱',tablet:'📱',camera:'📷',other:'📟'};
let allDevices=[], currentIPMap=null;

// Theme
function toggleTheme(){ document.body.classList.toggle('light-mode'); const l=document.body.classList.contains('light-mode'); document.getElementById('themeIcon').className=l?'bi bi-sun-fill':'bi bi-moon-fill'; localStorage.setItem('theme',l?'light':'dark'); }
if(localStorage.getItem('theme')==='light'){ document.body.classList.add('light-mode'); document.getElementById('themeIcon').className='bi bi-sun-fill'; }

// Clock
function updateClock(){ const el=document.getElementById('serverTime'); if(el) el.textContent=new Date().toLocaleString('en-MY',{timeZone:'Asia/Kuala_Lumpur',hour12:false}); }
setInterval(updateClock,1000); updateClock();

// Tabs
function switchTab(name){
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('active'));
  const tb=document.getElementById('tab-'+name), pn=document.getElementById('panel-'+name);
  if(tb) tb.classList.add('active'); if(pn) pn.classList.add('active');
  if(name==='ip_map'&&!currentIPMap) loadNetworksIntoSelector();
  if(name==='devices') loadDeviceTable();
  if(name==='settings') loadSettings();
  if(name==='networks') loadNetworksTable();
  if(name==='tools') loadDbStats();
}

// API helper
async function api(body){
  try{ const r=await fetch('master_config.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}); return await r.json(); }
  catch(e){ return {success:false,error:e.message}; }
}

// Toast
function toast(msg,type='success'){
  const area=document.getElementById('toastArea'), t=document.createElement('div');
  t.className='toast '+type;
  t.innerHTML=`<span style="font-size:15px;">${type==='success'?'✅':'❌'}</span><span>${msg}</span>`;
  area.appendChild(t);
  setTimeout(()=>{ t.style.opacity='0'; t.style.transition='.3s'; setTimeout(()=>t.remove(),300); },3500);
}

function escH(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// Overview
async function loadOverview(){
  const d=await api({action:'get_overview'}); if(!d.success) return;
  const s=d.stats;
  document.getElementById('ov-total').textContent=s.total_devices;
  document.getElementById('ov-online').textContent=s.online_devices;
  document.getElementById('ov-offline').textContent=s.offline_devices;
  document.getElementById('ov-networks').textContent=s.total_networks;
  document.getElementById('ov-critical').textContent=s.critical_devices;
  document.getElementById('ov-sched').textContent=s.pending_schedules;
  document.getElementById('ov-logs').textContent=s.recent_logs;
}

// IP Map
async function loadNetworksIntoSelector(){
  const d=await api({action:'get_networks'});
  const sel=document.getElementById('networkSelect');
  const nf=document.getElementById('deviceNetFilter');
  if(!d.success||!d.networks.length){ sel.innerHTML='<option value="">No networks found</option>'; return; }
  sel.innerHTML=d.networks.map(n=>`<option value="${n}">${n}</option>`).join('');
  nf.innerHTML='<option value="">All Networks</option>'+d.networks.map(n=>`<option value="${n}">${n}</option>`).join('');
  loadIPMap();
}

async function loadIPMap(){
  const range=document.getElementById('networkSelect').value; if(!range) return;
  document.getElementById('ipMapGrid').innerHTML='<div class="spinner"></div>';
  const d=await api({action:'get_ip_map',network_range:range});
  if(!d.success){ toast('Failed to load IP map','error'); return; }
  currentIPMap=d;
  let online=0, offline=0;
  d.map.forEach(c=>{ if(c.assigned){ if(c.device?.status==='online') online++; else offline++; } });
  document.getElementById('statAssigned').textContent=d.assigned;
  document.getElementById('statOnline').textContent=online;
  document.getElementById('statOffline').textContent=offline;
  document.getElementById('statFree').textContent=d.free;
  document.getElementById('ipProgress').style.width=Math.round(d.assigned/2.54)+'%';
  const grid=document.getElementById('ipMapGrid'); grid.innerHTML='';
  d.map.forEach(cell=>{
    const el=document.createElement('div'); el.className='ip-cell';
    if(!cell.assigned){
      el.classList.add('free');
      el.innerHTML=`<span>${cell.octet}</span><div class="cell-dot" style="background:var(--green)"></div><div class="ip-cell-tip">${cell.ip}<br>Available</div>`;
      el.onclick=()=>openAddModalWithIP(cell.ip);
    } else {
      const dev=cell.device, online=dev?.status==='online';
      el.classList.add(online?'online-cell':'offline-cell');
      el.innerHTML=`<span>${cell.octet}</span><div class="cell-dot" style="background:${online?'var(--green)':'var(--red)'}"></div><div class="ip-cell-tip">${cell.ip}<br>${escH(dev?.name||'Unknown')}<br>${online?'Online':'Offline'}</div>`;
      el.onclick=()=>showIPDetail(cell);
    }
    grid.appendChild(el);
  });
}

function showIPDetail(cell){
  const dev=cell.device, online=dev?.status==='online';
  const lastSeen=dev?.last_seen_at?new Date(dev.last_seen_at).toLocaleString('en-MY'):'Unknown';
  document.getElementById('ipDetailTitle').innerHTML=`${TYPE_ICONS[dev?.device_type]||'📟'} ${cell.ip}`;
  document.getElementById('ipDetailBody').innerHTML=`
    <div style="display:grid;gap:12px;">
      <div style="display:flex;align-items:center;gap:8px;"><span class="badge ${online?'b-online':'b-offline'}">${online?'🟢 Online':'🔴 Offline'}</span><span style="font-size:11px;color:var(--text-secondary);">${dev?.device_type||'unknown'}</span></div>
      <div class="form-group" style="margin:0;"><label class="form-label">Device Name</label>
        <div class="ie"><input type="text" id="renameIn-${dev?.id}" value="${escH(dev?.name||'')}" class="form-control"><button class="btn btn-accent btn-sm" onclick="renameFromModal(${dev?.id})"><i class="bi bi-check-lg"></i></button></div>
      </div>
      <div><span class="form-label">MAC</span><div style="font-size:12px;margin-top:2px;font-family:monospace;">${dev?.mac_address||'Unknown'}</div></div>
      <div><span class="form-label">Last Seen</span><div style="font-size:12px;margin-top:2px;">${lastSeen}</div></div>
      <div><span class="form-label">Network</span><div style="font-size:12px;margin-top:2px;">${dev?.network_range||'—'}</div></div>
    </div>`;
  document.getElementById('ipDetailFooter').innerHTML=`
    <button class="btn btn-ghost btn-sm" onclick="closeIPDetail()">Close</button>
    <a href="device_detail.php?id=${dev?.id}" class="btn btn-accent btn-sm"><i class="bi bi-box-arrow-up-right"></i>View Detail</a>
    <button class="btn btn-danger btn-sm" onclick="deleteDevice(${dev?.id},'${escH(dev?.name)}')"><i class="bi bi-trash-fill"></i></button>`;
  document.getElementById('ipDetailModal').classList.add('open');
}
function closeIPDetail(){ document.getElementById('ipDetailModal').classList.remove('open'); }

async function renameFromModal(id){
  const val=document.getElementById(`renameIn-${id}`).value.trim(); if(!val) return toast('Name cannot be empty','error');
  const d=await api({action:'rename_device',id,name:val});
  if(d.success){ toast(d.message,'success'); closeIPDetail(); loadIPMap(); loadDeviceTable(); }
  else toast(d.error,'error');
}

// Device Table
async function loadDeviceTable(){
  const tbody=document.getElementById('deviceTableBody');
  tbody.innerHTML='<tr><td colspan="11" style="text-align:center;padding:30px;"><div class="spinner"></div></td></tr>';
  try{
    const r=await fetch('api_get_devices_fast.php?order_by=ip'); const data=await r.json();
    if(!data.success){ toast('Failed to load devices','error'); return; }
    allDevices=data.devices; renderDeviceTable(allDevices);
  }catch(e){ toast('Error loading devices','error'); }
}

function renderDeviceTable(devices){
  const tbody=document.getElementById('deviceTableBody');
  if(!devices.length){ tbody.innerHTML='<tr><td colspan="11"><div class="empty"><i class="bi bi-hdd-network"></i><p>No devices found</p></div></td></tr>'; return; }
  tbody.innerHTML=devices.map((d,i)=>{
    const online=d.online===true||d.online==='true'||d.online===1;
    const icon=TYPE_ICONS[d.type]||'📟';
    const ls=d.last_seen?new Date(d.last_seen).toLocaleString('en-MY'):'—';
    return `<tr>
      <td><input type="checkbox" class="dev-checkbox" value="${d.id}" onchange="updateBulkBar()" style="cursor:pointer;"></td>
      <td style="color:var(--text-muted);font-size:10px;">${i+1}</td>
      <td><div style="display:flex;align-items:center;gap:6px;"><span style="font-size:14px;">${icon}</span><div><div style="font-weight:600;font-size:12px;">${escH(d.name)}</div><div style="font-size:10px;color:var(--text-muted);">ID:${d.id}</div></div></div></td>
      <td><code style="font-size:11px;color:var(--accent);">${d.ip}</code></td>
      <td style="font-size:11px;">${icon} ${d.type||'unknown'}</td>
      <td style="font-size:11px;color:var(--text-secondary);font-family:monospace;">${d.mac||d.mac_address||'—'}</td>
      <td style="font-size:11px;color:var(--text-secondary);">${escH(d.location||'—')}</td>
      <td style="font-size:10px;color:var(--text-muted);">${d.network_range||'—'}</td>
      <td><span class="badge ${online?'b-online':'b-offline'}">${online?'Online':'Offline'}</span></td>
      <td style="font-size:10px;color:var(--text-muted);">${ls}</td>
      <td><div style="display:flex;gap:4px;">
        <button class="btn btn-ghost btn-sm" onclick='openEditModal(${JSON.stringify(d)})' title="Edit"><i class="bi bi-pencil-fill"></i></button>
        <a href="device_detail.php?id=${d.id}" class="btn btn-ghost btn-sm" title="View"><i class="bi bi-box-arrow-up-right"></i></a>
        <button class="btn btn-danger btn-sm" onclick="deleteDevice(${d.id},'${escH(d.name)}')" title="Delete"><i class="bi bi-trash-fill"></i></button>
      </div></td>
    </tr>`;
  }).join('');
}

function filterDevices(search){
  search=(search??document.getElementById('deviceSearch').value).toLowerCase();
  const net=document.getElementById('deviceNetFilter').value;
  renderDeviceTable(allDevices.filter(d=>(!search||d.name.toLowerCase().includes(search)||d.ip.includes(search)||(d.type||'').includes(search))&&(!net||d.network_range===net)));
}

// Checkbox bulk
function toggleSelectAll(cb){ document.querySelectorAll('.dev-checkbox').forEach(c=>c.checked=cb.checked); updateBulkBar(); }
function updateBulkBar(){ const sel=getSelected(); const card=document.getElementById('bulkActionsCard'); if(card) card.style.display=sel.length>0?'block':'none'; const lbl=document.getElementById('bulkCountLabel'); if(lbl) lbl.textContent=`(${sel.length} selected)`; }
function getSelected(){ return [...document.querySelectorAll('.dev-checkbox:checked')].map(c=>parseInt(c.value)); }

// Add device
function openAddModal(){ document.getElementById('addModal').classList.add('open'); }
function openAddModalWithIP(ip){ document.getElementById('add-ip').value=ip; openAddModal(); }
function closeAddModal(){ document.getElementById('addModal').classList.remove('open'); }
async function submitAddDevice(){
  const name=document.getElementById('add-name').value.trim(), ip=document.getElementById('add-ip').value.trim();
  const type=document.getElementById('add-type').value, mac=document.getElementById('add-mac').value.trim();
  const location=document.getElementById('add-location').value.trim(), notes=document.getElementById('add-notes').value.trim();
  if(!name||!ip){ toast('Name and IP are required','error'); return; }
  if(!/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/.test(ip)){ toast('Invalid IP format','error'); return; }
  const d=await api({action:'add_device',name,ip,device_type:type,mac,location,notes});
  if(d.success){ toast(d.message,'success'); closeAddModal(); ['add-name','add-ip','add-mac','add-location','add-notes'].forEach(id=>document.getElementById(id).value=''); loadOverview(); loadIPMap(); loadDeviceTable(); }
  else toast(d.error,'error');
}

// Edit device
function openEditModal(dev){
  document.getElementById('edit-id').value=dev.id;
  document.getElementById('edit-name').value=dev.name||'';
  document.getElementById('edit-ip').value=dev.ip||dev.ip_address||'';
  document.getElementById('edit-mac').value=dev.mac||dev.mac_address||'';
  document.getElementById('edit-location').value=dev.location||'';
  const t=document.getElementById('edit-type'); if(t) t.value=dev.type||dev.device_type||'computer';
  document.getElementById('editDevModal').classList.add('open');
}
async function submitEditDevice(){
  const id=parseInt(document.getElementById('edit-id').value);
  const name=document.getElementById('edit-name').value.trim(), ip=document.getElementById('edit-ip').value.trim();
  const type=document.getElementById('edit-type').value, mac=document.getElementById('edit-mac').value.trim();
  const location=document.getElementById('edit-location').value.trim();
  if(!name||!ip){ toast('Name and IP required','error'); return; }
  const results=await Promise.allSettled([
    api({action:'rename_device',id,name}),
    api({action:'update_device_ip',id,ip}),
    api({action:'update_device_type',id,device_type:type}),
    api({action:'update_device_mac',id,mac}),
    api({action:'update_device_location',id,location}),
  ]);
  const errors=results.filter(r=>r.status==='rejected'||!r.value?.success).map(r=>r.reason||r.value?.error);
  if(!errors.length){ toast('Device updated successfully','success'); document.getElementById('editDevModal').classList.remove('open'); loadDeviceTable(); loadIPMap(); loadOverview(); }
  else toast('Some updates failed: '+errors.join(', '),'error');
}

// Delete
async function deleteDevice(id,name){
  if(!confirm(`Delete device "${name}"?\n\nThis cannot be undone.`)) return;
  const d=await api({action:'delete_device',id});
  if(d.success){ toast(d.message,'success'); closeIPDetail(); loadOverview(); loadIPMap(); loadDeviceTable(); }
  else toast(d.error,'error');
}

// Settings
async function loadSettings(){
  const d=await api({action:'get_settings'}); if(!d.success) return;
  Object.entries(d.settings).forEach(([k,v])=>{ const el=document.getElementById('cfg-'+k); if(!el) return; if(el.type==='checkbox') el.checked=(v==='1'||v==='true'); else el.value=v; });
}
async function saveSettings(){
  const settings={};
  document.querySelectorAll('[id^="cfg-"]').forEach(el=>{ const k=el.id.replace('cfg-',''); settings[k]=el.type==='checkbox'?(el.checked?'1':'0'):el.value; });
  const d=await api({action:'save_settings',settings});
  if(d.success){
    const detail = d.changed && d.changed.length ? `<br><small style="color:var(--text-secondary)">${d.changed.length} constant(s) updated in config.php — live immediately</small>` : '';
    toast(d.message+detail,'success');
    // Reload settings to confirm values now match what's on disk
    setTimeout(loadSettings, 400);
  } else {
    toast(d.error,'error');
  }
}

// Networks tab
async function loadNetworksTable(){
  const tbody=document.getElementById('networksTableBody'); if(!tbody) return;
  const d=await api({action:'get_networks'});
  if(!d.success||!d.networks.length){ tbody.innerHTML='<tr><td colspan="5"><div class="empty"><i class="bi bi-globe2"></i><p>No networks found</p></div></td></tr>'; return; }
  const rows=await Promise.all(d.networks.map(async net=>{
    const map=await api({action:'get_ip_map',network_range:net}); let online=0,offline=0;
    if(map.success) map.map.forEach(c=>{ if(c.assigned){ if(c.device?.status==='online') online++; else offline++; } });
    return {net,assigned:map.assigned||0,online,offline};
  }));
  tbody.innerHTML=rows.map(r=>`<tr>
    <td><code style="color:var(--accent);">${r.net}</code></td>
    <td><span style="font-weight:700;">${r.assigned}</span><span style="font-size:10px;color:var(--text-muted);"> / 254</span></td>
    <td><span style="color:var(--green);font-weight:600;">${r.online}</span></td>
    <td><span style="color:var(--red);font-weight:600;">${r.offline}</span></td>
    <td><div style="display:flex;gap:5px;">
      <button class="btn btn-ghost btn-sm" onclick="switchToIPMap('${r.net}')"><i class="bi bi-grid-3x3-gap-fill"></i>Map</button>
      <button class="btn btn-danger btn-sm" onclick="deleteNetwork('${r.net}',${r.assigned})"><i class="bi bi-trash-fill"></i></button>
    </div></td>
  </tr>`).join('');
}
function switchToIPMap(net){ switchTab('ip_map'); setTimeout(()=>{ const s=document.getElementById('networkSelect'); if(s){s.value=net;loadIPMap();} },200); }
async function submitAddNetwork(){
  const range=document.getElementById('net-range').value.trim(), label=document.getElementById('net-label').value.trim();
  if(!range){ toast('Network range is required','error'); return; }
  const d=await api({action:'add_network',network_range:range,label});
  if(d.success){ toast(d.message,'success'); document.getElementById('addNetModal').classList.remove('open'); document.getElementById('net-range').value=''; document.getElementById('net-label').value=''; loadNetworksTable(); loadNetworksIntoSelector(); }
  else toast(d.error,'error');
}
async function deleteNetwork(range,dc){
  if(!confirm(dc>0?`"${range}" has ${dc} devices. Delete network entry only?`:`Delete network "${range}"?`)) return;
  const d=await api({action:'delete_network',network_range:range,force:true});
  if(d.success){ toast(d.message,'success'); loadNetworksTable(); } else toast(d.error,'error');
}

// DB Tools
async function loadDbStats(){
  const tbody=document.getElementById('dbStatsBody'); if(!tbody) return;
  tbody.innerHTML='<tr><td colspan="4" style="text-align:center;padding:20px;"><div class="spinner"></div></td></tr>';
  const d=await api({action:'db_stats'}); if(!d.success){ toast('Failed to load DB stats','error'); return; }
  tbody.innerHTML=Object.entries(d.tables).map(([tbl,info])=>`<tr>
    <td><code style="color:var(--accent);">${tbl}</code></td>
    <td style="font-weight:600;">${info.rows.toLocaleString()}</td>
    <td style="color:var(--text-secondary);">${info.size_mb} MB</td>
    <td>${info.exists?'<span class="badge b-ok">OK</span>':'<span class="badge b-miss">Missing</span>'}</td>
  </tr>`).join('')+`<tr style="border-top:2px solid var(--border-color);"><td colspan="2" style="font-weight:700;color:var(--accent);">Total</td><td style="font-weight:700;">${d.total_size_mb} MB</td><td></td></tr>`;
}
async function previewStale(){
  const days=parseInt(document.getElementById('staleDays').value)||30;
  const d=await api({action:'db_cleanup_stale',days,confirm:false});
  const p=document.getElementById('stalePreview'); p.style.display='block';
  if(!d.success){ p.textContent='Error: '+d.error; return; }
  p.innerHTML=`<strong>${d.count} devices</strong> would be removed (offline > ${days} days)`+(d.devices.length?'<br><small>'+d.devices.map(x=>`${x.name} (${x.ip_address})`).join(', ')+'</small>':'');
}
async function runStaleCleanup(){
  const days=parseInt(document.getElementById('staleDays').value)||30;
  if(!confirm(`Delete all devices offline > ${days} days? Cannot be undone.`)) return;
  const d=await api({action:'db_cleanup_stale',days,confirm:true});
  if(d.success){ toast(d.message,'success'); loadDeviceTable(); loadOverview(); } else toast(d.error,'error');
}
async function clearLogs(){
  const days=parseInt(document.getElementById('logDays').value)||7;
  if(!confirm(`Delete all system logs older than ${days} days?`)) return;
  const d=await api({action:'clear_system_logs',days});
  if(d.success){ toast(d.message,'success'); loadDbStats(); } else toast(d.error,'error');
}
async function triggerBulkPredict(){
  const res=document.getElementById('bulkPredictResult'); if(res){res.style.display='block';res.innerHTML='<div class="spinner" style="width:20px;height:20px;border-width:2px;margin:0 0 0 4px;display:inline-block;vertical-align:middle;"></div> Running bulk prediction…';}
  const d=await api({action:'trigger_bulk_predict'});
  if(d.success){ toast(d.message,'success'); if(res) res.innerHTML=`<span style="color:var(--green)">✅ ${d.message}</span>`; }
  else{ toast(d.error,'error'); if(res) res.innerHTML=`<span style="color:var(--red)">❌ ${d.error}</span>`; }
}
async function resetPredictions(){
  if(!confirm('Clear ALL prediction data? Devices will show no health score until re-predicted.')) return;
  const d=await api({action:'reset_predictions'});
  if(d.success){ toast(d.message,'success'); loadOverview(); } else toast(d.error,'error');
}
async function importCSV(){
  const file=document.getElementById('importFile').files[0]; if(!file){ toast('Select a CSV file','error'); return; }
  const text=await file.text(), lines=text.split('\n').filter(l=>l.trim());
  const headers=lines[0].split(',').map(h=>h.trim().toLowerCase().replace(/\s+/g,'_').replace(/"/g,''));
  let imp=0,skip=0;
  for(let i=1;i<lines.length;i++){
    const vals=lines[i].split(',').map(v=>v.trim().replace(/"/g,'')); const row={};
    headers.forEach((h,idx)=>row[h]=vals[idx]||'');
    const name=row['name']||row['device_name']||'', ip=row['ip_address']||row['ip']||'';
    if(!name||!ip){skip++;continue;}
    const r=await api({action:'add_device',name,ip,device_type:row['device_type']||row['type']||'computer',location:row['location']||'',mac:row['mac_address']||''});
    if(r.success) imp++; else skip++;
  }
  toast(`Imported ${imp} devices, skipped ${skip}`,'success'); loadDeviceTable(); loadOverview();
}

// Bulk ops
async function bulkRename(){
  const ids=getSelected(), pattern=document.getElementById('bulkRenamePattern').value.trim(), start=parseInt(document.getElementById('bulkRenameStart').value)||1;
  if(!ids.length||!pattern){toast('Select devices and enter a pattern','error');return;}
  if(!pattern.includes('{N}')){toast('Pattern must include {N}','error');return;}
  const d=await api({action:'bulk_rename',ids,pattern,start_num:start});
  if(d.success){toast(d.message,'success');loadDeviceTable();}else toast(d.error,'error');
}
async function bulkChangeType(){
  const ids=getSelected(),type=document.getElementById('bulkType').value;
  if(!ids.length){toast('Select at least one device','error');return;}
  const d=await api({action:'bulk_change_type',ids,device_type:type});
  if(d.success){toast(d.message,'success');loadDeviceTable();}else toast(d.error,'error');
}
async function bulkDelete(){
  const ids=getSelected(); if(!ids.length){toast('Select devices first','error');return;}
  if(!confirm(`Delete ${ids.length} selected devices? Cannot be undone.`)) return;
  const d=await api({action:'bulk_delete',ids});
  if(d.success){toast(d.message,'success');loadDeviceTable();loadOverview();}else toast(d.error,'error');
}

// Health check
async function runHealthCheck(){
  const body=document.getElementById('healthCheckBody'); body.innerHTML='<div class="spinner"></div>';
  const d=await api({action:'get_system_health'}); if(!d.success){toast('Health check failed','error');return;}
  const checks=Object.entries(d.checks).map(([key,info])=>`<div class="hc-row">
    <span class="hc-icon">${info.ok?'✅':'❌'}</span>
    <div style="flex:1;"><div class="hc-label">${key.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase())}</div><div class="hc-msg" style="color:${info.ok?'var(--text-secondary)':'var(--red)'};">${info.msg}</div></div>
    <span class="badge ${info.ok?'b-ok':'b-miss'}">${info.ok?'OK':'ISSUE'}</span>
  </div>`).join('');
  body.innerHTML=`<div style="display:flex;align-items:center;gap:12px;padding:14px;background:rgba(${d.all_ok?'16,185,129':'239,68,68'},.07);border:1px solid rgba(${d.all_ok?'16,185,129':'239,68,68'},.2);border-radius:6px;margin-bottom:16px;">
    <span style="font-size:24px;">${d.all_ok?'✅':'⚠️'}</span>
    <div><div style="font-size:14px;font-weight:700;">${d.all_ok?'All systems healthy':'Issues detected'}</div><div style="font-size:11px;color:var(--text-secondary);">Checked at ${d.timestamp}</div></div>
  </div>${checks}`;
}

// Close modals on overlay click
document.querySelectorAll('.modal-overlay').forEach(o=>o.addEventListener('click',e=>{if(e.target===o)o.classList.remove('open');}));

// Init
loadOverview();
loadNetworksIntoSelector();
</script>
</body>
</html>