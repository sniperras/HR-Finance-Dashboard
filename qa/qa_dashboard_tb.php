<?php
require_once '../session_config.php';
require_once '../includes/auth.php';

// Check if user has access
if (
    !isset($_SESSION['user_id']) ||
    !in_array($_SESSION['user_role'], ['director', 'md', 'it_admin', 'qa auditor', 'hr', 'manager'])
) {
    header('Location: ../index.php');
    exit();
}

$conn = getConnection();
$userRole = $_SESSION['user_role'];
$username = $_SESSION['username'];
$userFullName = $_SESSION['full_name'];

// Get theme from cookie or default to dark
$theme = isset($_COOKIE['dashboard_theme']) ? $_COOKIE['dashboard_theme'] : 'dark';

// Determine user's department from username
$userDepartment = null;
if (($userRole === 'director' || $userRole === 'manager') && preg_match('/_([A-Z\/\s]+)/', $username, $matches)) {
    $userDepartment = trim($matches[1]);
}

// Get selected filters
$selectedMonth = $_GET['month'] ?? date('m');
$selectedYear = $_GET['year'] ?? date('Y');
$selectedDepartment = $_GET['department'] ?? ($userDepartment ?? 'ALL');

// Define departments
$departments = ['ALL', 'AEP', 'BMT', 'CMT', 'EMT', 'LMT', 'PSCM', 'QA'];
$months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$years = [2024, 2025, 2026, 2027];

// Function to get Investigation Required Action data
function getInvestigationRequiredAction($conn, $month, $year, $department)
{
    $query = "SELECT ResponsibleManager, Status FROM ir_report WHERE Month = ? AND Year = ?";
    $params = [$month, $year];
    $types = "ii";

    if ($department !== 'ALL') {
        $query .= " AND ResponsibleManager LIKE ?";
        $params[] = "%$department%";
        $types .= "s";
    }

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $dept = extractDepartment($row['ResponsibleManager']);
        if (!isset($data[$dept])) {
            $data[$dept] = ['total' => 0, 'closed' => 0, 'overdue' => 0, 'open' => 0];
        }
        $data[$dept]['total']++;

        $status = strtolower(trim($row['Status']));
        if ($status === 'closed' || $status === 'completed') {
            $data[$dept]['closed']++;
        } elseif ($status === 'overdue' || $status === 'over due') {
            $data[$dept]['overdue']++;
        } else {
            $data[$dept]['open']++;
        }
    }
    $stmt->close();

    return $data;
}

// Function to get SRB Action Item data
function getSRBActionItem($conn, $month, $year, $department)
{
    $query = "SELECT ActionBy, Status FROM srbai_report WHERE Month = ? AND Year = ?";
    $params = [$month, $year];
    $types = "ii";

    if ($department !== 'ALL') {
        $query .= " AND ActionBy LIKE ?";
        $params[] = "%$department%";
        $types .= "s";
    }

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $dept = extractDepartment($row['ActionBy']);
        if (!isset($data[$dept])) {
            $data[$dept] = ['closed' => 0, 'overdue' => 0, 'open' => 0];
        }

        $status = strtolower(trim($row['Status']));
        if ($status === 'closed' || $status === 'completed') {
            $data[$dept]['closed']++;
        } elseif ($status === 'overdue' || $status === 'over due') {
            $data[$dept]['overdue']++;
        } else {
            $data[$dept]['open']++;
        }
    }
    $stmt->close();

    return $data;
}

// Function to get Safety Report data
function getSafetyReport($conn, $month, $year, $department)
{
    $query = "SELECT Section FROM sr_report WHERE Month = ? AND Year = ?";
    $params = [$month, $year];
    $types = "ii";

    if ($department !== 'ALL') {
        $query .= " AND Section LIKE ?";
        $params[] = "%$department%";
        $types .= "s";
    }

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $dept = extractDepartment($row['Section']);
        if (!isset($data[$dept])) {
            $data[$dept] = 0;
        }
        $data[$dept]++;
    }
    $stmt->close();

    return $data;
}

