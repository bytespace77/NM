<?php
// ================================================================
// API: GET REPORT SUMMARY WITH AI/MANUAL/CRITICAL COUNTS
// Returns report statistics for dashboard
// ================================================================

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once 'config.php';

try {
    $db = getDB();
    
    // Total reports
    $stmt = $db->query("SELECT COUNT(*) as total FROM maintenance_reports");
    $total = $stmt->fetch_assoc()['total'];
    
    // AI Prediction reports
    $stmt = $db->query("
        SELECT COUNT(*) as ai_count 
        FROM maintenance_reports 
        WHERE report_type = 'ai_prediction'
    ");
    $aiCount = $stmt->fetch_assoc()['ai_count'];
    
    // Manual/Scheduled Maintenance reports
    $stmt = $db->query("
        SELECT COUNT(*) as manual_count 
        FROM maintenance_reports 
        WHERE report_type = 'scheduled_maintenance'
    ");
    $manualCount = $stmt->fetch_assoc()['manual_count'];
    
    // Critical reports (high confidence OR flagged as critical)
    $stmt = $db->query("
        SELECT COUNT(*) as critical_count 
        FROM maintenance_reports 
        WHERE confidence_score >= 80 
           OR report_status = 'critical'
    ");
    $criticalCount = $stmt->fetch_assoc()['critical_count'];
    
    // Return summary in Flutter-expected format
    echo json_encode([
        'success' => true,
        'overall' => [
            'total_reports' => (int)$total,
            'ai_generated' => (int)$aiCount,
            'maintenance_generated' => (int)$manualCount,
            'critical_issues' => (int)$criticalCount
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>