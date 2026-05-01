<?php
// upload_ir_report.php - Handle Investigation Recommendations Excel file upload
require_once __DIR__ . '/../session_config.php';
require_once __DIR__ . '/../includes/auth.php';

// Check if user is logged in and has QA Auditor role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'qa auditor') {
    $response = ['success' => false, 'message' => 'Unauthorized access'];
    echo json_encode($response);
    exit;
}

// Include Composer autoloader for PhpSpreadsheet
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

$response = ['success' => false, 'data' => [], 'message' => '', 'record_count' => 0, 'columns' => []];

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
$department = $_POST['department'] ?? 'ALL';

if ($report_type !== 'ir') {
    $response['message'] = 'This upload is only for Investigation Recommendations report';
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

    // Parse the data - Start from row 3 (after header row)
    $highestRow = $worksheet->getHighestRow();
    $data = [];
    $rowNumber = 1;
    $lastOccurrence = '';
    $lastItems = '';

    for ($row = 3; $row <= $highestRow; $row++) {
        $items = trim($worksheet->getCell('A' . $row)->getValue() ?: '');
        $occurrence = trim($worksheet->getCell('B' . $row)->getValue() ?: '');
        $recommendationDesc = trim($worksheet->getCell('C' . $row)->getValue() ?: '');
        $responsibleManager = trim($worksheet->getCell('E' . $row)->getValue() ?: '');
        $targetDateRaw = $worksheet->getCell('F' . $row)->getValue();
        $status = trim($worksheet->getCell('G' . $row)->getValue() ?: '');

        // If occurrence is empty, use the last occurrence
        if (empty($occurrence) && !empty($lastOccurrence)) {
            $occurrence = $lastOccurrence;
            // Also use last items if items is empty
            if (empty($items)) {
                $items = $lastItems;
            }
        } elseif (!empty($occurrence)) {
            $lastOccurrence = $occurrence;
            if (!empty($items)) {
                $lastItems = $items;
            }
        }

        // Skip rows with no recommendation description
        if (empty($recommendationDesc)) {
            continue;
        }

        // Process target date
        $targetDate = null;
        if (!empty($targetDateRaw)) {
            if (is_numeric($targetDateRaw)) {
                // Excel serial date
                $targetDate = Date::excelToDateTimeObject($targetDateRaw)->format('Y-m-d');
            } else {
                // Try to parse as string
                $timestamp = strtotime($targetDateRaw);
                if ($timestamp !== false) {
                    $targetDate = date('Y-m-d', $timestamp);
                }
            }
        }

        $record = [
            'row_number' => $rowNumber,
            'Items' => $items,
            'Occurrence' => $occurrence,
            'RecommendationDescription' => $recommendationDesc,
            'ResponsibleManager' => $responsibleManager,
            'TargetDate' => $targetDate,
            'Status' => $status,
            'Month' => $month,
            'Year' => $year,
            'department' => $department,
            'report_type' => 'ir'
        ];

        $data[] = $record;
        $rowNumber++;
    }

    if (empty($data)) {
        $response['message'] = 'No data found in the Excel file. Please check that rows 3 and below contain data.';
        echo json_encode($response);
        exit;
    }

    // Define columns for preview
    $columns = ['Items', 'Occurrence', 'RecommendationDescription', 'ResponsibleManager', 'TargetDate', 'Status'];

    $response['success'] = true;
    $response['data'] = $data;
    $response['record_count'] = count($data);
    $response['columns'] = $columns;
    $response['message'] = 'File uploaded successfully. Found ' . count($data) . ' records to import.';
} catch (Exception $e) {
    error_log("IR Excel upload error: " . $e->getMessage());
    $response['message'] = 'Error reading Excel file: ' . $e->getMessage();
}

echo json_encode($response);
exit;