// Function to get Open Hazard Mitigation Action data
function getOpenHazardMitigation($conn, $month, $year, $department)
{
    $query = "SELECT OwnerDir, Status FROM looh_report WHERE Month = ? AND Year = ? AND Status != 'Closed' AND Status != 'Completed'";
    $params = [$month, $year];
    $types = "ii";

    if ($department !== 'ALL') {
        $query .= " AND OwnerDir LIKE ?";
        $params[] = "%$department%";
        $types .= "s";
    }

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $dept = extractDepartment($row['OwnerDir']);
        if (!isset($data[$dept])) {
            $data[$dept] = ['open' => 0, 'overdue' => 0];
        }

        $status = strtolower(trim($row['Status']));
        if ($status === 'overdue' || $status === 'over due') {
            $data[$dept]['overdue']++;
        } else {
            $data[$dept]['open']++;
        }
    }
    $stmt->close();

    return $data;
}

// Function to get List of Occurrence data - Using loo_report table with EventTitle
function getListOfOccurrence($conn, $month, $year)
{
    $query = "SELECT EventTitle, COUNT(*) as count FROM loo_report WHERE Month = ? AND Year = ? GROUP BY EventTitle ORDER BY count DESC LIMIT 10";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        if (!empty($row['EventTitle'])) {
            $data[] = ['event' => $row['EventTitle'], 'count' => $row['count']];
        }
    }
    $stmt->close();

    // If no data from loo_report, try listofoccurrenceform
    if (empty($data)) {
        $query2 = "SELECT Event, COUNT(*) as count FROM listofoccurrenceform WHERE Month = ? AND Year = ? GROUP BY Event ORDER BY count DESC LIMIT 10";
        $stmt2 = $conn->prepare($query2);
        $stmt2->bind_param("ii", $month, $year);
        $stmt2->execute();
        $result2 = $stmt2->get_result();

        while ($row = $result2->fetch_assoc()) {
            if (!empty($row['Event'])) {
                $data[] = ['event' => $row['Event'], 'count' => $row['count']];
            }
        }
        $stmt2->close();
    }

    return $data;
}

// Helper function to extract department from string
function extractDepartment($str)
{
    if (empty($str)) return 'Other';

    $depts = ['AEP', 'BMT', 'CMT', 'EMT', 'LMT', 'PSCM', 'QA'];
    foreach ($depts as $dept) {
        if (stripos($str, $dept) !== false) {
            return $dept;
        }
    }
    return 'Other';
}

// Fetch all data
$investigationData = getInvestigationRequiredAction($conn, $selectedMonth, $selectedYear, $selectedDepartment);
$srbData = getSRBActionItem($conn, $selectedMonth, $selectedYear, $selectedDepartment);
$safetyData = getSafetyReport($conn, $selectedMonth, $selectedYear, $selectedDepartment);
$hazardData = getOpenHazardMitigation($conn, $selectedMonth, $selectedYear, $selectedDepartment);
$occurrenceData = getListOfOccurrence($conn, $selectedMonth, $selectedYear);

// Calculate totals
$investigationTotals = ['total' => 0, 'closed' => 0, 'overdue' => 0, 'open' => 0];
foreach ($investigationData as $dept => $vals) {
    $investigationTotals['total'] += $vals['total'];
    $investigationTotals['closed'] += $vals['closed'];
    $investigationTotals['overdue'] += $vals['overdue'];
    $investigationTotals['open'] += $vals['open'];
}

$srbTotals = ['closed' => 0, 'overdue' => 0, 'open' => 0];
foreach ($srbData as $dept => $vals) {
    $srbTotals['closed'] += $vals['closed'];
    $srbTotals['overdue'] += $vals['overdue'];
    $srbTotals['open'] += $vals['open'];
}

$safetyTotal = array_sum($safetyData);
$hazardTotals = ['open' => 0, 'overdue' => 0];
foreach ($hazardData as $dept => $vals) {
    $hazardTotals['open'] += $vals['open'];
    $hazardTotals['overdue'] += $vals['overdue'];
}

