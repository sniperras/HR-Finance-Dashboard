<?php
header('Content-Type: application/json');
require_once '../session_config.php';
require_once '../includes/auth.php';
requireRole('hr');

$conn = getConnection();
$query = "SELECT indicator_name FROM performance_indicators ORDER BY indicator_name";
$result = $conn->query($query);

$indicators = [];
while ($row = $result->fetch_assoc()) {
    $indicators[] = $row['indicator_name'];
}

$conn->close();

echo json_encode(['success' => true, 'indicators' => $indicators]);
?>