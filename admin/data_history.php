<?php
require_once '../session_config.php';
require_once '../includes/auth.php';
requireRole('hr');

$conn = getConnection();

// Get filter parameters - treat empty values as null, and 0 as empty
$filterRecord = isset($_GET['record_id']) && $_GET['record_id'] !== '' && intval($_GET['record_id']) > 0 ? intval($_GET['record_id']) : null;
$filterUser = isset($_GET['user_id']) && $_GET['user_id'] !== '' && intval($_GET['user_id']) > 0 ? intval($_GET['user_id']) : null;
$filterAction = isset($_GET['action']) && $_GET['action'] !== '' ? $_GET['action'] : null;
$filterDateFrom = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? $_GET['date_from'] : null;
$filterDateTo = isset($_GET['date_to']) && $_GET['date_to'] !== '' ? $_GET['date_to'] : null;
$filterDataType = isset($_GET['data_type']) ? $_GET['data_type'] : 'all';
$filterDepartment = isset($_GET['department']) && $_GET['department'] !== '' ? $_GET['department'] : null;
$filterIndicator = isset($_GET['indicator']) && $_GET['indicator'] !== '' ? $_GET['indicator'] : null;

// Get departments for filter dropdown
$departmentsQuery = "SELECT DISTINCT department FROM master_performance_data UNION SELECT DISTINCT department FROM mro_cpr_report ORDER BY department";
$departmentsResult = $conn->query($departmentsQuery);
$departments = [];
while ($row = $departmentsResult->fetch_assoc()) {
    if (!empty($row['department'])) {
        $departments[] = $row['department'];
    }
}

// Get indicators for filter dropdown
$indicatorsQuery = "SELECT DISTINCT indicator_name as name FROM performance_indicators UNION SELECT DISTINCT report_type as name FROM mro_cpr_report ORDER BY name";
$indicatorsResult = $conn->query($indicatorsQuery);
$indicators = [];
while ($row = $indicatorsResult->fetch_assoc()) {
    if (!empty($row['name'])) {
        $indicators[] = $row['name'];
    }
}

// Build WHERE clause parts
$whereParts = [];
$params = [];
$types = "";

if ($filterRecord !== null) {
    $whereParts[] = "al.record_id = ?";
    $params[] = $filterRecord;
    $types .= "i";
}

if ($filterUser !== null) {
    $whereParts[] = "al.performed_by = ?";
    $params[] = $filterUser;
    $types .= "i";
}

if ($filterAction !== null) {
    $whereParts[] = "al.action = ?";
    $params[] = $filterAction;
    $types .= "s";
}

if ($filterDateFrom !== null) {
    $whereParts[] = "DATE(al.performed_at) >= ?";
    $params[] = $filterDateFrom;
    $types .= "s";
}

if ($filterDateTo !== null) {
    $whereParts[] = "DATE(al.performed_at) <= ?";
    $params[] = $filterDateTo;
    $types .= "s";
}

$whereClause = !empty($whereParts) ? "WHERE " . implode(" AND ", $whereParts) : "";

// Build query based on data type filter
$query = "";