$occurrenceTotal = array_sum(array_column($occurrenceData, 'count'));

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QA Dashboard - Summary View</title>
    <link rel="icon" type="image/png" href="../assets/images/ethiopian_logo.ico">
    <style>
        :root {
            --dark-bg: #0F172A;
            --medium-bg: #1E293B;
            --accent: #38BDF8;
            --accent-hover: #60A5FA;
            --light-bg: #F8FAFC;
            --light-card: #FFFFFF;
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
            --info: #8B5CF6;
            --border-light: #334155;
            --border-light-theme: #E2E8F0;
            --card-bg: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
            --text-primary-light: #0F172A;
            --text-secondary-light: #475569;
        }

        body {
            --bg-color: var(--dark-bg);
            --card-bg-color: var(--card-bg);
            --text-color: var(--text-primary);
            --text-secondary-color: var(--text-secondary);
            --border-color: var(--border-light);
            --input-bg: var(--dark-bg);
        }

        body.light-theme {
            --bg-color: var(--light-bg);
            --card-bg-color: var(--light-card);
            --text-color: var(--text-primary-light);
            --text-secondary-color: var(--text-secondary-light);
            --border-color: var(--border-light-theme);
            --input-bg: var(--light-card);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: var(--bg-color);
            color: var(--text-color);
            font-family: 'Segoe UI', 'Inter', system-ui, sans-serif;
            transition: background-color 0.3s, color 0.3s;
        }

        .navbar {
            background: var(--card-bg-color);
            padding: 0.75rem 2rem;
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .navbar-brand {
            font-size: 1.1rem;
            font-weight: bold;
            color: var(--accent);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .navbar-menu {
            display: flex;
            gap: 1.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .navbar-menu a {
            color: var(--text-color);
            text-decoration: none;
            font-size: 0.85rem;
            transition: color 0.2s;
        }

        .navbar-menu a:hover,
        .navbar-menu a.active {
            color: var(--accent);
        }

        .user-info {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .role-badge {
            background: rgba(56, 189, 248, 0.15);
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: bold;
            color: var(--accent);
        }

        .change-password-link {
            color: var(--accent);
            text-decoration: none;
            font-size: 0.85rem;
            transition: color 0.2s;
            padding: 0.4rem 0;
            cursor: pointer;
        }

        .change-password-link:hover {
            color: var(--accent-hover);
        }

        .theme-toggle {
            background: transparent;
            border: 1px solid var(--accent);
            color: var(--accent);
            padding: 0.3rem 0.8rem;
            cursor: pointer;
            border-radius: 6px;
        }

        .btn,
        .logout-btn {
            background: var(--accent);
            color: var(--dark-bg);
            border: none;
            padding: 0.4rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.8rem;
            text-decoration: none;
        }

        .container {
            width: 100%;
            padding: 1.5rem 2rem;
        }

        .department-badge {
            background: var(--accent);
            color: var(--dark-bg);
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: bold;
        }

        body.light-theme .department-badge {
            background: #0284C7;
            color: white;
        }

        .dashboard-header {
            background: linear-gradient(135deg, var(--card-bg-color) 0%, var(--bg-color) 100%);
            padding: 1.5rem;
            border-radius: 16px;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-color);
        }

        .dashboard-header h1 {
            font-size: 1.4rem;
            color: var(--accent);
        }

        .filter-section {
            background: var(--card-bg-color);
            padding: 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-color);
        }

        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 150px;
        }

        .filter-group label {
            font-size: 0.7rem;
            font-weight: bold;
            color: var(--accent);
            display: block;
            margin-bottom: 0.25rem;
            text-transform: uppercase;
        }

        .filter-group select {
            width: 100%;
            padding: 0.6rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: var(--input-bg);
            color: var(--text-color);
            font-size: 0.85rem;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .summary-card {
            background: var(--card-bg-color);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .card-title {
            padding: 1rem 1.5rem;
            background: linear-gradient(135deg, var(--card-bg-color) 0%, var(--bg-color) 100%);
            border-bottom: 3px solid var(--accent);
        }

        .card-title h2 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--accent);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-content {
            padding: 1rem;
            overflow-x: auto;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }

        .summary-table th,
        .summary-table td {
            padding: 0.6rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .summary-table th {
            background: var(--bg-color);
            color: var(--accent);
            font-weight: 600;
        }

        .summary-table tr:hover {
            background: rgba(56, 189, 248, 0.05);
        }

        .total-row {
            background: rgba(56, 189, 248, 0.1);
            font-weight: bold;
        }

        .total-row td {
            border-top: 2px solid var(--accent);
        }

        .full-width {
            grid-column: span 2;
        }

        /* Password Modal - Simple overlay with just the iframe */
        #passwordModalOverlay {
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(3px);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #passwordModalOverlay iframe {
            width: 100%;
            max-width: 480px;
            height: auto;
            min-height: 450px;
            border: none;
            border-radius: 16px;
            background: transparent;
        }

        /* Light theme */
        body.light-theme .navbar-menu a {
            color: #1E293B;
        }

        body.light-theme .navbar-menu a:hover,
        body.light-theme .navbar-menu a.active {
            color: #0284C7;
        }

        body.light-theme .user-name {
            color: #0284C7;
        }

        .user-name {
            color: var(--accent);
            font-weight: bold;
            font-size: 0.85rem;
        }

        body.light-theme .role-badge {
            background: rgba(2, 132, 199, 0.15);
            color: #0284C7;
        }

        body.light-theme .change-password-link {
            color: #0284C7;
        }

        body.light-theme .change-password-link:hover {
            color: #0EA5E9;
        }

        body.light-theme #passwordModalOverlay {
            background: rgba(0, 0, 0, 0.5);
        }

        @media (max-width: 768px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }

            .full-width {
                grid-column: span 1;
            }

            .container {
                padding: 1rem;
            }

            .navbar-container {
                flex-direction: column;
                text-align: center;
            }

            .navbar-menu {
                justify-content: center;
            }
        }
    </style>
