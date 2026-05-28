<?php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/vendor/autoload.php';
require_once 'config.php';

class ReportGenerator
{

    private $db;
    private $outputDir;

    public function __construct()
    {
        $this->db = getDB();
        $this->outputDir = __DIR__ . '/generated_reports/';

        if (!file_exists($this->outputDir . 'pdf')) {
            mkdir($this->outputDir . 'pdf', 0777, true);
        }
    }

    // GENERATE REPORT NUMBER
    private function generateReportNumber($equipmentTypeCode)
    {
        $year = date('Y');

        $stmt = $this->db->prepare("
            INSERT INTO report_counters (year, equipment_type_code, counter)
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE counter = counter + 1
        ");
        $stmt->bind_param('is', $year, $equipmentTypeCode);
        $stmt->execute();

        $stmt = $this->db->prepare("
            SELECT counter FROM report_counters 
            WHERE year = ? AND equipment_type_code = ?
        ");
        $stmt->bind_param('is', $year, $equipmentTypeCode);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $counter = str_pad($row['counter'], 3, '0', STR_PAD_LEFT);
        return "{$equipmentTypeCode}-{$year}-{$counter}";
    }

    // GENERATE FROM AI PREDICTION
    public function generateFromAIPrediction($predictionData)
    {
        try {
            $deviceId = $predictionData['device_id'];
            $equipmentTypeId = $predictionData['equipment_type_id'] ?? 1;
            $predictedIssue = $predictionData['predicted_issue'];
            $confidence = $predictionData['confidence_score'];
            $force = $predictionData['force'] ?? false;

            // ── Duplicate guard ──────────────────────────────────────────────
            // If a report for this device was already generated today, return it
            // instead of creating another. Pass force=true to override.
            if (!$force) {
                $stmt = $this->db->prepare("
                    SELECT id, report_number, pdf_file, generated_at
                    FROM maintenance_reports
                    WHERE device_id = ?
                      AND report_type = 'ai_prediction'
                      AND DATE(generated_at) = CURDATE()
                    ORDER BY generated_at DESC
                    LIMIT 1
                ");
                $stmt->bind_param('i', $deviceId);
                $stmt->execute();
                $existing = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($existing) {
                    return [
                        'success'    => true,
                        'duplicate'  => true,
                        'report_id'  => $existing['id'],
                        'report_number' => $existing['report_number'],
                        'pdf_file'   => $existing['pdf_file'],
                        'message'    => "A report for this device was already generated today ({$existing['report_number']}). Pass force=true to generate another."
                    ];
                }
            }
            // ────────────────────────────────────────────────────────────────

            $deviceInfo = $this->getDeviceInfo($deviceId);
            $equipmentType = $this->getEquipmentType($equipmentTypeId);
            $reportNumber = $this->generateReportNumber($equipmentType['type_code']);

            $reportData = [
                'report_number' => $reportNumber,
                'device_id' => $deviceId,
                'equipment_type_id' => $equipmentTypeId,
                'report_title' => "AI Prediction Report - {$deviceInfo['name']}",
                'report_type' => 'ai_prediction',
                'predicted_issue' => $predictedIssue,
                'confidence_score' => $confidence,
                'maintenance_date' => date('Y-m-d H:i:s'),
                'generated_by' => 'AI Prediction System'
            ];

            // Choose correct template based on equipment type
            $pdfFile = $this->generateSmartPDF($reportData, $deviceInfo, $equipmentType);
            $reportId = $this->saveReportToDatabase($reportData, $pdfFile);

            return [
                'success' => true,
                'report_id' => $reportId,
                'report_number' => $reportNumber,
                'pdf_file' => $pdfFile
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // GENERATE FROM MAINTENANCE
    public function generateFromMaintenance($maintenanceScheduleId)
    {
        try {
            $maintenanceData = $this->getMaintenanceData($maintenanceScheduleId);

            if (!$maintenanceData) {
                throw new Exception("Maintenance schedule not found");
            }

            $deviceInfo = $this->getDeviceInfo($maintenanceData['device_id']);
            $equipmentTypeId = $maintenanceData['equipment_type_id'] ?? 1;
            $equipmentType = $this->getEquipmentType($equipmentTypeId);
            $reportNumber = $this->generateReportNumber($equipmentType['type_code']);

            $reportData = [
                'report_number' => $reportNumber,
                'device_id' => $maintenanceData['device_id'],
                'equipment_type_id' => $equipmentTypeId,
                'maintenance_schedule_id' => $maintenanceScheduleId,
                'report_title' => "Maintenance Report - {$maintenanceData['task_name']}",
                'report_type' => 'scheduled_maintenance',
                'maintenance_date' => $maintenanceData['completed_at'] ?? date('Y-m-d H:i:s'),
                'technician_name' => $maintenanceData['technician_name'] ?? 'N/A',
                'actions_taken' => json_encode($maintenanceData['actions'] ?? []),
                'generated_by' => $maintenanceData['technician_name'] ?? 'System'
            ];

            // Choose correct template
            $pdfFile = $this->generateSmartPDF($reportData, $deviceInfo, $equipmentType);
            $reportId = $this->saveReportToDatabase($reportData, $pdfFile);

            return [
                'success' => true,
                'report_id' => $reportId,
                'report_number' => $reportNumber,
                'pdf_file' => $pdfFile
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // SMART PDF GENERATOR - DETECTS EQUIPMENT TYPE
    private function generateSmartPDF($reportData, $deviceInfo, $equipmentType)
    {
        $equipmentCode = strtoupper($equipmentType['type_code']);

        // Route to correct template
        switch ($equipmentCode) {
            case 'HARDWARE':
                return $this->generateHardwareReport($reportData, $deviceInfo, $equipmentType);

            case 'TOWER':
                return $this->generateTowerReport($reportData, $deviceInfo, $equipmentType);

            case 'RADAR':
                return $this->generateRadarReport($reportData, $deviceInfo, $equipmentType);

            case 'DATABASE':
                return $this->generateDatabaseReport($reportData, $deviceInfo, $equipmentType);

            default:
                return $this->generateGenericReport($reportData, $deviceInfo, $equipmentType);
        }
    }

    // HARDWARE REPORT TEMPLATE
    private function generateHardwareReport($reportData, $deviceInfo, $equipmentType)
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Network Monitor System');
        $pdf->SetTitle($reportData['report_title']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->AddPage();

        // ========== TITLE ==========
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->Cell(0, 10, 'Asset & Equipment Maintenance Log', 0, 1, 'C');
        $pdf->Ln(3);

        // Document info
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(40, 6, 'Document No:', 0, 0);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, $reportData['report_number'], 0, 1);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(40, 6, 'Effective Date:', 0, 0);
        $pdf->Cell(0, 6, date('Y-m-d', strtotime($reportData['maintenance_date'])), 0, 1);

        // Add technician name if manual report
        if ($reportData['report_type'] === 'scheduled_maintenance' && !empty($reportData['technician_name'])) {
            $pdf->Cell(40, 6, 'Technician:', 0, 0);
            $pdf->Cell(0, 6, $reportData['technician_name'], 0, 1);
        }
        $pdf->Ln(5);

        // ========== ASSET IDENTIFICATION ==========
        $pdf->SetFillColor(220, 220, 220);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 8, '1. Asset Identification', 1, 1, 'L', true);

        $pdf->SetFont('helvetica', '', 9);
        $this->addTableRow($pdf, 'Asset ID', $deviceInfo['id']);
        $this->addTableRow($pdf, 'Asset Name / Type', $deviceInfo['name']);
        $this->addTableRow($pdf, 'Model No.', $deviceInfo['device_type'] ?? 'computer');
        $this->addTableRow($pdf, 'Location', $deviceInfo['location'] ?? 'Data Center');
        $this->addTableRow($pdf, 'IP Address', $deviceInfo['ip_address']);
        $this->addTableRow($pdf, 'Criticality Level', 'High');
        $pdf->Ln(3);

        // ========== MANUAL MAINTENANCE WORK PERFORMED ==========
        if ($reportData['report_type'] === 'scheduled_maintenance' && !empty($reportData['work_performed'])) {
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetFillColor(200, 230, 200); // Green background for manual
            $pdf->Cell(0, 8, '2. Maintenance Work Performed', 1, 1, 'L', true);

            $pdf->SetFont('helvetica', '', 9);
            $pdf->MultiCell(0, 5, $reportData['work_performed'], 1, 'L');

            // Status
            if (!empty($reportData['status'])) {
                $pdf->Ln(2);
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->Cell(40, 6, 'Overall Status:', 1, 0, 'L');
                $pdf->SetFont('helvetica', '', 9);
                $statusColor = ($reportData['status'] === 'Pass') ? [0, 200, 0] : [200, 0, 0];
                $pdf->SetTextColor($statusColor[0], $statusColor[1], $statusColor[2]);
                $pdf->Cell(0, 6, $reportData['status'], 1, 1, 'L');
                $pdf->SetTextColor(0, 0, 0); // Reset color
            }
            $pdf->Ln(3);
        }

        // ========== PREVENTIVE MAINTENANCE SCHEDULE ==========
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetFillColor(220, 220, 220);
        $sectionNum = ($reportData['report_type'] === 'scheduled_maintenance') ? '3' : '2';
        $pdf->Cell(0, 8, $sectionNum . '. Preventive Maintenance Schedule', 1, 1, 'L', true);

        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(15, 7, 'Task ID', 1, 0, 'C', true);
        $pdf->Cell(50, 7, 'Task Description', 1, 0, 'C', true);
        $pdf->Cell(30, 7, 'Frequency', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Last Done', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Next Due', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Status', 1, 1, 'C', true);

        // Sample tasks
        $tasks = [
            ['PM-001', 'System Health Check', 'Daily', date('Y-m-d'), date('Y-m-d', strtotime('+1 day')), '[ ] Done'],
            ['PM-002', 'Performance Monitoring', 'Weekly', date('Y-m-d'), date('Y-m-d', strtotime('+7 days')), '[ ] Done'],
            ['PM-003', 'Hardware Inspection', 'Monthly', date('Y-m-d'), date('Y-m-d', strtotime('+30 days')), '[ ] Done'],
        ];

        $pdf->SetFont('helvetica', '', 7);
        foreach ($tasks as $task) {
            $pdf->Cell(15, 6, $task[0], 1, 0, 'C');
            $pdf->Cell(50, 6, $task[1], 1, 0, 'L');
            $pdf->Cell(30, 6, $task[2], 1, 0, 'C');
            $pdf->Cell(25, 6, $task[3], 1, 0, 'C');
            $pdf->Cell(25, 6, $task[4], 1, 0, 'C');
            $pdf->Cell(25, 6, $task[5], 1, 1, 'C');
        }
        $pdf->Ln(3);

        // ========== AI PREDICTION ANALYSIS ==========
        if ($reportData['report_type'] === 'ai_prediction') {
            $sectionNum2 = '3';
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetFillColor(255, 200, 200);
            $pdf->Cell(0, 8, $sectionNum2 . '. AI Prediction Analysis - Corrective Maintenance Required', 1, 1, 'L', true);

            $pdf->SetFont('helvetica', '', 9);
            $pdf->MultiCell(0, 5, 'Predicted Issue: ' . $reportData['predicted_issue'], 1, 'L');
            $pdf->Cell(50, 6, 'Confidence Score:', 1, 0, 'L');
            $pdf->Cell(0, 6, $reportData['confidence_score'] . '%', 1, 1, 'L');
            $pdf->Ln(3);
        }

        // ========== MAINTENANCE CHECKLIST ==========
        $sectionNum3 = ($reportData['report_type'] === 'ai_prediction') ? '4' : ($reportData['report_type'] === 'scheduled_maintenance' ? '4' : '3');
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(0, 8, $sectionNum3 . '. Maintenance Checklist', 1, 1, 'L', true);

        $checklistItems = [
            'Visual inspection of hardware components',
            'Check system temperature and cooling',
            'Verify network connectivity',
            'Test backup systems',
            'Review system logs for errors'
        ];

        $pdf->SetFont('helvetica', '', 9);
        foreach ($checklistItems as $item) {
            $pdf->Cell(10, 6, '[ ]', 1, 0, 'C');
            $pdf->Cell(0, 6, $item, 1, 1, 'L');
        }
        $pdf->Ln(3);

        // ========== SIGNATURES ==========
        $this->addSignatureSection($pdf);

        return $this->savePDF($pdf, $reportData['report_number']);
    }

    // TOWER REPORT TEMPLATE
    private function generateTowerReport($reportData, $deviceInfo, $equipmentType)
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Network Monitor System');
        $pdf->SetTitle($reportData['report_title']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->AddPage();

        // ========== TITLE ==========
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, '40m Lattice Tower Maintenance & Inspection Log', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 6, 'Island Installation Protocol - Marine Environment', 0, 1, 'C');
        $pdf->Ln(5);

        // ========== GENERAL INSPECTION INFO ==========
        $pdf->SetFillColor(220, 220, 220);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 8, '1. General Inspection Information', 1, 1, 'L', true);

        $pdf->SetFont('helvetica', '', 9);
        $this->addTableRow($pdf, 'Tower ID / Asset Number', $deviceInfo['name']);
        $this->addTableRow($pdf, 'Location / Island Name', $deviceInfo['location'] ?? 'Remote Island');
        $this->addTableRow($pdf, 'GPS Coordinates', 'N/A');
        $this->addTableRow($pdf, 'Inspection Date', date('Y-m-d'));
        $this->addTableRow($pdf, 'Inspector Name(s)', $reportData['generated_by']);
        $this->addTableRow($pdf, 'Weather Conditions', 'Clear, Wind: Moderate');
        $pdf->Ln(3);

        // ========== PRE-INSPECTION SAFETY CHECKLIST ==========
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetFillColor(255, 220, 220);
        $pdf->Cell(0, 8, '2. Pre-Inspection Safety & Environmental Briefing', 1, 1, 'L', true);

        $pdf->SetFont('helvetica', '', 8);
        $pdf->MultiCell(0, 5, 'This checklist must be completed before any work or climbing commences.', 0, 'L');
        $pdf->Ln(2);

        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(15, 7, 'Item', 1, 0, 'C', true);
        $pdf->Cell(100, 7, 'Check Point', 1, 0, 'C', true);
        $pdf->Cell(20, 7, 'Status', 1, 0, 'C', true);
        $pdf->Cell(35, 7, 'Comments', 1, 1, 'C', true);

        $safetyChecks = [
            ['2.1', 'Site Access Assessment (Tide, Sea Conditions)', '[ ] Pass'],
            ['2.2', 'Emergency Action Plan Review', '[ ] Pass'],
            ['2.3', 'PPE Check (Harness, Helmet, Life Vests)', '[ ] Pass'],
            ['2.4', 'Weather Forecast Review (48h)', '[ ] Pass'],
        ];

        $pdf->SetFont('helvetica', '', 7);
        foreach ($safetyChecks as $check) {
            $pdf->Cell(15, 6, $check[0], 1, 0, 'C');
            $pdf->Cell(100, 6, $check[1], 1, 0, 'L');
            $pdf->Cell(20, 6, $check[2], 1, 0, 'C');
            $pdf->Cell(35, 6, '', 1, 1, 'L');
        }
        $pdf->Ln(3);

        // ========== STRUCTURAL INSPECTION ==========
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(0, 8, '3. Structural Inspection', 1, 1, 'L', true);

        $structuralItems = [
            'Tower legs and bracing members (corrosion, damage)',
            'Bolts and connections (torque, wear)',
            'Foundation and anchor bolts',
            'Ladder and safety cable',
            'Antenna mounting brackets'
        ];

        $pdf->SetFont('helvetica', '', 9);
        foreach ($structuralItems as $item) {
            $pdf->Cell(10, 6, '[ ]', 1, 0, 'C');
            $pdf->Cell(0, 6, $item, 1, 1, 'L');
        }
        $pdf->Ln(3);

        // ========== AI PREDICTION ==========
        if ($reportData['report_type'] === 'ai_prediction') {
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetFillColor(255, 200, 200);
            $pdf->Cell(0, 8, '4. AI System Alert - Predicted Issues', 1, 1, 'L', true);

            $pdf->SetFont('helvetica', '', 9);
            $pdf->MultiCell(0, 5, $reportData['predicted_issue'], 1, 'L');
            $pdf->Cell(50, 6, 'Confidence:', 1, 0, 'L');
            $pdf->Cell(0, 6, $reportData['confidence_score'] . '%', 1, 1, 'L');
        }

        $this->addSignatureSection($pdf);

        return $this->savePDF($pdf, $reportData['report_number']);
    }

    // RADAR REPORT TEMPLATE
    private function generateRadarReport($reportData, $deviceInfo, $equipmentType)
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Network Monitor System');
        $pdf->SetTitle($reportData['report_title']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->AddPage();

        // ========== TITLE ==========
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'Simrad HALO-6 Pulse Compression Radar', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 6, 'Technical Maintenance and Service Log', 0, 1, 'C');
        $pdf->Ln(5);

        // ========== EQUIPMENT INFO ==========
        $pdf->SetFillColor(220, 220, 220);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 8, 'Equipment and Service Information', 1, 1, 'L', true);

        $pdf->SetFont('helvetica', '', 9);
        $this->addTableRow($pdf, 'Equipment Model', 'Simrad HALO-6 Pulse Compression Radar');
        $this->addTableRow($pdf, 'Device Name / ID', $deviceInfo['name']);
        $this->addTableRow($pdf, 'Scanner Serial Number', $deviceInfo['id']);
        $this->addTableRow($pdf, 'Service Date', date('Y-m-d'));
        $this->addTableRow($pdf, 'Technician Name', $reportData['generated_by']);
        $pdf->Ln(3);

        // ========== PREVENTIVE MAINTENANCE CHECKLIST ==========
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(0, 8, 'Preventive Maintenance Checklist', 1, 1, 'L', true);

        $pdf->SetFont('helvetica', '', 8);
        $pdf->MultiCell(0, 5, 'Status options: Pass, Fail, N/A (Not Applicable)', 0, 'L');
        $pdf->Ln(2);

        // Category Physical & Mechanical
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 7, 'A. Physical & Mechanical Inspection', 0, 1, 'L');

        $physicalChecks = [
            ['A.1', 'Antenna Array - Inspect for cracks, damage, delamination'],
            ['A.2', 'Antenna Cleaning - Clean with soft cloth and mild soap'],
            ['A.3', 'Pedestal Housing - Check for water ingress'],
            ['A.4', 'Cable Connections - Verify all connections are secure'],
        ];

        $pdf->SetFont('helvetica', '', 8);
        foreach ($physicalChecks as $check) {
            $pdf->Cell(15, 6, $check[0], 1, 0, 'C');
            $pdf->Cell(120, 6, $check[1], 1, 0, 'L');
            $pdf->Cell(25, 6, '[ ] Pass [ ] Fail', 1, 1, 'C');
        }
        $pdf->Ln(2);

        // Category Electrical & Software
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 7, 'B. Electrical & Software Check', 0, 1, 'L');

        $electricalChecks = [
            ['B.1', 'Power Supply - Verify voltage within spec'],
            ['B.2', 'Firmware Version - Check for updates'],
            ['B.3', 'System Diagnostics - Run built-in tests'],
        ];

        $pdf->SetFont('helvetica', '', 8);
        foreach ($electricalChecks as $check) {
            $pdf->Cell(15, 6, $check[0], 1, 0, 'C');
            $pdf->Cell(120, 6, $check[1], 1, 0, 'L');
            $pdf->Cell(25, 6, '[ ] Pass [ ] Fail', 1, 1, 'C');
        }
        $pdf->Ln(3);

        // ========== AI PREDICTION ==========
        if ($reportData['report_type'] === 'ai_prediction') {
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetFillColor(255, 200, 200);
            $pdf->Cell(0, 8, 'AI System Alert - Predicted Issues', 1, 1, 'L', true);

            $pdf->SetFont('helvetica', '', 9);
            $pdf->MultiCell(0, 5, $reportData['predicted_issue'], 1, 'L');
            $pdf->Cell(50, 6, 'Confidence:', 1, 0, 'L');
            $pdf->Cell(0, 6, $reportData['confidence_score'] . '%', 1, 1, 'L');
        }

        $this->addSignatureSection($pdf);

        return $this->savePDF($pdf, $reportData['report_number']);
    }

    // DATABASE REPORT TEMPLATE
    private function generateDatabaseReport($reportData, $deviceInfo, $equipmentType)
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Network Monitor System');
        $pdf->SetTitle($reportData['report_title']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->AddPage();

        // ========== TITLE ==========
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->Cell(0, 10, 'Database Maintenance Report', 0, 1, 'C');
        $pdf->Ln(3);

        $pdf->SetFont('helvetica', '', 9);
        $this->addTableRow($pdf, 'Report Date', date('Y-m-d'));
        $this->addTableRow($pdf, 'Database System', $deviceInfo['name']);
        $this->addTableRow($pdf, 'Server IP', $deviceInfo['ip_address']);
        $this->addTableRow($pdf, 'Prepared By', $reportData['generated_by']);
        $pdf->Ln(5);

        // ========== EXECUTIVE SUMMARY ==========
        $pdf->SetFillColor(220, 220, 220);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 8, '1.0 Executive Summary', 1, 1, 'L', true);

        $pdf->SetFont('helvetica', '', 9);
        $summary = "This report provides a comprehensive overview of the database health and performance. ";
        if ($reportData['report_type'] === 'ai_prediction') {
            $summary .= "AI monitoring has detected potential issues requiring attention.";
        } else {
            $summary .= "Regular maintenance activities have been completed successfully.";
        }
        $pdf->MultiCell(0, 5, $summary, 0, 'L');
        $pdf->Ln(3);

        // ========== KEY SYSTEM METRICS ==========
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(0, 8, '2.0 Key System Metrics', 1, 1, 'L', true);

        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(70, 7, 'Metric', 1, 0, 'C', true);
        $pdf->Cell(40, 7, 'Value', 1, 0, 'C', true);
        $pdf->Cell(30, 7, 'Threshold', 1, 0, 'C', true);
        $pdf->Cell(30, 7, 'Status', 1, 1, 'C', true);

        $metrics = [
            ['CPU Usage (Avg)', '45%', '< 80%', '[ ] OK'],
            ['Memory Usage (Avg)', '62%', '< 85%', '[ ] OK'],
            ['Disk Space Available', '250 GB', '> 100 GB', '[ ] OK'],
            ['Database Size', '180 GB', 'Monitor', '[ ] OK'],
        ];

        $pdf->SetFont('helvetica', '', 8);
        foreach ($metrics as $metric) {
            $pdf->Cell(70, 6, $metric[0], 1, 0, 'L');
            $pdf->Cell(40, 6, $metric[1], 1, 0, 'C');
            $pdf->Cell(30, 6, $metric[2], 1, 0, 'C');
            $pdf->Cell(30, 6, $metric[3], 1, 1, 'C');
        }
        $pdf->Ln(3);

        // ========== BACKUP STATUS ==========
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(0, 8, '3.0 Backup & Recovery Status', 1, 1, 'L', true);

        $backupItems = [
            'Full backup completed successfully',
            'Transaction log backup current',
            'Backup integrity verified',
            'Disaster recovery plan tested'
        ];

        $pdf->SetFont('helvetica', '', 9);
        foreach ($backupItems as $item) {
            $pdf->Cell(10, 6, '[ ]', 1, 0, 'C');
            $pdf->Cell(0, 6, $item, 1, 1, 'L');
        }
        $pdf->Ln(3);

        // ========== AI PREDICTION ==========
        if ($reportData['report_type'] === 'ai_prediction') {
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetFillColor(255, 200, 200);
            $pdf->Cell(0, 8, '4.0 AI-Detected Issues & Recommendations', 1, 1, 'L', true);

            $pdf->SetFont('helvetica', '', 9);
            $pdf->MultiCell(0, 5, $reportData['predicted_issue'], 1, 'L');
            $pdf->Cell(50, 6, 'Confidence Score:', 1, 0, 'L');
            $pdf->Cell(0, 6, $reportData['confidence_score'] . '%', 1, 1, 'L');
        }

        $this->addSignatureSection($pdf);

        return $this->savePDF($pdf, $reportData['report_number']);
    }

    // GENERIC REPORT TEMPLATE
    private function generateGenericReport($reportData, $deviceInfo, $equipmentType)
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Network Monitor System');
        $pdf->SetTitle($reportData['report_title']);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(20, 20, 20);
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->Cell(0, 12, $reportData['report_title'], 0, 1, 'C');
        $pdf->Ln(5);

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(0, 10, 'Report Number: ' . $reportData['report_number'], 1, 1, 'C', true);
        $pdf->Ln(5);

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 8, '1. Device Information', 0, 1);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(50, 6, 'Device Name:', 0, 0);
        $pdf->Cell(0, 6, $deviceInfo['name'], 0, 1);
        $pdf->Cell(50, 6, 'IP Address:', 0, 0);
        $pdf->Cell(0, 6, $deviceInfo['ip_address'], 0, 1);
        $pdf->Ln(5);

