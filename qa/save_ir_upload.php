<?php
// save_ir_upload.php - Save Investigation Recommendations uploaded data with Month and Year
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

if ($report_type !== 'ir') {
    $response['message'] = 'This save is only for Investigation Recommendations report';
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
$username = $_SESSION['username'];
$recordMonth = intval($month);
$recordYear = intval($year);

// First, ensure unique constraint exists (run once)
try {
    $conn->query("ALTER TABLE ir_report ADD UNIQUE INDEX IF NOT EXISTS unique_ir_record (Occurrence, ResponsibleManager, TargetDate, Month, Year)");
} catch (Exception $e) {
    // Index might already exist or doesn't need creation
    error_log("Index creation notice: " . $e->getMessage());
}

// Start transaction
$conn->begin_transaction();

try {
    $importedCount = 0;
    $updatedCount = 0;
    $skippedCount = 0;
    $failedCount = 0;
    $warnings = [];

    // SINGLE UPSERT QUERY - replaces all SELECT + INSERT/UPDATE logic
    $upsertStmt = $conn->prepare("INSERT INTO ir_report 
        (Items, Occurrence, RecommendationDescription, ResponsibleManager, TargetDate, Status, Month, Year, CreatedBy, UpdatedBy) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            RecommendationDescription = VALUES(RecommendationDescription),
            UpdatedBy = VALUES(UpdatedBy),
            Status = IF(Status != 'Closed', VALUES(Status), Status)");

    foreach ($data as $record) {
        $items = $record['Items'] ?? '';
        $occurrence = $record['Occurrence'] ?? '';
        $recommendationDesc = $record['RecommendationDescription'] ?? '';
        $responsibleManager = $record['ResponsibleManager'] ?? '';
        $targetDateRaw = $record['TargetDate'] ?? null;
        $status = $record['Status'] ?? 'Open';

        // Skip if missing required fields
        if (empty($occurrence)) {
            $failedCount++;
            $warnings[] = "Row {$record['row_number']}: Missing Occurrence";
            continue;
        }

        if (empty($recommendationDesc)) {
            $failedCount++;
            $warnings[] = "Row {$record['row_number']}: Missing Recommendation Description";
            continue;
        }

        // Validate and normalize TargetDate
        $targetDateValue = null;
        if (!empty($targetDateRaw)) {
            $timestamp = strtotime($targetDateRaw);
            if ($timestamp !== false) {
                $targetDateValue = date('Y-m-d', $timestamp);
            } else {
                $failedCount++;
                $warnings[] = "Row {$record['row_number']}: Invalid TargetDate format: $targetDateRaw";
                continue;
            }
        }

        // Normalize status (use case-insensitive comparison later)
        $statusNormalized = ucfirst(strtolower(trim($status)));

        // CORRECT bind_param: 6 strings + 2 ints + 2 strings = 10 parameters
        // Type string: "ssssssiiss" (6 strings, 2 ints, 2 strings)
        $upsertStmt->bind_param(
            "ssssssiiss",
            $items,              // string
            $occurrence,         // string
            $recommendationDesc, // string
            $responsibleManager, // string
            $targetDateValue,    // string (or null)
            $statusNormalized,   // string
            $recordMonth,        // int
            $recordYear,         // int
            $username,           // string (CreatedBy)
            $username            // string (UpdatedBy)
        );

        if ($upsertStmt->execute()) {
            // affected_rows = 1 for insert, 2 for update
            if ($upsertStmt->affected_rows == 1) {
                $importedCount++;
            } elseif ($upsertStmt->affected_rows == 2) {
                $updatedCount++;
            } else {
                $skippedCount++;
            }
        } else {
            $failedCount++;
            $warnings[] = "Row {$record['row_number']}: " . $upsertStmt->error;
            error_log("IR Upsert Error: " . $upsertStmt->error);
        }
    }

    $upsertStmt->close();
    $conn->commit();

    // Write log to file
    $logDir = __DIR__ . '/../logs/';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $logFile = $logDir . 'qa_import.log';
    $logEntry = date('Y-m-d H:i:s') . " | IR | Month: $month | Year: $year | Dept: $department | User: $username | New: $importedCount | Updated: $updatedCount | Skipped: $skippedCount | Failed: $failedCount";

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
    error_log("Save IR upload error: " . $e->getMessage());
    $response['message'] = 'Error saving data: ' . $e->getMessage();
    $response['imported'] = 0;
    $response['failed'] = count($data);
}

$conn->close();
echo json_encode($response);
exit;
