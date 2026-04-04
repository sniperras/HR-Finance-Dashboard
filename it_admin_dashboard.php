<?php
require_once 'session_config.php';
require_once '../HR-Finance-Dashboard/includes/auth.php';
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
                    SUM(CASE WHEN role = 'it_admin' THEN 1 ELSE 0 END) as admin_count
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

// Handle password reset
$resetMessage = '';
$resetError = '';
$resetType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'reset_password') {
        $userId = intval($_POST['user_id']);
        $newPassword = $_POST['new_password'];

        // Hash the new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $updateStmt->bind_param("si", $hashedPassword, $userId);

        if ($updateStmt->execute()) {
            $resetMessage = "Password reset successfully for user ID: " . $userId;
            $resetType = "success";

            // Log the action
            $logStmt = $conn->prepare("INSERT INTO data_audit_log (record_id, action, old_data, new_data, performed_by, performed_at) 
                                       VALUES (?, 'password_reset', ?, ?, ?, NOW())");
            $oldData = json_encode(['password' => '********']);
            $newData = json_encode(['password' => 'reset_by_admin']);
            $logStmt->bind_param("isssi", $userId, $oldData, $newData, $_SESSION['user_id']);
            $logStmt->execute();
            $logStmt->close();
        } else {
            $resetError = "Failed to reset password. Please try again.";
            $resetType = "error";
        }
        $updateStmt->close();
    } elseif ($_POST['action'] === 'reset_all_hr') {
        // Reset all HR users to default password
        $defaultPassword = 'password123';
        $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);

        $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE role = 'hr'");
        $updateStmt->bind_param("s", $hashedPassword);

        if ($updateStmt->execute()) {
            $affectedRows = $updateStmt->affected_rows;
            $resetMessage = "Reset password for $affectedRows HR user(s) to: password123";
            $resetType = "success";
        } else {
            $resetError = "Failed to reset HR passwords.";
            $resetType = "error";
        }
        $updateStmt->close();
    }
}

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $userId = intval($_POST['user_id']);

    // Don't allow deleting own account
    if ($userId == $_SESSION['user_id']) {
        $resetError = "You cannot delete your own account.";
        $resetType = "error";
    } else {
        // Check if user has any records
        $checkStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM master_performance_data WHERE created_by = ? OR updated_by = ?");
        $checkStmt->bind_param("ii", $userId, $userId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $recordCount = $checkResult->fetch_assoc()['cnt'];
        $checkStmt->close();

        if ($recordCount > 0) {
            $resetError = "Cannot delete user. User has $recordCount record(s) in the system. Reassign or delete records first.";
            $resetType = "error";
        } else {
            $deleteStmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $deleteStmt->bind_param("i", $userId);
            if ($deleteStmt->execute()) {
                $resetMessage = "User deleted successfully.";
                $resetType = "success";
            } else {
                $resetError = "Failed to delete user.";
                $resetType = "error";
            }
            $deleteStmt->close();
        }
    }
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

        .role-admin {
            background: #10b981;
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
        body.light-theme .stat-card {
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
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="it_admin_dashboard.php" class="navbar-brand">IT Admin Dashboard</a>
            <div class="navbar-menu">
                <a href="it_admin_dashboard.php" style="color: var(--accent);">Dashboard</a>
                <a href="master_data.php">Master Data</a>
                <a href="../director/md_dashboard.php">Dashboard</a>
                <div class="user-info">
                    <button id="themeToggle" class="theme-toggle">☀️ Light</button>
                    <span class="user-name">👤 <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="#" onclick="openPasswordModal(); return false;" style="cursor: pointer;">🔑 Change Password</a>
                    <a href="../logout.php" class="btn">Logout</a>
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
                                </td>
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
                <a href="manage_indicators.php" class="btn">📊 Manage Indicators</a>
                <a href="data_history.php" class="btn">📜 View Audit Log</a>
                <button class="btn btn-warning" onclick="clearCache()">🗑️ Clear System Cache</button>
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
                    <div class="password-option" onclick="selectPassword('password123')">
                        <span>🔑 password123</span>
                        <span>Select →</span>
                    </div>
                    <div class="password-option" onclick="selectPassword('Qwer@1234')">
                        <span>🔒 Qwer@1234</span>
                        <span>Select →</span>
                    </div>
                    <div class="password-option" onclick="selectPassword('Zxcv@1234')">
                        <span>🔐 Zxcv@1234</span>
                        <span>Select →</span>
                    </div>
                </div>
                <form id="resetForm" method="POST" style="margin-top: 1rem;">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" id="resetUserId">
                    <input type="hidden" name="new_password" id="resetPassword">
                    <button type="submit" class="btn btn-warning" style="width: 100%;">Confirm Reset</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        let currentUserId = null;

        function openResetModal(userId, username) {
            currentUserId = userId;
            document.getElementById('resetUserId').value = userId;
            document.getElementById('resetUsername').textContent = username;
            document.getElementById('resetModal').style.display = 'flex';
        }

        function closeResetModal() {
            document.getElementById('resetModal').style.display = 'none';
        }

        function selectPassword(password) {
            document.getElementById('resetPassword').value = password;
            alert('Selected password: ' + password + '\nClick Confirm Reset to apply.');
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

        function clearCache() {
            if (confirm('Clear system cache? This may temporarily slow down the system while caches rebuild.')) {
                const btn = event.target;
                const originalText = btn.textContent;
                btn.textContent = '⏳ Clearing...';
                btn.disabled = true;

                fetch('clear_cache.php', {
                        method: 'POST'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('✓ ' + data.message);
                        } else {
                            alert('⚠ ' + data.message + (data.warnings ? '\nWarnings: ' + data.warnings : ''));
                        }
                        location.reload();
                    })
                    .catch(error => {
                        alert('Error clearing cache: ' + error);
                        btn.textContent = originalText;
                        btn.disabled = false;
                    });
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
            const modal = document.getElementById('resetModal');
            if (event.target === modal) {
                closeResetModal();
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
            iframe.src = '../change_password.php';
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

        // Keep session alive
        function keepSessionAlive() {
            fetch('../keep_alive.php', {
                method: 'GET',
                cache: 'no-cache'
            }).catch(error => console.log('Session keep-alive failed:', error));
        }

        setInterval(keepSessionAlive, 5 * 60 * 1000);
    </script>
</body>

</html>