        if ($reportData['report_type'] === 'ai_prediction') {
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(0, 8, '2. AI Prediction', 0, 1);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->MultiCell(0, 5, $reportData['predicted_issue'], 1, 'L');
            $pdf->Cell(50, 6, 'Confidence:', 1, 0);
            $pdf->Cell(0, 6, $reportData['confidence_score'] . '%', 1, 1);
        }

        $this->addSignatureSection($pdf);

        return $this->savePDF($pdf, $reportData['report_number']);
    }

    // HELPER METHODS
    private function addTableRow($pdf, $label, $value)
    {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(60, 6, $label, 1, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 6, $value, 1, 1, 'L');
    }

    private function addSignatureSection($pdf)
    {
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 8, 'Approval & Signatures', 0, 1);
        $pdf->Ln(2);

        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(85, 20, '', 1, 0);
        $pdf->Cell(5, 20, '', 0, 0);
        $pdf->Cell(85, 20, '', 1, 1);

        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(85, 5, 'Technician Signature', 0, 0, 'C');
        $pdf->Cell(5, 5, '', 0, 0);
        $pdf->Cell(85, 5, 'Supervisor Signature', 0, 1, 'C');

        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->Cell(0, 5, 'Generated by Network Monitor System - ' . date('Y-m-d H:i:s'), 0, 1, 'C');
    }

    private function savePDF($pdf, $reportNumber)
    {
        $filename = 'pdf/' . $reportNumber . '.pdf';
        $outputPath = $this->outputDir . $filename;
        $pdf->Output($outputPath, 'F');
        return $filename;
    }

    // DATABASE HELPERS
    private function getDeviceInfo($deviceId)
    {
        $stmt = $this->db->prepare("SELECT * FROM devices WHERE id = ?");
        $stmt->bind_param('i', $deviceId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    private function getEquipmentType($equipmentTypeId)
    {
        $stmt = $this->db->prepare("SELECT * FROM equipment_types WHERE id = ?");
        $stmt->bind_param('i', $equipmentTypeId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    private function getMaintenanceData($scheduleId)
    {
        $stmt = $this->db->prepare("
            SELECT ms.*, d.equipment_type_id 
            FROM maintenance_schedules ms
            JOIN devices d ON ms.device_id = d.id
            WHERE ms.id = ?
        ");
        $stmt->bind_param('i', $scheduleId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    private function saveReportToDatabase($reportData, $pdfFile)
    {
        $maintenanceScheduleId = $reportData['maintenance_schedule_id'] ?? null;
        $aiPredictionId = $reportData['ai_prediction_id'] ?? null;
        $predictedIssue = $reportData['predicted_issue'] ?? null;
        $confidenceScore = $reportData['confidence_score'] ?? null;
        $technicianName = $reportData['technician_name'] ?? null;

        $stmt = $this->db->prepare("
            INSERT INTO maintenance_reports (
                report_number, device_id, equipment_type_id, maintenance_schedule_id,
                report_title, report_type, report_status, ai_prediction_id,
                predicted_issue, confidence_score, maintenance_date, technician_name,
                pdf_file, generated_by
            ) VALUES (?, ?, ?, ?, ?, ?, 'completed', ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            'siisssissssss',
            $reportData['report_number'],
            $reportData['device_id'],
            $reportData['equipment_type_id'],
            $maintenanceScheduleId,
            $reportData['report_title'],
            $reportData['report_type'],
            $aiPredictionId,
            $predictedIssue,
            $confidenceScore,
            $reportData['maintenance_date'],
            $technicianName,
            $pdfFile,
            $reportData['generated_by']
        );

        $stmt->execute();
        return $this->db->insert_id;
    }
    // GENERATE REPORT FROM MANUAL FORM
    public function generateFromManualForm($formData)
    {
        try {
            $deviceId = $formData['device_id'];
            $equipmentTypeId = $formData['equipment_type_id'] ?? 1;
            $technicianName = $formData['technician_name'];
            $workPerformed = $formData['work_performed'];
            $maintenanceDate = $formData['maintenance_date'] ?? date('Y-m-d H:i:s');
            $status = $formData['status'] ?? 'completed';
            $overallCondition = $formData['overall_condition'] ?? 'good';

            $deviceInfo = $this->getDeviceInfo($deviceId);
            $equipmentType = $this->getEquipmentType($equipmentTypeId);
            $reportNumber = $this->generateReportNumber($equipmentType['type_code']);

            $reportData = [
                'report_number' => $reportNumber,
                'device_id' => $deviceId,
                'equipment_type_id' => $equipmentTypeId,
                'report_title' => "Manual Maintenance Report - {$deviceInfo['name']}",
                'report_type' => 'scheduled_maintenance',
                'maintenance_date' => $maintenanceDate,
                'technician_name' => $technicianName,
                'work_performed' => $workPerformed,
                'status' => $status,
                'overall_condition' => $overallCondition,
                'generated_by' => $technicianName
            ];

            $pdfFile = $this->generateSmartPDF($reportData, $deviceInfo, $equipmentType);
            $reportId = $this->saveReportToDatabase($reportData, $pdfFile);

            return [
                'success' => true,
                'report_id' => $reportId,
                'report_number' => $reportNumber,
                'pdf_file' => $pdfFile
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    public function generateFromManualFormWithTemplate($formData)
    {
        try {
            $deviceId = $formData['device_id'];
            $technicianName = $formData['technician_name'];
            $workPerformed = $formData['work_performed'];
            $maintenanceDate = $formData['maintenance_date'] ?? date('Y-m-d H:i:s');
            $status = $formData['status'] ?? 'completed';
            $overallCondition = $formData['overall_condition'] ?? 'good';
            $templateType = strtoupper($formData['template_type']); // HARDWARE, TOWER, RADAR, DATABASE

            // Get device info
            $deviceInfo = $this->getDeviceInfo($deviceId);

            // Generate report number based on template type
            $reportNumber = $this->generateReportNumber($templateType);

            // Get equipment type ID for template
            $equipmentTypeId = $this->getEquipmentTypeIdByCode($templateType);

            // Prepare report data
            $reportData = [
                'report_number' => $reportNumber,
                'device_id' => $deviceId,
                'equipment_type_id' => $equipmentTypeId,
                'report_title' => "Manual Maintenance Report - {$deviceInfo['name']}",
                'report_type' => 'scheduled_maintenance',
                'maintenance_date' => $maintenanceDate,
                'technician_name' => $technicianName,
                'work_performed' => $workPerformed,
                'status' => $status,
                'overall_condition' => $overallCondition,
                'generated_by' => $technicianName,
                'template_type' => $templateType // Store which template was used
            ];

            // Generate PDF using SELECTED template (not auto-detection)
            $pdfFile = $this->generateManualReportPDF($reportData, $deviceInfo, $templateType);

            // Save to database
            $reportId = $this->saveReportToDatabase($reportData, $pdfFile);

            return [
                'success' => true,
                'report_id' => $reportId,
                'report_number' => $reportNumber,
                'pdf_file' => $pdfFile,
                'template_used' => $templateType
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // HARDWARE MANUAL REPORT
    private function generateHardwareManualReport($reportData, $deviceInfo)
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Network Monitor System');
        $pdf->SetTitle('Asset & Equipment Maintenance Log');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        $mainDate = date('Y-m-d', strtotime($reportData['maintenance_date']));

        // ========== TITLE ==========
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->Cell(0, 12, 'Asset & Equipment Maintenance Log', 0, 1, 'L');
        $pdf->Ln(3);

        // Document header info
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(60, 6, 'Document No:', 0, 0, 'R');
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, $reportData['report_number'], 0, 1, 'L');

        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(60, 6, 'Revision:', 0, 0, 'R');
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, '1.0', 0, 1, 'L');

        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(60, 6, 'Effective Date:', 0, 0, 'R');
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, $mainDate, 0, 1, 'L');
        $pdf->Ln(5);

        // ========== 1. ASSET IDENTIFICATION ==========
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, '1. Asset Identification', 0, 1, 'L');
        $pdf->Ln(1);

        $pdf->SetFont('helvetica', '', 9);
        $fields = [
            ['Asset ID',             $deviceInfo['id'] ?? 'N/A'],
            ['Asset Name / Type',    $deviceInfo['name'] ?? 'N/A'],
            ['Manufacturer',         'N/A'],
            ['Model No.',            $deviceInfo['device_type'] ?? 'N/A'],
            ['Serial No.',           'N/A'],
            ['Location',             $deviceInfo['location'] ?? 'Data Center'],
            ['Installation Date',    'N/A'],
            ['Warranty Expiry',      'N/A'],
            ['Assigned Department',  'IT Infrastructure'],
            ['Criticality Level',    'High'],
        ];
        foreach ($fields as $row) {
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(60, 6, $row[0], 1, 0, 'L');
            $pdf->SetFont('helvetica', 'I', 9);
            $pdf->Cell(0, 6, $row[1], 1, 1, 'L');
        }
        $pdf->Ln(5);

        // ========== 2. PREVENTIVE MAINTENANCE SCHEDULE ==========
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, '2. Preventive Maintenance Schedule', 0, 1, 'L');
        $pdf->Ln(1);

        // Table header
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(22, 7, 'Task ID', 1, 0, 'C', true);
        $pdf->Cell(28, 7, 'Asset Type', 1, 0, 'C', true);
        $pdf->Cell(50, 7, 'Task Description', 1, 0, 'C', true);
        $pdf->Cell(22, 7, 'Frequency', 1, 0, 'C', true);
        $pdf->Cell(24, 7, 'Next Due Date', 1, 0, 'C', true);
        $pdf->Cell(24, 7, 'Assigned To', 1, 0, 'C', true);
        $pdf->Cell(20, 7, 'Status', 1, 1, 'C', true);

        $pmTasks = [
            ['PM-SRV-001', 'Server',            'Review system logs for critical errors and warnings.',             'Daily',     date('Y-m-d', strtotime('+1 day')),   'NOC Team',     'Scheduled'],
            ['PM-WKS-001', 'Workstation',        'Run full antivirus scan and apply OS security patches.',          'Weekly',    date('Y-m-d', strtotime('+7 days')),   'IT Support',   'Scheduled'],
            ['PM-SRV-002', 'Server',             'Physically clean air intakes, fans, and check internal components.', 'Monthly', date('Y-m-d', strtotime('+30 days')), 'NOC Team',     'Scheduled'],
            ['PM-NET-001', 'Network Equipment',  'Backup router and switch configurations.',                        'Monthly',   date('Y-m-d', strtotime('+30 days')),  'Network Admin', 'Scheduled'],
            ['PM-UPS-001', 'UPS',                'Perform battery self-test and check load percentage.',            'Quarterly', date('Y-m-d', strtotime('+90 days')),  'Facilities',   'Scheduled'],
            ['PM-GEN-001', 'Generator',          'Perform no-load test run for 15 minutes.',                        'Quarterly', date('Y-m-d', strtotime('+90 days')),  'Facilities',   'Scheduled'],
            ['PM-SRV-003', 'Server',             'Verify backup integrity by performing a test restore.',           'Quarterly', date('Y-m-d', strtotime('+90 days')),  'SysAdmin',     'Scheduled'],
            ['PM-UPS-002', 'UPS',                'Full battery rundown test and calibration.',                      'Annually',  date('Y-m-d', strtotime('+365 days')), 'Certified Vendor', 'Scheduled'],
        ];

        $pdf->SetFont('helvetica', '', 6);
        foreach ($pmTasks as $task) {
            $h = 5;
            $pdf->Cell(22, $h, $task[0], 1, 0, 'C');
            $pdf->Cell(28, $h, $task[1], 1, 0, 'L');
            $pdf->Cell(50, $h, $task[2], 1, 0, 'L');
            $pdf->Cell(22, $h, $task[3], 1, 0, 'C');
            $pdf->Cell(24, $h, $task[4], 1, 0, 'C');
            $pdf->Cell(24, $h, $task[5], 1, 0, 'C');
            $pdf->Cell(20, $h, $task[6], 1, 1, 'C');
        }
        $pdf->Ln(5);

        // ========== 3. CORRECTIVE MAINTENANCE LOG ==========
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, '3. Corrective Maintenance Log', 0, 1, 'L');
        $pdf->Ln(1);

        // Table header
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(18, 7, 'Log ID', 1, 0, 'C', true);
        $pdf->Cell(20, 7, 'Date Reported', 1, 0, 'C', true);
        $pdf->Cell(20, 7, 'Asset ID', 1, 0, 'C', true);
        $pdf->Cell(38, 7, 'Problem Description', 1, 0, 'C', true);
        $pdf->Cell(38, 7, 'Action Taken', 1, 0, 'C', true);
        $pdf->Cell(20, 7, 'Technician', 1, 0, 'C', true);
        $pdf->Cell(18, 7, 'Date Resolved', 1, 0, 'C', true);
        $pdf->Cell(18, 7, 'Status', 1, 1, 'C', true);

        // MANUAL WORK DATA ROW
        $pdf->SetFont('helvetica', '', 6);
        $logId = 'CM-' . date('md', strtotime($reportData['maintenance_date']));
        $resolvedStatus = ($reportData['status'] === 'Pass') ? 'Resolved' : 'Open';
        $resolvedDate   = ($reportData['status'] === 'Pass') ? $mainDate : '-';

        // Use MultiCell for work_performed to handle long text
        $x = $pdf->GetX();
        $y = $pdf->GetY();

        $pdf->Cell(18, 6, $logId, 1, 0, 'C');
        $pdf->Cell(20, 6, $mainDate, 1, 0, 'C');
        $pdf->Cell(20, 6, $deviceInfo['id'] ?? 'N/A', 1, 0, 'C');

        // Problem description cell using MultiCell
        $xAfter = $pdf->GetX();
        $yAfter = $pdf->GetY();
        $pdf->MultiCell(38, 6, $reportData['work_performed'], 1, 'L');
        $rowH = $pdf->GetY() - $yAfter;

        $pdf->SetXY($xAfter + 38, $yAfter);
        $pdf->MultiCell(38, $rowH, 'See work performed above', 1, 'L');
        $pdf->SetXY($xAfter + 76, $yAfter);
        $pdf->Cell(20, $rowH, $reportData['technician_name'], 1, 0, 'C');
        $pdf->Cell(18, $rowH, $resolvedDate, 1, 0, 'C');
        $pdf->Cell(18, $rowH, $resolvedStatus, 1, 1, 'C');

        // Empty row for future entries
        $pdf->SetFont('helvetica', 'I', 6);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Cell(18, 6, '[Auto-gen ID]', 1, 0, 'C');
        $pdf->Cell(20, 6, '[Date]', 1, 0, 'C');
        $pdf->Cell(20, 6, '[Asset ID]', 1, 0, 'C');
        $pdf->Cell(38, 6, '[Detailed description of the issue]', 1, 0, 'L');
        $pdf->Cell(38, 6, '[Steps taken to resolve the issue]', 1, 0, 'L');
        $pdf->Cell(20, 6, '[Name/ID]', 1, 0, 'C');
        $pdf->Cell(18, 6, '[Date]', 1, 0, 'C');
        $pdf->Cell(18, 6, '[Open/Resolved]', 1, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);

        // ========== PAGE 2: DETAILED CHECKLISTS ==========
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, '4. Detailed Maintenance Task Checklists', 0, 1, 'L');
        $pdf->Ln(2);

        // Helper closure for checklist tables
        $checklistHeader = function () use ($pdf) {
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(12, 6, 'Item', 1, 0, 'C', true);
            $pdf->Cell(55, 6, 'Checkpoint / Task', 1, 0, 'C', true);
            $pdf->Cell(50, 6, 'Specification / Standard', 1, 0, 'C', true);
            $pdf->Cell(25, 6, 'Result (P/F/NA)', 1, 0, 'C', true);
            $pdf->Cell(28, 6, 'Remarks / Readings', 1, 0, 'C', true);
            $pdf->Cell(20, 6, 'Initials', 1, 1, 'C', true);
        };

        $checklistRow = function ($item, $task, $spec) use ($pdf) {
            $pdf->SetFont('helvetica', '', 6);
            $pdf->Cell(12, 5, $item, 1, 0, 'C');
            $pdf->Cell(55, 5, $task, 1, 0, 'L');
            $pdf->Cell(50, 5, $spec, 1, 0, 'L');
            $pdf->Cell(25, 5, '', 1, 0, 'C');
            $pdf->Cell(28, 5, '', 1, 0, 'C');
            $pdf->Cell(20, 5, '', 1, 1, 'C');
        };

        // 4.1 Server Maintenance Checklist
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, '4.1 Server Maintenance Checklist (Quarterly)', 0, 1, 'L');
        $checklistHeader();
        $checklistRow('1.1', 'Physical Inspection: Check for loose cables, physical damage.',         'All connections secure, no visible damage.');
        $checklistRow('1.2', 'Hardware Cleaning: Clean air vents, fans, and chassis interior of dust.', 'No significant dust buildup. Airflow is unobstructed.');
        $checklistRow('1.3', 'Hardware Health Check: Review logs (iDRAC/iLO) for hardware errors.',   'No predictive failures or errors for PSU, RAM, CPU, Fans.');
        $checklistRow('1.4', 'RAID Array Status: Check health of RAID controller and all physical disks.', 'Array status: Optimal. All disks: Online/OK.');
        $checklistRow('1.5', 'Firmware/Driver Review: Check for critical firmware updates (BIOS, RAID, NIC).', 'Firmware is within N-1 version compliance.');
        $checklistRow('1.6', 'Backup Verification: Perform a test restore of a random file/folder.', 'Restore successful, data integrity confirmed.');
        $pdf->Ln(4);

        // 4.2 Generator Maintenance Checklist
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, '4.2 Generator Maintenance Checklist (Quarterly)', 0, 1, 'L');
        $checklistHeader();
        $checklistRow('2.1', 'Visual Inspection: Check for leaks (oil, fuel, coolant), damage, loose parts.', 'No visible leaks or damage.');
        $checklistRow('2.2', 'Fluid Levels: Check engine oil, coolant, and fuel levels.',             'Oil: Full. Coolant: Full. Fuel: > 80%.');
        $checklistRow('2.3', 'Battery Check: Check voltage and inspect terminals for corrosion.',     'Voltage > 12.4V (or 24.8V). Terminals clean.');
        $checklistRow('2.4', 'No-Load Test Run: Start generator and run for 15 minutes.',             'Starts within 10s. Runs smoothly.');
        $checklistRow('2.5', 'Check Block Heater: Verify block heater is operational.',               'Engine block is warm to the touch.');
        $checklistRow('2.6', 'Automatic Transfer Switch (ATS): Check indicator lights and status.',   'ATS indicates "Normal Power". No alarms.');
        $pdf->Ln(4);

        $pdf->AddPage();

        // 4.3 UPS Maintenance Checklist
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, '4.3 UPS Maintenance Checklist (Quarterly)', 0, 1, 'L');
        $checklistHeader();
        $checklistRow('3.1', 'Visual Inspection: Check for alarms, dust buildup, and fan operation.', 'No active alarms. Fans running quietly.');
        $checklistRow('3.2', 'Check UPS Status: Record input/output voltage, load, and battery charge.', 'Input/Output within +/-5% of nominal. Load < 75%. Batt > 98%.');
        $checklistRow('3.3', 'Perform Battery Self-Test: Initiate self-test via front panel or software.', 'Test completes successfully with "Pass" result.');
        $checklistRow('3.4', 'Review Event Log: Check for past alarms or events.',                    'No critical events logged since last check.');
        $pdf->Ln(4);

        // 4.4 Solar Panel & Inverter Checklist
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, '4.4 Solar Panel & Inverter Checklist (Quarterly)', 0, 1, 'L');
        $checklistHeader();
        $checklistRow('4.1', 'Panel Visual Inspection: Check for cracks, discoloration, delamination, soiling.', 'Panels are physically intact and free of major soiling.');
        $checklistRow('4.2', 'Mounting & Racking Check: Inspect for loose bolts, corrosion, or damage.', 'All mounting hardware is secure.');
        $checklistRow('4.3', 'Inverter Check: Inspect for alarms, fan operation, and dust buildup.',  'No alarms. Fans operating. Vents are clean.');
        $checklistRow('4.4', 'Record Inverter Readings: Log DC input voltage, AC output voltage, and current power production.', 'Readings are consistent with current weather conditions.');
        $pdf->Ln(4);

        // 4.5 Network Equipment Checklist
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, '4.5 Network Equipment Checklist (Monthly)', 0, 1, 'L');
        $checklistHeader();
        $checklistRow('5.1', 'Backup Configuration: Perform a full backup of the running configuration.', 'Backup completes successfully and is stored securely.');
        $checklistRow('5.2', 'Log Review: Check logs for critical errors, flapping ports, high CPU usage.', 'No critical errors found. CPU < 20%.');
        $checklistRow('5.3', 'Physical Inspection: Check status LEDs, fan operation, and cable connections.', 'All status LEDs are green/normal. Fans are operational.');
        $checklistRow('5.4', 'Firmware Check: Review vendor advisories for security vulnerabilities.', 'Current firmware is not listed in any new critical advisories.');
        $pdf->Ln(4);

        $pdf->AddPage();

        // 4.6 Workstation / Industrial PC Checklist
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, '4.6 Workstation / Industrial PC Checklist (As Needed / Annually)', 0, 1, 'L');

        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(12, 6, 'Item', 1, 0, 'C', true);
        $pdf->Cell(65, 6, 'Checkpoint / Task', 1, 0, 'C', true);
        $pdf->Cell(40, 6, 'Specification / Standard', 1, 0, 'C', true);
        $pdf->Cell(25, 6, 'Result (P/F/NA)', 1, 0, 'C', true);
        $pdf->Cell(28, 6, 'Remarks / Readings', 1, 0, 'C', true);
        $pdf->Cell(20, 6, 'Initials', 1, 1, 'C', true);

        $ws = [
            ['6.1', 'Physical Cleaning: Clean keyboard, mouse, screen, and case vents.',                  'Device is free of dust and grime.'],
            ['6.2', 'OS & Software Updates: Apply all critical/security OS and application patches.',     'System is fully patched.'],
            ['6.3', 'Antivirus/Malware Scan: Run a full system scan.',                                    'Scan completed with no threats detected.'],
            ['6.4', 'Disk Health & Cleanup: Run disk cleanup and check S.M.A.R.T. status.',               'S.M.A.R.T. status is "OK". Freed up disk space.'],
            ['6.5', '(Industrial PC) Enclosure & I/O Check: Inspect enclosure seals and test I/O ports.', 'Seals are intact. All required I/O ports are functional.'],
        ];

        $pdf->SetFont('helvetica', '', 6);
        foreach ($ws as $row) {
            $pdf->Cell(12, 5, $row[0], 1, 0, 'C');
            $pdf->Cell(65, 5, $row[1], 1, 0, 'L');
            $pdf->Cell(40, 5, $row[2], 1, 0, 'L');
            $pdf->Cell(25, 5, '', 1, 0, 'C');
            $pdf->Cell(28, 5, '', 1, 0, 'C');
            $pdf->Cell(20, 5, '', 1, 1, 'C');
        }

        // ========== SIGNATURES ==========
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(85, 5, 'Reviewed By:', 0, 0, 'L');
        $pdf->Cell(0, 5, 'Approved By:', 0, 1, 'L');
        $pdf->Ln(12);

        $pdf->Cell(85, 5, 'Name: ' . $reportData['technician_name'], 0, 0, 'L');
        $pdf->Cell(0, 5, 'Name: ____________________________', 0, 1, 'L');
        $pdf->Cell(85, 5, 'Date: ' . $mainDate, 0, 0, 'L');
        $pdf->Cell(0, 5, 'Date: ____________________________', 0, 1, 'L');

        // ========== LAST PAGE: CONFIDENTIAL ==========
        $pdf->AddPage();
        $pdf->Ln(100);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->MultiCell(0, 6, 'This document contains confidential information proprietary to the company. Unauthorized distribution or reproduction is strictly prohibited.', 0, 'C');

        return $this->savePDF($pdf, $reportData['report_number']);
    }

    // TOWER MANUAL REPORT
    private function generateTowerManualReport($reportData, $deviceInfo)
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Network Monitor System');
        $pdf->SetTitle('40m Lattice Tower Maintenance & Inspection Log');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        $mainDate = date('Y-m-d', strtotime($reportData['maintenance_date']));

        // ========== TITLE ==========
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, '40m Lattice Tower Maintenance & Inspection Log', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 6, 'Island Installation Protocol - Marine Environment', 0, 1, 'C');
        $pdf->Ln(5);

        // ========== 1. GENERAL INSPECTION INFORMATION ==========
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, '1. General Inspection Information', 0, 1, 'L');
        $pdf->Ln(1);

        $pdf->SetFont('helvetica', '', 9);
        $genFields = [
            ['Tower ID / Asset Number:', $deviceInfo['name'] ?? 'N/A'],
            ['Location / Island Name:', $deviceInfo['location'] ?? 'N/A'],
            ['GPS Coordinates:', 'N/A'],
            ['Inspection Date:', $mainDate],
            ['Inspector Name(s):', $reportData['technician_name']],
            ['Company / Organization:', 'N/A'],
            ['Weather Conditions:', 'Temp (°C): ____, Wind (km/h, Dir): ____, Precipitation: ____, Sea State: ____'],
            ['Time On-Site / Off-Site:', '____ / ____'],
        ];
        foreach ($genFields as $f) {
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(55, 6, $f[0], 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 6, $f[1], 0, 1, 'L');
        }
        $pdf->Ln(5);

        // ========== 2. PRE-INSPECTION SAFETY & ENVIRONMENTAL BRIEFING ==========
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, '2. Pre-Inspection Safety & Environmental Briefing', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->MultiCell(0, 5, 'This checklist must be completed before any work or climbing commences. The remote and marine nature of this site requires heightened safety and environmental awareness.', 0, 'L');
        $pdf->Ln(2);

        // Safety table header
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(12, 6, 'Item', 1, 0, 'C', true);
        $pdf->Cell(65, 6, 'Check Point', 1, 0, 'C', true);
        $pdf->Cell(28, 6, 'Status', 1, 0, 'C', true);
        $pdf->Cell(40, 6, 'Comments / Observations', 1, 0, 'C', true);
        $pdf->Cell(25, 6, 'Action Taken', 1, 1, 'C', true);

        $safety = [
            ['2.1', 'Site Access Assessment (Tide, Sea Conditions, Landing Zone Safety)'],
            ['2.2', 'Emergency Action Plan (EAP) Review (Medevac, Communication Plan, First Aid)'],
            ['2.3', 'Personal Protective Equipment (PPE) Check (Climbing Harness, Lanyards, Helmet, Life Vests, Boots)'],
            ['2.4', 'Tools & Equipment Check (Torque Wrenches, Test Meters, Cleaning Supplies)'],
            ['2.5', 'Environmental Protection Measures (Spill Kits, Waste Containment)'],
        ];

        $pdf->SetFont('helvetica', '', 6);
        foreach ($safety as $s) {
            $pdf->Cell(12, 6, $s[0], 1, 0, 'C');
            $pdf->Cell(65, 6, $s[1], 1, 0, 'L');
            $pdf->Cell(28, 6, 'Pass  Fail', 1, 0, 'C');
            $pdf->Cell(40, 6, '', 1, 0, 'L');
            $pdf->Cell(25, 6, '', 1, 1, 'L');
        }
        $pdf->Ln(5);

        // ========== 3. SITE & FOUNDATION INSPECTION ==========
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, '3. Site & Foundation Inspection', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->MultiCell(0, 5, 'Focus on impacts from the marine environment, including erosion, saltwater corrosion, and foundation stability.', 0, 'L');
        $pdf->Ln(2);

        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(12, 6, 'Item', 1, 0, 'C', true);
        $pdf->Cell(65, 6, 'Inspection Point', 1, 0, 'C', true);
        $pdf->Cell(35, 6, 'Status', 1, 0, 'C', true);
        $pdf->Cell(40, 6, 'Comments / Observations', 1, 0, 'C', true);
        $pdf->Cell(18, 6, 'Action Required', 1, 1, 'C', true);

        $site = [
            ['3.1', 'Site Access Path / Landing Area'],
            ['3.2', 'Perimeter Fence, Gate, and Signage'],
            ['3.3', 'Ground Condition & Vegetation Control'],
            ['3.4', 'Evidence of Coastal Erosion or Scouring near Foundation'],
            ['3.5', 'Concrete Foundation Blocks/Piers'],
            ['3.6', 'Anchor Bolts, Nuts, and Base Plates'],
            ['3.7', 'Site Drainage'],
        ];

        $pdf->SetFont('helvetica', '', 6);
        foreach ($site as $s) {
            $pdf->Cell(12, 5, $s[0], 1, 0, 'C');
            $pdf->Cell(65, 5, $s[1], 1, 0, 'L');
            $pdf->Cell(35, 5, 'Pass  Monitor  Fail  N/A', 1, 0, 'C');
            $pdf->Cell(40, 5, '', 1, 0, 'L');
            $pdf->Cell(18, 5, '', 1, 1, 'L');
        }
        $pdf->Ln(5);

        // ========== MAINTENANCE WORK PERFORMED (MANUAL DATA!) ==========
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetFillColor(200, 230, 200);
        $pdf->Cell(0, 8, 'Maintenance Work Performed', 1, 1, 'L', true);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->MultiCell(0, 5, $reportData['work_performed'], 1, 'L');
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(50, 6, 'Overall Status:', 1, 0, 'L');
        if ($reportData['status'] === 'Pass') {
            $pdf->SetTextColor(0, 150, 0);
        } else {
            $pdf->SetTextColor(200, 0, 0);
        }
        $pdf->Cell(0, 6, $reportData['status'], 1, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(5);

        // ========== PAGE 2 ==========
        $pdf->AddPage();

        // 4. Structural Integrity
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, '4. Structural Integrity (Lattice Members & Connections)', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->MultiCell(0, 5, 'A thorough visual and tactile inspection is required. Pay close attention to signs of corrosion, which is accelerated in marine environments.', 0, 'L');
        $pdf->Ln(2);

        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(12, 6, 'Item', 1, 0, 'C', true);
        $pdf->Cell(65, 6, 'Inspection Point', 1, 0, 'C', true);
        $pdf->Cell(35, 6, 'Status', 1, 0, 'C', true);
        $pdf->Cell(40, 6, 'Comments / Observations', 1, 0, 'C', true);
        $pdf->Cell(18, 6, 'Action Required', 1, 1, 'C', true);

        $struct = [
            ['4.1', 'Tower Plumb and Alignment'],
            ['4.2', 'Main Leg Members'],
            ['4.3', 'Horizontal and Diagonal Bracing'],
            ['4.4', 'Bolts, Nuts, and Washers'],
            ['4.5', 'Welded Connections'],
            ['4.6', 'Surface Coating / Galvanization'],
            ['4.7', 'Salt Crystal Accumulation'],
        ];

        $pdf->SetFont('helvetica', '', 6);
        foreach ($struct as $s) {
            $pdf->Cell(12, 5, $s[0], 1, 0, 'C');
            $pdf->Cell(65, 5, $s[1], 1, 0, 'L');
            $pdf->Cell(35, 5, 'Pass  Monitor  Fail  N/A', 1, 0, 'C');
            $pdf->Cell(40, 5, '', 1, 0, 'L');
            $pdf->Cell(18, 5, '', 1, 1, 'L');
        }
        $pdf->Ln(4);

        // 5. Climbing & Safety Systems
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, '5. Climbing & Safety Systems', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->MultiCell(0, 5, 'Safety systems must be in perfect working order. Corrosion can severely compromise their integrity.', 0, 'L');
        $pdf->Ln(2);

        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(12, 6, 'Item', 1, 0, 'C', true);
        $pdf->Cell(65, 6, 'Inspection Point', 1, 0, 'C', true);
        $pdf->Cell(35, 6, 'Status', 1, 0, 'C', true);
        $pdf->Cell(40, 6, 'Comments / Observations', 1, 0, 'C', true);
        $pdf->Cell(18, 6, 'Action Required', 1, 1, 'C', true);

        $climb = [
            ['5.1', 'Climbing Ladder / Step Pegs'],
            ['5.2', 'Vertical Safety Cable / Rail System'],
            ['5.3', 'Safety System Anchor Points'],
            ['5.4', 'Rest Platforms and Handrails'],
        ];

        $pdf->SetFont('helvetica', '', 6);
        foreach ($climb as $s) {
            $pdf->Cell(12, 5, $s[0], 1, 0, 'C');
            $pdf->Cell(65, 5, $s[1], 1, 0, 'L');
            $pdf->Cell(35, 5, 'Pass  Monitor  Fail  N/A', 1, 0, 'C');
            $pdf->Cell(40, 5, '', 1, 0, 'L');
            $pdf->Cell(18, 5, '', 1, 1, 'L');
        }
        $pdf->Ln(4);

        // 6. Antenna, Coaxial Cable & Ancillary Systems
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, '6. Antenna, Coaxial Cable & Ancillary Systems', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->MultiCell(0, 5, 'Inspect all mounted equipment for security and weather resistance, which are critical in high-wind, high-moisture environments.', 0, 'L');
        $pdf->Ln(2);

        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(12, 6, 'Item', 1, 0, 'C', true);
        $pdf->Cell(65, 6, 'Inspection Point', 1, 0, 'C', true);
        $pdf->Cell(35, 6, 'Status', 1, 0, 'C', true);
        $pdf->Cell(40, 6, 'Comments / Observations', 1, 0, 'C', true);
        $pdf->Cell(18, 6, 'Action Required', 1, 1, 'C', true);

        $antenna = [
            ['6.1', 'Antenna Mounts and Hardware'],
            ['6.2', 'Antenna Condition (Radomes, Seals)'],
            ['6.3', 'Coaxial Cable / Waveguide Condition'],
            ['6.4', 'Connector Weatherproofing'],
            ['6.5', 'Cable Trays and Supports'],
        ];

        $pdf->SetFont('helvetica', '', 6);
        foreach ($antenna as $s) {
            $pdf->Cell(12, 5, $s[0], 1, 0, 'C');
            $pdf->Cell(65, 5, $s[1], 1, 0, 'L');
            $pdf->Cell(35, 5, 'Pass  Monitor  Fail  N/A', 1, 0, 'C');
            $pdf->Cell(40, 5, '', 1, 0, 'L');
            $pdf->Cell(18, 5, '', 1, 1, 'L');
        }
        $pdf->Ln(4);

        // ========== PAGE 3 ==========
        $pdf->AddPage();

        // 7. Grounding & Lightning Protection
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, '7. Grounding & Lightning Protection System', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->MultiCell(0, 5, 'Essential for safety and equipment protection on an exposed island. The saline environment can impact both corrosion and grounding effectiveness.', 0, 'L');
        $pdf->Ln(2);

        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(12, 6, 'Item', 1, 0, 'C', true);
        $pdf->Cell(65, 6, 'Inspection Point', 1, 0, 'C', true);
        $pdf->Cell(35, 6, 'Status', 1, 0, 'C', true);
        $pdf->Cell(40, 6, 'Comments / Observations', 1, 0, 'C', true);
        $pdf->Cell(18, 6, 'Action Required', 1, 1, 'C', true);

        $ground = [
            ['7.1', 'Air Terminal / Lightning Rod'],
            ['7.2', 'Down Conductors'],
            ['7.3', 'Grounding Connections (Exothermic Welds, Lugs)'],
            ['7.4', 'Ground Ring / Rods (Visible Portions)'],
            ['7.5', 'System Resistance Test (if performed)'],
        ];

        $pdf->SetFont('helvetica', '', 6);
        foreach ($ground as $s) {
            $pdf->Cell(12, 5, $s[0], 1, 0, 'C');
            $pdf->Cell(65, 5, $s[1], 1, 0, 'L');
            $pdf->Cell(35, 5, 'Pass  Monitor  Fail  N/A', 1, 0, 'C');
            $pdf->Cell(40, 5, '', 1, 0, 'L');
            $pdf->Cell(18, 5, '', 1, 1, 'L');
        }
        $pdf->Ln(4);

        // 8. Aviation Obstruction Lighting
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, '8. Aviation Obstruction Lighting & Ancillary Equipment', 0, 1, 'L');
        $pdf->Ln(1);

        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(12, 6, 'Item', 1, 0, 'C', true);
        $pdf->Cell(65, 6, 'Inspection Point', 1, 0, 'C', true);
        $pdf->Cell(35, 6, 'Status', 1, 0, 'C', true);
        $pdf->Cell(40, 6, 'Comments / Observations', 1, 0, 'C', true);
        $pdf->Cell(18, 6, 'Action Required', 1, 1, 'C', true);

        $aviation = [
            ['8.1', 'Aviation Light Fixtures'],
            ['8.2', 'Light Functionality (Day/Night Mode)'],
            ['8.3', 'Equipment Shelter / Cabinets'],
            ['8.4', 'Power Systems (Solar Panels, Generator, Batteries)'],
        ];

        $pdf->SetFont('helvetica', '', 6);
        foreach ($aviation as $s) {
            $pdf->Cell(12, 5, $s[0], 1, 0, 'C');
            $pdf->Cell(65, 5, $s[1], 1, 0, 'L');
            $pdf->Cell(35, 5, 'Pass  Monitor  Fail  N/A', 1, 0, 'C');
            $pdf->Cell(40, 5, '', 1, 0, 'L');
            $pdf->Cell(18, 5, '', 1, 1, 'L');
        }
        $pdf->Ln(5);

        // 9. Inspection Summary
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, '9. Inspection Summary & Recommendations', 0, 1, 'L');
        $pdf->Ln(1);

        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 5, 'Overall Tower Condition Assessment & Narrative Summary:', 0, 1, 'L');
        $pdf->MultiCell(0, 6, $reportData['work_performed'], 1, 'L');
        $pdf->Ln(3);

        $pdf->Cell(0, 5, 'Overall Rating:  [ ] Good - No significant issues found.  [ ] Fair - Minor issues, requires monitoring.  [ ] Poor - Significant issues.  [ ] Critical - Immediate action required.', 0, 1, 'L');
        $pdf->Ln(3);
        $pdf->Cell(0, 5, 'High-Priority Action Items Summary:', 0, 1, 'L');
        $pdf->Cell(0, 15, '', 1, 1, 'L');
        $pdf->Ln(5);

        // 10. Inspector Sign-Off
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, '10. Inspector Sign-Off', 0, 1, 'L');
        $pdf->Ln(2);

        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(85, 5, 'Lead Inspector:', 0, 0, 'L');
        $pdf->Cell(0, 5, 'Reviewer / Supervisor:', 0, 1, 'L');
        $pdf->Ln(10);
        $pdf->Cell(85, 5, 'Signature: ____________________________', 0, 0, 'L');
        $pdf->Cell(0, 5, 'Signature: ____________________________', 0, 1, 'L');
        $pdf->Cell(85, 5, 'Name: ' . $reportData['technician_name'], 0, 0, 'L');
        $pdf->Cell(0, 5, 'Name: ____________________________', 0, 1, 'L');
        $pdf->Cell(85, 5, 'Date: ' . $mainDate, 0, 0, 'L');
        $pdf->Cell(0, 5, 'Date: ____________________________', 0, 1, 'L');

        return $this->savePDF($pdf, $reportData['report_number']);
    }

    // RADAR MANUAL REPORT
    private function generateRadarManualReport($reportData, $deviceInfo)
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Network Monitor System');
        $pdf->SetTitle('Simrad HALO-6 Technical Maintenance and Service Log');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        $mainDate = date('Y-m-d', strtotime($reportData['maintenance_date']));

        // ========== TITLE ==========
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'Simrad HALO-6 Pulse Compression Radar', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 6, 'Technical Maintenance and Service Log', 0, 1, 'C');
        $pdf->Ln(5);

        // ========== EQUIPMENT AND SERVICE INFORMATION ==========
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, 'Equipment and Service Information', 0, 1, 'L');
        $pdf->Ln(1);

        $pdf->SetFont('helvetica', '', 9);
        $equipFields = [
            ['Equipment Model:',         'Simrad HALO-6 Pulse Compression Radar (w/ 6-foot open array)'],
            ['Vessel Name / ID:',        $deviceInfo['name'] ?? 'N/A'],
            ['Scanner Serial Number:',   $deviceInfo['id'] ?? 'N/A'],
            ['RI-12 Interface Box S/N:', 'N/A'],
            ['Service Date:',            $mainDate],
            ['Technician Name:',         $reportData['technician_name']],
            ['Technician Signature:',    '____________________________'],
        ];
        foreach ($equipFields as $f) {
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(55, 6, $f[0], 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 6, $f[1], 0, 1, 'L');
        }
        $pdf->Ln(5);

        // ========== MAINTENANCE WORK PERFORMED (MANUAL DATA!) ==========
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetFillColor(200, 230, 200);
        $pdf->Cell(0, 8, 'Maintenance Work Performed', 1, 1, 'L', true);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->MultiCell(0, 5, $reportData['work_performed'], 1, 'L');
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(50, 6, 'Service Status:', 1, 0, 'L');
        if ($reportData['status'] === 'Pass') {
            $pdf->SetTextColor(0, 150, 0);
        } else {
            $pdf->SetTextColor(200, 0, 0);
        }
        $pdf->Cell(0, 6, $reportData['status'], 1, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(5);

        // ========== PREVENTIVE MAINTENANCE CHECKLIST ==========
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, 'Preventive Maintenance Checklist', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->MultiCell(0, 5, 'Perform these checks periodically to ensure optimal performance and longevity of the radar system. Status options: Pass, Fail, N/A (Not Applicable).', 0, 'L');
        $pdf->Ln(2);

        // Table header
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(12, 6, 'Task #', 1, 0, 'C', true);
        $pdf->Cell(28, 6, 'Category', 1, 0, 'C', true);
        $pdf->Cell(90, 6, 'Task Description', 1, 0, 'C', true);
        $pdf->Cell(20, 6, 'Status', 1, 0, 'C', true);
        $pdf->Cell(20, 6, 'Comments', 1, 1, 'C', true);

        // Category A
        $pdf->SetFont('helvetica', '', 6);
        $catA = [
            ['A.1', 'A. Physical & Mechanical Inspection', 'Visually inspect the 6-foot open array antenna for cracks, physical damage, or delamination. Ensure it is clean and free of debris.'],
            ['A.2', '', 'Clean the antenna array using a soft cloth, fresh water, and a mild, non-abrasive soap. Rinse thoroughly. Do not use chemical solvents or high-pressure washers.'],
            ['A.3', '', 'Inspect the pedestal housing for cracks, seal integrity, and signs of water ingress. Check that all covers are secure.'],
            ['A.4', '', 'Check all mounting bolts for the pedestal for proper tightness and signs of corrosion. Ensure marine-grade stainless steel hardware is used.'],
            ['A.5', '', 'With the system powered down, manually check that the antenna can swing freely without obstruction or excessive resistance.'],
        ];

        foreach ($catA as $row) {
            $pdf->Cell(12, 5, $row[0], 1, 0, 'C');
            $pdf->Cell(28, 5, $row[1], 1, 0, 'L');
            $pdf->Cell(90, 5, $row[2], 1, 0, 'L');
            $pdf->Cell(20, 5, '', 1, 0, 'C');
            $pdf->Cell(20, 5, '', 1, 1, 'L');
        }

        // Category B
        $catB = [
            ['B.1', 'B. Electrical & Network Inspection', 'Inspect the main power cable to the RI-12 Interface Box. Check for chafing, cuts, UV damage, and corrosion at the connection points. Ensure connections are tight.'],
            ['B.2', '', 'Inspect the cable between the RI-12 Interface Box and the scanner pedestal. Check for damage to the cable and connectors (RJ45). Ensure a secure connection.'],
            ['B.3', '', 'Inspect the Ethernet cable connecting the RI-12 to the MFD or network switch. Check for secure connections and cable integrity.'],
            ['B.4', '', 'Open the RI-12 Interface Box. Visually inspect internal connections for tightness, signs of corrosion, or moisture. Ensure the lid seal is in good condition.'],
            ['B.5', '', 'With the system on, use a multimeter to measure the DC voltage at the RI-12 power input terminals. Verify it is within the specified range (typically 12/24V DC).'],
            ['B.6', '', 'If connected, inspect the NMEA 2000 backbone. Verify the presence of exactly two terminators, secure T-connectors, and a stable power supply (9-16V DC).'],
        ];

        foreach ($catB as $row) {
            $pdf->Cell(12, 5, $row[0], 1, 0, 'C');
            $pdf->Cell(28, 5, $row[1], 1, 0, 'L');
            $pdf->Cell(90, 5, $row[2], 1, 0, 'L');
            $pdf->Cell(20, 5, '', 1, 0, 'C');
            $pdf->Cell(20, 5, '', 1, 1, 'L');
        }

        // Category C
        $catC = [
            ['C.1', 'C. System & Software Checks', 'Check the radar\'s installed software version via the MFD\'s network device list. Compare with the latest version available on the Simrad Yachting support website. Current: _____ Latest: _____'],
            ['C.2', '', 'Power on the MFD and radar system. Confirm the radar is detected by the MFD within the normal 30-40 second startup time and does not display a "No Scanner" error.'],
            ['C.3', '', 'Place the radar in Transmit (Tx) mode. Confirm the open array antenna begins to rotate smoothly and quietly. Listen for any unusual noises from the pedestal motor.'],
            ['C.4', '', 'Verify a clear radar picture is painted on the MFD. Check for good target definition at both short (e.g., <1 NM) and long ranges (e.g., >12 NM).'],
            ['C.5', '', 'Briefly test key operational modes such as Harbour, Offshore, Weather, and Bird to confirm they activate and adjust the display as expected.'],
            ['C.6', '', 'Activate Simultaneous Dual Range operation. Confirm the MFD displays two independent radar ranges and that both update correctly.'],
        ];

        foreach ($catC as $row) {
            $pdf->Cell(12, 5, $row[0], 1, 0, 'C');
            $pdf->Cell(28, 5, $row[1], 1, 0, 'L');
            $pdf->Cell(90, 5, $row[2], 1, 0, 'L');
            $pdf->Cell(20, 5, '', 1, 0, 'C');
            $pdf->Cell(20, 5, '', 1, 1, 'L');
        }

        // ========== PAGE 2: CORRECTIVE MAINTENANCE LOG ==========
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, 'Corrective Maintenance Log', 0, 1, 'L');
        $pdf->Ln(1);

        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(55, 6, 'Reported Fault / Symptom Description:', 0, 0, 'L');
        $pdf->MultiCell(0, 6, $reportData['work_performed'], 1, 'L');
        $pdf->Ln(4);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, 'Troubleshooting Steps & Findings', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(0, 5, 'Follow these guided steps based on the reported symptom. Document all actions and findings.', 0, 1, 'L');
        $pdf->Ln(2);

        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(35, 6, 'Step', 1, 0, 'C', true);
        $pdf->Cell(55, 6, 'Action Taken / Check Performed', 1, 0, 'C', true);
        $pdf->Cell(45, 6, 'Result / Finding', 1, 0, 'C', true);
        $pdf->Cell(35, 6, 'Corrective Action / Notes', 1, 1, 'C', true);

        $steps = [
            ['1.1 Check dedicated breaker/fuse for marine electronics.',          '', ''],
            ['1.2 Confirm voltage at the RI-12 Interface Box power input.',       'Voltage Reading: _____ V', ''],
            ['1.3 Inspect power cable from source to RI-12 for damage.',          '', ''],
            ['1.4 Power cycle the entire system. Wait 60 seconds.',               '', ''],
            ['2.1 Check MFD > Settings > Network > Device List.',                 '', ''],
            ['2.2 Inspect Ethernet cable from RI-12 to MFD/switch. Reseat ends.',  '', ''],
            ['2.3 Inspect interconnect cable from RI-12 to scanner pedestal.',    'Common point of failure.', ''],
            ['3.1 Confirm radar is in Transmit (Tx) mode on MFD.',               '', ''],
            ['3.2 Observe scanner pedestal - confirm antenna rotating.',           '', ''],
            ['4.1 Listen for motor drive sound in pedestal when in Tx mode.',     '', ''],
            ['4.2 Power down - inspect antenna path for physical obstructions.',  '', ''],
        ];

        $pdf->SetFont('helvetica', '', 6);
        foreach ($steps as $s) {
            $pdf->Cell(35, 5, $s[0], 1, 0, 'L');
            $pdf->Cell(55, 5, '', 1, 0, 'L');
            $pdf->Cell(45, 5, $s[1], 1, 0, 'L');
            $pdf->Cell(35, 5, $s[2], 1, 1, 'L');
        }

        $pdf->Ln(3);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(0, 5, 'Final Diagnosis & Resolution Summary:', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 15, '', 1, 1, 'L');
        $pdf->Ln(5);

        // ========== FINAL SYSTEM TEST & VERIFICATION ==========
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, 'Final System Test & Verification', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(0, 5, 'To be completed after any maintenance or corrective action.', 0, 1, 'L');
        $pdf->Ln(2);

        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(15, 6, 'Test #', 1, 0, 'C', true);
        $pdf->Cell(110, 6, 'Verification Check', 1, 0, 'C', true);
        $pdf->Cell(25, 6, 'Pass / Fail', 1, 0, 'C', true);
        $pdf->Cell(20, 6, 'Comments', 1, 1, 'C', true);

        $tests = [
            ['1', 'System powers on correctly and radar is detected by MFD without errors.'],
            ['2', 'Radar transmits and antenna rotates smoothly.'],
            ['3', 'Clear radar image is displayed with good target separation.'],
            ['4', 'All operational modes (Harbour, Offshore, etc.) and Dual Range function correctly.'],
            ['5', 'System operates for a minimum of 15 minutes without recurrence of the original fault.'],
        ];

        $pdf->SetFont('helvetica', '', 6);
        foreach ($tests as $t) {
            $pdf->Cell(15, 6, $t[0], 1, 0, 'C');
            $pdf->Cell(110, 6, $t[1], 1, 0, 'L');
            $pdf->Cell(25, 6, '', 1, 0, 'C');
            $pdf->Cell(20, 6, '', 1, 1, 'L');
        }
        $pdf->Ln(5);

        // ========== SIGNATURES ==========
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(85, 5, 'Technician:', 0, 0, 'L');
        $pdf->Cell(0, 5, 'Customer / Supervisor:', 0, 1, 'L');
        $pdf->Ln(10);
        $pdf->Cell(85, 5, 'Signature: ____________________________', 0, 0, 'L');
        $pdf->Cell(0, 5, 'Signature: ____________________________', 0, 1, 'L');
        $pdf->Cell(85, 5, 'Name: ' . $reportData['technician_name'], 0, 0, 'L');
        $pdf->Cell(0, 5, 'Name: ____________________________', 0, 1, 'L');
        $pdf->Cell(85, 5, 'Date: ' . $mainDate, 0, 0, 'L');
        $pdf->Cell(0, 5, 'Date: ____________________________', 0, 1, 'L');

        return $this->savePDF($pdf, $reportData['report_number']);
    }

    // DATABASE MANUAL REPORT
    private function generateDatabaseManualReport($reportData, $deviceInfo)
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Network Monitor System');
        $pdf->SetTitle('Database Maintenance Report');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        $mainDate  = date('Y-m-d', strtotime($reportData['maintenance_date']));
        $startDate = date('Y-m-01', strtotime($reportData['maintenance_date']));
        $endDate   = date('Y-m-t',  strtotime($reportData['maintenance_date']));

        // ========== TITLE ==========
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->Cell(0, 12, 'Database Maintenance Report', 0, 1, 'C');
        $pdf->Ln(3);

        // Header fields
        $pdf->SetFont('helvetica', '', 9);
        $headerFields = [
            ['Report Date',       $mainDate],
            ['Reporting Period',  $startDate . ' to ' . $endDate],
            ['Prepared For',      'IT Steering Committee'],
            ['Prepared By',       $reportData['technician_name']],
            ['Monitored Systems', $deviceInfo['name'] . ' (' . ($deviceInfo['ip_address'] ?? 'N/A') . ')'],
        ];
        foreach ($headerFields as $f) {
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(40, 6, $f[0], 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 6, $f[1], 0, 1, 'L');
        }
        $pdf->Ln(5);

        // ========== 1.0 EXECUTIVE SUMMARY ==========
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, '1.0 Executive Summary', 0, 1, 'L');
        $pdf->Ln(1);

        $pdf->SetFont('helvetica', '', 9);
        $pdf->MultiCell(0, 5, 'This report provides a comprehensive overview of the health, performance, and maintenance activities for the production database environment during the specified period. The primary objective is to ensure system stability, data integrity, and optimal performance in support of business operations.', 0, 'L');
        $pdf->Ln(3);

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(0, 5, 'Overall Health Assessment:', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $overallHealth = $reportData['work_performed'];
        $pdf->MultiCell(0, 5, $overallHealth, 1, 'L');
        $pdf->Ln(3);

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(0, 5, 'Maintenance Status:', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9);
        if ($reportData['status'] === 'Pass') {
            $pdf->SetTextColor(0, 150, 0);
            $pdf->Cell(0, 5, '✓ ' . $reportData['status'] . ' - All maintenance tasks completed successfully.', 0, 1, 'L');
        } else {
            $pdf->SetTextColor(200, 0, 0);
            $pdf->Cell(0, 5, '✗ ' . $reportData['status'] . ' - Issues detected, review required.', 0, 1, 'L');
        }
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(5);

        // ========== 2.0 KEY SYSTEM METRICS ==========
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, '2.0 Key System Metrics', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(0, 5, 'This section presents a snapshot of the core performance and health indicators for the primary database servers. Thresholds are defined based on established operational baselines.', 0, 1, 'L');
        $pdf->Ln(2);

        // 2.1 Server Health KPIs
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, '2.1 Server Health KPIs', 0, 1, 'L');

        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(45, 6, 'Metric', 1, 0, 'C', true);
        $pdf->Cell(40, 6, 'Server/Instance', 1, 0, 'C', true);
        $pdf->Cell(35, 6, 'Value (Avg / Peak)', 1, 0, 'C', true);
        $pdf->Cell(30, 6, 'Threshold', 1, 0, 'C', true);
        $pdf->Cell(20, 6, 'Status', 1, 1, 'C', true);

        $kpis = [
            ['Uptime / Availability',    $deviceInfo['name'], 'N/A',    '> 99.95%', '[ ] Normal'],
            ['CPU Utilization',          $deviceInfo['name'], 'N/A',    'Avg < 60%', '[ ] Normal'],
            ['Memory Usage (PLE)',       $deviceInfo['name'], 'N/A',    '> 300 sec', '[ ] Normal'],
            ['Disk I/O Latency (Data)',  $deviceInfo['name'], 'N/A',    '< 20ms',   '[ ] Normal'],
            ['Disk I/O Latency (Log)',   $deviceInfo['name'], 'N/A',    '< 5ms',    '[ ] Normal'],
        ];

        $pdf->SetFont('helvetica', '', 6);
        foreach ($kpis as $k) {
            $pdf->Cell(45, 5, $k[0], 1, 0, 'L');
            $pdf->Cell(40, 5, $k[1], 1, 0, 'L');
            $pdf->Cell(35, 5, $k[2], 1, 0, 'C');
            $pdf->Cell(30, 5, $k[3], 1, 0, 'C');
            $pdf->Cell(20, 5, $k[4], 1, 1, 'C');
        }
        $pdf->Ln(3);

        // 2.2 Storage Capacity
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, '2.2 Storage Capacity', 0, 1, 'L');

        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(40, 6, 'Server/Instance', 1, 0, 'C', true);
        $pdf->Cell(30, 6, 'Drive/Mount Point', 1, 0, 'C', true);
        $pdf->Cell(20, 6, 'Total Size', 1, 0, 'C', true);
        $pdf->Cell(20, 6, 'Used Space', 1, 0, 'C', true);
        $pdf->Cell(20, 6, 'Free Space', 1, 0, 'C', true);
        $pdf->Cell(15, 6, '% Used', 1, 0, 'C', true);
        $pdf->Cell(25, 6, 'Status', 1, 1, 'C', true);

        $pdf->SetFont('helvetica', '', 6);
        $pdf->Cell(40, 5, $deviceInfo['name'], 1, 0, 'L');
        $pdf->Cell(30, 5, 'N/A', 1, 0, 'C');
        $pdf->Cell(20, 5, 'N/A', 1, 0, 'C');
        $pdf->Cell(20, 5, 'N/A', 1, 0, 'C');
        $pdf->Cell(20, 5, 'N/A', 1, 0, 'C');
        $pdf->Cell(15, 5, 'N/A', 1, 0, 'C');
        $pdf->Cell(25, 5, '[ ] Normal', 1, 1, 'C');
        $pdf->Ln(5);

        // ========== PAGE 2 ==========
        $pdf->AddPage();

        // ========== 3.0 BACKUP AND RECOVERY STATUS ==========
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, '3.0 Backup and Recovery Status', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->MultiCell(0, 5, 'This section details the status of all database backup operations, which are fundamental to the organization\'s data protection and disaster recovery strategy.', 0, 'L');
        $pdf->Ln(2);

        // 3.1 Backup Execution Summary
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, '3.1 Backup Execution Summary', 0, 1, 'L');

        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(35, 6, 'Database Name', 1, 0, 'C', true);
        $pdf->Cell(30, 6, 'Server', 1, 0, 'C', true);
        $pdf->Cell(25, 6, 'Backup Type', 1, 0, 'C', true);
        $pdf->Cell(30, 6, 'Last Successful Backup', 1, 0, 'C', true);
        $pdf->Cell(20, 6, 'Status', 1, 0, 'C', true);
        $pdf->Cell(30, 6, 'Location', 1, 1, 'C', true);

        $pdf->SetFont('helvetica', '', 6);
        $backupTypes = ['Full', 'Differential', 'Transaction Log'];
        foreach ($backupTypes as $bt) {
            $pdf->Cell(35, 5, $deviceInfo['name'], 1, 0, 'L');
            $pdf->Cell(30, 5, $deviceInfo['ip_address'] ?? 'N/A', 1, 0, 'L');
            $pdf->Cell(25, 5, $bt, 1, 0, 'C');
            $pdf->Cell(30, 5, $mainDate, 1, 0, 'C');
            $pdf->Cell(20, 5, '[ ] Success', 1, 0, 'C');
            $pdf->Cell(30, 5, '\\\\backup-nas\\sql\\', 1, 1, 'L');
        }
        $pdf->Ln(3);

        // 3.2 Backup Verification and Restore Tests
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, '3.2 Backup Verification and Restore Tests', 0, 1, 'L');

        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(40, 6, 'Database', 1, 0, 'C', true);
        $pdf->Cell(40, 6, 'Verification Type', 1, 0, 'C', true);
        $pdf->Cell(30, 6, 'Last Test Date', 1, 0, 'C', true);
        $pdf->Cell(20, 6, 'Result', 1, 0, 'C', true);
        $pdf->Cell(40, 6, 'Next Scheduled Test', 1, 1, 'C', true);

        $pdf->SetFont('helvetica', '', 6);
        $pdf->Cell(40, 5, $deviceInfo['name'], 1, 0, 'L');
        $pdf->Cell(40, 5, 'Full Restore Drill', 1, 0, 'L');
        $pdf->Cell(30, 5, $mainDate, 1, 0, 'C');
        $pdf->Cell(20, 5, '[ ] Success', 1, 0, 'C');
        $pdf->Cell(40, 5, date('Y-m-d', strtotime('+90 days')), 1, 1, 'C');

        $pdf->Cell(40, 5, 'All Databases', 1, 0, 'L');
        $pdf->Cell(40, 5, 'Backup Integrity Check (RESTORE VERIFYONLY)', 1, 0, 'L');
        $pdf->Cell(30, 5, 'Daily', 1, 0, 'C');
        $pdf->Cell(20, 5, '[ ] Success', 1, 0, 'C');
        $pdf->Cell(40, 5, 'Daily', 1, 1, 'C');
        $pdf->Ln(5);

        // ========== 4.0 DETAILED PERFORMANCE ANALYSIS ==========
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, '4.0 Detailed Performance Analysis', 0, 1, 'L');
        $pdf->Ln(1);

        // 4.1 Index Health and Fragmentation
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, '4.1 Index Health and Fragmentation', 0, 1, 'L');

        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(35, 6, 'Database', 1, 0, 'C', true);
        $pdf->Cell(35, 6, 'Table', 1, 0, 'C', true);
        $pdf->Cell(50, 6, 'Index Name', 1, 0, 'C', true);
        $pdf->Cell(25, 6, 'Fragmentation %', 1, 0, 'C', true);
        $pdf->Cell(25, 6, 'Recommended Action', 1, 1, 'C', true);

        $pdf->SetFont('helvetica', '', 6);
        $pdf->Cell(35, 5, $deviceInfo['name'], 1, 0, 'L');
        $pdf->Cell(35, 5, 'N/A', 1, 0, 'L');
        $pdf->Cell(50, 5, 'N/A', 1, 0, 'L');
        $pdf->Cell(25, 5, 'N/A', 1, 0, 'C');
        $pdf->Cell(25, 5, '[ ] Rebuild  [ ] Reorganize', 1, 1, 'C');
        $pdf->Ln(3);

        // 4.4 Database Integrity Checks
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, '4.4 Database Integrity Checks (DBCC CHECKDB)', 0, 1, 'L');

        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(70, 6, 'Database Name', 1, 0, 'C', true);
        $pdf->Cell(60, 6, 'Last Integrity Check', 1, 0, 'C', true);
        $pdf->Cell(40, 6, 'Result', 1, 1, 'C', true);

        $pdf->SetFont('helvetica', '', 6);
        $pdf->Cell(70, 5, $deviceInfo['name'], 1, 0, 'L');
        $pdf->Cell(60, 5, $mainDate, 1, 0, 'C');
        $pdf->Cell(40, 5, '[ ] No errors found', 1, 1, 'C');
        $pdf->Ln(5);

        // ========== PAGE 3 ==========
        $pdf->AddPage();

        // ========== 5.0 SECURITY AND COMPLIANCE ==========
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, '5.0 Security and Compliance', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->MultiCell(0, 5, 'This section covers security-related maintenance, including user access reviews, patch management, and configuration audits to ensure compliance with internal security policies.', 0, 'L');
        $pdf->Ln(2);

        // 5.1 Patch Management
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, '5.1 Patch Management Status', 0, 1, 'L');

        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(50, 6, 'Server/Instance', 1, 0, 'C', true);
        $pdf->Cell(45, 6, 'Current Version', 1, 0, 'C', true);
        $pdf->Cell(45, 6, 'Latest Available CU', 1, 0, 'C', true);
        $pdf->Cell(30, 6, 'Status', 1, 1, 'C', true);

        $pdf->SetFont('helvetica', '', 6);
        $pdf->Cell(50, 5, $deviceInfo['name'], 1, 0, 'L');
        $pdf->Cell(45, 5, 'N/A', 1, 0, 'C');
        $pdf->Cell(45, 5, 'N/A', 1, 0, 'C');
        $pdf->Cell(30, 5, '[ ] Up-to-date  [ ] Patch Required', 1, 1, 'C');
        $pdf->Ln(3);

        // 5.2 Login and Permissions Audit
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, '5.2 Login and Permissions Audit', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->MultiCell(
            0,
            5,
            "- New Logins/Users: [#] new logins were created per approved access requests.\n" .
                "- Disabled Accounts: [#] accounts were disabled for terminated employees.\n" .
                "- Failed Logins: A review of failed login attempts was conducted. No suspicious attempts detected.\n" .
                "- Privileged Access Review: A review of sysadmin and db_owner role members was completed. All memberships are confirmed appropriate.",
            1,
            'L'
        );
        $pdf->Ln(5);

        // ========== 6.0 RECOMMENDATIONS AND ACTION PLAN ==========
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 7, '6.0 Recommendations and Action Plan', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->MultiCell(0, 5, 'Based on the findings in this report, the following actions are recommended to improve performance, enhance security, and ensure the continued stability of the database environment.', 0, 'L');
        $pdf->Ln(2);

        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetFillColor(220, 220, 220);
        $pdf->Cell(18, 6, 'ID', 1, 0, 'C', true);
        $pdf->Cell(65, 6, 'Recommendation', 1, 0, 'C', true);
        $pdf->Cell(40, 6, 'Justification / Finding', 1, 0, 'C', true);
        $pdf->Cell(20, 6, 'Priority', 1, 0, 'C', true);
        $pdf->Cell(27, 6, 'Status', 1, 1, 'C', true);

        $recs = [
            ['REC-001', 'Schedule and apply latest Cumulative Updates to all database servers.',   'Ensure servers are patched against latest security vulnerabilities.',  'High',   'Not Started'],
            ['REC-002', 'Implement weekly index rebuild job for heavily fragmented tables.',        'High fragmentation (>85%) is impacting query performance.',           'High',   'Not Started'],
            ['REC-003', 'Investigate and address top resource-intensive queries.',                 'High average duration and execution count contributing to CPU pressure.', 'Medium', 'Not Started'],
            ['REC-004', 'Schedule next full database maintenance window in 30 days.',              'Regular maintenance ensures continued system health.',                'Medium', 'Not Started'],
        ];

        $pdf->SetFont('helvetica', '', 6);
        foreach ($recs as $r) {
            $pdf->Cell(18, 5, $r[0], 1, 0, 'C');
            $pdf->Cell(65, 5, $r[1], 1, 0, 'L');
            $pdf->Cell(40, 5, $r[2], 1, 0, 'L');
            $pdf->Cell(20, 5, $r[3], 1, 0, 'C');
            $pdf->Cell(27, 5, $r[4], 1, 1, 'C');
        }

        // ========== SIGNATURES ==========
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(85, 5, 'Prepared By (DBA):', 0, 0, 'L');
        $pdf->Cell(0, 5, 'Approved By (Manager):', 0, 1, 'L');
        $pdf->Ln(10);
        $pdf->Cell(85, 5, 'Signature: ____________________________', 0, 0, 'L');
        $pdf->Cell(0, 5, 'Signature: ____________________________', 0, 1, 'L');
        $pdf->Cell(85, 5, 'Name: ' . $reportData['technician_name'], 0, 0, 'L');
        $pdf->Cell(0, 5, 'Name: ____________________________', 0, 1, 'L');
        $pdf->Cell(85, 5, 'Date: ' . $mainDate, 0, 0, 'L');
        $pdf->Cell(0, 5, 'Date: ____________________________', 0, 1, 'L');

        return $this->savePDF($pdf, $reportData['report_number']);
    }

    // ================================================================
    // GENERATE MANUAL REPORT PDF - Routes to correct template
    // ================================================================
    private function generateManualReportPDF($reportData, $deviceInfo, $templateType)
    {
        switch ($templateType) {
            case 'HARDWARE':
                return $this->generateHardwareManualReport($reportData, $deviceInfo);

            case 'TOWER':
                return $this->generateTowerManualReport($reportData, $deviceInfo);

            case 'RADAR':
                return $this->generateRadarManualReport($reportData, $deviceInfo);

            case 'DATABASE':
                return $this->generateDatabaseManualReport($reportData, $deviceInfo);

            default:
                throw new Exception("Unknown template type: {$templateType}");
        }
    }

    // ================================================================
    // HELPER: Get equipment type ID by code
    // ================================================================
    private function getEquipmentTypeIdByCode($code)
    {
        $stmt = $this->db->prepare("SELECT id FROM equipment_types WHERE type_code = ?");
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if (!$row) {
            // If not found, create it
            $stmt = $this->db->prepare("INSERT INTO equipment_types (type_code, type_name) VALUES (?, ?)");
            $typeName = ucfirst(strtolower($code));
            $stmt->bind_param('ss', $code, $typeName);
            $stmt->execute();
            return $this->db->insert_id;
        }

        return $row['id'];
    }
}