<?php
function checkAndGenerateCriticalReport($predictionData) {
    $confidence = $predictionData['confidence_score'] ?? 0;
    $healthScore = $predictionData['health_score'] ?? 100;
    
    $isCritical = ($confidence >= 80) || ($healthScore <= 30);
    
    if (!$isCritical) {
        return false; 
    }

    // ── Duplicate guard: skip if critical report already generated today ──
    $db = getDB();
    $deviceId = (int)($predictionData['device_id']);
    $stmt = $db->prepare("
        SELECT id FROM maintenance_reports
        WHERE device_id = ?
          AND report_type = 'ai_prediction'
          AND DATE(generated_at) = CURDATE()
        LIMIT 1
    ");
    $stmt->bind_param('i', $deviceId);
    $stmt->execute();
    $already = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($already) {
        return false; // Already have a report today, skip auto-generation
    }
    // ─────────────────────────────────────────────────────────────────────
    
    // Generate critical report
    require_once 'ReportGenerator.php';
    
    $generator = new ReportGenerator();
    $result = $generator->generateFromAIPrediction([
        'device_id' => $predictionData['device_id'],
        'equipment_type_id' => $predictionData['equipment_type_id'] ?? 1,
        'predicted_issue' => $predictionData['predicted_issue'],
        'confidence_score' => $confidence,
        'force' => true  // auto-critical bypasses the per-call duplicate check (we already checked above)
    ]);
    
    if ($result['success'] && !($result['duplicate'] ?? false)) {
        // Flag as critical in database
        $stmt = $db->prepare("
            UPDATE maintenance_reports 
            SET report_status = 'critical' 
            WHERE id = ?
        ");
        $stmt->bind_param('i', $result['report_id']);
        $stmt->execute();
        
        return [
            'critical_report_generated' => true,
            'report_id' => $result['report_id'],
            'report_number' => $result['report_number']
        ];
    }
    
    return false;
}
?>