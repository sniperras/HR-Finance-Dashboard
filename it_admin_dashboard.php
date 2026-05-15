<?php
require_once 'session_config.php';
require_once './includes/auth.php';
requireRole('it_admin');

$conn = getConnection();

// Get all users with their roles and details
$usersQuery = "SELECT u.id, u.oid, u.username, u.full_name, u.role, u.email, u.costcenter, u.section, u.created_at, u.last_login, 
               COUNT(DISTINCT m.id) as master_edits,
               COUNT(DISTINCT r.id) as mro_edits
               FROM users u
               LEFT JOIN master_performance_data m ON u.id = m.updated_by
               LEFT JOIN mro_cpr_report r ON u.id = r.updated_by
               GROUP BY u.id
               ORDER BY u.role, u.full_name";
$usersResult = $conn->query($usersQuery);

// Get system statistics
$stats = [];

// Total users count
$totalUsersQuery = "SELECT COUNT(*) as total, 
                    SUM(CASE WHEN role = 'hr' THEN 1 ELSE 0 END) as hr_count,
                    SUM(CASE WHEN role = 'qa auditor' THEN 1 ELSE 0 END) as qa_auditor_count,
                    SUM(CASE WHEN role = 'director' THEN 1 ELSE 0 END) as director_count,
                    SUM(CASE WHEN role = 'it_admin' THEN 1 ELSE 0 END) as admin_count,
                    SUM(CASE WHEN role = 'md' THEN 1 ELSE 0 END) as md_count,
                    SUM(CASE WHEN role = 'manager' THEN 1 ELSE 0 END) as manager_count
                    FROM users";
$totalUsersResult = $conn->query($totalUsersQuery);
$stats['users'] = $totalUsersResult->fetch_assoc();

// Total records in master_performance_data
$masterCountQuery = "SELECT COUNT(*) as total, 
                     SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as verified,
                     SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending
                     FROM master_performance_data";
$masterCountResult = $conn->query($masterCountQuery);
$stats['master'] = $masterCountResult->fetch_assoc();

// Total records in mro_cpr_report
$mroCountQuery = "SELECT COUNT(*) as total, 
                  SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as verified,
                  SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending
                  FROM mro_cpr_report";
$mroCountResult = $conn->query($mroCountQuery);
$stats['mro'] = $mroCountResult->fetch_assoc();

// Total indicators
$indicatorsQuery = "SELECT COUNT(*) as total FROM performance_indicators";
$indicatorsResult = $conn->query($indicatorsQuery);
$stats['indicators'] = $indicatorsResult->fetch_assoc()['total'];

