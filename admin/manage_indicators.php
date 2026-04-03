<?php
error_reporting(0); // Suppress all warnings/errors from breaking JSON
ini_set('display_errors', 0);

require_once '../includes/auth.php';
requireRole('hr');

// Only allow RamsisE to manage indicators
if ($_SESSION['username'] !== 'RamsisE') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$conn = getConnection();

// Clean existing duplicates on every request
$cleanQuery = "
    DELETE p1 FROM performance_indicators p1
    INNER JOIN performance_indicators p2 
    WHERE p1.id > p2.id 
    AND LOWER(TRIM(p1.indicator_name)) = LOWER(TRIM(p2.indicator_name))
";
$conn->query($cleanQuery);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Ensure JSON response
header('Content-Type: application/json');

try {
    switch ($action) {
        case 'add':
            $indicatorName = trim($_POST['indicator_name'] ?? '');
            if (empty($indicatorName)) {
                throw new Exception('Indicator name is required');
            }
            
            // Check if indicator already exists (case-insensitive)
            $checkStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM performance_indicators WHERE LOWER(TRIM(indicator_name)) = LOWER(?)");
            $checkStmt->bind_param("s", $indicatorName);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['cnt'] > 0) {
                throw new Exception('Indicator already exists');
            }
            $checkStmt->close();
            
            // Insert new indicator
            $insertStmt = $conn->prepare("INSERT INTO performance_indicators (indicator_name, created_by, created_at) VALUES (?, ?, NOW())");
            $insertStmt->bind_param("si", $indicatorName, $_SESSION['user_id']);
            
            if (!$insertStmt->execute()) {
                throw new Exception('Database error: ' . $conn->error);
            }
            
            $insertStmt->close();
            
            echo json_encode(['success' => true, 'indicator_name' => $indicatorName]);
            break;
            
        case 'edit':
            $oldName = trim($_POST['old_name'] ?? '');
            $newName = trim($_POST['new_name'] ?? '');
            
            if (empty($oldName) || empty($newName)) {
                throw new Exception('Both old and new names are required');
            }
            
            if (strtolower(trim($oldName)) === strtolower(trim($newName))) {
                throw new Exception('New name is the same as old name');
            }
            
            // Check if new name already exists
            $checkStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM performance_indicators WHERE LOWER(TRIM(indicator_name)) = LOWER(?) AND LOWER(TRIM(indicator_name)) != LOWER(?)");
            $checkStmt->bind_param("ss", $newName, $oldName);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['cnt'] > 0) {
                throw new Exception('Indicator name already exists');
            }
            $checkStmt->close();
            
            // Start transaction
            $conn->begin_transaction();
            
            // Get all records with the old name
            $findStmt = $conn->prepare("SELECT id FROM performance_indicators WHERE LOWER(TRIM(indicator_name)) = LOWER(?)");
            $findStmt->bind_param("s", $oldName);
            $findStmt->execute();
            $result = $findStmt->get_result();
            $ids = [];
            while ($row = $result->fetch_assoc()) {
                $ids[] = $row['id'];
            }
            $findStmt->close();
            
            if (empty($ids)) {
                throw new Exception('Original indicator not found');
            }
            
            // Update each record
            foreach ($ids as $id) {
                $updateStmt = $conn->prepare("UPDATE performance_indicators SET indicator_name = ? WHERE id = ?");
                $updateStmt->bind_param("si", $newName, $id);
                $updateStmt->execute();
                $updateStmt->close();
            }
            
            // Update current and future months data
            $currentMonth = date('Y-m') . '-01';
            $updateDataStmt = $conn->prepare("UPDATE master_performance_data SET indicator_name = ? WHERE indicator_name = ? AND data_month >= ?");
            $updateDataStmt->bind_param("sss", $newName, $oldName, $currentMonth);
            $updateDataStmt->execute();
            $updateDataStmt->close();
            
            $conn->commit();
            
            echo json_encode(['success' => true]);
            break;
            
        case 'delete':
            $indicatorName = trim($_POST['indicator_name'] ?? '');
            $force = isset($_POST['force']) && $_POST['force'] == 1;
            
            if (empty($indicatorName)) {
                throw new Exception('Indicator name is required');
            }
            
            // Get all records with this name
            $findStmt = $conn->prepare("SELECT indicator_name FROM performance_indicators WHERE LOWER(TRIM(indicator_name)) = LOWER(?)");
            $findStmt->bind_param("s", $indicatorName);
            $findStmt->execute();
            $result = $findStmt->get_result();
            $indicatorNames = [];
            while ($row = $result->fetch_assoc()) {
                $indicatorNames[] = $row['indicator_name'];
            }
            $findStmt->close();
            
            if (empty($indicatorNames)) {
                throw new Exception('Indicator not found');
            }
            
            // Check for data in current/future months
            $currentMonth = date('Y-m') . '-01';
            $placeholders = implode(',', array_fill(0, count($indicatorNames), '?'));
            $checkStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM master_performance_data WHERE indicator_name IN ($placeholders) AND data_month >= ?");
            $types = str_repeat('s', count($indicatorNames)) . 's';
            $params = array_merge($indicatorNames, [$currentMonth]);
            $checkStmt->bind_param($types, ...$params);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $row = $result->fetch_assoc();
            $dataCount = $row['cnt'];
            $checkStmt->close();
            
            if ($dataCount > 0 && !$force) {
                echo json_encode(['warning' => true, 'message' => "This indicator has $dataCount data record(s) in current/future months. Delete anyway? Past months data will be preserved."]);
                exit();
            }
            
            $conn->begin_transaction();
            
            // Delete from current/future months
            $deleteDataStmt = $conn->prepare("DELETE FROM master_performance_data WHERE indicator_name IN ($placeholders) AND data_month >= ?");
            $deleteDataStmt->bind_param($types, ...$params);
            $deleteDataStmt->execute();
            $deletedCount = $deleteDataStmt->affected_rows;
            $deleteDataStmt->close();
            
            // Delete from indicators list
            $deleteStmt = $conn->prepare("DELETE FROM performance_indicators WHERE indicator_name IN ($placeholders)");
            $deleteStmt->bind_param(str_repeat('s', count($indicatorNames)), ...$indicatorNames);
            $deleteStmt->execute();
            $deleteStmt->close();
            
            $conn->commit();
            
            echo json_encode(['success' => true, 'deleted_records' => $deletedCount]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    if (isset($conn) && $conn->connect_errno === 0) {
        @$conn->rollback();
    }
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>