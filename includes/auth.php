<?php
require_once __DIR__ . '/../config/database.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /HRandMDDash/index.php');
        exit();
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['user_role'] !== $role) {
        header('Location: /HRandMDDash/index.php');
        exit();
    }
}

function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'full_name' => $_SESSION['full_name'],
            'role' => $_SESSION['user_role']
        ];
    }
    return null;
}

function logAction($recordId, $action, $oldData, $newData) {
    $conn = getConnection();
    $userId = $_SESSION['user_id'] ?? 0;
    
    $stmt = $conn->prepare("INSERT INTO data_audit_log (record_id, action, old_data, new_data, performed_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssi", $recordId, $action, $oldData, $newData, $userId);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}
?>