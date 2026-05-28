<?php
// ================================================================
// SIMPLIFIED AI PREDICTION ENGINE (LEGACY/BACKUP)
// ⚠️  NOTE: This is a simplified backup engine.
// The main prediction engine is in api_ai_predictions.php
// ai_dashboard.php uses api_ai_predictions.php (full algorithm)
// Only use this file if api_ai_predictions.php is unavailable.
// ================================================================

require_once 'config.php';

class SimpleAIPredictionEngine
{
    private $db;

    public function __construct()
    {
        $this->db = getDB();
    }

    // ================================================================
    // MAIN PREDICTION FUNCTION
    // Auto-generates if data is older than 24 hours (1 day)
    // ================================================================
    public function predictDeviceHealth($deviceId)
    {
        try {
            // Get current device info
            $device = $this->getDeviceInfo($deviceId);

            if (!$device) {
                return [
                    'success' => false,
                    'error' => 'Device not found',
                ];
            }

            // ✅ AUTO-GENERATE: Check if prediction needs update (older than 24 hours)
            $existingPrediction = $this->getExistingPrediction($deviceId);

            if ($existingPrediction && !$this->needsUpdate($existingPrediction)) {
                // Return cached prediction (less than 24 hours old)
                return $existingPrediction;
            }

            // Calculate health score from current status
            $healthScore = $this->calculateSimpleHealth($device);

            // Determine risk level
            $riskLevel = $this->calculateRiskLevel($healthScore);

            // Predict days until failure
            $daysUntilFailure = $this->predictSimpleFailure($healthScore, $device);

            // Calculate confidence (lower since we have limited data)
            $confidenceLevel = $this->calculateSimpleConfidence($device);

            // Calculate predicted failure date
            $predictedFailureDate = date('Y-m-d', strtotime("+{$daysUntilFailure} days"));

            // Save prediction
            $this->savePrediction($deviceId, [
                'health_score' => $healthScore,
                'risk_level' => $riskLevel,
                'days_until_failure' => $daysUntilFailure,
                'predicted_failure_date' => $predictedFailureDate,
                'confidence_level' => $confidenceLevel,
            ]);

            return [
                'success' => true,
                'device_id' => $deviceId,
                'health_score' => round($healthScore, 1),
                'risk_level' => $riskLevel,
                'days_until_failure' => $daysUntilFailure,
                'predicted_failure_date' => $predictedFailureDate,
                'confidence_level' => round($confidenceLevel, 1),
                'data_source' => 'generated',
                'cached' => false,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // ================================================================
    // GET EXISTING PREDICTION
    // ================================================================
    private function getExistingPrediction($deviceId)
    {
        $sql = "SELECT 
                    dhm.*,
                    DATEDIFF(dhm.predicted_failure_date, CURDATE()) as days_until_failure
                FROM device_health_metrics dhm
                WHERE dhm.device_id = $deviceId";

        $result = $this->db->query($sql);

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();

            return [
                'success' => true,
                'device_id' => $deviceId,
                'health_score' => (float)$row['health_score'],
                'risk_level' => $row['risk_level'],
                'days_until_failure' => max(0, (int)$row['days_until_failure']),
                'predicted_failure_date' => $row['predicted_failure_date'],
                'confidence_level' => (float)$row['confidence_level'],
                'last_updated' => $row['last_updated'],
                'data_source' => 'cached',
                'cached' => true,
            ];
        }

        return null;
    }

    // ================================================================
    // CHECK IF PREDICTION NEEDS UPDATE
    // Returns true if older than 24 hours (1 day)
    // ================================================================
    private function needsUpdate($prediction)
    {
        if (!isset($prediction['last_updated'])) {
            return true;
        }

        $lastUpdated = strtotime($prediction['last_updated']);
        $now = time();
        $hoursAgo = ($now - $lastUpdated) / 3600;

        // ✅ THRESHOLD: Update if older than 24 hours (1 day)
        return $hoursAgo >= 24;
    }

    // ================================================================
    // GET DEVICE INFO
    // ================================================================
    private function getDeviceInfo($deviceId)
    {
        $sql = "SELECT * FROM devices WHERE id = $deviceId LIMIT 1";
        $result = $this->db->query($sql);

        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }

        return null;
    }

    // ================================================================
    // CALCULATE SIMPLE HEALTH SCORE
    // Based on current status and last_seen time
    // ================================================================
    private function calculateSimpleHealth($device)
    {
        $healthScore = 50; // Start at 50% (neutral)

        // ✅ Factor 1: Current Status (60 points)
        if ($device['status'] === 'online') {
            $healthScore += 40; // Online = +40 (90% total)
        } else if ($device['status'] === 'offline') {
            $healthScore -= 30; // Offline = -30 (20% total)
        }

        // ✅ Factor 2: Last Seen Recency (10 points)
        // Try last_seen_at first (matches main API), fallback to last_seen
        $lastSeenField = $device['last_seen_at'] ?? $device['last_seen'] ?? null;
        if (isset($lastSeenField) && $lastSeenField) {
            $lastSeen = strtotime($lastSeenField);
            $now = time();
            $hoursAgo = ($now - $lastSeen) / 3600;

            if ($hoursAgo < 1) {
                $healthScore += 10; // Seen recently = excellent
            } else if ($hoursAgo < 24) {
                $healthScore += 5;  // Seen today = good
            } else if ($hoursAgo < 168) {
                $healthScore += 2;  // Seen this week = fair
            }
            // Else no bonus
        }

        // Ensure 0-100 range
        return max(0, min(100, $healthScore));
    }

    // ================================================================
    // CALCULATE RISK LEVEL
    // ================================================================
    private function calculateRiskLevel($healthScore)
    {
        if ($healthScore >= 80) return 'LOW';
        if ($healthScore >= 60) return 'MEDIUM';
        if ($healthScore >= 40) return 'HIGH';
        return 'CRITICAL';
    }

    // ================================================================
    // PREDICT SIMPLE FAILURE
    // ================================================================
    private function predictSimpleFailure($healthScore, $device)
    {
        // Base prediction on health score
        if ($healthScore >= 90) return 365; // 1 year
        if ($healthScore >= 80) return 180; // 6 months
        if ($healthScore >= 70) return 90;  // 3 months
        if ($healthScore >= 60) return 60;  // 2 months
        if ($healthScore >= 50) return 45;  // 1.5 months
        if ($healthScore >= 40) return 30;  // 1 month
        if ($healthScore >= 30) return 21;  // 3 weeks
        return 14; // 2 weeks (critical)
    }

    // ================================================================
    // CALCULATE SIMPLE CONFIDENCE
    // ================================================================
    private function calculateSimpleConfidence($device)
    {
        // Since we only have current status, confidence is lower
        $confidence = 30; // Base confidence

        // If device is online, slightly higher confidence
        if ($device['status'] === 'online') {
            $confidence += 10;
        }

        // If we have recent last_seen, higher confidence
        // Try last_seen_at first (matches main API), fallback to last_seen
        $lastSeenField = $device['last_seen_at'] ?? $device['last_seen'] ?? null;
        if (isset($lastSeenField) && $lastSeenField) {
            $lastSeen = strtotime($lastSeenField);
            $now = time();
            $hoursAgo = ($now - $lastSeen) / 3600;

            if ($hoursAgo < 24) {
                $confidence += 15; // Seen recently = better confidence
            } else if ($hoursAgo < 168) {
                $confidence += 5;  // Seen this week
            }
        }

        return max(25, min(60, $confidence)); // 25-60% range
    }

    // ================================================================
    // SAVE PREDICTION TO DATABASE
    // ================================================================
    private function savePrediction($deviceId, $prediction)
    {
        $healthScore = $prediction['health_score'];
        $riskLevel = $this->db->real_escape_string($prediction['risk_level']);
        $predictedDate = $this->db->real_escape_string($prediction['predicted_failure_date']);
        $confidence = $prediction['confidence_level'];

        // Check if prediction exists
        $checkSql = "SELECT id FROM device_health_metrics WHERE device_id = $deviceId";
        $result = $this->db->query($checkSql);

        // Build factors JSON matching main prediction engine format
        $factorsJson = json_encode([
            'is_online' => ($this->db->real_escape_string($prediction['risk_level']) !== 'CRITICAL'),
            'device_age_days' => 0,
            'total_errors' => 0,
            'critical_errors' => 0,
            'offline_incidents' => 0,
            'days_since_maintenance' => 999,
            'hours_since_last_seen' => 0,
        ]);
        $factorsJsonEsc = $this->db->real_escape_string($factorsJson);

        if ($result && $result->num_rows > 0) {
            // Update existing
            $sql = "UPDATE device_health_metrics SET
                    health_score = $healthScore,
                    risk_level = '$riskLevel',
                    predicted_failure_date = '$predictedDate',
                    confidence_level = $confidence,
                    factors = '$factorsJsonEsc',
                    last_updated = NOW()
                    WHERE device_id = $deviceId";
        } else {
            // Insert new (with network_range from device)
            $networkSql = "SELECT network_range FROM devices WHERE id = $deviceId";
            $netResult = $this->db->query($networkSql);
            $networkRange = 'unknown';
            if ($netResult && $netResult->num_rows > 0) {
                $row = $netResult->fetch_assoc();
                $networkRange = $this->db->real_escape_string($row['network_range']);
            }

            $sql = "INSERT INTO device_health_metrics 
                    (device_id, network_range, health_score, risk_level, predicted_failure_date, confidence_level, factors, last_updated)
                    VALUES 
                    ($deviceId, '$networkRange', $healthScore, '$riskLevel', '$predictedDate', $confidence, '$factorsJsonEsc', NOW())
                    ON DUPLICATE KEY UPDATE
                    health_score = $healthScore,
                    risk_level = '$riskLevel',
                    predicted_failure_date = '$predictedDate',
                    confidence_level = $confidence,
                    factors = '$factorsJsonEsc',
                    last_updated = NOW()";
        }

        $this->db->query($sql);
    }
}

// ================================================================
// API ENDPOINT
// ================================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    $engine = new SimpleAIPredictionEngine();

    // Get device ID
    $deviceId = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $deviceId = $input['device_id'] ?? null;
    } else {
        $deviceId = $_GET['device_id'] ?? null;
    }

    if (!$deviceId) {
        echo json_encode([
            'success' => false,
            'error' => 'device_id is required',
        ]);
        exit;
    }

    // Run prediction
    $result = $engine->predictDeviceHealth($deviceId);
    echo json_encode($result, JSON_PRETTY_PRINT);
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed',
    ]);
}
