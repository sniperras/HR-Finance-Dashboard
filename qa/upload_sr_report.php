<?php
// upload_sr_report.php - Handle Safety Report Excel file upload
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

if ($report_type !== 'sr') {
    sendJsonResponse(false, 'This upload is only for Safety Report');
}

if ($month == 0 || $year == 0) {
    sendJsonResponse(false, 'Missing month or year');
}

if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    sendJsonResponse(false, 'PhpSpreadsheet not installed. Please run: composer require phpoffice/phpspreadsheet');
}

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

try {
    $spreadsheet = IOFactory::load($_FILES['excel_file']['tmp_name']);
    $worksheet = $spreadsheet->getActiveSheet();

    // Get month and year from row 2 or row 1
    $excelMonth = '';
    $excelYear = '';

    // Check row 2 first (where "January" and "2026" are in your data)
    for ($col = 'A'; $col <= 'J'; $col++) {
        $cellValue = trim($worksheet->getCell($col . '2')->getValue() ?: '');
        if (empty($cellValue)) continue;

        // Check for month
        $monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        foreach ($monthNames as $idx => $mName) {
            if (stripos($cellValue, $mName) !== false) {
                $excelMonth = $cellValue;
            }
        }

        // Check for year
        if (preg_match('/\b(20\d{2})\b/', $cellValue, $matches)) {
            $excelYear = $matches[1];
        }
    }

    // If not found in row 2, check row 1
    if (empty($excelMonth) || empty($excelYear)) {
        for ($col = 'A'; $col <= 'J'; $col++) {
            $cellValue = trim($worksheet->getCell($col . '1')->getValue() ?: '');
            if (empty($cellValue)) continue;

            $monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            foreach ($monthNames as $idx => $mName) {
                if (stripos($cellValue, $mName) !== false) {
                    $excelMonth = $cellValue;
                }
            }

            if (preg_match('/\b(20\d{2})\b/', $cellValue, $matches)) {
                $excelYear = $matches[1];
            }
        }
    }

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
        // Use selected month if Excel month not found
        $uploadedMonth = $month;
    }

    if ($uploadedMonth != $month) {
        sendJsonResponse(false, 'Month in Excel does not match selected month');
    }

    if (!empty($excelYear) && intval($excelYear) != $year) {
        sendJsonResponse(false, 'Year in Excel does not match selected year');
    }

    // Find where data starts - look for "Number" in row 3 or 4
    $headerRow = 0;
    $dataStartRow = 0;

    for ($row = 1; $row <= 5; $row++) {
        $cellValue = trim($worksheet->getCell('A' . $row)->getValue() ?: '');
        if (stripos($cellValue, 'Number') !== false || stripos($cellValue, 'number') !== false) {
            $headerRow = $row;
            $dataStartRow = $row + 1;
            break;
        }
    }

    if ($headerRow == 0) {
        // Default if header not found
        $headerRow = 3;
        $dataStartRow = 4;
    }

    // Parse data
    $highestRow = $worksheet->getHighestRow();
    $data = [];
    $rowNumber = 1;

    for ($row = $dataStartRow; $row <= $highestRow; $row++) {
        $number = trim($worksheet->getCell('A' . $row)->getValue() ?: '');
        $aircraftType = trim($worksheet->getCell('B' . $row)->getValue() ?: '');
        $type = trim($worksheet->getCell('C' . $row)->getValue() ?: '');
        $damageDescription = trim($worksheet->getCell('D' . $row)->getValue() ?: '');
        $reportedBy = trim($worksheet->getCell('E' . $row)->getValue() ?: '');
        $eventDateRaw = $worksheet->getCell('F' . $row)->getValue();
        $emailAddress = trim($worksheet->getCell('G' . $row)->getValue() ?: '');
        $name = trim($worksheet->getCell('H' . $row)->getValue() ?: '');
        $status = trim($worksheet->getCell('I' . $row)->getValue() ?: '');
        $section = trim($worksheet->getCell('J' . $row)->getValue() ?: '');

        // Skip empty rows
        if (empty($number) && empty($type) && empty($reportedBy)) {
            continue;
        }

        // Process event date
        $eventDate = null;
        if (!empty($eventDateRaw)) {
            if (is_numeric($eventDateRaw)) {
                $eventDate = Date::excelToDateTimeObject($eventDateRaw)->format('Y-m-d');
            } else {
                $timestamp = strtotime($eventDateRaw);
                if ($timestamp !== false) {
                    $eventDate = date('Y-m-d', $timestamp);
                }
            }
        }

        // Clean up email and name
        $emailAddress = str_replace('ET', 'et', $emailAddress);

        $record = [
            'row_number' => $rowNumber,
            'Number' => $number,
            'AircraftType' => $aircraftType,
            'Type' => $type,
            'DamageDescription' => $damageDescription,
            'ReportedBy' => $reportedBy,
            'EventDate' => $eventDate,
            'EmailAddress' => $emailAddress,
            'Name' => $name,
            'Status' => $status,
            'Section' => $section,
            'Month' => $month,
            'Year' => $year,
            'department' => $department,
            'report_type' => 'sr'
        ];

        $data[] = $record;
        $rowNumber++;
    }

    if (empty($data)) {
        sendJsonResponse(false, 'No data found in the Excel file. Please check that rows contain data.');
    }

    $columns = ['Number', 'AircraftType', 'Type', 'DamageDescription', 'ReportedBy', 'EventDate', 'EmailAddress', 'Name', 'Status', 'Section'];

    sendJsonResponse(true, 'File uploaded successfully. Found ' . count($data) . ' records to import.', $data, count($data), $columns);
} catch (Exception $e) {
    error_log("SR Upload Error: " . $e->getMessage());
    sendJsonResponse(false, 'Error reading Excel file: ' . $e->getMessage());
}
