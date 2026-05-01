<?php
// get_record_details.php - Fetch single record details for modal view
require_once '../session_config.php';
require_once '../includes/auth.php';
require_once '../config/database.php';

// Check if user has access
if (
    !isset($_SESSION['user_id']) ||
    !in_array($_SESSION['user_role'], ['director', 'md', 'it_admin', 'qa auditor', 'hr'])
) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$report = $_GET['report'] ?? '';
$id = intval($_GET['id'] ?? 0);

if (empty($report) || $id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Define report names for display
$reportNames = [
    'ir_report' => 'Investigation Recommendations',
    'loo_report' => 'List of Occurrence',
    'looh_report' => 'List of Open Hazards',
    'sr_report' => 'Safety Report',
    'srbai_report' => 'SRB Action Item',
    'listofoccurrenceform' => 'List of Occurrence Form'
];

// Validate report type
if (!isset($reportNames[$report])) {
    echo json_encode(['success' => false, 'message' => 'Invalid report type']);
    exit;
}

$conn = getConnection();

// Get the record
$stmt = $conn->prepare("SELECT * FROM $report WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$record = $result->fetch_assoc();
$stmt->close();
$conn->close();

if ($record) {
    echo json_encode([
        'success' => true,
        'record' => $record,
        'report_name' => $reportNames[$report]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Record not found'
    ]);
}
exit;
