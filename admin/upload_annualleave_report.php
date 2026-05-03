<?php
// upload_annualleave_report.php - Handle Annual Vacation Utilization Excel file upload
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

if ($report_type !== 'Annual Vacation Utilization Status') {
    $response['message'] = 'This upload is only for Annual Vacation Utilization report';
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

    // Read month from cell D1 and year from F1
    $excelMonth = trim($worksheet->getCell('D1')->getValue() ?: '');
    $excelYear = trim($worksheet->getCell('F1')->getValue() ?: '');

    error_log("=== Excel Upload Debug ===");
    error_log("Month cell D1: '$excelMonth'");
    error_log("Year cell F1: '$excelYear'");

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

    // Parse the data
    $highestRow = $worksheet->getHighestRow();
    error_log("Highest row: $highestRow");

    $data = [];
    $currentDept = null;
    $validDepts = ['BMT', 'CMT', 'LMT', 'EMT', 'AEP', 'PSCM', 'MSM', 'QA'];

    // First, let's debug what's in the first 20 rows
    for ($row = 1; $row <= 20; $row++) {
        $aVal = $worksheet->getCell('A' . $row)->getValue();
        $bVal = $worksheet->getCell('B' . $row)->getValue();
        $cVal = $worksheet->getCell('C' . $row)->getValue();
        error_log("Row $row: A='$aVal', B='$bVal', C='$cVal'");
    }

    for ($row = 2; $row <= $highestRow; $row++) {
        $deptVal = trim($worksheet->getCell('A' . $row)->getValue() ?: '');
        $sectionVal = trim($worksheet->getCell('B' . $row)->getValue() ?: '');
        $ccVal = trim($worksheet->getCell('C' . $row)->getValue() ?: '');
        $expectedVal = intval($worksheet->getCell('D' . $row)->getValue() ?: 0);
        $completedVal = intval($worksheet->getCell('E' . $row)->getValue() ?: 0);
        $remainingVal = intval($worksheet->getCell('G' . $row)->getValue() ?: 0);

        // Skip completely empty rows
        if (empty($deptVal) && empty($sectionVal) && empty($ccVal) && $expectedVal == 0 && $completedVal == 0) {
            continue;
        }

        // Check for department header (when column A has a valid department name)
        if (!empty($deptVal) && in_array($deptVal, $validDepts)) {
            $currentDept = $deptVal;
            if (!isset($data[$currentDept])) {
                $data[$currentDept] = [];
            }
            error_log("Department found: $currentDept at row $row");

            // Also check if this same row has section data (like a director on the same row as department)
            if (!empty($sectionVal) && trim($sectionVal) !== 'DIR SUMMARY') {
                $percentage = $expectedVal > 0 ? round(($completedVal / $expectedVal) * 100, 2) : 0;
                $notCompleted = $remainingVal > 0 ? $remainingVal : ($expectedVal - $completedVal);
                if ($notCompleted < 0) $notCompleted = 0;

                $data[$currentDept][] = [
                    'cost_center_code' => $ccVal,
                    'cost_center_text' => $sectionVal,
                    'expected' => $expectedVal,
                    'completed' => $completedVal,
                    'not_completed' => $notCompleted,
                    'percentage' => $percentage
                ];
                error_log("  Added director on same row: $sectionVal");
            }
            continue;
        }

        // Process rows that belong to the current department (including rows with empty column A)
        if ($currentDept !== null && !empty($sectionVal)) {
            // Skip "Total" rows
            if (strpos($sectionVal, 'Total') !== false || trim($sectionVal) === 'DIR SUMMARY') {
                error_log("Skipping total/summary row at row $row: $sectionVal");
                continue;
            }

            $percentage = $expectedVal > 0 ? round(($completedVal / $expectedVal) * 100, 2) : 0;
            $notCompleted = $remainingVal > 0 ? $remainingVal : ($expectedVal - $completedVal);
            if ($notCompleted < 0) $notCompleted = 0;

            $data[$currentDept][] = [
                'cost_center_code' => $ccVal,
                'cost_center_text' => $sectionVal,
                'expected' => $expectedVal,
                'completed' => $completedVal,
                'not_completed' => $notCompleted,
                'percentage' => $percentage
            ];

            error_log("Row $row: Dept=$currentDept, Section='$sectionVal', CC='$ccVal', E=$expectedVal, C=$completedVal");
        }
    }

    error_log("Final data structure: " . print_r($data, true));

    if (empty($data)) {
        $response['message'] = 'No data found in Excel file. Please check the format.';
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
    error_log("Annual Leave Excel upload error: " . $e->getMessage());
    $response['message'] = 'Error reading Excel file: ' . $e->getMessage();
}

echo json_encode($response);
exit;
