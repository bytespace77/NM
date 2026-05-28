<?php
set_time_limit(300);
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 300);

if (function_exists('opcache_reset')) {
    opcache_reset();
}
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Only POST requests allowed']);
    exit();
}

require_once 'ReportGenerator.php';

try {
    // Get POST data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception('Invalid JSON data');
    }

    // Validate required fields
    $required = ['device_id', 'equipment_type_id', 'technician_name', 'work_performed', 'template_type'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new Exception("Missing required field: {$field}");
        }
    }

    // Validate template type
    $validTemplates = ['HARDWARE', 'TOWER', 'RADAR', 'DATABASE'];
    if (!in_array($data['template_type'], $validTemplates)) {
        throw new Exception("Invalid template_type. Must be one of: " . implode(', ', $validTemplates));
    }

    // Prepare report data
    $reportData = [
        'device_id' => (int)$data['device_id'],
        'equipment_type_id' => (int)$data['equipment_type_id'],
        'technician_name' => $data['technician_name'],
        'maintenance_date' => $data['maintenance_date'] ?? date('Y-m-d H:i:s'),
        'work_performed' => $data['work_performed'],
        'status' => $data['status'] ?? 'completed',
        'overall_condition' => $data['status'] ?? 'good',
        'template_type' => $data['template_type'], // NEW: Template selection
    ];

    // Generate report with selected template
    $generator = new ReportGenerator();
    $result = $generator->generateFromManualFormWithTemplate($reportData);

    if ($result['success']) {
        echo json_encode($result);
    } else {
        throw new Exception($result['error'] ?? 'Failed to generate report');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
