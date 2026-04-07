<?php
require_once 'session_config.php';
require_once './includes/auth.php';
requireRole('it_admin');

$conn = getConnection();

// Get all users with their roles and details
$usersQuery = "SELECT u.id, u.username, u.full_name, u.role, u.email, u.created_at, u.last_login, 
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
    'mttr' => rand(2, 48), // Mean Time To Repair in hours
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

// Handle password reset with POST-Redirect-GET pattern to prevent resubmission
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

            // Log the action
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

        /* Stats Grid */
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

        /* Section Styles */
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

        /* Monitoring Grid */
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

        .gauge-container {
            position: relative;
            width: 100%;
            height: 100px;
            margin: 0.5rem 0;
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

        .severity-critical {
            background: #dc3545;
        }

        .severity-high {
            background: #fd7e14;
        }

        .severity-medium {
            background: #ffc107;
        }

        .severity-low {
            background: #28a745;
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

        /* Table Styles */
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

        /* Modal Styles */
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
            max-width: 450px;
            padding: 1.5rem;
            border: 1px solid var(--border-light);
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

        /* Light Theme */
        body.light-theme {
            --dark-bg: #F8FAFC;
            --medium-bg: #FFFFFF;
            --accent: #0284C7;
            --accent-hover: #0EA5E9;
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

        body.light-theme .navbar {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        body.light-theme .user-table th {
            background: #F1F5F9;
        }

        body.light-theme .theme-toggle {
            border-color: #0284C7;
            color: #0284C7;
        }

        body.light-theme .theme-toggle:hover {
            background: #0284C7;
            color: white;
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

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }

            to {
                transform: translateX(100%);
                opacity: 0;
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
                <!-- Incident Response - MTTR -->
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

                <!-- Vulnerability Management -->
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
                    <div class="progress-bar-custom mt-2">
                        <div class="progress-fill-custom" style="width: <?php echo $securityMetrics['patches_completed'] > 0 ? round(($securityMetrics['patches_completed'] / ($securityMetrics['patches_completed'] + $securityMetrics['patches_pending'])) * 100) : 0; ?>%; background: var(--success);"></div>
                    </div>
                    <div style="font-size: 0.65rem; margin-top: 0.5rem;">Patches: <?php echo $securityMetrics['patches_completed']; ?> completed / <?php echo $securityMetrics['patches_pending']; ?> pending</div>
                </div>

                <!-- Resource Consumption -->
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
                    <div style="margin-top: 0.5rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                            <span>Storage Usage</span>
                            <span><?php echo $serverStats['disk_usage']; ?>%</span>
                        </div>
                        <div class="progress-bar-custom">
                            <div class="progress-fill-custom" style="width: <?php echo $serverStats['disk_usage']; ?>%; background: <?php echo $serverStats['disk_usage'] > 85 ? '#dc3545' : ($serverStats['disk_usage'] > 70 ? '#ffc107' : '#28a745'); ?>;"></div>
                        </div>
                    </div>
                </div>

                <!-- Threat Activity -->
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
                    <div class="threat-item">
                        <span class="threat-name">DDoS Attempts</span>
                        <span class="threat-count"><?php echo $threatActivity['ddos']; ?></span>
                    </div>
                    <div class="threat-item">
                        <span class="threat-name">Unauthorized Access</span>
                        <span class="threat-count"><?php echo $threatActivity['unauthorized_access']; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Network Performance -->
        <div class="section">
            <div class="section-header">
                <div class="section-title">🌐 Network Performance</div>
            </div>
            <div class="monitoring-grid">
                <div class="monitor-card">
                    <div class="monitor-title">Bandwidth Usage</div>
                    <div class="gauge-value"><?php echo $serverStats['bandwidth_usage']; ?>%</div>
                    <div class="progress-bar-custom">
                        <div class="progress-fill-custom" style="width: <?php echo $serverStats['bandwidth_usage']; ?>%; background: <?php echo $serverStats['bandwidth_usage'] > 80 ? '#dc3545' : ($serverStats['bandwidth_usage'] > 60 ? '#ffc107' : '#28a745'); ?>;"></div>
                    </div>
                </div>
                <div class="monitor-card">
                    <div class="monitor-title">Latency</div>
                    <div class="gauge-value" style="color: <?php echo $serverStats['latency'] > 100 ? 'var(--danger)' : ($serverStats['latency'] > 50 ? 'var(--warning)' : 'var(--success)'); ?>">
                        <?php echo $serverStats['latency']; ?> ms
                    </div>
                    <div class="progress-bar-custom">
                        <div class="progress-fill-custom" style="width: <?php echo min(100, ($serverStats['latency'] / 200) * 100); ?>%; background: <?php echo $serverStats['latency'] > 100 ? '#dc3545' : ($serverStats['latency'] > 50 ? '#ffc107' : '#28a745'); ?>;"></div>
                    </div>
                </div>
                <div class="monitor-card">
                    <div class="monitor-title">Packet Loss</div>
                    <div class="gauge-value" style="color: <?php echo $serverStats['packet_loss'] > 2 ? 'var(--danger)' : ($serverStats['packet_loss'] > 1 ? 'var(--warning)' : 'var(--success)'); ?>">
                        <?php echo $serverStats['packet_loss']; ?>%
                    </div>
                    <div class="progress-bar-custom">
                        <div class="progress-fill-custom" style="width: <?php echo min(100, $serverStats['packet_loss'] * 10); ?>%; background: <?php echo $serverStats['packet_loss'] > 2 ? '#dc3545' : ($serverStats['packet_loss'] > 1 ? '#ffc107' : '#28a745'); ?>;"></div>
                    </div>
                    <div style="font-size: 0.65rem; margin-top: 0.5rem;">Target: &lt; 1%</div>
                </div>
            </div>
        </div>

        <!-- User Management Section -->
        <div class="section">
            <div class="section-header">
                <div class="section-title">👥 User Management</div>
                <div>
                    <button class="btn btn-warning btn-sm" onclick="resetAllHrPasswords()">Reset All HR Passwords</button>
                </div>
            </div>
            <div class="table-wrapper">
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Role</th>
                            <th>Email</th>
                            <th>Created At</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $usersResult->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td>
                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                        <?php echo strtoupper($user['role']); ?>
                                    </span>
                                    </span>
                                <td><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                <td><?php echo $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                                <td>
                                    <button class="btn btn-sm" onclick="openResetModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">Reset Password</button>
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
                    <strong>Database:</strong> MySQL / MariaDB<br>
                    <strong>Session Save Path:</strong> <?php echo session_save_path() ?: 'Default'; ?>
                </div>
                <div>
                    <strong>Upload Max Size:</strong> <?php echo ini_get('upload_max_filesize'); ?><br>
                    <strong>Post Max Size:</strong> <?php echo ini_get('post_max_size'); ?><br>
                    <strong>Memory Limit:</strong> <?php echo ini_get('memory_limit'); ?><br>
                    <strong>Max Execution Time:</strong> <?php echo ini_get('max_execution_time'); ?> seconds
                </div>
                <div>
                    <strong>Timezone:</strong> <?php echo date_default_timezone_get(); ?><br>
                    <strong>Current Time:</strong> <?php echo date('Y-m-d H:i:s'); ?><br>
                    <strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s', time()); ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions Section -->
        <div class="section">
            <div class="section-header">
                <div class="section-title">⚡ Quick Actions</div>
            </div>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <a href="admin/data_history.php" class="btn">📜 View Audit Log</a>
                <button class="btn btn-warning" onclick="openClearCacheModal()">🗑️ Clear System Cache</button>
            </div>
        </div>
    </div>

    <!-- Clear Cache Confirmation Modal -->
    <div id="clearCacheModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3>🗑️ Clear System Cache</h3>
                <button class="close-modal" onclick="closeClearCacheModal()">&times;</button>
            </div>
            <div style="padding: 1rem 0;">
                <p>⚠️ <strong>Warning:</strong> This action will clear the system cache.</p>
                <p style="font-size: 0.8rem; margin-top: 0.5rem; color: var(--warning);">
                    This may temporarily slow down the system while caches rebuild.
                </p>
                <p style="font-size: 0.8rem; margin-top: 0.5rem;">Are you sure you want to continue?</p>
            </div>
            <div class="modal-buttons" style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1rem;">
                <button class="btn" onclick="closeClearCacheModal()">Cancel</button>
                <button class="btn btn-warning" onclick="confirmClearCache()">Yes, Clear Cache</button>
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
                <form id="resetForm" method="POST" style="margin-top: 1rem;">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" id="resetUserId">
                    <input type="hidden" name="new_password" id="resetPassword">
                    <button type="submit" class="btn btn-warning" style="width: 100%;" id="confirmResetBtn" disabled>Confirm Reset</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        let currentUserId = null;
        let selectedPasswordValue = null;

        function openResetModal(userId, username) {
            currentUserId = userId;
            document.getElementById('resetUserId').value = userId;
            document.getElementById('resetUsername').textContent = username;
            document.getElementById('resetModal').style.display = 'flex';
            // Reset selection
            selectedPasswordValue = null;
            document.getElementById('resetPassword').value = '';
            document.getElementById('selectedPasswordDisplay').style.display = 'none';
            document.getElementById('confirmResetBtn').disabled = true;
            // Remove selected class from all options
            document.querySelectorAll('.password-option').forEach(opt => {
                opt.classList.remove('selected');
            });
        }

        function closeResetModal() {
            document.getElementById('resetModal').style.display = 'none';
        }

        function selectPassword(element, password) {
            // Remove selected class from all options
            document.querySelectorAll('.password-option').forEach(opt => {
                opt.classList.remove('selected');
            });

            // Add selected class to clicked option
            element.classList.add('selected');

            // Set the selected password
            selectedPasswordValue = password;
            document.getElementById('resetPassword').value = password;
            document.getElementById('selectedPasswordText').textContent = password;
            document.getElementById('selectedPasswordDisplay').style.display = 'block';
            document.getElementById('confirmResetBtn').disabled = false;
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

        // Clear Cache Modal Functions
        function openClearCacheModal() {
            document.getElementById('clearCacheModal').style.display = 'flex';
        }

        function closeClearCacheModal() {
            document.getElementById('clearCacheModal').style.display = 'none';
        }

        function confirmClearCache() {
            closeClearCacheModal();

            // Create a div for message display
            let messageDiv = document.getElementById('cacheMessage');
            if (!messageDiv) {
                messageDiv = document.createElement('div');
                messageDiv.id = 'cacheMessage';
                messageDiv.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 9999; min-width: 300px;';
                document.body.appendChild(messageDiv);
            }

            // Show loading message
            messageDiv.innerHTML = `<div class="alert alert-info" style="animation: slideIn 0.3s ease-out; background: rgba(56, 189, 248, 0.2); border-color: var(--accent);">⏳ Clearing cache, please wait...</div>`;

            fetch('clear_cache.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    }
                })
                .then(response => response.text())
                .then(text => {
                    try {
                        // Try to parse as JSON
                        const data = JSON.parse(text);
                        if (data.success) {
                            messageDiv.innerHTML = `<div class="alert alert-success" style="animation: slideIn 0.3s ease-out;">✓ ${data.message}</div>`;
                        } else {
                            messageDiv.innerHTML = `<div class="alert alert-error" style="animation: slideIn 0.3s ease-out;">⚠ ${data.message}${data.warnings ? '<br>' + data.warnings : ''}</div>`;
                        }
                        setTimeout(() => {
                            if (messageDiv) messageDiv.innerHTML = '';
                        }, 5000);
                    } catch (e) {
                        console.error('Parse error:', e);
                        messageDiv.innerHTML = `<div class="alert alert-error" style="animation: slideIn 0.3s ease-out;">⚠ Error clearing cache. Please check server logs.</div>`;
                        setTimeout(() => {
                            if (messageDiv) messageDiv.innerHTML = '';
                        }, 5000);
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    messageDiv.innerHTML = `<div class="alert alert-error" style="animation: slideIn 0.3s ease-out;">⚠ Network error: ${error.message}</div>`;
                    setTimeout(() => {
                        if (messageDiv) messageDiv.innerHTML = '';
                    }, 5000);
                });
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
            const modal = document.getElementById('resetModal');
            if (event.target === modal) {
                closeResetModal();
            }
            const clearCacheModal = document.getElementById('clearCacheModal');
            if (event.target === clearCacheModal) {
                closeClearCacheModal();
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            new ThemeManager();
        });

        // Function to open password change modal
        function openPasswordModal() {
            // Check if modal already exists
            if (document.getElementById('passwordModalOverlay')) {
                return;
            }

            // Create modal container
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
            // Add the onclick handler
            modalOverlay.onclick = function() {
                parent.closePasswordPopup();
            };
            // Create iframe to load the password change page
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

            // Store reference to close function
            window.closePasswordPopup = function() {
                if (modalOverlay && modalOverlay.parentNode) {
                    modalOverlay.remove();
                }
                delete window.closePasswordPopup;
            };

            // Close on Escape key
            const escapeHandler = function(e) {
                if (e.key === 'Escape') {
                    if (modalOverlay && modalOverlay.parentNode) {
                        modalOverlay.remove();
                        delete window.closePasswordPopup;
                    }
                    document.removeEventListener('keydown', escapeHandler);
                }
            };
            document.addEventListener('keydown', escapeHandler);
        }

        // Keep session alive by sending heartbeat every 5 minutes
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