<?php
require_once '../includes/auth.php';
requireRole('hr');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: master_data.php');
    exit();
}

$month = $_POST['month'] ?? '';
$data = $_POST['data'] ?? [];
$remarks = $_POST['remarks'] ?? [];

// Validate month
if (empty($month) || !preg_match('/^\d{4}-\d{2}$/', $month)) {
    $_SESSION['error'] = "Invalid month format";
    header('Location: master_data.php');
    exit();
}

$dataMonth = $month . '-01';

// Check if month is editable
if ($month < date('Y-m')) {
    $_SESSION['error'] = "Cannot edit past months data";
    header('Location: master_data.php?month=' . $month);
    exit();
}

$conn = getConnection();
$userId = $_SESSION['user_id'];
$successCount = 0;
$errorCount = 0;

$conn->begin_transaction();

try {
    foreach ($data as $department => $indicatorsData) {
        foreach ($indicatorsData as $indicator => $values) {
            if (!is_array($values)) continue;
            
            $actual = !empty($values['actual']) ? floatval($values['actual']) : null;
            $target = !empty($values['target']) ? floatval($values['target']) : null;
            $percentage = !empty($values['percentage']) ? floatval($values['percentage']) : null;
            
            // Calculate percentage if actual and target are available
            if ($actual !== null && $target !== null && $target != 0) {
                $percentage = ($actual / $target) * 100;
            }
            
            // Handle Remainder department
            if ($department === 'Remainder') {
                $mdDivKey = "MD/DIV.";
                if (isset($data[$mdDivKey][$indicator]['percentage'])) {
                    $mdDivPercentage = floatval($data[$mdDivKey][$indicator]['percentage']);
                    if ($mdDivPercentage !== null && !is_nan($mdDivPercentage)) {
                        $maxValue = max(100, $mdDivPercentage);
                        $percentage = $maxValue - $mdDivPercentage;
                        $percentage = max(0, $percentage);
                        $actual = $percentage;
                        $target = 100;
                    }
                }
            }
            
            $remarkValue = $remarks[$department] ?? '';
            
            // Check if record exists
            $checkStmt = $conn->prepare("SELECT id FROM master_performance_data 
                                          WHERE data_month = ? AND department = ? AND indicator_name = ?");
            $checkStmt->bind_param("sss", $dataMonth, $department, $indicator);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($existing = $result->fetch_assoc()) {
                $updateStmt = $conn->prepare("UPDATE master_performance_data 
                                              SET actual_value = ?, target_value = ?, percentage_achievement = ?, 
                                                  remarks = ?, updated_by = ?, updated_at = NOW()
                                              WHERE id = ?");
                $updateStmt->bind_param("dddssi", $actual, $target, $percentage, $remarkValue, $userId, $existing['id']);
                
                if ($updateStmt->execute()) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
                $updateStmt->close();
            } else {
                $insertStmt = $conn->prepare("INSERT INTO master_performance_data 
                                              (data_month, department, indicator_name, actual_value, target_value, 
                                               percentage_achievement, remarks, created_by, created_at, verification_status)
                                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')");
                $insertStmt->bind_param("sssdddss", $dataMonth, $department, $indicator, $actual, $target, $percentage, $remarkValue, $userId);
                
                if ($insertStmt->execute()) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
                $insertStmt->close();
            }
            $checkStmt->close();
        }
    }
    
    if ($errorCount == 0) {
        $conn->commit();
        $_SESSION['message'] = "✅ Saved $successCount records successfully for " . date('F Y', strtotime($dataMonth));
    } else {
        $conn->rollback();
        $_SESSION['error'] = "⚠️ Saved $successCount records, but $errorCount failed.";
    }
    
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "Database error: " . $e->getMessage();
}

$conn->close();
header('Location: master_data.php?month=' . $month);
exit();
?>