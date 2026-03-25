<?php
require_once '../includes/auth.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$recordId = $_GET['id'] ?? 0;
$month = $_GET['month'] ?? date('Y-m');
$dataMonth = $month . '-01';

if (!$recordId) {
    echo json_encode(['success' => false, 'message' => 'Invalid record ID']);
    exit();
}

$conn = getConnection();

$query = "SELECT m.*, 
          u1.full_name as verified_by_name,
          u2.full_name as verified_by_2_name,
          c.full_name as created_by_name,
          u.full_name as updated_by_name
          FROM master_performance_data m
          LEFT JOIN users u1 ON m.verified_by = u1.id
          LEFT JOIN users u2 ON m.verified_by_2 = u2.id
          LEFT JOIN users c ON m.created_by = c.id
          LEFT JOIN users u ON m.updated_by = u.id
          WHERE m.id = ? AND m.data_month = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("is", $recordId, $dataMonth);
$stmt->execute();
$result = $stmt->get_result();

if ($record = $result->fetch_assoc()) {
    echo json_encode([
        'success' => true,
        'record' => [
            'id' => $record['id'],
            'department' => $record['department'],
            'indicator_name' => $record['indicator_name'],
            'actual_value' => $record['actual_value'],
            'target_value' => $record['target_value'],
            'percentage_achievement' => $record['percentage_achievement'],
            'remarks' => $record['remarks'],
            'verification_status' => $record['verification_status'],
            'created_by_name' => $record['created_by_name'],
            'created_at' => $record['created_at'],
            'updated_by_name' => $record['updated_by_name'],
            'updated_at' => $record['updated_at'],
            'verified_by_name' => $record['verified_by_name'],
            'verified_at' => $record['verified_at'],
            'verified_by_2_name' => $record['verified_by_2_name'],
            'verified_at_2' => $record['verified_at_2']
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Record not found']);
}

$stmt->close();
$conn->close();
?>