if ($filterDataType == 'master') {
    // Only Master Data - single query
    $query = "SELECT al.id, al.record_id, al.action, al.old_data, al.new_data, al.performed_at, al.performed_by,
              u.full_name as user_name,
              'master' as data_type,
              m.department,
              m.indicator_name as record_name,
              CONCAT(m.department, ' - ', m.indicator_name) as record_info
              FROM data_audit_log al
              LEFT JOIN users u ON al.performed_by = u.id
              LEFT JOIN master_performance_data m ON al.record_id = m.id";

    if ($filterDepartment) {
        $wherePartsMaster = $whereParts;
        $wherePartsMaster[] = "m.department = ?";
        $paramsMaster = $params;
        $paramsMaster[] = $filterDepartment;
        $typesMaster = $types . "s";
        $whereClauseMaster = "WHERE " . implode(" AND ", $wherePartsMaster);
        $query .= " $whereClauseMaster";
    } elseif (!empty($whereClause)) {
        $query .= " $whereClause";
    }
} elseif ($filterDataType == 'mro') {
    // Only MRO Data - single query
    $query = "SELECT al.id, al.record_id, al.action, al.old_data, al.new_data, al.performed_at, al.performed_by,
              u.full_name as user_name,
              'mro' as data_type,
              m.department,
              m.report_type as record_name,
              CONCAT(m.department, ' - ', m.cost_center_text, ' - ', m.report_type) as record_info
              FROM mro_audit_log al
              LEFT JOIN users u ON al.performed_by = u.id
              LEFT JOIN mro_cpr_report m ON al.record_id = m.id";

    if ($filterDepartment) {
        $wherePartsMro = $whereParts;
        $wherePartsMro[] = "m.department = ?";
        $paramsMro = $params;
        $paramsMro[] = $filterDepartment;
        $typesMro = $types . "s";
        $whereClauseMro = "WHERE " . implode(" AND ", $wherePartsMro);
        $query .= " $whereClauseMro";
    } elseif (!empty($whereClause)) {
        $query .= " $whereClause";
    }

    if ($filterIndicator) {
        if (strpos($query, "WHERE") !== false) {
            $query .= " AND m.report_type = ?";
        } else {
            $query .= " WHERE m.report_type = ?";
        }
        if ($filterDepartment) {
            $paramsMro[] = $filterIndicator;
            $typesMro .= "s";
        } else {
            $params[] = $filterIndicator;
            $types .= "s";
        }
    }
} else {
    // All Data Types (UNION) - Need to duplicate parameters for both parts
    $queryMaster = "SELECT al.id, al.record_id, al.action, al.old_data, al.new_data, al.performed_at, al.performed_by,
                    u.full_name as user_name,
                    'master' as data_type,
                    m.department,
                    m.indicator_name as record_name,
                    CONCAT(m.department, ' - ', m.indicator_name) as record_info
                    FROM data_audit_log al
                    LEFT JOIN users u ON al.performed_by = u.id
                    LEFT JOIN master_performance_data m ON al.record_id = m.id";

    $queryMro = "SELECT al.id, al.record_id, al.action, al.old_data, al.new_data, al.performed_at, al.performed_by,
                  u.full_name as user_name,
                  'mro' as data_type,
                  m.department,
                  m.report_type as record_name,
                  CONCAT(m.department, ' - ', m.cost_center_text, ' - ', m.report_type) as record_info
                  FROM mro_audit_log al
                  LEFT JOIN users u ON al.performed_by = u.id
                  LEFT JOIN mro_cpr_report m ON al.record_id = m.id";

    // Add filters to master query
    $masterWhereParts = $whereParts;
    $mroWhereParts = $whereParts;

    if ($filterDepartment) {
        $masterWhereParts[] = "m.department = ?";
        $mroWhereParts[] = "m.department = ?";
    }

    if ($filterIndicator && $filterDataType == 'all') {
        // For master data, filter by indicator_name
        $masterWhereParts[] = "m.indicator_name = ?";
        // For mro data, filter by report_type
        $mroWhereParts[] = "m.report_type = ?";
    }

    $masterWhereClause = !empty($masterWhereParts) ? "WHERE " . implode(" AND ", $masterWhereParts) : "";
    $mroWhereClause = !empty($mroWhereParts) ? "WHERE " . implode(" AND ", $mroWhereParts) : "";

    $queryMaster .= " $masterWhereClause";
    $queryMro .= " $mroWhereClause";

    $query = "($queryMaster) UNION ALL ($queryMro)";

    // Build parameters for UNION query
    $unionParams = [];
    $unionTypes = "";

    // Master query parameters
    foreach ($params as $p) {
        $unionParams[] = $p;
        $unionTypes .= $types[strlen($unionTypes)];
    }
    if ($filterDepartment) {
        $unionParams[] = $filterDepartment;
        $unionTypes .= "s";
    }
    if ($filterIndicator && $filterDataType == 'all') {
        $unionParams[] = $filterIndicator;
        $unionTypes .= "s";
    }

    // Mro query parameters (duplicate)
    foreach ($params as $p) {
        $unionParams[] = $p;
        $unionTypes .= $types[strlen($unionTypes) - strlen($types) + (strlen($unionTypes) % strlen($types))];
    }
    if ($filterDepartment) {
        $unionParams[] = $filterDepartment;
        $unionTypes .= "s";
    }
    if ($filterIndicator && $filterDataType == 'all') {
        $unionParams[] = $filterIndicator;
        $unionTypes .= "s";
    }

    $params = $unionParams;
    $types = $unionTypes;
}

