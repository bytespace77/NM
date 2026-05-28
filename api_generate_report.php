<?php
// ================================================================
// API: GENERATE MAINTENANCE REPORT
// Generates DOCX and PDF reports from AI predictions or maintenance
// ================================================================

require_once 'config.php';
require_once 'ReportGenerator.php';
require_once 'auto_critical_report.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $generator = new ReportGenerator();
    
    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    
    $reportType = $input['report_type'] ?? 'manual';

    // Normalize aliases
    if ($reportType === 'predictive_maintenance' || $reportType === 'manual') {
        $reportType = 'ai_prediction';
    }
    
    // ================================================================
    // GENERATE FROM AI PREDICTION
    // ================================================================
    if ($reportType === 'ai_prediction') {
        $predictionData = [
            'device_id' => $input['device_id'],
            'equipment_type_id' => $input['equipment_type_id'] ?? 1,
            'predicted_issue' => $input['predicted_issue'],
            'confidence_score' => $input['confidence_score'],
            'ai_prediction_id' => $input['ai_prediction_id'] ?? null,
            'health_score' => $input['health_score'] ?? 100,
            'force' => !empty($input['force'])   // allow intentional re-generation
        ];
        
        // Generate the report
        $result = $generator->generateFromAIPrediction($predictionData);
        
        // Check if this should be flagged as CRITICAL and auto-generate
        if ($result['success']) {
            $criticalResult = checkAndGenerateCriticalReport($predictionData);
            
            if ($criticalResult) {
                // Log that critical report was auto-generated
                error_log("Critical report auto-generated: " . $criticalResult['report_number']);
                
                // Add critical info to response
                $result['critical_report_generated'] = true;
                $result['critical_report_id'] = $criticalResult['report_id'];
            }
        }
        
        echo json_encode($result);
    }
    
    // ================================================================
    // GENERATE FROM COMPLETED MAINTENANCE
    // ================================================================
    else if ($reportType === 'scheduled_maintenance') {
        $maintenanceScheduleId = $input['maintenance_schedule_id'];
        
        if (!$maintenanceScheduleId) {
            echo json_encode([
                'success' => false,
                'error' => 'maintenance_schedule_id is required'
            ]);
            exit;
        }
        
        $result = $generator->generateFromMaintenance($maintenanceScheduleId);
        
        echo json_encode($result);
    }
    
    // ================================================================
    // INVALID TYPE
    // ================================================================
    else {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid report type. Use: ai_prediction or scheduled_maintenance'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>