// Recent activity (last 7 days)
$recentActivityQuery = "SELECT COUNT(*) as total, 
                         SUM(CASE WHEN DATE(performed_at) = CURDATE() THEN 1 ELSE 0 END) as today,
                         SUM(CASE WHEN DATE(performed_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) as yesterday
                         FROM data_audit_log 
                         WHERE performed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
$recentActivityResult = $conn->query($recentActivityQuery);
$stats['activity'] = $recentActivityResult->fetch_assoc();

// Database size
$dbSizeQuery = "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()";
$dbSizeResult = $conn->query($dbSizeQuery);
$stats['db_size'] = $dbSizeResult->fetch_assoc()['size_mb'] ?? 0;

// Get server resource usage (simulated - replace with actual monitoring)
$serverStats = [
    'cpu_usage' => rand(15, 85),
    'ram_usage' => rand(30, 90),
    'disk_usage' => rand(40, 95),
    'bandwidth_usage' => rand(20, 80),
    'latency' => rand(10, 200),
    'packet_loss' => rand(0, 5)
];

// Security metrics
$securityMetrics = [
    'mttr' => rand(2, 48),
    'vulnerabilities_critical' => rand(0, 5),
    'vulnerabilities_high' => rand(1, 12),
    'vulnerabilities_medium' => rand(5, 25),
    'vulnerabilities_low' => rand(10, 40),
    'patches_pending' => rand(0, 15),
    'patches_completed' => rand(5, 30)
];

// Threat activity by type
$threatActivity = [
    'malware' => rand(0, 50),
    'phishing' => rand(0, 100),
    'brute_force' => rand(0, 200),
    'ddos' => rand(0, 10),
    'unauthorized_access' => rand(0, 30)
];

// Handle actions
$resetMessage = '';
$resetError = '';
$resetType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $redirect = false;

    if ($_POST['action'] === 'reset_password') {
        $userId = intval($_POST['user_id']);
        $newPassword = $_POST['new_password'];

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $updateStmt->bind_param("si", $hashedPassword, $userId);

        if ($updateStmt->execute()) {
            $_SESSION['reset_message'] = "Password reset successfully for user ID: " . $userId;
            $_SESSION['reset_type'] = "success";
            $redirect = true;

            $logStmt = $conn->prepare("INSERT INTO data_audit_log (record_id, action, old_data, new_data, performed_by, performed_at) 
                                       VALUES (?, 'password_reset', ?, ?, ?, NOW())");
            $oldData = json_encode(['password' => '********']);
            $newData = json_encode(['password' => 'reset_by_admin']);
            $logStmt->bind_param("issi", $userId, $oldData, $newData, $_SESSION['user_id']);
            $logStmt->execute();
            $logStmt->close();
        } else {
            $_SESSION['reset_error'] = "Failed to reset password. Please try again.";
            $_SESSION['reset_type'] = "error";
            $redirect = true;
        }
        $updateStmt->close();
    } elseif ($_POST['action'] === 'reset_all_hr') {
        $defaultPassword = 'password123';
        $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);

        $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE role = 'hr'");
        $updateStmt->bind_param("s", $hashedPassword);

        if ($updateStmt->execute()) {
            $affectedRows = $updateStmt->affected_rows;
            $_SESSION['reset_message'] = "Reset password for $affectedRows HR user(s) to: password123";
            $_SESSION['reset_type'] = "success";
            $redirect = true;
        } else {
            $_SESSION['reset_error'] = "Failed to reset HR passwords.";
            $_SESSION['reset_type'] = "error";
            $redirect = true;
        }
        $updateStmt->close();
    } elseif ($_POST['action'] === 'delete_user') {
        $userId = intval($_POST['user_id']);

        if ($userId == $_SESSION['user_id']) {
            $_SESSION['reset_error'] = "You cannot delete your own account.";
            $_SESSION['reset_type'] = "error";
            $redirect = true;
        } else {
            $checkStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM master_performance_data WHERE created_by = ? OR updated_by = ?");
            $checkStmt->bind_param("ii", $userId, $userId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $recordCount = $checkResult->fetch_assoc()['cnt'];
            $checkStmt->close();

            if ($recordCount > 0) {
                $_SESSION['reset_error'] = "Cannot delete user. User has $recordCount record(s) in the system.";
                $_SESSION['reset_type'] = "error";
                $redirect = true;
            } else {
                $deleteStmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $deleteStmt->bind_param("i", $userId);
                if ($deleteStmt->execute()) {
                    $_SESSION['reset_message'] = "User deleted successfully.";
                    $_SESSION['reset_type'] = "success";
                    $redirect = true;
                } else {
                    $_SESSION['reset_error'] = "Failed to delete user.";
                    $_SESSION['reset_type'] = "error";
                    $redirect = true;
                }
                $deleteStmt->close();
            }
        }
    } elseif ($_POST['action'] === 'add_user') {
        // Get form data
        $oid = trim($_POST['oid']);
        $username = trim($_POST['username']);
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $costcenter = trim($_POST['costcenter'] ?? '');
        $section = trim($_POST['section'] ?? '');
        $password = $_POST['password'];

        // Validation
        $errors = [];

        // Check if OID exists
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE oid = ?");
        $checkStmt->bind_param("s", $oid);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows > 0) {
            $errors[] = "OID already exists";
        }
        $checkStmt->close();

        // Check if username exists
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $checkStmt->bind_param("s", $username);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows > 0) {
            $errors[] = "Username already exists";
        }
        $checkStmt->close();

        // Check if email exists
        if (!empty($email)) {
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $checkStmt->bind_param("s", $email);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            if ($checkResult->num_rows > 0) {
                $errors[] = "Email already exists";
            }
            $checkStmt->close();
        }

        // Validate password strength
        if (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters";
        }

        if (empty($errors)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $created_at = date('Y-m-d H:i:s');

            $insertStmt = $conn->prepare("INSERT INTO users (oid, username, full_name, email, role, costcenter, section, password, created_at, temp_password, temp_password_expiry) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL)");
            $insertStmt->bind_param("sssssssss", $oid, $username, $full_name, $email, $role, $costcenter, $section, $hashedPassword, $created_at);

            if ($insertStmt->execute()) {
                $newUserId = $insertStmt->insert_id;
                $_SESSION['reset_message'] = "User '$username' (OID: $oid) created successfully! Password: " . ($password === 'password123' ? 'password123' : 'custom password set');
                $_SESSION['reset_type'] = "success";
                $redirect = true;

                // Log the action
                $logStmt = $conn->prepare("INSERT INTO data_audit_log (record_id, action, old_data, new_data, performed_by, performed_at) 
                                           VALUES (?, 'user_created', ?, ?, ?, NOW())");
                $oldData = json_encode(['user' => 'none']);
                $newData = json_encode(['oid' => $oid, 'username' => $username, 'role' => $role]);
                $logStmt->bind_param("issi", $newUserId, $oldData, $newData, $_SESSION['user_id']);
                $logStmt->execute();
                $logStmt->close();
            } else {
                $_SESSION['reset_error'] = "Failed to create user: " . $conn->error;
                $_SESSION['reset_type'] = "error";
                $redirect = true;
            }
            $insertStmt->close();
        } else {
            $_SESSION['reset_error'] = "Validation errors: " . implode(", ", $errors);
            $_SESSION['reset_type'] = "error";
            $redirect = true;
        }
    }

    if ($redirect) {
        header('Location: it_admin_dashboard.php');
        exit();
    }
}

