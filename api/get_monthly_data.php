<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../session_config.php';
require_once '../includes/auth.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$month = $_GET['month'] ?? date('Y-m');

$conn = getConnection();

// Fetch overall metrics
$overallQuery = "SELECT indicator_name, AVG(percentage_achievement) as overall_percentage
                 FROM master_performance_data
                 WHERE DATE_FORMAT(data_month, '%Y-%m') = ? AND verification_status = 'verified'
                 GROUP BY indicator_name";
                 
$stmt = $conn->prepare($overallQuery);
$stmt->bind_param("s", $month);
$stmt->execute();
$overallMetrics = $stmt->get_result();
$overallData = [];
while ($row = $overallMetrics->fetch_assoc()) {
    $overallData[$row['indicator_name']] = round($row['overall_percentage'], 1);
}

// Fetch department breakdown
$deptQuery = "SELECT department, indicator_name, percentage_achievement
              FROM master_performance_data
              WHERE DATE_FORMAT(data_month, '%Y-%m') = ? AND verification_status = 'verified'
              ORDER BY department, indicator_name";
              
$stmt2 = $conn->prepare($deptQuery);
$stmt2->bind_param("s", $month);
$stmt2->execute();
$deptMetrics = $stmt2->get_result();
$departmentData = [];
while ($row = $deptMetrics->fetch_assoc()) {
    $departmentData[$row['indicator_name']][$row['department']] = round($row['percentage_achievement'], 1);
}

$stmt->close();
$stmt2->close();
$conn->close();

echo json_encode([
    'success' => true,
    'overall_metrics' => $overallData,
    'department_data' => $departmentData,
    'month' => $month
]);
?>