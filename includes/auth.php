<?php
require_once __DIR__ . '/../config/database.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /HRandMDDash/login.php');
        exit();
    }
}

function requireRole($role) {
    requireLogin();
    
    // HR role can access everything
    if ($_SESSION['user_role'] === 'hr') {
        return true;
    }
    
    // For other roles, check specific role
    if ($_SESSION['user_role'] !== $role) {
        header('Location: /HRandMDDash/login.php');
        exit();
    }
}

function checkAccess($allowedRoles = []) {
    requireLogin();
    
    // HR role can access everything
    if ($_SESSION['user_role'] === 'hr') {
        return true;
    }
    
    // Check if current role is allowed
    if (in_array($_SESSION['user_role'], $allowedRoles)) {
        return true;
    }
    
    // No access - redirect based on role
    if ($_SESSION['user_role'] === 'director') {
        header('Location: /HRandMDDash/director/md_dashboard.php');
    } else {
        header('Location: /HRandMDDash/login.php');
    }
    exit();
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

// Helper function to check if user has access to a specific page
function hasPageAccess($pageType) {
    if (!isLoggedIn()) {
        return false;
    }
    
    // HR has access to everything
    if ($_SESSION['user_role'] === 'hr') {
        return true;
    }
    
    // Director access
    if ($_SESSION['user_role'] === 'director') {
        $allowedPages = ['md_dashboard', 'director_dashboard', 'report_mro_cpr'];
        return in_array($pageType, $allowedPages);
    }
    
    return false;
}