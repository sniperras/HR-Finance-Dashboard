<?php
// sync_director_to_master.php - Run this once to backfill existing data
require_once '../includes/auth.php';
requireRole('hr');

if ($_SESSION['username'] !== 'RamsisE') {
    die("Only RamsisE can run this script");
}

$conn = getConnection();
$userId = $_SESSION['user_id'];
$currentDateTime = date('Y-m-d H:i:s');

// Get all DIR records from mro_cpr_report
$query = "SELECT r.*, 
          r.report_year || '-' || printf('%02d', r.report_month) || '-01' as data_month
          FROM mro_cpr_report r
          WHERE r.cost_center_code = 'DIR'";

$result = $conn->query($query);
$synced = 0;
$errors = 0;

while ($row = $result->fetch_assoc()) {
    $dataMonth = $row['data_month'];
    $department = $row['department'];
    $indicatorName = 'CPR';
    $actualValue = $row['completed'];
    $targetValue = $row['expected'];
    $percentageAchievement = $row['percentage'];
    $remarks = "Auto-synced from MRO CPR Report - " . $row['cost_center_text'];
    
    // Check if exists
    $checkStmt = $conn->prepare("SELECT id FROM master_performance_data 
                                 WHERE data_month = ? AND department = ? AND indicator_name = ?");
    $checkStmt->bind_param("sss", $dataMonth, $department, $indicatorName);
    $checkStmt->execute();
    $exists = $checkStmt->get_result();
    
    if ($exists->num_rows > 0) {
        // Update
        $updateStmt = $conn->prepare("UPDATE master_performance_data 
                                      SET actual_value = ?, target_value = ?, percentage_achievement = ?,
                                          remarks = ?, verification_status = 'verified',
                                          verified_by = ?, verified_at = ?,
                                          updated_by = ?, updated_at = ?
                                      WHERE data_month = ? AND department = ? AND indicator_name = ?");
        $updateStmt->bind_param("dddsississ", $actualValue, $targetValue, $percentageAchievement,
                               $remarks, $userId, $currentDateTime, $userId, $currentDateTime,
                               $dataMonth, $department, $indicatorName);
        $updateStmt->execute();
    } else {
        // Insert
        $insertStmt = $conn->prepare("INSERT INTO master_performance_data 
                                      (data_month, department, indicator_name, actual_value, target_value, 
                                       percentage_achievement, remarks, verification_status, verified_by, verified_at,
                                       created_by, created_at, updated_by, updated_at)
                                      VALUES (?, ?, ?, ?, ?, ?, ?, 'verified', ?, ?, ?, ?, ?, ?)");
        $insertStmt->bind_param("sssdddsssssss", $dataMonth, $department, $indicatorName,
                               $actualValue, $targetValue, $percentageAchievement,
                               $remarks, $userId, $currentDateTime, $userId, $currentDateTime,
                               $userId, $currentDateTime);
        $insertStmt->execute();
    }
    
    $synced++;
}

echo "✅ Synced $synced Director records to master_performance_data\n";
if ($errors > 0) {
    echo "⚠️ $errors errors occurred\n";
}

$conn->close();
?>