<?php
require_once '../includes/auth.php';
requireRole('hr');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: report_mro_cpr.php');
    exit();
}

$reportType = $_POST['report_type'] ?? '';
$reportMonth = $_POST['report_month'] ?? date('m');
$reportYear = $_POST['report_year'] ?? date('Y');
$department = $_POST['department'] ?? '';
$costCenters = $_POST['cost_center'] ?? [];

if (empty($department) || empty($costCenters)) {
    header('Location: report_mro_cpr.php?error=' . urlencode('No data to save'));
    exit();
}

$conn = getConnection();
$userId = $_SESSION['user_id'];
$userName = $_SESSION['full_name'];
$successCount = 0;
$errorCount = 0;

// Function to log MRO actions
function logMroAction($conn, $recordId, $action, $oldData, $newData, $userId) {
    $stmt = $conn->prepare("INSERT INTO mro_audit_log (record_id, action, old_data, new_data, performed_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssi", $recordId, $action, $oldData, $newData, $userId);
    $stmt->execute();
    $stmt->close();
}

// Begin transaction
$conn->begin_transaction();

try {
    // First, get existing records for this report/month/year/department to log deletions
    $selectStmt = $conn->prepare("SELECT id, cost_center_code, cost_center_text, expected, completed, not_completed, percentage 
                                   FROM mro_cpr_report 
                                   WHERE report_type = ? AND report_month = ? AND report_year = ? AND department = ?");
    $selectStmt->bind_param("siis", $reportType, $reportMonth, $reportYear, $department);
    $selectStmt->execute();
    $existingRecords = $selectStmt->get_result();
    
    // Log deletions for existing records
    while ($existing = $existingRecords->fetch_assoc()) {
        $oldData = json_encode([
            'cost_center_code' => $existing['cost_center_code'],
            'cost_center_text' => $existing['cost_center_text'],
            'expected' => $existing['expected'],
            'completed' => $existing['completed'],
            'not_completed' => $existing['not_completed'],
            'percentage' => $existing['percentage']
        ]);
        logMroAction($conn, $existing['id'], 'delete', $oldData, '{}', $userId);
    }
    $selectStmt->close();
    
    // Delete existing records for this report/month/year/department
    $deleteStmt = $conn->prepare("DELETE FROM mro_cpr_report 
                                   WHERE report_type = ? AND report_month = ? AND report_year = ? AND department = ?");
    $deleteStmt->bind_param("siis", $reportType, $reportMonth, $reportYear, $department);
    $deleteStmt->execute();
    $deleteStmt->close();
    
    // Insert new records
    $insertStmt = $conn->prepare("INSERT INTO mro_cpr_report 
                                   (report_type, report_month, report_year, department, cost_center_code, 
                                    cost_center_text, expected, completed, not_completed, percentage, 
                                    verification_status, created_by, created_at)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())");
    
    foreach ($costCenters as $code => $data) {
        $name = $data['name'] ?? '';
        $expected = intval($data['expected'] ?? 0);
        $completed = intval($data['completed'] ?? 0);
        $notCompleted = $expected - $completed;
        $percentage = $expected > 0 ? round(($completed / $expected) * 100, 2) : 0;
        
        $insertStmt->bind_param("siisssiiidi", 
            $reportType, $reportMonth, $reportYear, $department, $code,
            $name, $expected, $completed, $notCompleted, $percentage, $userId
        );
        
        if ($insertStmt->execute()) {
            $newId = $insertStmt->insert_id;
            $newData = json_encode([
                'cost_center_code' => $code,
                'cost_center_text' => $name,
                'expected' => $expected,
                'completed' => $completed,
                'not_completed' => $notCompleted,
                'percentage' => $percentage
            ]);
            logMroAction($conn, $newId, 'insert', '{}', $newData, $userId);
            $successCount++;
        } else {
            $errorCount++;
        }
    }
    
    $insertStmt->close();
    
    // Commit transaction
    $conn->commit();
    
    $_SESSION['message'] = "Report saved successfully! $successCount records updated.";
    
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "Error saving report: " . $e->getMessage();
    error_log("MRO Save Error: " . $e->getMessage());
}

$conn->close();

// Redirect back to the report page
header('Location: report_mro_cpr.php?report=' . urlencode($reportType) . 
       '&month=' . $reportMonth . '&year=' . $reportYear . 
       '&department=' . urlencode($department) . 
       '&message=' . urlencode($_SESSION['message'] ?? $_SESSION['error'] ?? ''));
exit();
?>