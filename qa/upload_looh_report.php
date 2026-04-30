<?php
// upload_looh_report.php - Handle List of Open Hazards Excel file upload
require_once __DIR__ . '/../session_config.php';
require_once __DIR__ . '/../includes/auth.php';

// Check if user is logged in and has QA Auditor role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'qa_auditor') {
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

if ($report_type !== 'looh') {
    $response['message'] = 'This upload is only for List of Open Hazards report';
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

    // Read month from cell F1
    $excelMonth = trim($worksheet->getCell('F1')->getValue() ?: '');
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
    $lastItem = '';
    $lastQpulseRefNo = '';
    $lastEventTitle = '';
    $lastOwnerDir = '';
    $lastAuditor = '';

    for ($row = 3; $row <= $highestRow; $row++) {
        $item = trim($worksheet->getCell('A' . $row)->getValue() ?: '');
        $qpulseRefNo = trim($worksheet->getCell('B' . $row)->getValue() ?: '');
        $eventTitle = trim($worksheet->getCell('C' . $row)->getValue() ?: '');
        $eventDateRaw = $worksheet->getCell('D' . $row)->getValue();
        $ownerDir = trim($worksheet->getCell('E' . $row)->getValue() ?: '');
        $targetDateRaw = $worksheet->getCell('F' . $row)->getValue();
        $auditor = trim($worksheet->getCell('G' . $row)->getValue() ?: '');
        $status = trim($worksheet->getCell('H' . $row)->getValue() ?: '');

        // Handle merged cells - use previous values if current is empty
        if (empty($item) && !empty($lastItem)) {
            $item = $lastItem;
        } elseif (!empty($item)) {
            $lastItem = $item;
        }

        if (empty($qpulseRefNo) && !empty($lastQpulseRefNo)) {
            $qpulseRefNo = $lastQpulseRefNo;
        } elseif (!empty($qpulseRefNo)) {
            $lastQpulseRefNo = $qpulseRefNo;
        }

        if (empty($eventTitle) && !empty($lastEventTitle)) {
            $eventTitle = $lastEventTitle;
        } elseif (!empty($eventTitle)) {
            $lastEventTitle = $eventTitle;
        }

        if (empty($ownerDir) && !empty($lastOwnerDir)) {
            $ownerDir = $lastOwnerDir;
        } elseif (!empty($ownerDir)) {
            $lastOwnerDir = $ownerDir;
        }

        if (empty($auditor) && !empty($lastAuditor)) {
            $auditor = $lastAuditor;
        } elseif (!empty($auditor)) {
            $lastAuditor = $auditor;
        }

        // Skip rows with no event title
        if (empty($eventTitle)) {
            continue;
        }

        // Process event date
        $eventDate = null;
        if (!empty($eventDateRaw)) {
            if (is_numeric($eventDateRaw)) {
                // Excel serial date
                $eventDate = Date::excelToDateTimeObject($eventDateRaw)->format('Y-m-d');
            } else {
                // Try to parse as string
                $timestamp = strtotime($eventDateRaw);
                if ($timestamp !== false) {
                    $eventDate = date('Y-m-d', $timestamp);
                }
            }
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
            'Item' => $item,
            'QpulseRefNo' => $qpulseRefNo,
            'EventTitle' => $eventTitle,
            'EventDate' => $eventDate,
            'OwnerDir' => $ownerDir,
            'TargetDate' => $targetDate,
            'Auditor' => $auditor,
            'Status' => $status,
            'month' => $month,
            'year' => $year,
            'department' => $department,
            'report_type' => 'looh'
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
    $columns = ['Item', 'QpulseRefNo', 'EventTitle', 'EventDate', 'OwnerDir', 'TargetDate', 'Auditor', 'Status'];

    $response['success'] = true;
    $response['data'] = $data;
    $response['record_count'] = count($data);
    $response['columns'] = $columns;
    $response['message'] = 'File uploaded successfully. Found ' . count($data) . ' records to import.';
} catch (Exception $e) {
    error_log("LOOH Excel upload error: " . $e->getMessage());
    $response['message'] = 'Error reading Excel file: ' . $e->getMessage();
}

echo json_encode($response);
exit;
