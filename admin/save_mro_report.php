<?php
require_once '../session_config.php';
require_once '../includes/auth.php';
requireRole('hr');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: report_mro_cpr.php');
    exit();
}

$conn = getConnection();
$userId = $_SESSION['user_id'];
$currentDateTime = date('Y-m-d H:i:s');

$reportType = $_POST['report_type'] ?? '';
$reportMonth = $_POST['report_month'] ?? '';
$reportYear = $_POST['report_year'] ?? '';
$department = $_POST['department'] ?? '';
$costCenters = $_POST['cost_center'] ?? [];

// Validate inputs
if (empty($reportType) || empty($reportMonth) || empty($reportYear) || empty($department) || empty($costCenters)) {
    $_SESSION['error'] = "Missing required fields";
    header('Location: report_mro_cpr.php?report=' . urlencode($reportType) . '&month=' . $reportMonth . '&year=' . $reportYear . '&department=' . $department);
    exit();
}

$dataMonth = $reportYear . '-' . str_pad($reportMonth, 2, '0', STR_PAD_LEFT) . '-01';

// Map report types to indicator names in master_performance_data
$indicatorMapping = [
    'MRO CPR REPORT' => 'CPR',
    'Crew Meeting Minutes Submission' => 'Crew Meeting Minutes Submission',
    'Exceptional Customer Experience Training' => 'Exceptional Customer Experience Training',
    '2025/26 1st Semiannual BSC/ISC Target Status' => '2025/26 1st Semiannual BSC/ISC Target Status',
    '2025/26 1st Semiannual BSC/ISC Evaluation Status' => '2025/26 1st Semiannual BSC/ISC Evaluation Status',
    'Activity Report Submission' => 'Activity Report Submission',
    'Cost Saving Report Submission' => 'Cost Saving Report Submission',
    'Lost time Justification' => 'Lost time Justification',
    'Attendance Approval Status' => 'Attendance Approval Status',
    'Productivity' => 'Productivity',
    'Employees Training Gap Clearance' => 'Employees Training Gap Clearance',
    'Employees Issue Resolution Rate' => 'Employees Issue Resolution Rate'
];

$indicatorName = $indicatorMapping[$reportType] ?? 'CPR';

$conn->begin_transaction();

