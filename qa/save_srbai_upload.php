<?php
// save_srbai_upload.php - Save SRB Action Item uploaded data
require_once __DIR__ . '/../session_config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Check if user is logged in and has QA Auditor role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'qa_auditor') {
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

if ($report_type !== 'srbai') {
    $response['message'] = 'This save is only for SRB Action Item report';
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
    $checkStmt = $conn->prepare("SELECT id, Status FROM srbai_report WHERE ItemNo = ? AND Agenda = ? AND ActionItem = ? AND Month = ? AND Year = ?");

    $insertStmt = $conn->prepare("INSERT INTO srbai_report 
        (ItemNo, Agenda, ActionItem, ActionBy, RaisedDate, TargetDate, Status, Month, Year, CreatedBy, UpdatedBy) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $updateStmt = $conn->prepare("UPDATE srbai_report SET 
        ActionBy = ?, RaisedDate = ?, TargetDate = ?, Status = ?, UpdatedBy = ? 
        WHERE id = ?");

    foreach ($data as $record) {
        $itemNo = $record['ItemNo'] ?? '';
        $agenda = $record['Agenda'] ?? '';
        $actionItem = $record['ActionItem'] ?? '';
        $actionBy = $record['ActionBy'] ?? '';
        $raisedDate = $record['RaisedDate'] ?? null;
        $targetDate = $record['TargetDate'] ?? null;
        $recordMonth = $record['Month'] ?? $month;
        $recordYear = $record['Year'] ?? $year;
        $status = $record['Status'] ?? 'Open';

        $statusNormalized = ucfirst(strtolower(trim($status)));

        // Skip if missing required fields
        if (empty($actionItem)) {
            $failedCount++;
            $warnings[] = "Row {$record['row_number']}: Missing Action Item";
            continue;
        }

        $raisedDateValue = !empty($raisedDate) ? $raisedDate : null;
        $targetDateValue = !empty($targetDate) ? $targetDate : null;

        // Check if record already exists
        $checkStmt->bind_param("sssii", $itemNo, $agenda, $actionItem, $recordMonth, $recordYear);
        $checkStmt->execute();
        $result = $checkStmt->get_result();

        if ($result->num_rows > 0) {
            $existing = $result->fetch_assoc();

            if (strtolower($existing['Status']) === 'closed') {
                $skippedCount++;
                $warnings[] = "Row {$record['row_number']}: Record exists with Status 'Closed' - no update made";
                $result->free();
                continue;
            }

            // Update existing record
            $updateStmt->bind_param(
                "sssssi",
                $actionBy,
                $raisedDateValue,
                $targetDateValue,
                $statusNormalized,
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
                "sssssssiiss",
                $itemNo,
                $agenda,
                $actionItem,
                $actionBy,
                $raisedDateValue,
                $targetDateValue,
                $statusNormalized,
                $recordMonth,
                $recordYear,
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
    $logEntry = date('Y-m-d H:i:s') . " | SRBAI | Month: $month | Year: $year | Dept: $department | User: $username | New: $importedCount | Updated: $updatedCount | Skipped: $skippedCount | Failed: $failedCount";

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
    error_log("Save SRBAI upload error: " . $e->getMessage());
    $response['message'] = 'Error saving data: ' . $e->getMessage();
    $response['imported'] = 0;
    $response['failed'] = count($data);
}

$conn->close();
echo json_encode($response);
exit;
