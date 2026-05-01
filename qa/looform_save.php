<?php
// looform_save.php - Save List of Occurrence Form data
require_once __DIR__ . '/../session_config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and has QA Auditor role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'qa auditor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

$data = $input['data'] ?? [];
$month = intval($input['month'] ?? 0);
$year = intval($input['year'] ?? 0);
$username = $_SESSION['username'];

if (empty($month) || empty($year)) {
    echo json_encode(['success' => false, 'message' => 'Missing month or year']);
    exit;
}

if (empty($data)) {
    echo json_encode(['success' => false, 'message' => 'No data to save']);
    exit;
}

$conn = getConnection();

// Start transaction
$conn->begin_transaction();

try {
    $savedCount = 0;
    $updatedCount = 0;
    $skippedCount = 0;
    $errors = [];

    // First, delete existing records for this month/year to replace with new data
    $deleteStmt = $conn->prepare("DELETE FROM ListofOccurrenceForm WHERE Month = ? AND Year = ?");
    $deleteStmt->bind_param("ii", $month, $year);
    $deleteStmt->execute();
    $deleteStmt->close();

    // Prepare INSERT statement
    $insertStmt = $conn->prepare("INSERT INTO ListofOccurrenceForm 
        (Item, Event, Number, Month, Year, CreatedBy, UpdatedBy) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");

    foreach ($data as $record) {
        $item = trim($record['item'] ?? '');
        $event = trim($record['event'] ?? '');
        $number = intval($record['number'] ?? 0);

        // Skip if event is empty
        if (empty($event)) {
            $skippedCount++;
            $errors[] = "Missing event for item: " . ($item ?: 'unknown');
            continue;
        }

        // Skip if number is zero
        if ($number <= 0) {
            $skippedCount++;
            $errors[] = "Skipped - Zero count for event: $event";
            continue;
        }

        $insertStmt->bind_param(
            "sssiiss",
            $item,
            $event,
            $number,
            $month,
            $year,
            $username,
            $username
        );

        if ($insertStmt->execute()) {
            $savedCount++;
        } else {
            $errors[] = "Failed to save: $item - $event - " . $insertStmt->error;
        }
    }

    $insertStmt->close();
    $conn->commit();

    // Write log to file
    $logDir = __DIR__ . '/../logs/';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $logFile = $logDir . 'qa_import.log';
    $logEntry = date('Y-m-d H:i:s') . " | LOOFORM | Month: $month | Year: $year | User: $username | Saved: $savedCount | Skipped: $skippedCount";
    if (!empty($errors)) {
        $logEntry .= " | Errors: " . implode("; ", array_slice($errors, 0, 3));
    }
    $logEntry .= PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND);

    echo json_encode([
        'success' => true,
        'message' => "List of Occurrence saved successfully",
        'saved' => $savedCount,
        'updated' => $updatedCount,
        'skipped' => $skippedCount,
        'errors' => array_slice($errors, 0, 5)
    ]);
} catch (Exception $e) {
    $conn->rollback();
    error_log("LOO Form Save error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error saving data: ' . $e->getMessage()
    ]);
}

$conn->close();
exit;