try {
    $successCount = 0;
    
    foreach ($costCenters as $code => $data) {
        $costCenterCode = $data['code'] ?? $code;
        $costCenterName = $data['name'] ?? '';
        $expected = isset($data['expected']) && $data['expected'] !== '' ? (int)$data['expected'] : 0;
        $completed = isset($data['completed']) && $data['completed'] !== '' ? (int)$data['completed'] : 0;
        $percentage = $expected > 0 ? round(($completed / $expected) * 100, 2) : 0;
        $notCompleted = $expected - $completed;
        
        // Check if record exists in mro_cpr_report
        $checkStmt = $conn->prepare("SELECT id FROM mro_cpr_report 
                                     WHERE report_type = ? AND report_month = ? AND report_year = ? 
                                     AND department = ? AND cost_center_code = ?");
        $checkStmt->bind_param("siiss", $reportType, $reportMonth, $reportYear, $department, $costCenterCode);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing
            $row = $result->fetch_assoc();
            $updateStmt = $conn->prepare("UPDATE mro_cpr_report 
                                          SET expected = ?, completed = ?, not_completed = ?, 
                                              percentage = ?, updated_by = ?, updated_at = ?
                                          WHERE id = ?");
            $updateStmt->bind_param("iiiddsi", $expected, $completed, $notCompleted, $percentage, $userId, $currentDateTime, $row['id']);
            $updateStmt->execute();
            $updateStmt->close();
        } else {
            // Insert new
            $insertStmt = $conn->prepare("INSERT INTO mro_cpr_report 
                                          (report_type, report_month, report_year, department, 
                                           cost_center_code, cost_center_text, expected, completed, 
                                           not_completed, percentage, created_by, created_at)
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insertStmt->bind_param("siissiiiiiss", $reportType, $reportMonth, $reportYear, $department, 
                                   $costCenterCode, $costCenterName, $expected, $completed, 
                                   $notCompleted, $percentage, $userId, $currentDateTime);
            $insertStmt->execute();
            $insertStmt->close();
        }
        $checkStmt->close();
        
        // Sync DIRECTOR data to master_performance_data
        // Also sync for all cost centers if the report type is not MRO CPR REPORT
        $shouldSync = ($costCenterCode === 'DIR') || ($reportType !== 'MRO CPR REPORT');
        
        if ($shouldSync) {
            $actualValue = (float)$completed;
            $targetValue = (float)$expected;
            $percentageAchievement = (float)$percentage;
            $remarks = "Auto-synced from " . $reportType . " - " . $costCenterName;
            
            // Determine which department to use in master_performance_data
            $masterDepartment = $department;
            // Map NSM to MSM
            if ($masterDepartment === 'NSM') {
                $masterDepartment = 'MSM';
            }
            // Map PSCM to MRO HR or appropriate department
            if ($masterDepartment === 'PSCM') {
                $masterDepartment = 'MRO HR';
            }
            
            // Check if record exists in master_performance_data
            $checkMasterStmt = $conn->prepare("SELECT id FROM master_performance_data 
                                                WHERE data_month = ? AND department = ? AND indicator_name = ?");
            $checkMasterStmt->bind_param("sss", $dataMonth, $masterDepartment, $indicatorName);
            $checkMasterStmt->execute();
            $masterResult = $checkMasterStmt->get_result();
            
            if ($masterResult->num_rows > 0) {
                // Update existing
                $masterRow = $masterResult->fetch_assoc();
                $updateMasterStmt = $conn->prepare("UPDATE master_performance_data 
                                                    SET actual_value = ?, target_value = ?, 
                                                        percentage_achievement = ?, 
                                                        remarks = ?,
                                                        verification_status = 'verified',
                                                        verified_by = ?, verified_at = ?,
                                                        updated_by = ?, updated_at = ?
                                                    WHERE id = ?");
                $updateMasterStmt->bind_param("dddssssii", 
                                              $actualValue,
                                              $targetValue,
                                              $percentageAchievement,
                                              $remarks,
                                              $userId,
                                              $currentDateTime,
                                              $userId,
                                              $currentDateTime,
                                              $masterRow['id']);
                $updateMasterStmt->execute();
                $updateMasterStmt->close();
            } else {
                // Insert new
                $insertMasterStmt = $conn->prepare("INSERT INTO master_performance_data 
                                                    (data_month, department, indicator_name, 
                                                     actual_value, target_value, percentage_achievement,
                                                     remarks, verification_status, verified_by, verified_at,
                                                     created_by, created_at, updated_by, updated_at)
                                                    VALUES (?, ?, ?, ?, ?, ?, ?, 'verified', ?, ?, ?, ?, ?, ?)");
                $insertMasterStmt->bind_param("sssdddsssssss", 
                                              $dataMonth, 
                                              $masterDepartment, 
                                              $indicatorName,
                                              $actualValue, 
                                              $targetValue, 
                                              $percentageAchievement,
                                              $remarks, 
                                              $userId, 
                                              $currentDateTime, 
                                              $userId, 
                                              $currentDateTime,
                                              $userId, 
                                              $currentDateTime);
                $insertMasterStmt->execute();
                $insertMasterStmt->close();
            }
            $checkMasterStmt->close();
        }
        
        $successCount++;
    }
    
    $conn->commit();
    
    $message = urlencode("✓ Successfully saved $successCount record(s) and synced to Master Performance Data");
    header('Location: report_mro_cpr.php?report=' . urlencode($reportType) . '&month=' . $reportMonth . '&year=' . $reportYear . '&department=' . $department . '&message=' . $message);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Save error: " . $e->getMessage());
    $error = urlencode("Error saving data: " . $e->getMessage());
    header('Location: report_mro_cpr.php?report=' . urlencode($reportType) . '&month=' . $reportMonth . '&year=' . $reportYear . '&department=' . $department . '&error=' . $error);
}

$conn->close();
exit();
?>