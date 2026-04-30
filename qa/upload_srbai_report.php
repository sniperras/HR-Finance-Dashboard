<?php
// upload_srbai_report.php - Handle SRB Action Item Excel file upload
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
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'qa_auditor') {
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

if ($report_type !== 'srbai') {
    sendJsonResponse(false, 'This upload is only for SRB Action Item report');
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

    // Get month and year from row 1 (F1 and G1)
    $excelMonth = trim($worksheet->getCell('F1')->getValue() ?: '');
    $excelYear = trim($worksheet->getCell('G1')->getValue() ?: '');

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

    // Find the header row (look for "Item No" in row 2 or 3)
    $headerRow = 0;
    $dataStartRow = 0;

    for ($row = 1; $row <= 5; $row++) {
        $cellValue = trim($worksheet->getCell('A' . $row)->getValue() ?: '');
        if (stripos($cellValue, 'Item No') !== false || stripos($cellValue, 'Item') !== false) {
            $headerRow = $row;
            $dataStartRow = $row + 1;
            break;
        }
    }

    if ($headerRow == 0) {
        $headerRow = 2;
        $dataStartRow = 3;
    }

    // Parse data
    $highestRow = $worksheet->getHighestRow();
    $data = [];
    $rowNumber = 1;

    // Variables to track merged cell values
    $lastItemNo = '';
    $lastAgenda = '';
    $lastActionItem = '';
    $lastActionBy = '';
    $lastRaisedDate = null;
    $lastTargetDate = null;
    $lastStatus = '';

    for ($row = $dataStartRow; $row <= $highestRow; $row++) {
        $itemNo = trim($worksheet->getCell('A' . $row)->getValue() ?: '');
        $agenda = trim($worksheet->getCell('B' . $row)->getValue() ?: '');
        $actionItem = trim($worksheet->getCell('C' . $row)->getValue() ?: '');
        $actionBy = trim($worksheet->getCell('D' . $row)->getValue() ?: '');
        $raisedDateRaw = $worksheet->getCell('E' . $row)->getValue();
        $targetDateRaw = $worksheet->getCell('F' . $row)->getValue();
        $status = trim($worksheet->getCell('G' . $row)->getValue() ?: '');

        // Skip the header row if it got included
        if (stripos($itemNo, 'Item No') !== false || stripos($agenda, 'Agenda') !== false) {
            continue;
        }

        // Handle ItemNo merge
        if (!empty($itemNo)) {
            $lastItemNo = $itemNo;
        } elseif (empty($itemNo) && !empty($lastItemNo)) {
            $itemNo = $lastItemNo;
        }

        // Handle Agenda merge
        if (!empty($agenda)) {
            $lastAgenda = $agenda;
        } elseif (empty($agenda) && !empty($lastAgenda)) {
            $agenda = $lastAgenda;
        }

        // Handle ActionItem merge - THIS IS KEY for Item 1 rows 2 and 3
        if (!empty($actionItem)) {
            $lastActionItem = $actionItem;
        } elseif (empty($actionItem) && !empty($lastActionItem)) {
            $actionItem = $lastActionItem;
        }

        // Handle ActionBy merge
        if (!empty($actionBy)) {
            $lastActionBy = $actionBy;
        } elseif (empty($actionBy) && !empty($lastActionBy)) {
            $actionBy = $lastActionBy;
        }

        // Handle raised date merge
        if (!empty($raisedDateRaw)) {
            if (is_numeric($raisedDateRaw)) {
                $lastRaisedDate = Date::excelToDateTimeObject($raisedDateRaw)->format('Y-m-d');
            } else {
                $timestamp = strtotime($raisedDateRaw);
                if ($timestamp !== false) {
                    $lastRaisedDate = date('Y-m-d', $timestamp);
                }
            }
            $raisedDate = $lastRaisedDate;
        } else {
            $raisedDate = $lastRaisedDate;
        }

        // Handle target date merge
        if (!empty($targetDateRaw)) {
            if (is_numeric($targetDateRaw)) {
                $lastTargetDate = Date::excelToDateTimeObject($targetDateRaw)->format('Y-m-d');
            } else {
                $timestamp = strtotime($targetDateRaw);
                if ($timestamp !== false) {
                    $lastTargetDate = date('Y-m-d', $timestamp);
                }
            }
            $targetDate = $lastTargetDate;
        } else {
            $targetDate = $lastTargetDate;
        }

        // Handle status merge
        if (!empty($status)) {
            $lastStatus = $status;
        } elseif (empty($status) && !empty($lastStatus)) {
            $status = $lastStatus;
        }

        // For Item 1 rows 2 and 3, we need to create records even if actionItem is empty?
        // Actually, we should create records as long as there is an ActionBy or ItemNo
        // For Item 1, rows 2 and 3 have ActionBy (Dir. LMT, Dir. AEP) but no ActionItem

        // Skip only if both ActionItem AND ActionBy are empty
        if (empty($actionItem) && empty($actionBy)) {
            continue;
        }

        // If ActionItem is empty but ActionBy is not, use the last ActionItem
        if (empty($actionItem) && !empty($lastActionItem)) {
            $actionItem = $lastActionItem;
        }

        // Default status if still empty
        if (empty($status)) {
            $status = 'Open';
        }

        // Default action by if still empty
        if (empty($actionBy)) {
            $actionBy = 'Not Assigned';
        }

        // Default action item if still empty
        if (empty($actionItem)) {
            $actionItem = 'No action item specified';
        }

        $record = [
            'row_number' => $rowNumber,
            'ItemNo' => $itemNo,
            'Agenda' => $agenda,
            'ActionItem' => $actionItem,
            'ActionBy' => $actionBy,
            'RaisedDate' => $raisedDate,
            'TargetDate' => $targetDate,
            'Status' => $status,
            'Month' => $month,
            'Year' => $year,
            'department' => $department,
            'report_type' => 'srbai'
        ];

        $data[] = $record;
        $rowNumber++;
    }

    if (empty($data)) {
        sendJsonResponse(false, 'No data found in the Excel file. Please check that rows contain data.');
    }

    $columns = ['ItemNo', 'Agenda', 'ActionItem', 'ActionBy', 'RaisedDate', 'TargetDate', 'Status'];

    sendJsonResponse(true, 'File uploaded successfully. Found ' . count($data) . ' records to import.', $data, count($data), $columns);
} catch (Exception $e) {
    error_log("SRBAI Upload Error: " . $e->getMessage());
    sendJsonResponse(false, 'Error reading Excel file: ' . $e->getMessage());
}
