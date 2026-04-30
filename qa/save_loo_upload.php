<?php
// save_loo_upload.php - Save List of Occurrence uploaded data
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

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

if ($report_type !== 'loo') {
    $response['message'] = 'This save is only for List of Occurrence report';
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

// Start transaction
$conn->begin_transaction();

try {
    $importedCount = 0;
    $updatedCount = 0;
    $skippedCount = 0;
    $failedCount = 0;
    $warnings = [];

    // UPSERT query - INSERT ON DUPLICATE KEY UPDATE (single query, no separate check)
    // First, ensure unique constraint exists
    $conn->query("ALTER TABLE loo_report ADD UNIQUE INDEX IF NOT EXISTS unique_loo_item_month_year (Item, Month, Year)");

    $upsertStmt = $conn->prepare("INSERT INTO loo_report 
        (Item, EventDate, EventTitle, ACModel, ACRegNo, LocOfOccur, ATANo, Description, QpulseReference, Month, Year, CreatedBy, UpdatedBy) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            EventDate = VALUES(EventDate),
            EventTitle = VALUES(EventTitle),
            ACModel = VALUES(ACModel),
            ACRegNo = VALUES(ACRegNo),
            LocOfOccur = VALUES(LocOfOccur),
            ATANo = VALUES(ATANo),
            Description = VALUES(Description),
            QpulseReference = VALUES(QpulseReference),
            UpdatedBy = VALUES(UpdatedBy)");

    foreach ($data as $record) {
        $item = $record['Item'] ?? '';
        $eventDate = $record['EventDate'] ?? null;
        $eventTitle = $record['EventTitle'] ?? '';
        $acModel = $record['ACModel'] ?? '';
        $acRegNo = $record['ACRegNo'] ?? '';
        $locOfOccur = $record['LocOfOccur'] ?? '';
        $ataNo = $record['ATANo'] ?? '';
        $description = $record['Description'] ?? '';
        $qpulseReference = $record['QpulseReference'] ?? '';
        $recordMonth = intval($record['Month'] ?? $month);
        $recordYear = intval($record['Year'] ?? $year);

        // Skip if missing required fields
        if (empty($item)) {
            $failedCount++;
            $warnings[] = "Row {$record['row_number']}: Missing Item";
            continue;
        }

        if (empty($description)) {
            $failedCount++;
            $warnings[] = "Row {$record['row_number']}: Missing Description";
            continue;
        }

        // Handle event date with validation
        $eventDateValue = null;
        if (!empty($eventDate)) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
                $eventDateValue = $eventDate;
            } else {
                $timestamp = strtotime($eventDate);
                if ($timestamp !== false) {
                    $eventDateValue = date('Y-m-d', $timestamp);
                } else {
                    $failedCount++;
                    $warnings[] = "Row {$record['row_number']}: Invalid date format: $eventDate";
                    continue;
                }
            }
        }

        // CORRECT bind_param types: 9 strings + 2 integers + 2 strings = 13 parameters
        // Type string: "sssssssss" (9 strings) + "ii" (2 integers) + "ss" (2 strings) = "sssssssssiiss"
        $upsertStmt->bind_param(
            "sssssssssiiss",  // 9 strings, 2 ints, 2 strings = total 13
            $item,           // string
            $eventDateValue, // string
            $eventTitle,     // string
            $acModel,        // string
            $acRegNo,        // string
            $locOfOccur,     // string
            $ataNo,          // string
            $description,    // string
            $qpulseReference, // string
            $recordMonth,    // int
            $recordYear,     // int
            $username,       // string (CreatedBy)
            $username        // string (UpdatedBy)
        );

        if ($upsertStmt->execute()) {
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
            error_log("LOO Upsert Error: " . $upsertStmt->error);
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
    $logEntry = date('Y-m-d H:i:s') . " | LOO | Month: $month | Year: $year | Dept: $department | User: $username | New: $importedCount | Updated: $updatedCount | Skipped: $skippedCount | Failed: $failedCount";

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
    error_log("Save LOO upload error: " . $e->getMessage());
    $response['message'] = 'Error saving data: ' . $e->getMessage();
    $response['imported'] = 0;
    $response['failed'] = count($data);
}

$conn->close();
echo json_encode($response);
exit;