// Add ORDER BY and LIMIT
$query .= " ORDER BY performed_at DESC LIMIT 100";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result();

// Get users for filter dropdown
// Get users for filter dropdown - HR only
$usersQuery = "SELECT id, full_name FROM users WHERE role = 'hr' ORDER BY full_name";
$users = $conn->query($usersQuery);

// Get statistics for master data
$statsMasterResult = $conn->query("SELECT 
                COUNT(*) as total_actions,
                COUNT(DISTINCT record_id) as records_affected,
                COUNT(DISTINCT performed_by) as users_involved,
                MIN(performed_at) as first_action,
                MAX(performed_at) as last_action
               FROM data_audit_log");

if ($statsMasterResult && $statsMasterResult->num_rows > 0) {
    $statsMaster = $statsMasterResult->fetch_assoc();
} else {
    $statsMaster = ['total_actions' => 0, 'records_affected' => 0, 'users_involved' => 0, 'first_action' => date('Y-m-d'), 'last_action' => date('Y-m-d')];
}

// Get statistics for MRO data
$statsMroResult = $conn->query("SELECT 
                COUNT(*) as total_actions,
                COUNT(DISTINCT record_id) as records_affected,
                COUNT(DISTINCT performed_by) as users_involved,
                MIN(performed_at) as first_action,
                MAX(performed_at) as last_action
               FROM mro_audit_log");

if ($statsMroResult && $statsMroResult->num_rows > 0) {
    $statsMro = $statsMroResult->fetch_assoc();
} else {
    $statsMro = ['total_actions' => 0, 'records_affected' => 0, 'users_involved' => 0, 'first_action' => date('Y-m-d'), 'last_action' => date('Y-m-d')];
}

$conn->close();

// Helper function to format JSON data nicely
function formatChangeData($oldData, $newData)
{
    $old = json_decode($oldData, true);
    $new = json_decode($newData, true);

    if (!$old && !$new) return '<span style="color: #888;">No data</span>';

    $html = '<table style="width:100%; font-size:0.7rem; border-collapse:collapse;">';

    if ($old) {
        $html .= '<tr><td style="padding:2px; color:#dc3545;"><strong>OLD:</strong></td><td style="padding:2px;">';
        foreach ($old as $key => $value) {
            $displayValue = is_array($value) ? json_encode($value) : $value;
            $html .= "<span style='color:#dc3545;'>$key:</span> " . htmlspecialchars($displayValue) . "<br>";
        }
        $html .= '</td></tr>';
    }

    if ($new) {
        $html .= '<tr><td style="padding:2px; color:#28a745;"><strong>NEW:</strong></td><td style="padding:2px;">';
        foreach ($new as $key => $value) {
            $displayValue = is_array($value) ? json_encode($value) : $value;
            $html .= "<span style='color:#28a745;'>$key:</span> " . htmlspecialchars($displayValue) . "<br>";
        }
        $html .= '</td></tr>';
    }

    $html .= '</table>';
    return $html;
}

// Helper function for action badge
function getActionBadge($action)
{
    switch ($action) {
        case 'insert':
            return '<span class="badge badge-insert">➕ Insert</span>';
        case 'update':
            return '<span class="badge badge-update">✏️ Update</span>';
        case 'verify':
            return '<span class="badge badge-verify">✓ Verify</span>';
        case 'reject':
            return '<span class="badge badge-reject">✗ Reject</span>';
        default:
            return '<span class="badge">' . $action . '</span>';
    }
}

// Helper function for data type badge
function getDataTypeBadge($dataType)
{
    if ($dataType == 'master') {
        return '<span class="badge badge-master">📊 Master Data</span>';
    } else {
        return '<span class="badge badge-mro">🔧 MRO Report</span>';
    }
}

$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['message']);
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data History - Audit Trail</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/ethiopian_logo.ico">
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

        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .history-header {
            background: linear-gradient(135deg, var(--medium-bg) 0%, var(--dark-bg) 100%);
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            transition: background 0.3s;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .stat-card {
            background: var(--dark-bg);
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
            transition: background 0.3s;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--accent);
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--light-bg);
            opacity: 0.8;
            margin-top: 0.25rem;
        }

        .filter-section {
            background: var(--medium-bg);
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            transition: background 0.3s;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .filter-group label {
            font-size: 0.7rem;
            color: var(--accent);
            font-weight: bold;
        }

        .filter-group select,
        .filter-group input {
            padding: 0.5rem;
            border-radius: 5px;
            border: 1px solid var(--border-light);
            background: var(--dark-bg);
            color: var(--light-bg);
            font-size: 0.8rem;
            transition: all 0.3s;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: var(--accent);
        }

        .table-wrapper {
            overflow-x: auto;
            background: var(--medium-bg);
            border-radius: 15px;
            padding: 1rem;
            transition: background 0.3s;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }

        .history-table th,
        .history-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
            vertical-align: top;
        }

        .history-table th {
            background: var(--dark-bg);
            color: var(--accent);
            font-weight: bold;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .history-table tr:hover {
            background: rgba(56, 189, 248, 0.05);
        }

        .badge {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: bold;
        }

        .badge-insert {
            background: #28a745;
            color: white;
        }

        .badge-update {
            background: #ffc107;
            color: #222;
        }

        .badge-verify {
            background: #17a2b8;
            color: white;
        }

        .badge-reject {
            background: #dc3545;
            color: white;
        }

        .badge-master {
            background: #3b82f6;
            color: white;
        }

        .badge-mro {
            background: #f59e0b;
            color: white;
        }

        .record-link {
            color: var(--accent);
            text-decoration: none;
            font-weight: bold;
        }

        .record-link:hover {
            text-decoration: underline;
        }

        .clear-filter {
            background: var(--accent);
            color: var(--dark-bg);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.7rem;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }

        .clear-filter:hover {
            transform: translateY(-1px);
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .history-table {
                font-size: 0.65rem;
            }

            .history-table th,
            .history-table td {
                padding: 0.5rem;
            }
        }

        .timestamp {
            font-family: monospace;
            font-size: 0.7rem;
        }

        .change-details {
            max-width: 300px;
        }

        /* Theme Toggle Button */
        .theme-toggle {
            background: transparent;
            border: 1px solid var(--accent);
            color: var(--accent);
            padding: 0.35rem 0.9rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s;
        }

        .theme-toggle:hover {
            background: var(--accent);
            color: var(--dark-bg);
        }

        /* Navbar styles */
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

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(56, 189, 248, 0.3);
            background: var(--accent-hover);
        }

        /* Light Theme */
        body.light-theme {
            background: #F8FAFC;
            color: #0F172A;
        }

        body.light-theme .navbar,
        body.light-theme .history-header,
        body.light-theme .filter-section,
        body.light-theme .table-wrapper {
            background: white !important;
            border-color: #E2E8F0 !important;
        }

        body.light-theme .navbar {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        body.light-theme .history-header {
            background: linear-gradient(135deg, #F1F5F9 0%, #E2E8F0 100%) !important;
        }

        body.light-theme .stat-card {
            background: #F1F5F9;
        }

        body.light-theme .history-table th {
            background: #F1F5F9;
            color: #0284C7;
        }

        body.light-theme .history-table td {
            border-bottom-color: #E2E8F0;
        }

        body.light-theme .filter-group select,
        body.light-theme .filter-group input {
            background: white;
            color: #0F172A;
            border-color: #CBD5E1;
        }

        body.light-theme .btn {
            background: #0284C7;
            color: white;
        }

        body.light-theme .theme-toggle {
            border-color: #0284C7;
            color: #0284C7;
        }

        body.light-theme .theme-toggle:hover {
            background: #0284C7;
            color: white;
        }

        body.light-theme .record-link {
            color: #0284C7;
        }

        body.light-theme .badge-master {
            background: #3b82f6;
        }

        body.light-theme .badge-mro {
            background: #f59e0b;
        }

        body.light-theme .clear-filter {
            background: #0284C7;
            color: white;
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="master_data.php" class="navbar-brand">HR & Finance Dashboard</a>
            <div class="navbar-menu">
                <a href="master_data.php">Master Data</a>
                <a href="../director/md_dashboard.php">Dashboard</a>
                <a href="../admin/report_mro_cpr.php">Director Data Entry</a>
                <a href="data_history.php" style="color: var(--accent);">History</a>
                <div class="user-info">
                    <button id="themeToggle" class="theme-toggle">☀️ Light</button>
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="#" onclick="openPasswordModal(); return false;" style="cursor: pointer;">🔑 Change Password</a>
                    <a href="../logout.php" class="btn">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="history-header">
            <h2>📜 Data History & Audit Trail</h2>
            <p>Complete history of all changes made to Master Data and MRO CPR Reports</p>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($statsMaster['total_actions']); ?></div>
                    <div class="stat-label">Master Data Actions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($statsMro['total_actions']); ?></div>
                    <div class="stat-label">MRO Report Actions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($statsMaster['records_affected'] + $statsMro['records_affected']); ?></div>
                    <div class="stat-label">Total Records Affected</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php
                                                $firstAction = min($statsMaster['first_action'], $statsMro['first_action']);
                                                echo date('M d, Y', strtotime($firstAction));
                                                ?></div>
                    <div class="stat-label">First Action</div>
                </div>
            </div>
        </div>

        <div class="filter-section">
            <form method="GET" action="" class="filter-form">
                <div class="filter-group">
                    <label>Data Type</label>
                    <select name="data_type" onchange="this.form.submit()">
                        <option value="all" <?php echo $filterDataType == 'all' ? 'selected' : ''; ?>>All Data Types</option>
                        <option value="master" <?php echo $filterDataType == 'master' ? 'selected' : ''; ?>>📊 Master Data</option>
                        <option value="mro" <?php echo $filterDataType == 'mro' ? 'selected' : ''; ?>>🔧 MRO Reports</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Department</label>
                    <select name="department">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $filterDepartment == $dept ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Indicator / Report Type</label>
                    <select name="indicator">
                        <option value="">All Indicators</option>
                        <?php foreach ($indicators as $indicator): ?>
                            <option value="<?php echo htmlspecialchars($indicator); ?>" <?php echo $filterIndicator == $indicator ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(strlen($indicator) > 40 ? substr($indicator, 0, 37) . '...' : $indicator); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Record ID</label>
                    <input type="number" name="record_id" placeholder="Filter by Record ID" value="<?php echo htmlspecialchars($filterRecord); ?>">
                </div>

                <div class="filter-group">
                    <label>User</label>
                    <select name="user_id">
                        <option value="">All Users</option>
                        <?php while ($user = $users->fetch_assoc()): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $filterUser == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['full_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Action</label>
                    <select name="action">
                        <option value="">All Actions</option>
                        <option value="insert" <?php echo $filterAction == 'insert' ? 'selected' : ''; ?>>Insert</option>
                        <option value="update" <?php echo $filterAction == 'update' ? 'selected' : ''; ?>>Update</option>
                        <option value="verify" <?php echo $filterAction == 'verify' ? 'selected' : ''; ?>>Verify</option>
                        <option value="reject" <?php echo $filterAction == 'reject' ? 'selected' : ''; ?>>Reject</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Date From</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($filterDateFrom); ?>">
                </div>

                <div class="filter-group">
                    <label>Date To</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($filterDateTo); ?>">
                </div>

                <div class="filter-group">
                    <button type="submit" class="btn">🔍 Apply Filters</button>
                    <a href="data_history.php" class="clear-filter">🗑️ Clear Filters</a>
                </div>
            </form>
        </div>

        <div class="table-wrapper">
            <table class="history-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date & Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Data Type</th>
                        <th>Department</th>
                        <th>Record</th>
                        <th>Changes</th>
                </thead>
                <tbody>
                    <?php if ($logs && $logs->num_rows > 0): ?>
                        <?php while ($log = $logs->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $log['id']; ?></td>
                                <td class="timestamp"><?php echo date('Y-m-d H:i:s', strtotime($log['performed_at'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($log['user_name']); ?></strong></td>
                                <td><?php echo getActionBadge($log['action']); ?></td>
                                <td><?php echo getDataTypeBadge($log['data_type']); ?></td>
                                <td>
                                    <?php if ($log['department']): ?>
                                        <span class="badge" style="background: var(--accent); color: var(--dark-bg);">
                                            <?php echo htmlspecialchars($log['department']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #888;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($log['record_id']): ?>
                                        <?php if ($log['data_type'] == 'master'): ?>
                                            <a href="master_data.php?month=<?php echo date('Y-m'); ?>" class="record-link">
                                                <?php echo htmlspecialchars($log['record_name'] ?? 'Record #' . $log['record_id']); ?>
                                            </a>
                                        <?php else: ?>
                                            <a href="../admin/report_mro_cpr.php?report=<?php echo urlencode($log['record_name'] ?? ''); ?>&month=<?php echo date('m'); ?>&year=<?php echo date('Y'); ?>&department=<?php echo urlencode($log['department'] ?? ''); ?>" class="record-link">
                                                <?php echo htmlspecialchars($log['record_name'] ?? 'Record #' . $log['record_id']); ?>
                                            </a>
                                        <?php endif; ?>
                                        <div style="font-size: 0.6rem; color: #888; margin-top: 3px;">
                                            ID: <?php echo $log['record_id']; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #888;">Record Deleted</span>
                                    <?php endif; ?>
                                </td>
                                <td class="change-details">
                                    <?php echo formatChangeData($log['old_data'], $log['new_data']); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 2rem;">
                                <div style="color: #888;">No history records found</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top: 1rem; text-align: right;">
            <small style="color: #888;">Showing last 100 records. Use filters to narrow down results.</small>
        </div>
    </div>

    <script>
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

        // Auto-submit form when filters change (except for text inputs)
        document.querySelectorAll('select, input[type="date"]').forEach(element => {
            element.addEventListener('change', function() {
                this.closest('form').submit();
            });
        });

        // Add loading indicator on form submit
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.innerHTML = '⏳ Loading...';
                    submitBtn.disabled = true;
                }
            });
        });

        // Initialize theme manager
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

        // Keep session alive by sending heartbeat every 5 minutes
        function keepSessionAlive() {
            fetch('/HRandMDDash/keep_alive.php', {
                method: 'GET',
                cache: 'no-cache'
            }).catch(error => console.log('Session keep-alive failed:', error));
        }

        setInterval(keepSessionAlive, 5 * 60 * 1000);
    </script>
</body>

</html>