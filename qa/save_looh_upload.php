<?php
// save_looh_upload.php - Save List of Open Hazards uploaded data
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

if ($report_type !== 'looh') {
    $response['message'] = 'This save is only for List of Open Hazards report';
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
    // Check if exact record exists (same QpulseRefNo, EventTitle, OwnerDir)
    $checkExactStmt = $conn->prepare("SELECT id, Status FROM looh_report WHERE QpulseRefNo = ? AND EventTitle = ? AND OwnerDir = ?");

    // Check if record exists for update (same QpulseRefNo)
    $checkUpdateStmt = $conn->prepare("SELECT id, Status FROM looh_report WHERE QpulseRefNo = ?");

    $insertStmt = $conn->prepare("INSERT INTO looh_report 
        (Item, QpulseRefNo, EventTitle, EventDate, OwnerDir, TargetDate, Auditor, Status, CreatedBy, UpdatedBy) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $updateStmt = $conn->prepare("UPDATE looh_report SET 
        Item = ?, EventTitle = ?, EventDate = ?, TargetDate = ?, Auditor = ?, Status = ?, UpdatedBy = ? 
        WHERE id = ?");

    foreach ($data as $record) {
        $item = $record['Item'] ?? '';
        $qpulseRefNo = $record['QpulseRefNo'] ?? '';
        $eventTitle = $record['EventTitle'] ?? '';
        $eventDate = $record['EventDate'] ?? null;
        $ownerDir = $record['OwnerDir'] ?? '';
        $targetDate = $record['TargetDate'] ?? null;
        $auditor = $record['Auditor'] ?? '';
        $status = $record['Status'] ?? 'Open';

        // Normalize status
        $statusNormalized = ucfirst(strtolower(trim($status)));

        // Skip if missing required fields
        if (empty($qpulseRefNo)) {
            $failedCount++;
            $warnings[] = "Row {$record['row_number']}: Missing Q-pulse Reference Number";
            continue;
        }

        if (empty($eventTitle)) {
            $failedCount++;
            $warnings[] = "Row {$record['row_number']}: Missing Event Title";
            continue;
        }

        // Handle dates
        $eventDateValue = !empty($eventDate) ? $eventDate : null;
        $targetDateValue = !empty($targetDate) ? $targetDate : null;

        // Check if EXACT record already exists (same QpulseRefNo, EventTitle, OwnerDir)
        $checkExactStmt->bind_param("sss", $qpulseRefNo, $eventTitle, $ownerDir);
        $checkExactStmt->execute();
        $exactResult = $checkExactStmt->get_result();

        if ($exactResult->num_rows > 0) {
            $existing = $exactResult->fetch_assoc();

            // If status is Closed, skip
            if (strtolower($existing['Status']) === 'closed') {
                $skippedCount++;
                $warnings[] = "Row {$record['row_number']}: Record exists with Status 'Closed' - no update made";
                $exactResult->free();
                continue;
            }

            // Update existing record
            $updateStmt->bind_param(
                "sssssssi",
                $item,
                $eventTitle,
                $eventDateValue,
                $targetDateValue,
                $auditor,
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
            $exactResult->free();
        } else {
            // Check if record with same QpulseRefNo exists (different event title)
            $checkUpdateStmt->bind_param("s", $qpulseRefNo);
            $checkUpdateStmt->execute();
            $updateResult = $checkUpdateStmt->get_result();

            if ($updateResult->num_rows > 0) {
                $existing = $updateResult->fetch_assoc();

                // If status is Closed, insert as new
                if (strtolower($existing['Status']) === 'closed') {
                    // Insert new record
                    $insertStmt->bind_param(
                        "ssssssssss",
                        $item,
                        $qpulseRefNo,
                        $eventTitle,
                        $eventDateValue,
                        $ownerDir,
                        $targetDateValue,
                        $auditor,
                        $statusNormalized,
                        $username,
                        $username
                    );

                    if ($insertStmt->execute()) {
                        $importedCount++;
                    } else {
                        $failedCount++;
                        $warnings[] = "Row {$record['row_number']}: Failed to insert - " . $insertStmt->error;
                    }
                } else {
                    // Update existing record
                    $updateStmt->bind_param(
                        "sssssssi",
                        $item,
                        $eventTitle,
                        $eventDateValue,
                        $targetDateValue,
                        $auditor,
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
                }
                $updateResult->free();
            } else {
                // Insert new record
                $insertStmt->bind_param(
                    "ssssssssss",
                    $item,
                    $qpulseRefNo,
                    $eventTitle,
                    $eventDateValue,
                    $ownerDir,
                    $targetDateValue,
                    $auditor,
                    $statusNormalized,
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
    }

    $checkExactStmt->close();
    $checkUpdateStmt->close();
    $insertStmt->close();
    $updateStmt->close();
    $conn->commit();

    // Write log to file
    $logDir = __DIR__ . '/../logs/';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $logFile = $logDir . 'qa_import.log';
    $logEntry = date('Y-m-d H:i:s') . " | LOOH | Month: $month | Year: $year | Dept: $department | User: $username | New: $importedCount | Updated: $updatedCount | Skipped: $skippedCount | Failed: $failedCount";

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
    error_log("Save LOOH upload error: " . $e->getMessage());
    $response['message'] = 'Error saving data: ' . $e->getMessage();
    $response['imported'] = 0;
    $response['failed'] = count($data);
}

$conn->close();
echo json_encode($response);
exit;
