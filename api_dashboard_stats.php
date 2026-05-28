<?php
// ================================================================
// DASHBOARD STATISTICS API
// FIXED: Removed JOIN multiplication bug, accurate device counts
// ================================================================

error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $db = getDB();
    $networkRange = $_GET['network_range'] ?? null;
    $rangeFilter  = $networkRange ? " AND d.network_range = '" . $db->real_escape_string($networkRange) . "'" : '';
    $rangeFilterH = $networkRange ? " AND network_range = '"    . $db->real_escape_string($networkRange) . "'" : '';

    // ================================================================
    // 1. DEVICE COUNTS — one row per device, no JOIN multiplication
    //    Use subquery aggregates instead of direct JOINs
    // ================================================================
    $deviceSql = "
        SELECT
            d.network_range,
            COUNT(DISTINCT d.id)                                                      AS total_devices,
            SUM(CASE WHEN d.status = 'online'  THEN 1 ELSE 0 END)                    AS online_devices,
            SUM(CASE WHEN d.status = 'offline' THEN 1 ELSE 0 END)                    AS offline_devices,

            -- Risk counts (NULL risk = not yet predicted, treated as UNKNOWN)
            SUM(CASE WHEN dhm.risk_level = 'CRITICAL' THEN 1 ELSE 0 END)             AS critical_devices,
            SUM(CASE WHEN dhm.risk_level = 'HIGH'     THEN 1 ELSE 0 END)             AS high_risk_devices,
            SUM(CASE WHEN dhm.risk_level = 'MEDIUM'   THEN 1 ELSE 0 END)             AS medium_risk_devices,
            SUM(CASE WHEN dhm.risk_level = 'LOW'      THEN 1 ELSE 0 END)             AS low_risk_devices,

            -- Healthy = LOW risk OR health_score >= 80
            SUM(CASE WHEN dhm.risk_level = 'LOW' OR dhm.health_score >= 80 THEN 1 ELSE 0 END) AS healthy_devices,

            -- Needs maintenance = MEDIUM or HIGH
            SUM(CASE WHEN dhm.risk_level IN ('MEDIUM','HIGH') THEN 1 ELSE 0 END)     AS needs_maintenance,

            -- Failing = CRITICAL
            SUM(CASE WHEN dhm.risk_level = 'CRITICAL' THEN 1 ELSE 0 END)             AS failing_devices,

            AVG(dhm.health_score)                                                     AS avg_health_score
        FROM devices d
        LEFT JOIN device_health_metrics dhm ON d.id = dhm.device_id
        WHERE 1=1 $rangeFilter
        GROUP BY d.network_range
        ORDER BY d.network_range
    ";

    $result    = $db->query($deviceSql);
    $byNetwork = [];

    $overall = [
        'total_devices'        => 0,
        'online_devices'       => 0,
        'offline_devices'      => 0,
        'critical_devices'     => 0,
        'high_risk_devices'    => 0,
        'medium_risk_devices'  => 0,
        'low_risk_devices'     => 0,
        'healthy_devices'      => 0,
        'needs_maintenance'    => 0,
        'failing_devices'      => 0,
        'avg_health_score'     => 0,
        'total_maintenance'    => 0,
        'maintenance_last_30_days' => 0,
        'overdue_schedules'    => 0,
    ];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $net = [
                'network_range'       => $row['network_range'],
                'total_devices'       => (int)$row['total_devices'],
                'online_devices'      => (int)$row['online_devices'],
                'offline_devices'     => (int)$row['offline_devices'],
                'critical_devices'    => (int)$row['critical_devices'],
                'high_risk_devices'   => (int)$row['high_risk_devices'],
                'medium_risk_devices' => (int)$row['medium_risk_devices'],
                'low_risk_devices'    => (int)$row['low_risk_devices'],
                'healthy_devices'     => (int)$row['healthy_devices'],
                'needs_maintenance'   => (int)$row['needs_maintenance'],
                'failing_devices'     => (int)$row['failing_devices'],
                'avg_health_score'    => round((float)$row['avg_health_score'], 1),
            ];
            $byNetwork[] = $net;

            foreach (
                [
                    'total_devices',
                    'online_devices',
                    'offline_devices',
                    'critical_devices',
                    'high_risk_devices',
                    'medium_risk_devices',
                    'low_risk_devices',
                    'healthy_devices',
                    'needs_maintenance',
                    'failing_devices'
                ] as $key
            ) {
                $overall[$key] += $net[$key];
            }
        }
    }

    // ================================================================
    // 2. AVERAGE HEALTH SCORE — separate query, no GROUP BY distortion
    // ================================================================
    $avgSql    = "SELECT AVG(health_score) as avg FROM device_health_metrics WHERE 1=1 $rangeFilterH";
    $avgResult = $db->query($avgSql);
    if ($avgResult && $avgRow = $avgResult->fetch_assoc()) {
        $overall['avg_health_score'] = round((float)$avgRow['avg'], 1);
    }

    // ================================================================
    // 3. MAINTENANCE COUNTS — separate queries, no row multiplication
    // ================================================================
    $maintSql = "
        SELECT COUNT(*) as total,
               SUM(CASE WHEN completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as last30
        FROM maintenance_history mh
        JOIN devices d ON mh.device_id = d.id
        WHERE 1=1 $rangeFilter
    ";
    $maintResult = $db->query($maintSql);
    if ($maintResult && $maintRow = $maintResult->fetch_assoc()) {
        $overall['total_maintenance']        = (int)$maintRow['total'];
        $overall['maintenance_last_30_days'] = (int)$maintRow['last30'];
    }

    $overdueSql = "
        SELECT COUNT(*) as overdue
        FROM maintenance_schedules ms
        JOIN devices d ON ms.device_id = d.id
        WHERE ms.status = 'pending'
          AND ms.scheduled_date <= NOW()
          $rangeFilter
    ";
    $overdueResult = $db->query($overdueSql);
    if ($overdueResult && $overdueRow = $overdueResult->fetch_assoc()) {
        $overall['overdue_schedules'] = (int)$overdueRow['overdue'];
    }

    // ================================================================
    // 4. RECENT ALERTS
    // ================================================================
    $alertsSql = "
        SELECT pa.id, pa.device_id, pa.alert_type, pa.severity,
               pa.message, pa.predicted_date, pa.is_acknowledged,
               d.name as device_name, d.ip_address, d.network_range
        FROM predictive_alerts pa
        JOIN devices d ON pa.device_id = d.id
        WHERE pa.is_acknowledged = 0 $rangeFilter
        ORDER BY
            CASE pa.severity
                WHEN 'CRITICAL' THEN 1
                WHEN 'WARNING'  THEN 2
                ELSE 3
            END,
            pa.created_at DESC
        LIMIT 10
    ";
    $alertsResult = $db->query($alertsSql);
    $recentAlerts = [];
    if ($alertsResult) {
        while ($row = $alertsResult->fetch_assoc()) {
            $recentAlerts[] = [
                'id'           => (int)$row['id'],
                'device_id'    => (int)$row['device_id'],
                'device_name'  => $row['device_name'],
                'ip_address'   => $row['ip_address'],
                'network_range' => $row['network_range'],
                'alert_type'   => $row['alert_type'],
                'severity'     => $row['severity'],
                'message'      => $row['message'],
                'predicted_date' => $row['predicted_date'],
            ];
        }
    }

    // ================================================================
    // 5. RESPONSE
    // ================================================================
    echo json_encode([
        'success'        => true,
        'timestamp'      => date('Y-m-d H:i:s'),
        'network_filter' => $networkRange,
        'overall'        => $overall,
        'by_network'     => $byNetwork,
        'recent_alerts'  => $recentAlerts,
        'summary'        => sprintf(
            "%d devices: %d healthy, %d needs maintenance, %d failing",
            $overall['total_devices'],
            $overall['healthy_devices'],
            $overall['needs_maintenance'],
            $overall['failing_devices']
        ),
    ]);
} catch (Exception $e) {
    error_log("Dashboard stats API error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error'   => 'Internal server error',
        'details' => $e->getMessage(),
    ]);
}
