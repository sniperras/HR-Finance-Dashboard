<?php
// upload_crew_meeting.php - Handle Crew Meeting Excel file upload
require_once '../session_config.php';
require_once '../includes/auth.php';
requireRole('hr');

// Include Composer autoloader for PhpSpreadsheet
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

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

if ($report_type !== 'Crew Meeting Minutes Submission') {
    $response['message'] = 'This upload is only for Crew Meeting Minutes Submission report';
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

    // Read month from cell D3 and year from F3
    $excelMonth = trim($worksheet->getCell('D3')->getValue() ?: '');
    $excelYear = trim($worksheet->getCell('F3')->getValue() ?: '');

    error_log("=== Crew Meeting Excel Upload Debug ===");
    error_log("Month cell D3: '$excelMonth'");
    error_log("Year cell F3: '$excelYear'");

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

    // Parse the data - Excel columns:
    // A4 = Dept., B4 = Section, C4 = CC, D4 = Expected, E4 = Conducted
    $highestRow = $worksheet->getHighestRow();
    error_log("Highest row: $highestRow");

    // Debug first 20 rows to see what's in the file
    for ($row = 1; $row <= 30; $row++) {
        $aVal = $worksheet->getCell('A' . $row)->getValue();
        $bVal = $worksheet->getCell('B' . $row)->getValue();
        $cVal = $worksheet->getCell('C' . $row)->getValue();
        $dVal = $worksheet->getCell('D' . $row)->getValue();
        $eVal = $worksheet->getCell('E' . $row)->getValue();
        if (!empty($aVal) || !empty($bVal) || !empty($cVal)) {
            error_log("Row $row: A='$aVal', B='$bVal', C='$cVal', D='$dVal', E='$eVal'");
        }
    }

    $data = [];
    $currentDept = null;

    // Valid department patterns (what appears in column A)
    $validDeptPatterns = [
        'Dir.BMT' => 'BMT',
        'Dir.EMT' => 'EMT',
        'Dir.AEP' => 'AEP',
        'Dir.CMT' => 'CMT',
        'Dir.LMT' => 'LMT',
        'Dir.PSCM' => 'PSCM',
        'Dir. MSM' => 'MSM',
        'Mgr. QA' => 'QA',
        'Mgr. MRO HR' => 'HR',
        // Also check for department names without prefix
        'BMT' => 'BMT',
        'EMT' => 'EMT',
        'AEP' => 'AEP',
        'CMT' => 'CMT',
        'LMT' => 'LMT',
        'PSCM' => 'PSCM',
        'MSM' => 'MSM',
        'QA' => 'QA',
        'HR' => 'HR',
    ];

    for ($row = 4; $row <= $highestRow; $row++) {
        $deptVal = trim($worksheet->getCell('A' . $row)->getValue() ?: '');
        $sectionVal = trim($worksheet->getCell('B' . $row)->getValue() ?: '');
        $ccVal = trim($worksheet->getCell('C' . $row)->getValue() ?: '');

        // Handle Expected - could be in column D or sometimes empty
        $expectedVal = intval($worksheet->getCell('D' . $row)->getValue() ?: 0);
        $conductedVal = intval($worksheet->getCell('E' . $row)->getValue() ?: 0);

        // Skip completely empty rows
        if (empty($deptVal) && empty($sectionVal) && empty($ccVal) && $expectedVal == 0 && $conductedVal == 0) {
            continue;
        }

        // Check if this row is a department header
        $isDeptHeader = false;
        $matchedDept = null;

        foreach ($validDeptPatterns as $pattern => $dbDept) {
            // Check if the cell value contains the pattern or is exactly the pattern
            if (strpos($deptVal, $pattern) !== false || $deptVal === $pattern) {
                $isDeptHeader = true;
                $matchedDept = $dbDept;
                break;
            }
        }

        if ($isDeptHeader) {
            $currentDept = $matchedDept;
            if (!isset($data[$currentDept])) {
                $data[$currentDept] = [];
            }
            error_log("Department found: $currentDept at row $row (Value: '$deptVal')");

            // Also check if this same row has section data (like director row)
            if (!empty($sectionVal) && $expectedVal > 0) {
                $notConducted = $expectedVal - $conductedVal;
                if ($notConducted < 0) $notConducted = 0;
                $percentage = $expectedVal > 0 ? round(($conductedVal / $expectedVal) * 100, 2) : 0;

                $data[$currentDept][] = [
                    'cost_center_code' => $ccVal,
                    'cost_center_text' => $sectionVal,
                    'expected' => $expectedVal,
                    'completed' => $conductedVal,
                    'not_completed' => $notConducted,
                    'percentage' => $percentage
                ];
                error_log("  Added record on same row: $sectionVal, E=$expectedVal, C=$conductedVal");
            }
            continue;
        }

        // Process rows that belong to the current department
        if ($currentDept !== null && !empty($sectionVal)) {
            // Skip TOTAL rows
            if (
                strpos(strtoupper($sectionVal), 'TOTAL') !== false ||
                strpos(strtoupper($sectionVal), 'GRAND TOTAL') !== false
            ) {
                error_log("Skipping total row at row $row: $sectionVal");
                continue;
            }

            // Skip rows with no expected value (they might be empty data rows)
            if ($expectedVal == 0 && empty($sectionVal)) {
                continue;
            }

            // Calculate not conducted and percentage
            $notConducted = $expectedVal - $conductedVal;
            if ($notConducted < 0) $notConducted = 0;
            $percentage = $expectedVal > 0 ? round(($conductedVal / $expectedVal) * 100, 2) : 0;

            $data[$currentDept][] = [
                'cost_center_code' => $ccVal,
                'cost_center_text' => $sectionVal,
                'expected' => $expectedVal,
                'completed' => $conductedVal,
                'not_completed' => $notConducted,
                'percentage' => $percentage
            ];

            error_log("Row $row: Dept=$currentDept, Section='$sectionVal', CC='$ccVal', Expected=$expectedVal, Conducted=$conductedVal");
        }
    }

    error_log("Final data structure: " . print_r($data, true));

    if (empty($data)) {
        $response['message'] = 'No data found in Excel file. Please check the format. Make sure department names (Dir.BMT, Dir.EMT, etc.) are in column A starting from row 4.';
        echo json_encode($response);
        exit;
    }

    // Count total records
    $totalRecords = 0;
    foreach ($data as $dept => $records) {
        $totalRecords += count($records);
    }

    $response['success'] = true;
    $response['data'] = $data;
    $response['message'] = 'File uploaded successfully. Found ' . count($data) . ' departments with ' . $totalRecords . ' records.';
} catch (Exception $e) {
    error_log("Crew Meeting Excel upload error: " . $e->getMessage());
    $response['message'] = 'Error reading Excel file: ' . $e->getMessage();
}

echo json_encode($response);
exit;
