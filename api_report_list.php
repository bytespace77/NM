<?php
// ================================================================
// API: REPORT LIST AND DOWNLOAD
// Fetch generated reports and download DOCX/PDF files
// ================================================================

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $db = getDB();
    $action = $_GET['action'] ?? 'list';
    
    // ================================================================
    // LIST ALL REPORTS
    // ================================================================
    if ($action === 'list') {
        $deviceId = $_GET['device_id'] ?? null;
        $reportType = $_GET['report_type'] ?? null;
        $equipmentType = $_GET['equipment_type'] ?? null;
        $from_date = $_GET['from_date'] ?? null;
        $to_date = $_GET['to_date'] ?? null;
        
        $sql = "SELECT * FROM v_reports_list WHERE 1=1";
        
        if ($deviceId) {
            $deviceId = (int)$deviceId;
            $sql .= " AND device_id = $deviceId";
        }
        
        if ($reportType) {
            $safeReportType = $db->real_escape_string($reportType);
            $sql .= " AND report_type = '$safeReportType'";
        }
        
        if ($equipmentType) {
            $safeEquipmentType = $db->real_escape_string($equipmentType);
            $sql .= " AND equipment_type_code = '$safeEquipmentType'";
        }
        
        if ($from_date) {
            $safeFromDate = $db->real_escape_string($from_date);
            $sql .= " AND DATE(generated_at) >= '$safeFromDate'";
        }
        
        if ($to_date) {
            $safeToDate = $db->real_escape_string($to_date);
            $sql .= " AND DATE(generated_at) <= '$safeToDate'";
        }
        
        $sql .= " ORDER BY generated_at DESC";
        
        $result = $db->query($sql);
        $reports = [];
        
        while ($row = $result->fetch_assoc()) {
            $reports[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'reports' => $reports,
            'total' => count($reports)
        ]);
    }
    
    // ================================================================
    // GET SINGLE REPORT
    // ================================================================
    else if ($action === 'get') {
        $reportId = $_GET['id'] ?? null;
        
        if (!$reportId) {
            echo json_encode([
                'success' => false,
                'error' => 'Report ID is required'
            ]);
            exit;
        }
        
        $reportId = (int)$reportId;
        $sql = "SELECT * FROM v_reports_list WHERE id = $reportId";
        $result = $db->query($sql);
        
        if ($result->num_rows > 0) {
            $report = $result->fetch_assoc();
            
            // Get report sections
            $sectionsQuery = "SELECT * FROM report_sections WHERE report_id = $reportId ORDER BY section_order";
            $sectionsResult = $db->query($sectionsQuery);
            $sections = [];
            
            while ($section = $sectionsResult->fetch_assoc()) {
                $sections[] = $section;
            }
            
            $report['sections'] = $sections;
            
            echo json_encode([
                'success' => true,
                'report' => $report
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Report not found'
            ]);
        }
    }
    
    // ================================================================
    // DOWNLOAD FILE
    // ================================================================
    else if ($action === 'download') {
        header('Content-Type: application/octet-stream');
        
        $reportId = $_GET['id'] ?? null;
        $format = $_GET['format'] ?? 'pdf'; // docx or pdf
        
        if (!$reportId) {
            echo json_encode([
                'success' => false,
                'error' => 'Report ID is required'
            ]);
            exit;
        }
        
        $reportId = (int)$reportId;
        $sql = "SELECT * FROM maintenance_reports WHERE id = $reportId";
        $result = $db->query($sql);
        
        if ($result->num_rows > 0) {
            $report = $result->fetch_assoc();
            
            $fileField = $format === 'docx' ? 'docx_file' : 'pdf_file';
            $filePath = __DIR__ . '/generated_reports/' . $report[$fileField];
            
            if (file_exists($filePath)) {
                // Set download headers
                $filename = basename($filePath);
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . filesize($filePath));
                
                // Output file
                readfile($filePath);
                exit;
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'File not found: ' . $filePath
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Report not found'
            ]);
        }
    }
    
    // ================================================================
    // GET SUMMARY STATISTICS
    // ================================================================
    else if ($action === 'summary') {
        $sql = "SELECT * FROM v_reports_summary";
        $result = $db->query($sql);
        
        $summary = [];
        while ($row = $result->fetch_assoc()) {
            $summary[] = $row;
        }
        
        // Overall stats
        $overallQuery = "SELECT 
            COUNT(*) as total_reports,
            SUM(CASE WHEN report_type = 'ai_prediction' THEN 1 ELSE 0 END) as ai_generated,
            SUM(CASE WHEN report_type = 'scheduled_maintenance' THEN 1 ELSE 0 END) as maintenance_generated,
            SUM(CASE WHEN overall_condition = 'critical' THEN 1 ELSE 0 END) as critical_issues
        FROM maintenance_reports";
        
        $overallResult = $db->query($overallQuery);
        $overall = $overallResult->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'by_equipment' => $summary,
            'overall' => $overall
        ]);
    }
    
    // ================================================================
    // DELETE REPORT
    // ================================================================
    else if ($action === 'delete') {
        $reportId = $_GET['id'] ?? null;
        
        if (!$reportId) {
            echo json_encode([
                'success' => false,
                'error' => 'Report ID is required'
            ]);
            exit;
        }
        
        $reportId = (int)$reportId;
        
        // Get report files
        $sql = "SELECT docx_file, pdf_file FROM maintenance_reports WHERE id = $reportId";
        $result = $db->query($sql);
        
        if ($result->num_rows > 0) {
            $report = $result->fetch_assoc();
            
            // Delete files
            $docxPath = __DIR__ . '/generated_reports/' . $report['docx_file'];
            $pdfPath = __DIR__ . '/generated_reports/' . $report['pdf_file'];
            
            @unlink($docxPath);
            @unlink($pdfPath);
            
            // Delete from database
            $deleteQuery = "DELETE FROM maintenance_reports WHERE id = $reportId";
            $db->query($deleteQuery);
            
            echo json_encode([
                'success' => true,
                'message' => 'Report deleted successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Report not found'
            ]);
        }
    }
    
    else {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid action. Use: list, get, download, summary, delete'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>