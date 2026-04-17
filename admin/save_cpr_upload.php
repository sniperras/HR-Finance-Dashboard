<?php
// save_cpr_upload.php - Save CPR uploaded data for ALL departments
require_once '../session_config.php';
require_once '../includes/auth.php';
requireRole('hr');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

$report_type = $_POST['report_type'] ?? '';
$report_month = $_POST['report_month'] ?? '';
$report_year = $_POST['report_year'] ?? '';
$jsonData = $_POST['data'] ?? '';

if ($report_type !== 'CPR') {
    $response['message'] = 'This save is only for CPR report type';
    echo json_encode($response);
    exit;
}

if (empty($report_month) || empty($report_year) || empty($jsonData)) {
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
$currentDateTime = date('Y-m-d H:i:s');
$dataMonth = $report_year . '-' . str_pad($report_month, 2, '0', STR_PAD_LEFT) . '-01';

// Start transaction
$conn->begin_transaction();

try {
    $totalRows = 0;

    foreach ($data as $department => $costCenters) {
        // Delete existing data for this department for this report
        $deleteStmt = $conn->prepare("DELETE FROM mro_cpr_report WHERE report_type = ? AND report_month = ? AND report_year = ? AND department = ?");
        $deleteStmt->bind_param("siis", $report_type, $report_month, $report_year, $department);
        $deleteStmt->execute();
        $deleteStmt->close();

        // Insert new data for this department
        $insertStmt = $conn->prepare("INSERT INTO mro_cpr_report 
            (report_type, report_month, report_year, department, cost_center_code, cost_center_text, 
             expected, completed, not_completed, percentage, created_by, created_at, verification_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'verified')");

        foreach ($costCenters as $code => $rowData) {
            $name = $rowData['name'] ?? $code;
            $expected = intval($rowData['expected'] ?? 0);
            $completed = intval($rowData['completed'] ?? 0);
            $notCompleted = $expected - $completed;
            $percentage = $expected > 0 ? round(($completed / $expected) * 100, 2) : 0;

            $insertStmt->bind_param(
                "siisssiiidss",
                $report_type,
                $report_month,
                $report_year,
                $department,
                $code,
                $name,
                $expected,
                $completed,
                $notCompleted,
                $percentage,
                $userId,
                $currentDateTime
            );
            $insertStmt->execute();
            $totalRows++;
        }
        $insertStmt->close();
    }

    $conn->commit();

    $response['success'] = true;
    $response['message'] = "Successfully saved data for all departments ($totalRows records)";

} catch (Exception $e) {
    $conn->rollback();
    error_log("Save CPR upload error: " . $e->getMessage());
    $response['message'] = 'Error saving data: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
exit;
?>