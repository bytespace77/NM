<?php
// ============================================
// RUN AI PREDICTIONS FOR ALL DEVICES
// ============================================

header('Content-Type: application/json');

// Database connection
$host = 'localhost';
$db = 'network_monitor';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die(json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]));
}

// Get all devices
$stmt = $pdo->query("SELECT id, name FROM devices");
$devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($devices)) {
    die(json_encode(['success' => false, 'error' => 'No devices found in database']));
}

echo "<h1>Generating AI Predictions for All Devices...</h1>";
echo "<pre>";

$successCount = 0;
$errorCount = 0;

foreach ($devices as $device) {
    echo "\n";
    echo "============================================\n";
    echo "Device ID: {$device['id']}\n";
    echo "Device Name: {$device['name']}\n";
    echo "============================================\n";
    
    try {
        $prediction = calculatePrediction($pdo, $device['id']);
        savePrediction($pdo, $device['id'], $prediction);
        
        echo "✅ SUCCESS!\n";
        echo "Health Score: {$prediction['health_score']}\n";
        echo "Risk Level: {$prediction['risk_level']}\n";
        echo "Predicted Failure: {$prediction['predicted_failure_date']}\n";
        echo "Days Until Failure: {$prediction['days_until_failure']}\n";
        echo "Confidence: {$prediction['confidence_level']}%\n";
        
        $successCount++;
    } catch (Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
        $errorCount++;
    }
}

echo "\n";
echo "============================================\n";
echo "SUMMARY\n";
echo "============================================\n";
echo "Total Devices: " . count($devices) . "\n";
echo "Success: $successCount\n";
echo "Errors: $errorCount\n";
echo "============================================\n";
echo "</pre>";

echo "<h2>Done! <a href='api_ai_predictions.php?action=get_health_metrics'>View Results</a></h2>";

// ============================================
// PREDICTION FUNCTIONS
// ============================================

function calculatePrediction($pdo, $deviceId) {
    // Get device data
    $stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$device) {
        throw new Exception('Device not found');
    }
    
    // ✅ FIXED: completed_date changed to completed_at
    // Get maintenance history
    $stmt = $pdo->prepare("SELECT * FROM maintenance_history WHERE device_id = ? ORDER BY completed_at DESC LIMIT 10");
    $stmt->execute([$deviceId]);
    $maintenanceHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ✅ FIXED: level changed to log_level, timestamp changed to created_at
    // Get system logs (errors/failures)
    $stmt = $pdo->prepare("SELECT COUNT(*) as error_count FROM system_logs WHERE device_id = ? AND log_level = 'ERROR' AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute([$deviceId]);
    $errorCount = $stmt->fetch(PDO::FETCH_ASSOC)['error_count'] ?? 0;
    
    // ============================================
    // AI PREDICTION ALGORITHM
    // ============================================
    
    $healthScore = 100;
    $riskLevel = 'LOW';
    $predictedDays = 180; // Default: 6 months
    $confidenceLevel = 75;
    
    // Factor 1: Days since last maintenance
    $daysSinceLastMaintenance = 999;
    if (!empty($maintenanceHistory)) {
        // ✅ FIXED: completed_date changed to completed_at
        $lastMaintenanceDate = strtotime($maintenanceHistory[0]['completed_at']);
        $daysSinceLastMaintenance = floor((time() - $lastMaintenanceDate) / 86400);
    }
    
    if ($daysSinceLastMaintenance > 180) {
        $healthScore -= 30;
        $predictedDays = 30;
        $riskLevel = 'HIGH';
        $confidenceLevel = 85;
    } elseif ($daysSinceLastMaintenance > 120) {
        $healthScore -= 20;
        $predictedDays = 60;
        $riskLevel = 'MEDIUM';
        $confidenceLevel = 80;
    } elseif ($daysSinceLastMaintenance > 90) {
        $healthScore -= 10;
        $predictedDays = 90;
        $riskLevel = 'MEDIUM';
    }
    
    // Factor 2: Error/Failure rate
    $failureRate = $errorCount / 30; // Errors per day
    if ($failureRate > 1) {
        $healthScore -= 25;
        $predictedDays = min($predictedDays, 45);
        $riskLevel = 'HIGH';
        $confidenceLevel = 90;
    } elseif ($failureRate > 0.5) {
        $healthScore -= 15;
        $predictedDays = min($predictedDays, 75);
    }
    
    // ✅ FIXED: Check for 'online' status string
    // Factor 3: Device status
    if ($device['status'] !== 'online') {
        $healthScore -= 40;
        $predictedDays = 7;
        $riskLevel = 'CRITICAL';
        $confidenceLevel = 95;
    }
    
    // Factor 4: Maintenance frequency
    $maintenanceCount = count($maintenanceHistory);
    if ($maintenanceCount == 0) {
        $healthScore -= 20;
        $predictedDays = min($predictedDays, 90);
    } elseif ($maintenanceCount < 2) {
        $healthScore -= 10;
    }
    
    // Adjust risk level based on final health score
    if ($healthScore < 40) {
        $riskLevel = 'CRITICAL';
    } elseif ($healthScore < 60) {
        $riskLevel = 'HIGH';
    } elseif ($healthScore < 80) {
        $riskLevel = 'MEDIUM';
    } else {
        $riskLevel = 'LOW';
    }
    
    // Calculate predicted failure date
    $predictedFailureDate = date('Y-m-d', strtotime("+{$predictedDays} days"));
    
    return [
        'health_score' => max(0, $healthScore),
        'risk_level' => $riskLevel,
        'predicted_failure_date' => $predictedFailureDate,
        'days_until_failure' => $predictedDays,
        'confidence_level' => $confidenceLevel,
    ];
}

function savePrediction($pdo, $deviceId, $prediction) {
    $sql = "INSERT INTO device_health_metrics 
            (device_id, health_score, risk_level, predicted_failure_date, confidence_level, last_updated)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            health_score = VALUES(health_score),
            risk_level = VALUES(risk_level),
            predicted_failure_date = VALUES(predicted_failure_date),
            confidence_level = VALUES(confidence_level),
            last_updated = NOW()";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $deviceId,
        $prediction['health_score'],
        $prediction['risk_level'],
        $prediction['predicted_failure_date'],
        $prediction['confidence_level']
    ]);
}
?>