// Check for session messages after redirect
if (isset($_SESSION['reset_message'])) {
    $resetMessage = $_SESSION['reset_message'];
    $resetType = $_SESSION['reset_type'] ?? 'success';
    unset($_SESSION['reset_message']);
    unset($_SESSION['reset_type']);
}
if (isset($_SESSION['reset_error'])) {
    $resetError = $_SESSION['reset_error'];
    unset($_SESSION['reset_error']);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Admin Dashboard - System Management</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/ethiopian_logo.ico">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --dark-bg: #0F172A;
            --medium-bg: #1E293B;
            --accent: #38BDF8;
            --accent-hover: #60A5FA;
            --light-bg: #F1F5F9;
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
            --info: #3B82F6;
            --border-light: #334155;
            --card-bg: #1E293B;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', 'Inter', system-ui, sans-serif;
            background: var(--dark-bg);
            color: var(--light-bg);
            transition: background-color 0.3s, color 0.3s;
        }

        .navbar {
            background: var(--medium-bg);
            padding: 0.5rem 0;
            transition: background-color 0.3s;
        }

        .navbar-container {
            max-width: 100%;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .navbar-brand {
            font-size: 1rem;
            font-weight: bold;
            color: var(--accent);
            text-decoration: none;
        }

        .navbar-menu {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .navbar-menu a {
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.8rem;
            transition: color 0.2s;
        }

        .navbar-menu a:hover {
            color: var(--accent);
        }

        .user-info {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .user-name {
            color: var(--accent);
            font-weight: bold;
            font-size: 0.8rem;
        }

        .btn {
            background: var(--accent);
            color: var(--dark-bg);
            border: none;
            padding: 0.4rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.8rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-warning {
            background: var(--warning);
            color: var(--dark-bg);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-sm {
            padding: 0.2rem 0.5rem;
            font-size: 0.7rem;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(56, 189, 248, 0.3);
        }

        .theme-toggle {
            background: transparent;
            border: 1px solid var(--accent);
            color: var(--accent);
            padding: 0.3rem 0.8rem;
            cursor: pointer;
            border-radius: 5px;
            font-size: 0.7rem;
        }

        .container {
            max-width: 1400px;
            margin: 1rem auto;
            padding: 0 1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--card-bg);
            padding: 1rem;
            border-radius: 12px;
            border: 1px solid var(--border-light);
            text-align: center;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--accent);
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        .stat-sub {
            font-size: 0.65rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 1px solid var(--border-light);
        }

        .section {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-light);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--accent);
        }

        .section-title {
            font-size: 1rem;
            font-weight: bold;
            color: var(--accent);
        }

        .monitoring-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .monitor-card {
            background: var(--dark-bg);
            border-radius: 10px;
            padding: 1rem;
            border: 1px solid var(--border-light);
        }

        .monitor-title {
            font-size: 0.85rem;
            font-weight: bold;
            color: var(--accent);
            margin-bottom: 0.75rem;
        }

        .gauge-value {
            font-size: 1.5rem;
            font-weight: bold;
            text-align: center;
        }

        .progress-bar-custom {
            height: 8px;
            background: var(--border-light);
            border-radius: 4px;
            overflow: hidden;
            margin: 0.5rem 0;
        }

        .progress-fill-custom {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s;
        }

        .threat-item {
            display: flex;
            justify-content: space-between;
            padding: 0.4rem 0;
            border-bottom: 1px solid var(--border-light);
        }

        .threat-name {
            font-weight: bold;
        }

        .threat-count {
            font-family: monospace;
            font-size: 1.1rem;
        }

        .user-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }

        .user-table th,
        .user-table td {
            padding: 0.75rem 0.5rem;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
        }

        .user-table th {
            background: var(--dark-bg);
            color: var(--accent);
            font-weight: bold;
            position: sticky;
            top: 0;
        }

        .user-table tr:hover {
            background: rgba(56, 189, 248, 0.05);
        }

        .role-badge {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: bold;
        }

        .role-hr {
            background: #3b82f6;
            color: white;
        }

        .role-director {
            background: #f59e0b;
            color: white;
        }

        .role-it_admin {
            background: #10b981;
            color: white;
        }

        .role-md {
            background: #8b5cf6;
            color: white;
        }

        .role-manager {
            background: #ec489a;
            color: white;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--medium-bg);
            border-radius: 12px;
            width: 90%;
            max-width: 550px;
            padding: 1.5rem;
            border: 1px solid var(--border-light);
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--accent);
        }

        .modal-header h3 {
            color: var(--accent);
        }

        .close-modal {
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 1.5rem;
            cursor: pointer;
        }

        .password-options {
            margin: 1rem 0;
        }

        .password-option {
            background: var(--dark-bg);
            padding: 0.5rem;
            margin: 0.5rem 0;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: monospace;
        }

        .password-option:hover {
            background: rgba(56, 189, 248, 0.1);
        }

        .password-option.selected {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid var(--success);
        }

        .alert {
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid var(--success);
            color: var(--success);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid var(--danger);
            color: var(--danger);
        }

        .table-wrapper {
            overflow-x: auto;
            max-height: 400px;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.25rem;
            font-weight: bold;
            font-size: 0.85rem;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.5rem;
            border-radius: 5px;
            border: 1px solid var(--border-light);
            background: var(--dark-bg);
            color: var(--text-primary);
            font-size: 0.85rem;
        }

        .form-group small {
            font-size: 0.7rem;
            color: var(--text-secondary);
        }

        .password-input-group {
            display: flex;
            gap: 0.5rem;
        }

        .password-input-group input {
            flex: 1;
        }

        .modal-buttons {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }

        body.light-theme {
            --dark-bg: #F8FAFC;
            --medium-bg: #FFFFFF;
            --accent: #0284C7;
            --light-bg: #0F172A;
            --card-bg: #FFFFFF;
            --border-light: #E2E8F0;
            --text-primary: #0F172A;
            --text-secondary: #475569;
        }

        body.light-theme .navbar,
        body.light-theme .section,
        body.light-theme .stat-card,
        body.light-theme .monitor-card {
            background: white !important;
            border-color: #E2E8F0 !important;
        }

        body.light-theme .user-table th {
            background: #F1F5F9;
        }

        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: var(--dark-bg);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 3px;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="it_admin_dashboard.php" class="navbar-brand">IT Admin Dashboard</a>
            <div class="navbar-menu">
                <a href="it_admin_dashboard.php" style="color: var(--accent);">Dashboard</a>
                <a href="admin/master_data.php">Master Data</a>
                <a href="director/md_dashboard.php">Dashboard</a>
                <div class="user-info">
                    <button id="themeToggle" class="theme-toggle">☀️ Light</button>
                    <span class="user-name">👤 <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="#" onclick="openPasswordModal(); return false;" style="cursor: pointer;">🔑 Change Password</a>
                    <a href="logout.php" class="btn">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <?php if ($resetMessage): ?>
            <div class="alert alert-success">✓ <?php echo htmlspecialchars($resetMessage); ?></div>
        <?php endif; ?>
        <?php if ($resetError): ?>
            <div class="alert alert-error">⚠ <?php echo htmlspecialchars($resetError); ?></div>
        <?php endif; ?>

        <!-- System Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['users']['total']; ?></div>
                <div class="stat-label">Total Users</div>
                <div class="stat-sub">
                    HR: <?php echo $stats['users']['hr_count']; ?> |
                    Director: <?php echo $stats['users']['director_count']; ?> |
                    Admin: <?php echo $stats['users']['admin_count']; ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['master']['total']); ?></div>
                <div class="stat-label">Master Data Records</div>
                <div class="stat-sub">
                    Verified: <?php echo $stats['master']['verified']; ?> |
                    Pending: <?php echo $stats['master']['pending']; ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['mro']['total']); ?></div>
                <div class="stat-label">MRO Report Records</div>
                <div class="stat-sub">
                    Verified: <?php echo $stats['mro']['verified']; ?> |
                    Pending: <?php echo $stats['mro']['pending']; ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['indicators']; ?></div>
                <div class="stat-label">Performance Indicators</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['activity']['total']); ?></div>
                <div class="stat-label">Activities (Last 7 Days)</div>
                <div class="stat-sub">
                    Today: <?php echo $stats['activity']['today']; ?> |
                    Yesterday: <?php echo $stats['activity']['yesterday']; ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['db_size']; ?> MB</div>
                <div class="stat-label">Database Size</div>
            </div>
        </div>

        <!-- Security & Infrastructure Monitoring -->
        <div class="section">
            <div class="section-header">
                <div class="section-title">🛡️ Security & Infrastructure Monitoring</div>
            </div>
            <div class="monitoring-grid">
                <div class="monitor-card">
                    <div class="monitor-title">🚨 Mean Time to Repair (MTTR)</div>
                    <div class="gauge-value" style="color: <?php echo $securityMetrics['mttr'] > 24 ? 'var(--danger)' : ($securityMetrics['mttr'] > 12 ? 'var(--warning)' : 'var(--success)'); ?>">
                        <?php echo $securityMetrics['mttr']; ?> hours
                    </div>
                    <div class="progress-bar-custom">
                        <div class="progress-fill-custom" style="width: <?php echo min(100, ($securityMetrics['mttr'] / 48) * 100); ?>%; background: <?php echo $securityMetrics['mttr'] > 24 ? '#dc3545' : ($securityMetrics['mttr'] > 12 ? '#ffc107' : '#28a745'); ?>;"></div>
                    </div>
                    <div style="font-size: 0.65rem; margin-top: 0.5rem;">Target: &lt; 24 hours</div>
                </div>

                <div class="monitor-card">
                    <div class="monitor-title">🔓 Vulnerability Status</div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span>Critical</span>
                        <span style="color: #dc3545; font-weight: bold;"><?php echo $securityMetrics['vulnerabilities_critical']; ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span>High</span>
                        <span style="color: #fd7e14; font-weight: bold;"><?php echo $securityMetrics['vulnerabilities_high']; ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span>Medium</span>
                        <span style="color: #ffc107; font-weight: bold;"><?php echo $securityMetrics['vulnerabilities_medium']; ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Low</span>
                        <span style="color: #28a745; font-weight: bold;"><?php echo $securityMetrics['vulnerabilities_low']; ?></span>
                    </div>
                </div>

                <div class="monitor-card">
                    <div class="monitor-title">💻 Resource Consumption</div>
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                            <span>CPU Usage</span>
                            <span><?php echo $serverStats['cpu_usage']; ?>%</span>
                        </div>
                        <div class="progress-bar-custom">
                            <div class="progress-fill-custom" style="width: <?php echo $serverStats['cpu_usage']; ?>%; background: <?php echo $serverStats['cpu_usage'] > 80 ? '#dc3545' : ($serverStats['cpu_usage'] > 60 ? '#ffc107' : '#28a745'); ?>;"></div>
                        </div>
                    </div>
                    <div style="margin-top: 0.5rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                            <span>RAM Usage</span>
                            <span><?php echo $serverStats['ram_usage']; ?>%</span>
                        </div>
                        <div class="progress-bar-custom">
                            <div class="progress-fill-custom" style="width: <?php echo $serverStats['ram_usage']; ?>%; background: <?php echo $serverStats['ram_usage'] > 80 ? '#dc3545' : ($serverStats['ram_usage'] > 60 ? '#ffc107' : '#28a745'); ?>;"></div>
                        </div>
                    </div>
                </div>

                <div class="monitor-card">
                    <div class="monitor-title">⚠️ Threat Activity (Last 24h)</div>
                    <div class="threat-item">
                        <span class="threat-name">Malware Detections</span>
                        <span class="threat-count"><?php echo $threatActivity['malware']; ?></span>
                    </div>
                    <div class="threat-item">
                        <span class="threat-name">Phishing Attempts</span>
                        <span class="threat-count"><?php echo $threatActivity['phishing']; ?></span>
                    </div>
                    <div class="threat-item">
                        <span class="threat-name">Brute Force Attacks</span>
                        <span class="threat-count"><?php echo $threatActivity['brute_force']; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Management Section -->
        <div class="section">
            <div class="section-header">
                <div class="section-title">👥 User Management</div>
                <div>
                    <button class="btn btn-success" onclick="openAddUserModal()" style="margin-right: 0.5rem;">➕ Add New User</button>
                    <button class="btn btn-warning btn-sm" onclick="resetAllHrPasswords()">Reset All HR Passwords</button>
                </div>
            </div>
            <div class="table-wrapper">
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>OID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Role</th>
                            <th>Email</th>
                            <th>Cost Center</th>
                            <th>Section</th>
                            <th>Created</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $usersResult->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['oid']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td>
                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                        <?php echo strtoupper($user['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($user['costcenter'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($user['section'] ?? '-'); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                <td><?php echo $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                                <td>
                                    <button class="btn btn-sm" onclick="openResetModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">Reset Pwd</button>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <button class="btn btn-danger btn-sm" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">Delete</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- System Info Section -->
        <div class="section">
            <div class="section-header">
                <div class="section-title">ℹ️ System Information</div>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
                <div>
                    <strong>PHP Version:</strong> <?php echo phpversion(); ?><br>
                    <strong>Server Software:</strong> <?php echo $_SERVER['SERVER_SOFTWARE']; ?><br>
                    <strong>Database:</strong> MySQL / MariaDB
                </div>
                <div>
                    <strong>Timezone:</strong> <?php echo date_default_timezone_get(); ?><br>
                    <strong>Current Time:</strong> <?php echo date('Y-m-d H:i:s'); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Password Reset Modal -->
    <div id="resetModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reset Password</h3>
                <button class="close-modal" onclick="closeResetModal()">&times;</button>
            </div>
            <div>
                <p>User: <strong id="resetUsername"></strong></p>
                <p>Select a default password:</p>
                <div class="password-options">
                    <div class="password-option" data-password="password123" onclick="selectPassword(this, 'password123')">
                        <span>🔑 password123</span>
                        <span>Select →</span>
                    </div>
                    <div class="password-option" data-password="Qwer@1234" onclick="selectPassword(this, 'Qwer@1234')">
                        <span>🔒 Qwer@1234</span>
                        <span>Select →</span>
                    </div>
                    <div class="password-option" data-password="Zxcv@1234" onclick="selectPassword(this, 'Zxcv@1234')">
                        <span>🔐 Zxcv@1234</span>
                        <span>Select →</span>
                    </div>
                </div>
                <div id="selectedPasswordDisplay" style="margin: 0.5rem 0; padding: 0.5rem; background: var(--dark-bg); border-radius: 5px; text-align: center; display: none;">
                    Selected: <strong id="selectedPasswordText"></strong>
                </div>
                <form id="resetForm" method="POST">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" id="resetUserId">
                    <input type="hidden" name="new_password" id="resetPassword">
                    <button type="submit" class="btn btn-warning" style="width: 100%;" id="confirmResetBtn" disabled>Confirm Reset</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>➕ Add New User</h3>
                <button class="close-modal" onclick="closeAddUserModal()">&times;</button>
            </div>
            <form id="addUserForm" method="POST" onsubmit="return validateAddUserForm()">
                <input type="hidden" name="action" value="add_user">

                <div class="form-group">
                    <label>OID * (Organization ID)</label>
                    <input type="text" name="oid" id="oid" required
                        placeholder="e.g., 25582">
                    <small>Unique Organization ID - Must be unique</small>
                </div>

                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" id="username" required
                        placeholder="e.g., john.doe">
                    <small>Unique login username</small>
                </div>

                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" id="full_name" required
                        placeholder="e.g., John Doe">
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="email"
                        placeholder="abebe@ethiopianairlines.com">
                </div>

                <div class="form-group">
                    <label>Role *</label>
                    <select name="role" id="role" required>
                        <option value="">Select Role</option>
                        <option value="hr">HR Specialist</option>
                        <option value="qa auditor">QA Auditor</option>
                        <option value="manager">Manager</option>
                        <option value="director">Director</option>
                        <option value="md">Managing Director</option>
                        <option value="it_admin">IT Admin</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Cost Center</label>
                    <input type="text" name="costcenter" id="costcenter"
                        placeholder="e.g., MROMG335">
                </div>

                <div class="form-group">
                    <label>Section/Department</label>
                    <input type="text" name="section" id="section"
                        placeholder="e.g., HR Department">
                </div>

                <div class="form-group">
                    <label>Password *</label>
                    <div class="password-input-group">
                        <input type="password" name="password" id="password" required>
                        <button type="button" class="btn btn-sm" onclick="generateRandomPassword()">Generate</button>
                        <button type="button" class="btn btn-sm" onclick="setDefaultPassword()">Set Default</button>
                    </div>
                    <small>Minimum 6 characters. Default: password123</small>
                </div>

                <div class="form-group">
                    <label>Confirm Password *</label>
                    <input type="password" name="confirm_password" id="confirm_password" required>
                </div>

                <div class="modal-buttons">
                    <button type="button" class="btn" onclick="closeAddUserModal()" style="background: var(--border-light);">Cancel</button>
                    <button type="submit" class="btn btn-success">Create User</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentUserId = null;
        let selectedPasswordValue = null;

        // Reset Password Modal Functions
        function openResetModal(userId, username) {
            currentUserId = userId;
            document.getElementById('resetUserId').value = userId;
            document.getElementById('resetUsername').textContent = username;
            document.getElementById('resetModal').style.display = 'flex';
            selectedPasswordValue = null;
            document.getElementById('resetPassword').value = '';
            document.getElementById('selectedPasswordDisplay').style.display = 'none';
            document.getElementById('confirmResetBtn').disabled = true;
            document.querySelectorAll('.password-option').forEach(opt => {
                opt.classList.remove('selected');
            });
        }

        function closeResetModal() {
            document.getElementById('resetModal').style.display = 'none';
        }

        function selectPassword(element, password) {
            document.querySelectorAll('.password-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            element.classList.add('selected');
            selectedPasswordValue = password;
            document.getElementById('resetPassword').value = password;
            document.getElementById('selectedPasswordText').textContent = password;
            document.getElementById('selectedPasswordDisplay').style.display = 'block';
            document.getElementById('confirmResetBtn').disabled = false;
        }

        // Add User Modal Functions
        function openAddUserModal() {
            document.getElementById('addUserModal').style.display = 'flex';
            document.getElementById('addUserForm').reset();
        }

        function closeAddUserModal() {
            document.getElementById('addUserModal').style.display = 'none';
        }

        function generateRandomPassword() {
            const length = 10;
            const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%";
            let password = "";
            for (let i = 0; i < length; i++) {
                const randomIndex = Math.floor(Math.random() * charset.length);
                password += charset[randomIndex];
            }
            document.getElementById('password').value = password;
            document.getElementById('confirm_password').value = password;
        }

        function setDefaultPassword() {
            document.getElementById('password').value = 'password123';
            document.getElementById('confirm_password').value = 'password123';
        }

        function validateAddUserForm() {
            const oid = document.getElementById('oid').value;
            const username = document.getElementById('username').value;
            const fullName = document.getElementById('full_name').value;
            const role = document.getElementById('role').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (!oid || !username || !fullName || !role) {
                alert('Please fill in all required fields (OID, Username, Full Name, and Role)');
                return false;
            }

            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                return false;
            }

            if (password.length < 6) {
                alert('Password must be at least 6 characters long!');
                return false;
            }

            return true;
        }

        function resetAllHrPasswords() {
            if (confirm('WARNING: This will reset ALL HR users\' passwords to "password123".\nAre you sure you want to continue?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="reset_all_hr">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteUser(userId, username) {
            if (confirm(`WARNING: You are about to delete user "${username}".\nThis action cannot be undone.\nAre you sure you want to continue?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Theme Manager
        class ThemeManager {
            constructor() {
                this.themeKey = 'dashboard_theme';
                this.loadTheme();
                this.initToggle();
            }

            loadTheme() {
                const savedTheme = localStorage.getItem(this.themeKey);
                if (savedTheme === 'light') {
                    document.body.classList.add('light-theme');
                    this.updateToggleButton(true);
                } else {
                    document.body.classList.remove('light-theme');
                    this.updateToggleButton(false);
                }
            }

            toggleTheme() {
                if (document.body.classList.contains('light-theme')) {
                    document.body.classList.remove('light-theme');
                    localStorage.setItem(this.themeKey, 'dark');
                    this.updateToggleButton(false);
                } else {
                    document.body.classList.add('light-theme');
                    localStorage.setItem(this.themeKey, 'light');
                    this.updateToggleButton(true);
                }
            }

            updateToggleButton(isLight) {
                const toggleBtn = document.getElementById('themeToggle');
                if (toggleBtn) {
                    toggleBtn.innerHTML = isLight ? '🌙 Dark' : '☀️ Light';
                }
            }

            initToggle() {
                const toggleBtn = document.getElementById('themeToggle');
                if (toggleBtn) {
                    toggleBtn.addEventListener('click', () => this.toggleTheme());
                }
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const resetModal = document.getElementById('resetModal');
            if (event.target === resetModal) {
                closeResetModal();
            }
            const addUserModal = document.getElementById('addUserModal');
            if (event.target === addUserModal) {
                closeAddUserModal();
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            new ThemeManager();
        });

        // Function to open password change modal
        function openPasswordModal() {
            if (document.getElementById('passwordModalOverlay')) {
                return;
            }
            const modalOverlay = document.createElement('div');
            modalOverlay.id = 'passwordModalOverlay';
            modalOverlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.8);
                z-index: 10001;
                display: flex;
                align-items: center;
                justify-content: center;
            `;
            const iframe = document.createElement('iframe');
            iframe.src = 'change_password.php';
            iframe.style.cssText = `
                width: 100%;
                max-width: 450px;
                height: auto;
                min-height: 450px;
                border: none;
                border-radius: 16px;
                background: transparent;
            `;
            modalOverlay.appendChild(iframe);
            document.body.appendChild(modalOverlay);
            window.closePasswordPopup = function() {
                if (modalOverlay && modalOverlay.parentNode) {
                    modalOverlay.remove();
                }
                delete window.closePasswordPopup;
            };
        }

        // Keep session alive
        function keepSessionAlive() {
            fetch('keep_alive.php', {
                method: 'GET',
                cache: 'no-cache'
            }).catch(error => console.log('Session keep-alive failed:', error));
        }

        setInterval(keepSessionAlive, 5 * 60 * 1000);
    </script>
</body>

</html>