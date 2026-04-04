<?php
header('Content-Type: application/json');
require_once '../session_config.php';
require_once '../includes/auth.php';
requireRole('director');

$conn = getConnection();

$department = $_GET['dept'] ?? '';
$indicator = $_GET['indicator'] ?? '';
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

if (empty($department) || empty($indicator)) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit();
}

// In get_dept_indicator_data.php, add verification_status filter
$query = "SELECT cost_center_code, cost_center_text, expected, completed, percentage 
          FROM mro_cpr_report 
          WHERE department = ? AND report_type = ? AND report_month = ? AND report_year = ?
          AND verification_status = 'verified'
          ORDER BY FIELD(cost_center_code, 'DIR', 'ACS', 'AVS', 'B787', 'B737', 'CAB', 'B777', 'APS', 'TEC', 'DMM', 'ADM', 'ALM', 'GAM', 'TPL', 'ACM', 'WKH', 'CES', 'NDT', 'MES', 'MCS', 'EMI', 'ETS', 'RNP', 'CFM', 'RSH', 'ALE', 'AMP', 'MPR', 'EQA', 'ASE', 'ADO', 'MSM', 'MCS', 'QAS', 'HR', 'FIN', 'REM')";

$stmt = $conn->prepare($query);
$stmt->bind_param("ssii", $department, $indicator, $month, $year);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'data' => $data]);
