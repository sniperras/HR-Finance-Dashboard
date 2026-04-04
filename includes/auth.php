<?php
// IMPORTANT: Include session config FIRST
require_once '../session_config.php';

require_once __DIR__ . '/../config/database.php';

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function requireLogin()
{
    if (!isLoggedIn()) {
        // Clear any existing session data
        session_write_close();
        header('Location: ../index.php');
        exit();
    }
}

function requireRole($role)
{
    requireLogin();

    // IT Admin role - special handling
    if ($_SESSION['user_role'] === 'it_admin') {
        // IT Admin can access IT admin pages
        if ($role === 'it_admin') {
            return true;
        }
        // IT Admin cannot access other role pages (redirect to their dashboard)
        header('Location: ../admin/it_admin_dashboard.php');
        exit();
    }

    // HR role can access everything except IT Admin pages
    if ($_SESSION['user_role'] === 'hr') {
        // HR cannot access IT Admin pages
        if ($role === 'it_admin') {
            header('Location: ../admin/master_data.php');
            exit();
        }
        return true;
    }

    // Manager role - check if role matches
    if ($role === 'manager' && $_SESSION['user_role'] === 'manager') {
        return true;
    }

    // Director role - check if role matches or if user is director accessing director pages
    if ($role === 'director' && $_SESSION['user_role'] === 'director') {
        return true;
    }

    // For MD/Managing Director role
    if ($role === 'md' && $_SESSION['user_role'] === 'md') {
        return true;
    }

    // For specific role check
    if ($_SESSION['user_role'] !== $role) {
        // Redirect based on role
        if ($_SESSION['user_role'] === 'it_admin') {
            header('Location: ../admin/it_admin_dashboard.php');
        } elseif ($_SESSION['user_role'] === 'manager') {
            header('Location: /HRandMDDash/director/manager_dashboard.php');
        } elseif ($_SESSION['user_role'] === 'director') {
            header('Location: /HRandMDDash/director/director_dashboard.php');
        } elseif ($_SESSION['user_role'] === 'md') {
            header('Location: /HRandMDDash/director/md_dashboard.php');
        } elseif ($_SESSION['user_role'] === 'hr') {
            header('Location: ../admin/master_data.php');
        } else {
            header('Location: ../index.php');
        }
        exit();
    }
}

function checkAccess($allowedRoles = [])
{
    requireLogin();

    // IT Admin role
    if ($_SESSION['user_role'] === 'it_admin') {
        if (in_array('it_admin', $allowedRoles)) {
            return true;
        }
        header('Location: ../admin/it_admin_dashboard.php');
        exit();
    }

    // HR role can access everything except IT Admin pages
    if ($_SESSION['user_role'] === 'hr') {
        if (in_array('it_admin', $allowedRoles)) {
            header('Location: ../admin/master_data.php');
            exit();
        }
        return true;
    }

    // Check if current role is allowed
    if (in_array($_SESSION['user_role'], $allowedRoles)) {
        return true;
    }

    // No access - redirect based on role
    if ($_SESSION['user_role'] === 'it_admin') {
        header('Location: ../admin/it_admin_dashboard.php');
    } elseif ($_SESSION['user_role'] === 'manager') {
        header('Location: /HRandMDDash/director/manager_dashboard.php');
    } elseif ($_SESSION['user_role'] === 'director') {
        header('Location: /HRandMDDash/director/director_dashboard.php');
    } elseif ($_SESSION['user_role'] === 'md') {
        header('Location: /HRandMDDash/director/md_dashboard.php');
    } elseif ($_SESSION['user_role'] === 'hr') {
        header('Location: ../admin/master_data.php');
    } else {
        header('Location: ../index.php');
    }
    exit();
}

function getCurrentUser()
{
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

// Get user's department from username (for director and manager users)
function getUserDepartment()
{
    if (!isLoggedIn()) {
        return null;
    }

    $username = $_SESSION['username'];
    $role = $_SESSION['user_role'];

    // For director users (format: director_BMT, director_LMT, etc.)
    if ($role === 'director' && preg_match('/director_([A-Z\/\s]+)/', $username, $matches)) {
        return trim($matches[1]);
    }

    // For manager users - get section from users table
    if ($role === 'manager') {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT section FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            $conn->close();
            return $row['section'];
        }
        $stmt->close();
        $conn->close();
    }

    return null;
}

// Get user's cost center
function getUserCostCenter()
{
    if (!isLoggedIn() || $_SESSION['user_role'] !== 'manager') {
        return null;
    }

    $username = $_SESSION['username'];
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT costcenter, section FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        $conn->close();
        return [
            'costcenter' => $row['costcenter'],
            'section' => $row['section']
        ];
    }
    $stmt->close();
    $conn->close();
    return null;
}

// Check if user is a manager
function isManager()
{
    return (isLoggedIn() && $_SESSION['user_role'] === 'manager');
}

// Check if user is a department director
function isDepartmentDirector()
{
    return (isLoggedIn() && $_SESSION['user_role'] === 'director' && getUserDepartment() !== null);
}

// Check if user is Managing Director
function isManagingDirector()
{
    return (isLoggedIn() && $_SESSION['user_role'] === 'md');
}

// Check if user is IT Admin
function isItAdmin()
{
    return (isLoggedIn() && $_SESSION['user_role'] === 'it_admin');
}

function logAction($recordId, $action, $oldData, $newData)
{
    $conn = getConnection();
    $userId = $_SESSION['user_id'] ?? 0;

    $stmt = $conn->prepare("INSERT INTO data_audit_log (record_id, action, old_data, new_data, performed_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isssi", $recordId, $action, $oldData, $newData, $userId);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

// Helper function to check if user has access to a specific page
function hasPageAccess($pageType)
{
    if (!isLoggedIn()) {
        return false;
    }

    // IT Admin access
    if ($_SESSION['user_role'] === 'it_admin') {
        $allowedPages = ['it_admin_dashboard', 'master_data', 'data_history', 'manage_indicators'];
        return in_array($pageType, $allowedPages);
    }

    // HR has access to everything except IT Admin pages
    if ($_SESSION['user_role'] === 'hr') {
        $allowedPages = ['master_data', 'data_history', 'manage_indicators', 'report_mro_cpr', 'md_dashboard'];
        return in_array($pageType, $allowedPages);
    }

    // Manager access
    if ($_SESSION['user_role'] === 'manager') {
        $allowedPages = ['manager_dashboard', 'report_mro_cpr'];
        return in_array($pageType, $allowedPages);
    }

    // Director access
    if ($_SESSION['user_role'] === 'director') {
        $allowedPages = ['md_dashboard', 'director_dashboard', 'report_mro_cpr'];
        return in_array($pageType, $allowedPages);
    }

    // Managing Director access
    if ($_SESSION['user_role'] === 'md') {
        $allowedPages = ['md_dashboard', 'director_dashboard'];
        return in_array($pageType, $allowedPages);
    }

    return false;
}
