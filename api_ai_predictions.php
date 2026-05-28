<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Database connection
$host = 'localhost';
$db = 'network_monitor';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_health_metrics':
        getHealthMetrics($pdo);
        break;

    case 'get_device_health':
        $deviceId = $_GET['device_id'] ?? 0;
        getDeviceHealth($pdo, $deviceId);
        break;

    case 'run_prediction':
        $input = json_decode(file_get_contents('php://input'), true);
        runPrediction($pdo, $input['device_id'] ?? 0, $input['analysis_type'] ?? 'full');
        break;

    case 'get_alerts':
        getAlerts($pdo);
        break;

    case 'acknowledge_alert':
        $input = json_decode(file_get_contents('php://input'), true);
        acknowledgeAlert($pdo, $input['alert_id'] ?? 0);
        break;

    case 'get_analytics':
        getAnalytics($pdo);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

// ============================================
// FUNCTIONS
// ============================================

function getHealthMetrics($pdo)
{
    try {
        // Ensure factors column exists
        try {
            $pdo->exec("ALTER TABLE device_health_metrics ADD COLUMN IF NOT EXISTS factors JSON NULL");
        } catch (Exception $e) {
        }

        // ✅ OPTIMIZED: Only update if predictions are older than 24 hours
        updatePredictionsIfNeeded($pdo);

        $networkRange = $_GET['network_range'] ?? null;

        // ✅ FIXED: Use LEFT JOIN from devices table to show ALL devices
        // Even if they don't have predictions yet
        $sql = "SELECT 
                    d.id as device_id,
                    d.name as device_name,
                    d.ip_address as device_ip,
                    d.network_range,
                    d.status,
                    d.device_type,
                    COALESCE(h.health_score, 50) as health_score,
                    COALESCE(h.risk_level, 'UNKNOWN') as risk_level,
                    h.predicted_failure_date,
                    COALESCE(h.confidence_level, 0) as confidence_level,
                    h.last_updated,
                    COALESCE(DATEDIFF(h.predicted_failure_date, CURDATE()), 365) as days_until_failure,
                    h.factors
                FROM devices d
                LEFT JOIN device_health_metrics h ON d.id = h.device_id
                WHERE 1=1";

        $params = [];

        if ($networkRange) {
            $sql .= " AND d.network_range = ?";
            $params[] = $networkRange;
        }

        $sql .= " ORDER BY 
                  CASE COALESCE(h.risk_level, 'UNKNOWN')
                      WHEN 'CRITICAL' THEN 1
                      WHEN 'HIGH' THEN 2
                      WHEN 'MEDIUM' THEN 3
                      WHEN 'LOW' THEN 4
                      ELSE 5
                  END,
                  COALESCE(h.health_score, 50) ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // ✅ Decode factors JSON string -> array so Flutter receives an object not a string
        foreach ($results as &$row) {
            if (isset($row['factors']) && is_string($row['factors'])) {
                $row['factors'] = json_decode($row['factors'], true) ?: null;
            }
        }
        unset($row);
        
        echo json_encode([
            'success' => true,
            'data' => $results,
            'count' => count($results)
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ============================================
// ✅ IMPROVED: Per-device lazy loading with 24-hour threshold
// Only generates prediction when actually needed for that device
// ============================================
function updatePredictionsIfNeeded($pdo)
{
    // Get all devices
    $stmt = $pdo->query("SELECT id FROM devices ORDER BY id");
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $generated = 0;
    $cached = 0;

    foreach ($devices as $device) {
        $deviceId = $device['id'];

        // Check if THIS device needs update
        if (needsDeviceUpdate($pdo, $deviceId)) {
            try {
                // Generate prediction for THIS device only
                $prediction = calculatePrediction($pdo, $deviceId);
                savePrediction($pdo, $deviceId, $prediction);
                $generated++;
            } catch (Exception $e) {
                // Skip failed devices
                continue;
            }
        } else {
            $cached++;
        }
    }

    // Log for debugging (optional)
    if ($generated > 0) {
        error_log("AI Predictions: Generated $generated new, returned $cached cached");
    }
}

function getDeviceHealth($pdo, $deviceId)
{
    try {
        // ✅ OPTIMIZED: Only update this device if older than 24 hours
        $needsUpdate = needsDeviceUpdate($pdo, $deviceId);

        if ($needsUpdate) {
            $prediction = calculatePrediction($pdo, $deviceId);
            savePrediction($pdo, $deviceId, $prediction);
        }
        // ✅ IMPORTANT: If not updating, just return existing data without touching timestamp

        $sql = "SELECT 
                    h.*,
                    d.name as device_name,
                    d.ip_address as device_ip,
                    d.status,
                    d.device_type,
                    COALESCE(DATEDIFF(h.predicted_failure_date, CURDATE()), 365) as days_until_failure
                FROM device_health_metrics h
                JOIN devices d ON h.device_id = d.id
                WHERE h.device_id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$deviceId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            echo json_encode(['success' => true, 'data' => $result]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Device not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ============================================
// ✅ NEW: Check if device needs update
// ============================================
function needsDeviceUpdate($pdo, $deviceId)
{
    $stmt = $pdo->prepare("
        SELECT last_updated 
        FROM device_health_metrics 
        WHERE device_id = ?
    ");
    $stmt->execute([$deviceId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        return true; // No prediction exists
    }

    $lastUpdated = strtotime($result['last_updated']);
    $now = time();
    $hoursAgo = ($now - $lastUpdated) / 3600;

    // ✅ THRESHOLD: Update if older than 24 hours
    return $hoursAgo >= 24;
}

function runPrediction($pdo, $deviceId, $analysisType = 'full')
{
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $analysisType = $input['analysis_type'] ?? 'full';

        // Quick check: return cached if less than 1 hour old
        if ($analysisType === 'quick') {
            $stmt = $pdo->prepare("SELECT last_updated FROM device_health_metrics WHERE device_id = ?");
            $stmt->execute([$deviceId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $ageMinutes = (time() - strtotime($row['last_updated'])) / 60;
                if ($ageMinutes < 60) {
                    // Return existing cached prediction
                    $stmt2 = $pdo->prepare("SELECT h.*, d.name as device_name FROM device_health_metrics h JOIN devices d ON h.device_id = d.id WHERE h.device_id = ?");
                    $stmt2->execute([$deviceId]);
                    $cached = $stmt2->fetch(PDO::FETCH_ASSOC);
                    echo json_encode(['success' => true, 'message' => 'Quick check (cached)', 'data' => $cached, 'cached' => true]);
                    return;
                }
            }
        }

        // Full and Deep: always recalculate (deep uses 90-day error window)
        $prediction = calculatePrediction($pdo, $deviceId, $analysisType === 'deep' ? 90 : 60);
        savePrediction($pdo, $deviceId, $prediction);

        // Create alert if needed
        if ($prediction['risk_level'] == 'CRITICAL' || $prediction['risk_level'] == 'HIGH') {
            createAlert($pdo, $deviceId, $prediction);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Prediction completed (' . $analysisType . ' analysis)',
            'data' => $prediction
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ============================================
// ✅ IMPROVED AI PREDICTION ALGORITHM
// More accurate calculations based on real data
// ============================================
function calculatePrediction($pdo, $deviceId, $errorDays = 60)
{
    // Get device data
    $stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$device) {
        throw new Exception('Device not found');
    }

    // Get maintenance history (last 6 months)
    $stmt = $pdo->prepare("
        SELECT * FROM maintenance_history 
        WHERE device_id = ? 
        AND completed_at > DATE_SUB(NOW(), INTERVAL 180 DAY)
        ORDER BY completed_at DESC
    ");
    $stmt->execute([$deviceId]);
    $maintenanceHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get error logs (last 60 days for better accuracy)
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_errors,
            SUM(CASE WHEN log_level = 'CRITICAL' THEN 1 ELSE 0 END) as critical_errors,
            SUM(CASE WHEN log_level = 'ERROR' THEN 1 ELSE 0 END) as errors,
            SUM(CASE WHEN log_level = 'WARNING' THEN 1 ELSE 0 END) as warnings
        FROM system_logs 
        WHERE device_id = ? 
        AND created_at > DATE_SUB(NOW(), INTERVAL $errorDays DAY)
    ");
    $stmt->execute([$deviceId]);
    $errorStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get offline history (how often device goes offline)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as offline_incidents
        FROM system_logs
        WHERE device_id = ?
        AND category = 'network'
        AND message LIKE '%OFFLINE%'
        AND created_at > DATE_SUB(NOW(), INTERVAL 90 DAY)
    ");
    $stmt->execute([$deviceId]);
    $offlineIncidents = $stmt->fetch(PDO::FETCH_ASSOC)['offline_incidents'];

    // Get device age (days since first seen)
    $deviceAge = floor((time() - strtotime($device['created_at'])) / 86400);

    // ✅ FIXED: Use last_seen_at column (not last_seen)
    $lastSeenTime = $device['last_seen_at'] ?? $device['last_checked_at'] ?? null;
    $hoursSinceLastSeen = 999; // Default: very old

    if ($lastSeenTime) {
        $hoursSinceLastSeen = (time() - strtotime($lastSeenTime)) / 3600;
    }

    // ============================================
    // IMPROVED CALCULATION
    // ============================================

    $healthScore = 100;
    $riskLevel = 'LOW';
    $predictedDays = 365; // Default: 1 year
    $confidenceLevel = 60; // Start lower, increase with more data

    // FACTOR 1: Maintenance History Analysis (40% weight)
    $maintenanceScore = 100;
    $daysSinceLastMaintenance = 999;

    if (!empty($maintenanceHistory)) {
        $lastMaintenanceDate = strtotime($maintenanceHistory[0]['completed_at']);
        $daysSinceLastMaintenance = floor((time() - $lastMaintenanceDate) / 86400);

        // Count maintenance frequency
        $maintenanceCount = count($maintenanceHistory);
        $avgDaysBetweenMaintenance = $maintenanceCount > 1 ? 180 / $maintenanceCount : 999;

        // Deduct points based on time since last maintenance
        if ($daysSinceLastMaintenance > 180) {
            $maintenanceScore -= 50;
            $predictedDays = 30;
        } elseif ($daysSinceLastMaintenance > 120) {
            $maintenanceScore -= 35;
            $predictedDays = 60;
        } elseif ($daysSinceLastMaintenance > 90) {
            $maintenanceScore -= 20;
            $predictedDays = 120;
        } elseif ($daysSinceLastMaintenance > 60) {
            $maintenanceScore -= 10;
            $predictedDays = 180;
        }

        // Bonus for regular maintenance
        if ($maintenanceCount >= 4) {
            $maintenanceScore += 10;
            $confidenceLevel += 15;
        } elseif ($maintenanceCount >= 2) {
            $confidenceLevel += 10;
        }

        // Check maintenance results
        $failedMaintenance = 0;
        foreach ($maintenanceHistory as $maint) {
            if ($maint['result'] === 'failed') {
                $failedMaintenance++;
            }
        }

        if ($failedMaintenance > 0) {
            $maintenanceScore -= ($failedMaintenance * 10);
            $predictedDays = min($predictedDays, 45);
        }
    } else {
        // No maintenance history — penalise but not catastrophically
        // A new device or unmanaged device shouldn't auto-become CRITICAL
        $maintenanceScore = 65; // was 40 — softer default
        $predictedDays = 90;   // 3 months, not 60
        $confidenceLevel = 45;
    }

    $healthScore -= (100 - $maintenanceScore) * 0.25; // 25% weight (was 40% — too dominant)

    // FACTOR: Last Seen Freshness (15% weight)
    // How recently was device seen on the network
    $lastSeenScore = 100;

    if ($hoursSinceLastSeen > 168) { // > 1 week
        $lastSeenScore = 10;
        if ($riskLevel == 'LOW') $riskLevel = 'HIGH';
        $predictedDays = min($predictedDays, 30);
    } elseif ($hoursSinceLastSeen > 72) { // > 3 days
        $lastSeenScore = 40;
        if ($riskLevel == 'LOW') $riskLevel = 'MEDIUM';
        $predictedDays = min($predictedDays, 60);
    } elseif ($hoursSinceLastSeen > 24) { // > 1 day
        $lastSeenScore = 70;
        if ($riskLevel == 'LOW') $riskLevel = 'MEDIUM';
    } elseif ($hoursSinceLastSeen > 6) { // > 6 hours
        $lastSeenScore = 90;
    }
    // < 6 hours = 100 points (fresh!)

    $healthScore -= (100 - $lastSeenScore) * 0.15; // 15% weight (single deduction)

    // FACTOR 2: Error Rate Analysis (30% weight)
    $errorScore = 100;
    $totalErrors = (int)$errorStats['total_errors'];
    $criticalErrors = (int)$errorStats['critical_errors'];
    $regularErrors = (int)$errorStats['errors'];

    // Calculate daily error rate
    $dailyErrorRate = $totalErrors / 60;

    // ✅ FIXED: Stronger penalties + set risk level
    if ($criticalErrors > 0) {
        $errorScore -= ($criticalErrors * 20); // Increased from 15 to 20
        $predictedDays = min($predictedDays, 14);
        $riskLevel = 'HIGH'; // ✅ Set risk level
        $confidenceLevel += 20;
    }

    if ($dailyErrorRate > 2) {
        $errorScore -= 50; // Increased from 40 to 50
        $riskLevel = 'HIGH'; // ✅ Set risk level
        $predictedDays = min($predictedDays, 30);
    } elseif ($dailyErrorRate > 1) {
        $errorScore -= 35; // Increased from 25 to 35
        if ($riskLevel == 'LOW') $riskLevel = 'MEDIUM'; // ✅ Set risk level
        $predictedDays = min($predictedDays, 60);
    } elseif ($dailyErrorRate > 0.5) {
        $errorScore -= 20; // Increased from 15 to 20
        if ($riskLevel == 'LOW') $riskLevel = 'MEDIUM'; // ✅ Set risk level
        $predictedDays = min($predictedDays, 90);
    } elseif ($dailyErrorRate > 0.1) {
        $errorScore -= 10; // Increased from 5 to 10
    }

    if ($totalErrors > 0) {
        $confidenceLevel += 15; // ✅ Moved outside if statements
    }

    $healthScore -= (100 - $errorScore) * 0.3; // 30% weight

    // ✅ FACTOR 3: Device Status & Uptime (30% weight)
    $statusScore = 100;
    // Normalize: DB stores online as 0/1 integer OR 'online'/'offline' string
    $isOnline = ($device['status'] === 'online') || ($device['status'] === null && ($device['online'] == 1 || $device['online'] === true));

    if (!$isOnline) {
        // Offline penalty — but not instant CRITICAL
        // How long has it been offline?
        if ($hoursSinceLastSeen > 168) { // > 1 week offline
            $healthScore = min($healthScore, 30);
            $statusScore = 0;
            $riskLevel = 'CRITICAL';
            $predictedDays = min($predictedDays, 7);
            $confidenceLevel = 85;
        } elseif ($hoursSinceLastSeen > 48) { // > 2 days offline
            $healthScore = min($healthScore, 40);
            $statusScore = 20;
            $riskLevel = 'HIGH';
            $predictedDays = min($predictedDays, 14);
            $confidenceLevel = 75;
        } else { // Recently offline (< 48h) — could be temporary
            $healthScore = min($healthScore, 55);
            $statusScore = 50;
            if ($riskLevel === 'LOW') $riskLevel = 'MEDIUM';
            $predictedDays = min($predictedDays, 30);
            $confidenceLevel = 65;
        }
    } else {
        // Online — check offline incident frequency
        if ($offlineIncidents > 0) {
            $offlineRate = $offlineIncidents / 90; // per day
            if ($offlineRate > 1) {
                $statusScore -= 40;
                $predictedDays = min($predictedDays, 30);
            } elseif ($offlineRate > 0.5) {
                $statusScore -= 25;
                $predictedDays = min($predictedDays, 60);
            } elseif ($offlineRate > 0.2) {
                $statusScore -= 10;
            }
        }
    }

    $healthScore -= (100 - $statusScore) * 0.3; // 30% weight

    // ✅ FACTOR 4: Device Age (10% weight) 
    // Also use this to infer stability if last_seen is missing
    $ageScore = 100;

    if ($deviceAge > 730) { // > 2 years
        $ageScore -= 30;
    } elseif ($deviceAge > 365) { // > 1 year
        $ageScore -= 15;
    } elseif ($deviceAge > 180) { // > 6 months
        $ageScore -= 5;
    }

    if ($deviceAge > 30) {
        $confidenceLevel += 10; // More data = more confidence
    }

    // ✅ NEW: If last_seen is missing, use created_at age as proxy
    // Older devices with no recent updates might be stale
    if ($deviceAge > 90 && $totalErrors == 0 && empty($maintenanceHistory)) {
        // Device is old but has NO logs and NO maintenance
        // Might be abandoned or forgotten
        $ageScore -= 20;
        if ($riskLevel == 'LOW') $riskLevel = 'MEDIUM';
    }

    $healthScore -= (100 - $ageScore) * 0.1; // 10% weight

    // Ensure health score is between 0-100
    $healthScore = max(0, min(100, $healthScore));

    // Determine risk level based on final health score
    // Only override if health score is the deciding factor — don't downgrade explicit escalations
    if ($healthScore < 25) {
        $riskLevel = 'CRITICAL';
        $predictedDays = min($predictedDays, 14);
        $confidenceLevel = min(95, $confidenceLevel + 15);
    } elseif ($healthScore < 45) {
        if ($riskLevel !== 'CRITICAL') $riskLevel = 'HIGH';
        $predictedDays = min($predictedDays, 45);
        $confidenceLevel = min(90, $confidenceLevel + 10);
    } elseif ($healthScore < 65) {
        if (!in_array($riskLevel, ['CRITICAL','HIGH'])) $riskLevel = 'MEDIUM';
        $predictedDays = min($predictedDays, 120);
        $confidenceLevel = min(85, $confidenceLevel + 5);
    } else {
        if (!in_array($riskLevel, ['CRITICAL','HIGH','MEDIUM'])) $riskLevel = 'LOW';
        // Keep longer prediction
    }

    // Ensure confidence level is between 0-100
    $confidenceLevel = max(50, min(100, $confidenceLevel));

    // Calculate predicted failure date
    $predictedFailureDate = date('Y-m-d', strtotime("+{$predictedDays} days"));

    return [
        'health_score' => round($healthScore, 1),
        'risk_level' => $riskLevel,
        'predicted_failure_date' => $predictedFailureDate,
        'days_until_failure' => $predictedDays,
        'confidence_level' => round($confidenceLevel, 1),
        'factors' => [
            'days_since_maintenance' => $daysSinceLastMaintenance,
            'maintenance_count' => count($maintenanceHistory),
            'total_errors' => $totalErrors,
            'critical_errors' => $criticalErrors,
            'daily_error_rate' => round($dailyErrorRate, 2),
            'offline_incidents' => $offlineIncidents,
            'device_age_days' => $deviceAge,
            'hours_since_last_seen' => round($hoursSinceLastSeen, 1),
            'is_online' => ($device['status'] === 'online')
        ]
    ];
}

function savePrediction($pdo, $deviceId, $prediction)
{
    // Get network_range from device
    $stmt = $pdo->prepare("SELECT network_range FROM devices WHERE id = ?");
    $stmt->execute([$deviceId]);
    $networkRange = $stmt->fetch(PDO::FETCH_ASSOC)['network_range'] ?? 'unknown';

    // ✅ Save current prediction (overwrites old)
    $sql = "INSERT INTO device_health_metrics 
            (device_id, network_range, health_score, risk_level, predicted_failure_date, confidence_level, factors, last_updated)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            health_score = VALUES(health_score),
            risk_level = VALUES(risk_level),
            predicted_failure_date = VALUES(predicted_failure_date),
            confidence_level = VALUES(confidence_level),
            factors = VALUES(factors),
            last_updated = NOW()";

    $stmt = $pdo->prepare($sql);
    $factorsJson = json_encode($prediction['factors']);
    $stmt->execute([
        $deviceId,
        $networkRange,
        $prediction['health_score'],
        $prediction['risk_level'],
        $prediction['predicted_failure_date'],
        $prediction['confidence_level'],
        $factorsJson
    ]);

    // ✅ NEW: Also save historical snapshot (for graphs)
    // Only one snapshot per device per day
    $historySql = "INSERT INTO device_health_history 
                   (device_id, health_score, risk_level, predicted_failure_date, confidence_level, snapshot_date)
                   VALUES (?, ?, ?, ?, ?, CURDATE())
                   ON DUPLICATE KEY UPDATE
                   health_score = VALUES(health_score),
                   risk_level = VALUES(risk_level),
                   predicted_failure_date = VALUES(predicted_failure_date),
                   confidence_level = VALUES(confidence_level)";

    try {
        $historyStmt = $pdo->prepare($historySql);
        $historyStmt->execute([
            $deviceId,
            $prediction['health_score'],
            $prediction['risk_level'],
            $prediction['predicted_failure_date'],
            $prediction['confidence_level']
        ]);
    } catch (Exception $e) {
        // Table might not exist yet - ignore error
        error_log("History save failed (table may not exist): " . $e->getMessage());
    }
}

function createAlert($pdo, $deviceId, $prediction)
{
    // Check if alert already exists for this device
    $stmt = $pdo->prepare("
        SELECT id FROM predictive_alerts 
        WHERE device_id = ? 
        AND is_acknowledged = 0
        AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$deviceId]);

    if ($stmt->rowCount() > 0) {
        return; // Alert already exists
    }

    $message = "Device requires attention! Health score: {$prediction['health_score']}%. ";
    $message .= "Predicted failure in {$prediction['days_until_failure']} days.";

    $severity = ($prediction['risk_level'] == 'CRITICAL') ? 'CRITICAL' : 'WARNING';

    $recommendedAction = "Schedule preventive maintenance within " . max(1, floor($prediction['days_until_failure'] / 2)) . " days.";

    $sql = "INSERT INTO predictive_alerts 
            (device_id, alert_type, severity, message, predicted_date, recommended_action, created_at)
            VALUES (?, 'FAILURE_WARNING', ?, ?, ?, ?, NOW())";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $deviceId,
        $severity,
        $message,
        $prediction['predicted_failure_date'],
        $recommendedAction
    ]);
}

function updateAllPredictions($pdo)
{
    // Get all devices (prioritize online ones)
    $stmt = $pdo->query("SELECT id FROM devices WHERE status = 'online' ORDER BY id");
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updated = 0;
    $skipped = 0;

    foreach ($devices as $device) {
        try {
            // ✅ FIXED: Only update if THIS SPECIFIC device needs it (24 hour threshold)
            if (needsDeviceUpdate($pdo, $device['id'])) {
                $prediction = calculatePrediction($pdo, $device['id']);
                savePrediction($pdo, $device['id'], $prediction);
                $updated++;
            } else {
                $skipped++;
            }
        } catch (Exception $e) {
            continue;
        }
    }

    // Optional: Log for debugging
    error_log("AI Predictions: Updated $updated devices, skipped $skipped (already fresh)");
}

function getAlerts($pdo)
{
    try {
        $severity = $_GET['severity'] ?? null;
        $acknowledged = $_GET['acknowledged'] ?? null;

        $sql = "SELECT 
                    a.*,
                    d.name as device_name,
                    d.ip_address as device_ip,
                    DATEDIFF(a.predicted_date, CURDATE()) as days_until
                FROM predictive_alerts a
                JOIN devices d ON a.device_id = d.id
                WHERE 1=1";

        $params = [];

        if ($severity) {
            $sql .= " AND a.severity = ?";
            $params[] = $severity;
        }

        if ($acknowledged !== null) {
            $sql .= " AND a.is_acknowledged = ?";
            $params[] = ($acknowledged == '1') ? 1 : 0;
        }

        $sql .= " ORDER BY a.created_at DESC LIMIT 100";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $results]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function acknowledgeAlert($pdo, $alertId)
{
    try {
        $sql = "UPDATE predictive_alerts SET is_acknowledged = 1 WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$alertId]);

        echo json_encode(['success' => true, 'message' => 'Alert acknowledged']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getAnalytics($pdo)
{
    try {
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');

        // Get prediction accuracy
        $sql = "SELECT AVG(confidence_level) as avg_confidence FROM device_health_metrics";
        $stmt = $pdo->query($sql);
        $avgConfidence = $stmt->fetch(PDO::FETCH_ASSOC)['avg_confidence'];

        // Get risk distribution
        $sql = "SELECT risk_level, COUNT(*) as count FROM device_health_metrics GROUP BY risk_level";
        $stmt = $pdo->query($sql);
        $riskDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get average health score
        $sql = "SELECT AVG(health_score) as avg_health FROM device_health_metrics";
        $stmt = $pdo->query($sql);
        $avgHealth = $stmt->fetch(PDO::FETCH_ASSOC)['avg_health'];

        echo json_encode([
            'success' => true,
            'data' => [
                'avg_confidence' => round($avgConfidence, 2),
                'avg_health_score' => round($avgHealth, 2),
                'risk_distribution' => $riskDistribution,
                'total_alerts' => getTotalAlerts($pdo),
                'prevented_failures' => calculatePreventedFailures($pdo)
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getTotalAlerts($pdo)
{
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM predictive_alerts");
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

function calculatePreventedFailures($pdo)
{
    // Count devices that were HIGH/CRITICAL but had maintenance and now are LOW/MEDIUM
    $sql = "
        SELECT COUNT(DISTINCT mh.device_id) as count
        FROM maintenance_history mh
        JOIN device_health_metrics dhm ON mh.device_id = dhm.device_id
        WHERE mh.completed_at > DATE_SUB(NOW(), INTERVAL 90 DAY)
        AND dhm.risk_level IN ('LOW', 'MEDIUM')
        AND dhm.health_score > 60
    ";

    $stmt = $pdo->query($sql);
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}