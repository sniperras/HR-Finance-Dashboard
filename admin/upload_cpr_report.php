<?php
// upload_cpr_report.php - Handle CPR Excel file upload for ALL departments
require_once '../session_config.php';
require_once '../includes/auth.php';
requireRole('hr');

// Include Composer autoloader for PhpSpreadsheet
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

$response = ['success' => false, 'data' => [], 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    $response['message'] = 'Please select a valid Excel file';
    echo json_encode($response);
    exit;
}

$report_type = $_POST['report_type'] ?? '';
$month = intval($_POST['month'] ?? 0);
$year = intval($_POST['year'] ?? 0);

if ($report_type !== 'CPR') {
    $response['message'] = 'This upload is only for CPR report type';
    echo json_encode($response);
    exit;
}

if ($month == 0 || $year == 0) {
    $response['message'] = 'Missing month or year';
    echo json_encode($response);
    exit;
}

try {
    $spreadsheet = IOFactory::load($_FILES['excel_file']['tmp_name']);
    $worksheet = $spreadsheet->getActiveSheet();
    
    // Debug: Log the first few rows to see what we're getting
    error_log("=== Excel Debug Info ===");
    error_log("Cell E1: " . $worksheet->getCell('E1')->getValue());
    error_log("Cell G1: " . $worksheet->getCell('G1')->getValue());
    
    for ($i = 2; $i <= 10; $i++) {
        error_log("Row $i - B: " . $worksheet->getCell('B' . $i)->getValue() . " | C: " . $worksheet->getCell('C' . $i)->getValue() . " | E: " . $worksheet->getCell('E' . $i)->getValue() . " | F: " . $worksheet->getCell('F' . $i)->getValue());
    }

    // Read month from cell E1
    $excelMonth = trim($worksheet->getCell('E1')->getValue() ?: '');
    $excelYear = trim($worksheet->getCell('G1')->getValue() ?: '');

    // Validate month
    $monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $foundMonth = false;
    $uploadedMonth = 0;
    foreach ($monthNames as $idx => $mName) {
        if (stripos($excelMonth, $mName) !== false) {
            $uploadedMonth = $idx + 1;
            $foundMonth = true;
            break;
        }
    }

    if (!$foundMonth || $uploadedMonth != $month) {
        $response['message'] = 'Month in Excel (' . $excelMonth . ') does not match selected month';
        echo json_encode($response);
        exit;
    }

    if (intval($excelYear) != $year) {
        $response['message'] = 'Year in Excel (' . $excelYear . ') does not match selected year';
        echo json_encode($response);
        exit;
    }

    // Parse the data - Get all rows as array for easier processing
    $highestRow = $worksheet->getHighestRow();
    $data = [];
    
    for ($row = 2; $row <= $highestRow; $row++) {
        $bVal = trim($worksheet->getCell('B' . $row)->getValue() ?: '');
        $cVal = trim($worksheet->getCell('C' . $row)->getValue() ?: '');
        $eVal = intval($worksheet->getCell('E' . $row)->getValue() ?: 0);
        $fVal = intval($worksheet->getCell('F' . $row)->getValue() ?: 0);
        
        if (empty($cVal) && empty($bVal)) {
            continue;
        }
        
        $data[] = [
            'dept' => $bVal,
            'cost_center' => $cVal,
            'expected' => $eVal,
            'completed' => $fVal,
            'row' => $row
        ];
    }
    
    error_log("Total rows parsed: " . count($data));
    
    // Define department-cost center mapping with exact Excel text
    $mapping = [
        'BMT' => [
            'DIR' => ['Dir. BMT'],
            'ACS' => ['Mgr. A/C Structure Main'],
            'AVS' => ['Mgr. Avionc Sys Maint,'],
            'B787' => ['Mgr. B787/767 Mainten'],
            'B737' => ['Mgr. B737 Maintenance'],
            'CAB' => ['Mgr. Cabin Maint'],
            'B777' => ['MGR. B777/A350 Mainten'],
            'APS' => ['Mgr. A/C Paith Svs,'],
            'TEC' => ['Mgr. Technical Supp ,'],
        ],
        'LMT' => [
            'DIR' => ['Dir. LMT'],
            'DMM' => ['Duty Manager MCC,'],
            'ADM' => ['MGR. Admin & OutstationMaint'],
            'ALM' => ['Mgr A/C Line Maint.'],
            'GAM' => ['Mgr. General Ava. A/C Maint.'],
            'TPL' => ['MGR. Turbo Prop & Light A/C Maint'],
            'ACM' => ['Mgr. A/C Cabin Maint'],
        ],
        'CMT' => [
            'DIR' => ['Dir. CMT'],
            'WKH' => ['Mgr. Wire Kit & Harness Prod.'],
            'CES' => ['Mgr Computerized equipment shop'],
            'NDT' => ['Mgr. NDT, Stand. & Part Recv. Insp.'],
            'MES' => ['Comp.Maint.Engineering support'],
            'MCS' => ['Mgr. Mechanical Comp shops'],
            'ACS' => ['Mgr Avionics Comp shops'],
        ],
        'EMT' => [
            'DIR' => ['Dir. EMT'],
            'EMI' => ['Mgr. Engine Maint. Inspection'],
            'ETS' => ['Mgr. Technical Support'],
            'RNP' => ['Mgr. RR/PW4000/LEAP/APU eng. Maint.'],
            'CFM' => ['Mgr. CFM56/GE90/GENX & Turbo prop. Engines'],
            'RSH' => ['Mgr. Repair Shops'],
        ],
        'AEP' => [
            'DIR' => ['DIR. AEP'],
            'ALE' => ['MGR. A/C LEASE , EIS & SPECIAL PROJECTS'],
            'AMP' => ['MGR. A/C MAINT. PROG.& TASK CARD ENGINEE'],
            'MPR' => ['MGR. MAINT. PLNG & RECORD CONTROL'],
            'EQA' => ['MGR. ENGINEERING QUALITY ASSURANCE'],
            'ASE' => ['Mgr. A/C systems Eng'],
            'ADO' => ['MGR. A/C Design Organization'],
        ],
        'MSM' => [
            'DIR' => ['Dir MSM'],
            'MSM' => ['Mgr. MRO Sales and Marketing'],
            'MCS' => ['Mgr. MRO Customer Support'],
        ],
        'QA' => [
            'QAS' => ['Mgr .MRO Qty Ass & Sa ,'],
        ],
        'PSCM' => [
            'DIR' => ['Dir. Pro. & Supp. Chain Mgt.'],
            'GWC' => ['Mgr. Grp Warr,Cont Mg,'],
            'TPU' => ['Mgr. Tactical Purchase,'],
            'MMP' => ['Mgr. Group Material Planning'],
            'EMP' => ['Mgr.Engine Maint.Tactical Pur'],
            'WAP' => ['Mgr. Warehouse A/C Part'],
            'PLC' => ['Mgr.Tac. Purchase -LMT&CMT Maint.'],
        ],
    ];
    
    // Process the data
    $parsedData = [];
    $currentDept = null;
    
    foreach ($data as $item) {
        $deptCell = $item['dept'];
        $costCenterText = $item['cost_center'];
        $expected = $item['expected'];
        $completed = $item['completed'];
        
        // Check if this is a department header
        if (!empty($deptCell) && isset($mapping[$deptCell])) {
            $currentDept = $deptCell;
            error_log("Department found: $currentDept at row " . $item['row']);
            
            // Also try to match the cost center if this row has one
            if (!empty($costCenterText) && stripos($costCenterText, 'Total') === false) {
                foreach ($mapping[$currentDept] as $code => $patterns) {
                    foreach ($patterns as $pattern) {
                        if (trim($costCenterText) === trim($pattern) || stripos($costCenterText, $pattern) !== false) {
                            if (!isset($parsedData[$currentDept])) {
                                $parsedData[$currentDept] = [];
                            }
                            $parsedData[$currentDept][$code] = [
                                'name' => $pattern,
                                'expected' => $expected,
                                'completed' => $completed,
                            ];
                            error_log("  Matched: $code -> $pattern (E:$expected, C:$completed)");
                            break 2;
                        }
                    }
                }
            }
            continue;
        }
        
        // Regular cost center row
        if (!empty($costCenterText) && $currentDept !== null && isset($mapping[$currentDept])) {
            // Skip total rows
            if (stripos($costCenterText, 'Total') !== false) {
                error_log("Skipping total row: $costCenterText");
                continue;
            }
            
            $matched = false;
            foreach ($mapping[$currentDept] as $code => $patterns) {
                foreach ($patterns as $pattern) {
                    if (trim($costCenterText) === trim($pattern) || stripos($costCenterText, $pattern) !== false) {
                        if (!isset($parsedData[$currentDept])) {
                            $parsedData[$currentDept] = [];
                        }
                        $parsedData[$currentDept][$code] = [
                            'name' => $pattern,
                            'expected' => $expected,
                            'completed' => $completed,
                        ];
                        error_log("  Matched: $code -> $pattern (E:$expected, C:$completed)");
                        $matched = true;
                        break 2;
                    }
                }
            }
            
            if (!$matched) {
                error_log("  UNMATCHED: $costCenterText");
            }
        }
    }
    
    // Verify all required cost centers are present (skip EXT as it's optional)
    $missingData = [];
    foreach ($mapping as $dept => $costCentersList) {
        if (!isset($parsedData[$dept])) {
            $missingData[] = $dept . ' (all cost centers missing)';
            continue;
        }
        foreach ($costCentersList as $code => $patterns) {
            if (!isset($parsedData[$dept][$code])) {
                $missingData[] = $dept . ' - ' . implode(' OR ', $patterns);
            }
        }
    }
    
    // Also check if any department is completely missing
    foreach ($mapping as $dept => $costCentersList) {
        if (!isset($parsedData[$dept]) || empty($parsedData[$dept])) {
            $missingData[] = $dept . ' (no data found)';
        }
    }
    
    error_log("Parsed data structure: " . print_r($parsedData, true));
    error_log("Missing data: " . print_r($missingData, true));

    if (!empty($missingData)) {
        $response['message'] = 'Missing data for: ' . implode(', ', $missingData);
        echo json_encode($response);
        exit;
    }

    $response['success'] = true;
    $response['data'] = $parsedData;
    $response['message'] = 'File uploaded successfully. Please review the data below.';

} catch (Exception $e) {
    error_log("Excel upload error: " . $e->getMessage());
    $response['message'] = 'Error reading Excel file: ' . $e->getMessage();
}

echo json_encode($response);
exit;
?>