<?php
// save_sr_upload.php - Save Safety Report uploaded data with Month and Year
require_once __DIR__ . '/../session_config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Check if user is logged in and has QA Auditor role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'qa auditor') {
    $response = ['success' => false, 'message' => 'Unauthorized access'];
    echo json_encode($response);
    exit;
}

$response = ['success' => false, 'message' => '', 'imported' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

$report_type = $_POST['report_type'] ?? '';
$month = $_POST['month'] ?? '';
$year = $_POST['year'] ?? '';
$department = $_POST['department'] ?? 'ALL';
$jsonData = $_POST['data'] ?? '';

if ($report_type !== 'sr') {
    $response['message'] = 'This save is only for Safety Report';
    echo json_encode($response);
    exit;
}

if (empty($month) || empty($year) || empty($jsonData)) {
    $response['message'] = 'Missing required parameters';
    echo json_encode($response);
    exit;
}

$data = json_decode($jsonData, true);
if (!$data || !is_array($data)) {
    $response['message'] = 'Invalid data format';
    echo json_encode($response);
    exit;
}

$conn = getConnection();
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Start transaction
$conn->begin_transaction();

try {
    $importedCount = 0;
    $updatedCount = 0;
    $skippedCount = 0;
    $failedCount = 0;
    $warnings = [];

    // Prepare statements
    // Check if record exists (same Number, Month, Year)
    $checkStmt = $conn->prepare("SELECT id, Status FROM sr_report WHERE Number = ? AND Month = ? AND Year = ?");

    $insertStmt = $conn->prepare("INSERT INTO sr_report 
        (Number, AircraftType, Type, DamageDescription, ReportedBy, EventDate, Month, Year, EmailAddress, Name, Status, Section, CreatedBy, UpdatedBy) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $updateStmt = $conn->prepare("UPDATE sr_report SET 
        AircraftType = ?, Type = ?, DamageDescription = ?, ReportedBy = ?, EventDate = ?, EmailAddress = ?, Name = ?, Status = ?, Section = ?, UpdatedBy = ? 
        WHERE id = ?");

    foreach ($data as $record) {
        $number = $record['Number'] ?? '';
        $aircraftType = $record['AircraftType'] ?? '';
        $type = $record['Type'] ?? '';
        $damageDescription = $record['DamageDescription'] ?? '';
        $reportedBy = $record['ReportedBy'] ?? '';
        $eventDate = $record['EventDate'] ?? null;
        $recordMonth = $record['Month'] ?? $month;
        $recordYear = $record['Year'] ?? $year;
        $emailAddress = $record['EmailAddress'] ?? '';
        $name = $record['Name'] ?? '';
        $status = $record['Status'] ?? 'Reported';
        $section = $record['Section'] ?? '';

        // Normalize status
        $statusNormalized = ucfirst(strtolower(trim($status)));

        // Skip if missing required fields (Number is required)
        if (empty($number)) {
            $failedCount++;
            $warnings[] = "Row {$record['row_number']}: Missing Number";
            continue;
        }

        // DamageDescription is NOT required anymore - it can be empty
        // Handle event date
        $eventDateValue = !empty($eventDate) ? $eventDate : null;

        // Check if record already exists (same Number, Month, Year)
        $checkStmt->bind_param("sii", $number, $recordMonth, $recordYear);
        $checkStmt->execute();
        $result = $checkStmt->get_result();

        if ($result->num_rows > 0) {
            $existing = $result->fetch_assoc();

            // If status is Closed, skip
            if (strtolower($existing['Status']) === 'closed') {
                $skippedCount++;
                $warnings[] = "Row {$record['row_number']}: Record exists with Status 'Closed' - no update made";
                $result->free();
                continue;
            }

            // Update existing record
            $updateStmt->bind_param(
                "ssssssssssi",
                $aircraftType,
                $type,
                $damageDescription,
                $reportedBy,
                $eventDateValue,
                $emailAddress,
                $name,
                $statusNormalized,
                $section,
                $username,
                $existing['id']
            );

            if ($updateStmt->execute()) {
                $updatedCount++;
            } else {
                $failedCount++;
                $warnings[] = "Row {$record['row_number']}: Failed to update - " . $updateStmt->error;
            }
            $result->free();
        } else {
            // Insert new record
            $insertStmt->bind_param(
                "ssssssiissssss",
                $number,
                $aircraftType,
                $type,
                $damageDescription,
                $reportedBy,
                $eventDateValue,
                $recordMonth,
                $recordYear,
                $emailAddress,
                $name,
                $statusNormalized,
                $section,
                $username,
                $username
            );

            if ($insertStmt->execute()) {
                $importedCount++;
            } else {
                $failedCount++;
                $warnings[] = "Row {$record['row_number']}: Failed to insert - " . $insertStmt->error;
            }
        }
    }

    $checkStmt->close();
    $insertStmt->close();
    $updateStmt->close();
    $conn->commit();

    // Write log to file
    $logDir = __DIR__ . '/../logs/';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $logFile = $logDir . 'qa_import.log';
    $logEntry = date('Y-m-d H:i:s') . " | SR | Month: $month | Year: $year | Dept: $department | User: $username | New: $importedCount | Updated: $updatedCount | Skipped: $skippedCount | Failed: $failedCount";

    if (!empty($warnings)) {
        $logEntry .= " | Issues: " . implode("; ", array_slice($warnings, 0, 3));
    }
    $logEntry .= PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND);

    $response['success'] = true;
    $response['imported'] = $importedCount;
    $response['updated'] = $updatedCount;
    $response['skipped'] = $skippedCount;
    $response['failed'] = $failedCount;
    $response['message'] = "Successfully processed: $importedCount new, $updatedCount updated, $skippedCount skipped, $failedCount failed";

    if (!empty($warnings) && count($warnings) <= 5) {
        $response['warnings'] = $warnings;
    }
} catch (Exception $e) {
    $conn->rollback();
    error_log("Save SR upload error: " . $e->getMessage());
    $response['message'] = 'Error saving data: ' . $e->getMessage();
    $response['imported'] = 0;
    $response['failed'] = count($data);
}

$conn->close();
echo json_encode($response);
exit;
