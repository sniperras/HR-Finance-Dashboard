<?php
// upload_loo_report.php - Handle List of Occurrence Excel file upload
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');

require_once __DIR__ . '/../session_config.php';
require_once __DIR__ . '/../includes/auth.php';

function sendJsonResponse($success, $message, $data = [], $record_count = 0, $columns = [])
{
    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'record_count' => $record_count,
        'columns' => $columns
    ];
    echo json_encode($response);
    exit;
}

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'qa auditor') {
    sendJsonResponse(false, 'Unauthorized access');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Invalid request method');
}

if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    sendJsonResponse(false, 'Please select a valid Excel file');
}

$report_type = $_POST['report_type'] ?? '';
$month = intval($_POST['month'] ?? 0);
$year = intval($_POST['year'] ?? 0);
$department = $_POST['department'] ?? 'ALL';

if ($report_type !== 'loo') {
    sendJsonResponse(false, 'This upload is only for List of Occurrence report');
}

if ($month == 0 || $year == 0) {
    sendJsonResponse(false, 'Missing month or year');
}

if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    sendJsonResponse(false, 'PhpSpreadsheet not installed');
}

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

try {
    $spreadsheet = IOFactory::load($_FILES['excel_file']['tmp_name']);
    $worksheet = $spreadsheet->getActiveSheet();

    // Get month and year from row 1 (H1 and I1)
    $excelMonth = trim($worksheet->getCell('H1')->getValue() ?: '');
    $excelYear = trim($worksheet->getCell('I1')->getValue() ?: '');

    // Validate month
    $monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $uploadedMonth = 0;
    foreach ($monthNames as $idx => $mName) {
        if (stripos($excelMonth, $mName) !== false) {
            $uploadedMonth = $idx + 1;
            break;
        }
    }

    if ($uploadedMonth == 0) {
        $uploadedMonth = $month;
    }

    if ($uploadedMonth != $month) {
        sendJsonResponse(false, 'Month in Excel does not match selected month');
    }

    if (!empty($excelYear) && intval($excelYear) != $year) {
        sendJsonResponse(false, 'Year in Excel does not match selected year');
    }

    // Find the header row (look for "Item" in row 2)
    $headerRow = 0;
    $dataStartRow = 0;

    for ($row = 1; $row <= 5; $row++) {
        $cellValue = trim($worksheet->getCell('A' . $row)->getValue() ?: '');
        if (stripos($cellValue, 'Item') !== false && $cellValue == 'Item') {
            $headerRow = $row;
            $dataStartRow = $row + 1;
            break;
        }
    }

    if ($headerRow == 0) {
        $headerRow = 2;
        $dataStartRow = 3;
    }

    // Parse data - each row is a separate record
    $highestRow = $worksheet->getHighestRow();
    $data = [];
    $rowNumber = 1;

    for ($row = $dataStartRow; $row <= $highestRow; $row++) {
        $item = trim($worksheet->getCell('A' . $row)->getValue() ?: '');
        $eventDateRaw = $worksheet->getCell('B' . $row)->getValue();
        $eventTitle = trim($worksheet->getCell('C' . $row)->getValue() ?: '');
        $acModel = trim($worksheet->getCell('D' . $row)->getValue() ?: '');
        $acRegNo = trim($worksheet->getCell('E' . $row)->getValue() ?: '');
        $locOfOccur = trim($worksheet->getCell('F' . $row)->getValue() ?: '');
        $ataNo = trim($worksheet->getCell('G' . $row)->getValue() ?: '');
        $description = trim($worksheet->getCell('H' . $row)->getValue() ?: '');
        $qpulseReference = trim($worksheet->getCell('I' . $row)->getValue() ?: '');

        // Skip the header row if it got included
        if (stripos($item, 'Item') !== false && $item == 'Item') {
            continue;
        }

        // Skip empty rows (no item and no description)
        if (empty($item) && empty($description)) {
            continue;
        }

        // If item is empty but description exists, this is likely a continuation row - skip it
        // Because your data shows each item has its own row with item number
        if (empty($item) && !empty($description)) {
            continue;
        }

        // Process event date (handles "4-Mar-26" format)
        $eventDate = null;
        if (!empty($eventDateRaw)) {
            if (is_numeric($eventDateRaw)) {
                $eventDate = Date::excelToDateTimeObject($eventDateRaw)->format('Y-m-d');
            } else {
                // Handle date formats like "4-Mar-26", "12-Mar-26", "15-Mar-26", "24-Mar-26"
                $timestamp = strtotime($eventDateRaw);
                if ($timestamp !== false) {
                    $eventDate = date('Y-m-d', $timestamp);
                } else {
                    // Try to parse custom format
                    $dateParts = explode('-', $eventDateRaw);
                    if (count($dateParts) == 3) {
                        $day = str_pad($dateParts[0], 2, '0', STR_PAD_LEFT);
                        $monthAbbr = $dateParts[1];
                        $yearShort = $dateParts[2];

                        $monthMap = [
                            'Jan' => '01',
                            'Feb' => '02',
                            'Mar' => '03',
                            'Apr' => '04',
                            'May' => '05',
                            'Jun' => '06',
                            'Jul' => '07',
                            'Aug' => '08',
                            'Sep' => '09',
                            'Oct' => '10',
                            'Nov' => '11',
                            'Dec' => '12'
                        ];

                        $monthNum = $monthMap[$monthAbbr] ?? '01';
                        $fullYear = '20' . $yearShort;
                        $eventDate = $fullYear . '-' . $monthNum . '-' . $day;
                    }
                }
            }
        }

        $record = [
            'row_number' => $rowNumber,
            'Item' => $item,
            'EventDate' => $eventDate,
            'EventTitle' => $eventTitle,
            'ACModel' => $acModel,
            'ACRegNo' => $acRegNo,
            'LocOfOccur' => $locOfOccur,
            'ATANo' => $ataNo,
            'Description' => $description,
            'QpulseReference' => $qpulseReference,
            'Month' => $month,
            'Year' => $year,
            'department' => $department,
            'report_type' => 'loo'
        ];

        $data[] = $record;
        $rowNumber++;
    }

    if (empty($data)) {
        sendJsonResponse(false, 'No data found in the Excel file. Please check that rows contain data.');
    }

    $columns = ['Item', 'EventDate', 'EventTitle', 'ACModel', 'ACRegNo', 'LocOfOccur', 'ATANo', 'Description', 'QpulseReference'];

    sendJsonResponse(true, 'File uploaded successfully. Found ' . count($data) . ' records to import.', $data, count($data), $columns);
} catch (Exception $e) {
    error_log("LOO Upload Error: " . $e->getMessage());
    sendJsonResponse(false, 'Error reading Excel file: ' . $e->getMessage());
}
