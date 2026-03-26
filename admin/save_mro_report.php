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
$successCount = 0;
$errorCount = 0;

// Begin transaction
$conn->begin_transaction();

try {
    // First, delete existing records for this report/month/year/department
    $deleteStmt = $conn->prepare("DELETE FROM mro_cpr_report 
                                   WHERE report_type = ? AND report_month = ? AND report_year = ? AND department = ?");
    $deleteStmt->bind_param("siis", $reportType, $reportMonth, $reportYear, $department);
    $deleteStmt->execute();
    $deleteStmt->close();
    
    // Insert new records
    $insertStmt = $conn->prepare("INSERT INTO mro_cpr_report 
                                   (report_type, report_month, report_year, department, cost_center_code, 
                                    cost_center_text, expected, completed, not_completed, percentage, 
                                    created_by, created_at)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
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
}

$conn->close();

// Redirect back to the report page
header('Location: report_mro_cpr.php?report=' . urlencode($reportType) . 
       '&month=' . $reportMonth . '&year=' . $reportYear . 
       '&department=' . urlencode($department) . 
       '&message=' . urlencode($_SESSION['message'] ?? $_SESSION['error'] ?? ''));
exit();
?>