</head>

<body class="<?php echo $theme === 'light' ? 'light-theme' : ''; ?>">
    <nav class="navbar">
        <div class="navbar-container">
            <a href="qa_dashboard_tb.php" class="navbar-brand">
                QA Dashboard - Summary View
            </a>
            <div class="navbar-menu">
                <?php if ($_SESSION['user_role'] == 'it_admin'): ?>
                    <a href="/it_admin_dashboard.php">IT Dashboard</a>
                    <a href="qa_dashboard_tb.php" class="active">QA Summary Dashboard</a>
                    <a href="qa_dashboard.php">QA Dashboard</a>
                    <a href="qa_report_entry.php">Upload Reports</a>
                <?php endif; ?>

                <?php if ($_SESSION['user_role'] == 'manager'): ?>
                    <a href="qa_dashboard_tb.php" class="active">QA Summary Dashboard</a>
                    <a href="qa_dashboard.php">QA Dashboard</a>
                    <a href="../director/manager_dashboard.php">HR Dashboard</a>
                <?php endif; ?>
                <?php if ($_SESSION['user_role'] == 'director'): ?>
                    <a href="qa_dashboard_tb.php" class="active">QA Summary Dashboard</a>
                    <a href="qa_dashboard.php">QA Dashboard</a>

                <?php endif; ?>
                <?php if ($_SESSION['username'] == 'director_admin'): ?>
                    <a href="../director/md_dashboard.php">HR Dashboard</a>
                <?php elseif ($_SESSION['user_role'] == 'director'): ?>
                    <a href="../director/director_dashboard.php">HR Dashboard</a>
                <?php endif; ?>

                <?php if ($_SESSION['user_role'] == 'qa auditor'): ?>
                    <a href="qa_dashboard_tb.php" class="active">QA Summary Dashboard</a>
                    <a href="qa_dashboard.php">QA Dashboard</a>
                    <a href="qa_report_entry.php">Upload Reports</a>
                <?php endif; ?>
                <div class="user-info">
                    <button id="themeToggle" class="theme-toggle"><?php echo $theme === 'light' ? '🌙 Dark' : '☀️ Light'; ?></button>
                    <span class="user-name"><?php echo htmlspecialchars($userFullName); ?></span>
                    <?php if ($_SESSION['username'] == 'director_admin'): ?>
                        <span class="department-badge"><?php echo htmlspecialchars("MD"); ?></span>
                    <?php elseif ($_SESSION['user_role'] == 'director'): ?>
                        <span class="department-badge"><?php echo strtoupper($userRole); ?></span>
                    <?php endif; ?>
                    <a href="#" onclick="openPasswordModal(); return false;" class="change-password-link">🔑 Change Password</a>
                    <a href="../logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="filter-section">
            <form method="GET" action="" class="filter-form" id="filterForm">
                <div class="filter-group">
                    <label>Month</label>
                    <select name="month" id="monthSelect">
                        <?php foreach ($months as $idx => $m): ?>
                            <option value="<?php echo $idx + 1; ?>" <?php echo $selectedMonth == ($idx + 1) ? 'selected' : ''; ?>><?php echo $m; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Year</label>
                    <select name="year" id="yearSelect">
                        <?php foreach ($years as $y): ?>
                            <option value="<?php echo $y; ?>" <?php echo $selectedYear == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Department</label>
                    <select name="department" id="departmentSelect">
                        <option value="ALL" <?php echo $selectedDepartment == 'ALL' ? 'selected' : ''; ?>>ALL Departments</option>
                        <?php foreach (['AEP', 'BMT', 'CMT', 'EMT', 'LMT', 'PSCM', 'QA'] as $dept): ?>
                            <option value="<?php echo $dept; ?>" <?php echo $selectedDepartment == $dept ? 'selected' : ''; ?>><?php echo $dept; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <div class="summary-grid">
            <!-- Investigation Required Action Table -->
            <div class="summary-card">
                <div class="card-title">
                    <h2>🔍 Investigation Required Action</h2>
                </div>
                <div class="card-content">
                    <table class="summary-table">
                        <thead>
                            <tr>
                                <th>DIR</th>
                                <th>Total Target</th>
                                <th>Closed</th>
                                <th>Overdue</th>
                                <th>Open</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $deptOrder = ['AEP', 'BMT', 'CMT', 'EMT', 'LMT', 'PSCM', 'QA'];
                            foreach ($deptOrder as $dept):
                                $vals = $investigationData[$dept] ?? ['total' => 0, 'closed' => 0, 'overdue' => 0, 'open' => 0];
                            ?>
                                <tr>
                                    <td><?php echo $dept; ?></td>
                                    <td><?php echo $vals['total']; ?></td>
                                    <td><?php echo $vals['closed']; ?></td>
                                    <td><?php echo $vals['overdue']; ?></td>
                                    <td><?php echo $vals['open']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td><strong>TOTAL</strong></td>
                                <td><strong><?php echo $investigationTotals['total']; ?></strong></td>
                                <td><strong><?php echo $investigationTotals['closed']; ?></strong></td>
                                <td><strong><?php echo $investigationTotals['overdue']; ?></strong></td>
                                <td><strong><?php echo $investigationTotals['open']; ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SRB Action Item Table -->
            <div class="summary-card">
                <div class="card-title">
                    <h2>✅ SRB Action Item</h2>
                </div>
                <div class="card-content">
                    <table class="summary-table">
                        <thead>
                            <tr>
                                <th>DIR</th>
                                <th>Closed</th>
                                <th>Over Due</th>
                                <th>Open</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deptOrder as $dept):
                                $vals = $srbData[$dept] ?? ['closed' => 0, 'overdue' => 0, 'open' => 0];
                            ?>
                                <tr>
                                    <td><?php echo $dept; ?></td>
                                    <td><?php echo $vals['closed']; ?></td>
                                    <td><?php echo $vals['overdue']; ?></td>
                                    <td><?php echo $vals['open']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td><strong>TOTAL</strong></td>
                                <td><strong><?php echo $srbTotals['closed']; ?></strong></td>
                                <td><strong><?php echo $srbTotals['overdue']; ?></strong></td>
                                <td><strong><?php echo $srbTotals['open']; ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Number of Safety Report Table -->
            <div class="summary-card">
                <div class="card-title">
                    <h2>🛡️ Number of Safety Report</h2>
                </div>
                <div class="card-content">
                    <table class="summary-table">
                        <thead>
                            </tr>
                            <th>DIR</th>
                            <th>Received</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deptOrder as $dept):
                                $count = $safetyData[$dept] ?? 0;
                            ?>
                                <tr>
                                    <td><?php echo $dept; ?></td>
                                    <td><?php echo $count; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td><strong>TOTAL</strong></td>
                                <td><strong><?php echo $safetyTotal; ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Open Hazard Mitigation Action Table -->
            <div class="summary-card">
                <div class="card-title">
                    <h2>⚠️ Open Hazard Mitigation Action</h2>
                </div>
                <div class="card-content">
                    <table class="summary-table">
                        <thead>
                            <tr>
                                <th>DIR</th>
                                <th>Open</th>
                                <th>Overdue</th>
                                <th>Total Open</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deptOrder as $dept):
                                $vals = $hazardData[$dept] ?? ['open' => 0, 'overdue' => 0];
                            ?>
                                <tr>
                                    <td><?php echo $dept; ?></td>
                                    <td><?php echo $vals['open']; ?></td>
                                    <td><?php echo $vals['overdue']; ?></td>
                                    <td><?php echo $vals['open'] + $vals['overdue']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td><strong>TOTAL</strong></td>
                                <td><strong><?php echo $hazardTotals['open']; ?></strong></td>
                                <td><strong><?php echo $hazardTotals['overdue']; ?></strong></td>
                                <td><strong><?php echo $hazardTotals['open'] + $hazardTotals['overdue']; ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- List of Occurrence Table - Full Width -->
            <div class="summary-card full-width">
                <div class="card-title">
                    <h2>📋 List of Occurrence</h2>
                </div>
                <div class="card-content">
                    <table class="summary-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Event</th>
                                <th>Number</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $itemNum = 1;
                            foreach ($occurrenceData as $occ): ?>
                                <tr>
                                    <td><?php echo $itemNum++; ?></td>
                                    <td><?php echo htmlspecialchars($occ['event']); ?></td>
                                    <td><?php echo $occ['count']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($occurrenceData)): ?>
                                <tr>
                                    <td colspan="3" style="text-align: center;">No data available</td>
                                </tr>
                            <?php endif; ?>
                            <tr class="total-row">
                                <td><strong>TOTAL</strong></td>
                                <td></td>
                                <td><strong><?php echo $occurrenceTotal; ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Password Modal - Simple, just the iframe -->
    <div id="passwordModalOverlay" class="modal-overlay" style="display: none; position: fixed; top:0; left:0; width:100%; height:100%; z-index:10000; align-items:center; justify-content:center;">
        <iframe src="../change_password.php"></iframe>
    </div>

    <script>
        class ThemeManager {
            constructor() {
                this.themeKey = 'dashboard_theme';
                this.loadTheme();
                this.initToggle();
            }

            loadTheme() {
                let savedTheme = this.getCookie(this.themeKey);
                if (!savedTheme) {
                    savedTheme = localStorage.getItem(this.themeKey);
                }
                if (savedTheme === 'light') {
                    document.body.classList.add('light-theme');
                    this.updateToggleButton(true);
                } else {
                    document.body.classList.remove('light-theme');
                    this.updateToggleButton(false);
                }
            }

            setCookie(name, value, days = 365) {
                const expires = new Date();
                expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
                document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=/`;
            }

            getCookie(name) {
                const value = `; ${document.cookie}`;
                const parts = value.split(`; ${name}=`);
                if (parts.length === 2) return parts.pop().split(';').shift();
                return null;
            }

            toggleTheme() {
                if (document.body.classList.contains('light-theme')) {
                    document.body.classList.remove('light-theme');
                    localStorage.setItem(this.themeKey, 'dark');
                    this.setCookie(this.themeKey, 'dark');
                    this.updateToggleButton(false);
                    // Reload page to apply theme from PHP
                    location.reload();
                } else {
                    document.body.classList.add('light-theme');
                    localStorage.setItem(this.themeKey, 'light');
                    this.setCookie(this.themeKey, 'light');
                    this.updateToggleButton(true);
                    // Reload page to apply theme from PHP
                    location.reload();
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

        new ThemeManager();

        function submitFilters() {
            document.getElementById('filterForm').submit();
        }

        const monthSelect = document.getElementById('monthSelect');
        const yearSelect = document.getElementById('yearSelect');
        const departmentSelect = document.getElementById('departmentSelect');

        if (monthSelect) monthSelect.addEventListener('change', submitFilters);
        if (yearSelect) yearSelect.addEventListener('change', submitFilters);
        if (departmentSelect) departmentSelect.addEventListener('change', submitFilters);

        function openPasswordModal() {
            const modal = document.getElementById('passwordModalOverlay');
            if (modal) {
                modal.style.display = 'flex';
                // Close when clicking outside the iframe
                modal.onclick = function(e) {
                    if (e.target === modal) {
                        closePasswordModal();
                    }
                };
            }
        }

        function closePasswordModal() {
            const modal = document.getElementById('passwordModalOverlay');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        // This function can be called from the iframe (change_password.php)
        function closePasswordModalFromIframe() {
            closePasswordModal();
        }

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePasswordModal();
            }
        });

        // Session keep-alive
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