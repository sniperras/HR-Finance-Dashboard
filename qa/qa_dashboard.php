<?php
require_once '../session_config.php';
require_once '../includes/auth.php';

// Check if user has access (director, md, it_admin, qa auditor, hr, manager)
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

// Determine user's department from username (director_LMT, director_BMT, etc.)
$userDepartment = null;
if (($userRole === 'director' || $userRole === 'manager') && preg_match('/_([A-Z\/\s]+)/', $username, $matches)) {
    $userDepartment = trim($matches[1]);
}

// Get selected filters
$selectedMonth = $_GET['month'] ?? date('m');
$selectedYear = $_GET['year'] ?? date('Y');
$selectedDepartment = $_GET['department'] ?? ($userDepartment ?? 'ALL');
$searchTerm = $_GET['search'] ?? '';

// Define available reports with their display names and columns
// Hidden columns are those that won't show in main table but will show in detail modal
$reports = [
    'ir_report' => [
        'name' => 'Investigation Recommendations',
        'icon' => '🔍',
        'color' => '#3B82F6',
        'display_columns' => ['Items', 'Occurrence', 'RecommendationDescription', 'ResponsibleManager', 'TargetDate', 'Status'],
        'all_columns' => ['Items', 'Occurrence', 'RecommendationDescription', 'ResponsibleManager', 'TargetDate', 'Status', 'CreatedBy', 'CreateDate', 'UpdatedBy'],
        'department_field' => 'ResponsibleManager'
    ],
    'looh_report' => [
        'name' => 'List of Open Hazards',
        'icon' => '⚠️',
        'color' => '#F59E0B',
        'display_columns' => ['Item', 'QpulseRefNo', 'EventTitle', 'EventDate', 'OwnerDir', 'TargetDate', 'Auditor', 'Status'],
        'all_columns' => ['Item', 'QpulseRefNo', 'EventTitle', 'EventDate', 'OwnerDir', 'TargetDate', 'Auditor', 'Status', 'CreatedBy', 'CreateDate', 'UpdatedBy'],
        'department_field' => 'OwnerDir'
    ],
    'loo_report' => [
        'name' => 'List of Occurrence',
        'icon' => '📋',
        'color' => '#10B981',
        'display_columns' => ['Item', 'EventDate', 'EventTitle', 'ACModel', 'ACRegNo', 'LocOfOccur', 'Description'],
        'all_columns' => ['Item', 'EventDate', 'EventTitle', 'ACModel', 'ACRegNo', 'LocOfOccur', 'ATANo', 'Description', 'QpulseReference', 'CreatedBy', 'CreateDate', 'UpdatedBy'],
        'department_field' => null
    ],
    'sr_report' => [
        'name' => 'Safety Report',
        'icon' => '🛡️',
        'color' => '#8B5CF6',
        'display_columns' => ['Number', 'AircraftType', 'Type', 'DamageDescription', 'ReportedBy', 'EventDate', 'Name', 'Status', 'Section'],
        'all_columns' => ['Number', 'AircraftType', 'Type', 'DamageDescription', 'ReportedBy', 'EventDate', 'EmailAddress', 'Name', 'Status', 'Section', 'CreatedBy', 'CreateDate', 'UpdatedBy'],
        'department_field' => 'Section'
    ],
    'srbai_report' => [
        'name' => 'SRB Action Item',
        'icon' => '✅',
        'color' => '#EC4899',
        'display_columns' => ['ItemNo', 'Agenda', 'ActionItem', 'ActionBy', 'RaisedDate', 'TargetDate', 'Status'],
        'all_columns' => ['ItemNo', 'Agenda', 'ActionItem', 'ActionBy', 'RaisedDate', 'TargetDate', 'Status', 'CreatedBy', 'CreateDate', 'UpdatedBy'],
        'department_field' => 'ActionBy'
    ],
    'listofoccurrenceform' => [
        'name' => 'List of Occurrence Form',
        'icon' => '📝',
        'color' => '#06B6D4',
        'display_columns' => ['Item', 'Event', 'Number'],
        'all_columns' => ['Item', 'Event', 'Number', 'CreatedBy', 'CreateDate', 'UpdatedBy'],
        'department_field' => null
    ]
];

// Define departments for filter
$departments = ['ALL', 'BMT', 'LMT', 'CMT', 'EMT', 'AEP', 'MSM', 'PSCM', 'QA'];
$months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$years = [2024, 2025, 2026, 2027];

// Function to fetch data for a report
function fetchReportData($conn, $reportKey, $reportConfig, $month, $year, $department, $searchTerm)
{
    $query = "SELECT * FROM $reportKey WHERE 1=1";
    $params = [];
    $types = "";

    // Add month/year filters for reports that have them
    $hasDateFilters = in_array($reportKey, ['ir_report', 'loo_report', 'sr_report', 'srbai_report', 'listofoccurrenceform']);
    if ($hasDateFilters) {
        $query .= " AND Month = ? AND Year = ?";
        $params[] = $month;
        $params[] = $year;
        $types .= "ii";
    }

    // Add department filter
    if ($department !== 'ALL' && $reportConfig['department_field']) {
        $query .= " AND {$reportConfig['department_field']} LIKE ?";
        $params[] = "%$department%";
        $types .= "s";
    }

    // Add search filter
    if ($searchTerm) {
        $searchFields = [];
        foreach ($reportConfig['display_columns'] as $col) {
            $searchFields[] = "$col LIKE ?";
        }
        if (!empty($searchFields)) {
            $query .= " AND (" . implode(" OR ", $searchFields) . ")";
            for ($i = 0; $i < count($searchFields); $i++) {
                $params[] = "%$searchTerm%";
                $types .= "s";
            }
        }
    }

    $query .= " ORDER BY id DESC LIMIT 500";

    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $data;
}

// Fetch all data
$allData = [];
foreach ($reports as $key => $config) {
    $allData[$key] = fetchReportData($conn, $key, $config, $selectedMonth, $selectedYear, $selectedDepartment, $searchTerm);
}

$conn->close();

// Get theme from cookie or default to dark
$theme = isset($_COOKIE['dashboard_theme']) ? $_COOKIE['dashboard_theme'] : 'dark';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QA Dashboard - Quality Assurance Dashboard</title>
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

        /* Dark Theme (Default) */
        body {
            --bg-color: var(--dark-bg);
            --card-bg-color: var(--card-bg);
            --text-color: var(--text-primary);
            --text-secondary-color: var(--text-secondary);
            --border-color: var(--border-light);
            --input-bg: var(--dark-bg);
        }

        /* Light Theme */
        body.light-theme {
            --bg-color: var(--light-bg);
            --card-bg-color: var(--light-card);
            --text-color: var(--text-primary-light);
            --text-secondary-color: var(--text-secondary-light);
            --border-color: var(--border-light-theme);
            --input-bg: var(--light-card);
        }

        body {
            background: var(--bg-color);
            color: var(--text-color);
            transition: background-color 0.3s, color 0.3s;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', 'Inter', system-ui, sans-serif;
        }

        .navbar {
            background: var(--card-bg-color);
            padding: 0.75rem 1.5rem;
            transition: background-color 0.3s;
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 100;
            width: 100%;
        }

        .navbar-container {
            max-width: 100%;
            margin: 0;
            padding: 0;
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
            padding: 0.4rem 0;
        }

        .navbar-menu a:hover {
            color: var(--accent);
        }

        .user-info {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .user-name {
            color: var(--accent);
            font-weight: bold;
            font-size: 0.85rem;
        }

        .role-badge {
            background: rgba(56, 189, 248, 0.15);
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: bold;
            color: var(--accent);
        }

        .theme-toggle {
            background: transparent;
            border: 1px solid var(--accent);
            color: var(--accent);
            padding: 0.3rem 0.8rem;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.3s;
        }

        .btn {
            background: var(--accent);
            color: var(--dark-bg);
            border: none;
            padding: 0.4rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.8rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .logout-btn {
            background: var(--accent);
            color: var(--dark-bg);
            border: none;
            padding: 0.4rem 1.2rem;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.8rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            min-width: 70px;
            text-align: center;
        }

        .btn:hover,
        .logout-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(56, 189, 248, 0.3);
            background: var(--accent-hover);
        }

        .container {
            max-width: 100%;
            margin: 1.5rem auto;
            padding: 0 1.5rem;
        }

        /* Dashboard Header */
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
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dashboard-header p {
            font-size: 0.8rem;
            opacity: 0.8;
            margin-top: 0.5rem;
            color: var(--text-secondary-color);
        }

        /* Filter Section */
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
            margin-bottom: 0.25rem;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 0.6rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: var(--input-bg);
            color: var(--text-color);
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2);
        }

        /* Report Sections */
        .report-section {
            background: var(--card-bg-color);
            border-radius: 16px;
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .report-header {
            padding: 1rem 1.5rem;
            background: linear-gradient(135deg, var(--card-bg-color) 0%, var(--bg-color) 100%);
            border-bottom: 2px solid;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .report-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .report-title span {
            font-size: 1.1rem;
        }

        .report-title h2 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-color);
        }

        .report-count {
            background: rgba(56, 189, 248, 0.15);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
            color: var(--accent);
        }

        .table-wrapper {
            overflow-x: auto;
            max-height: 500px;
            overflow-y: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }

        .data-table th,
        .data-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .data-table th {
            background: var(--bg-color);
            color: var(--accent);
            font-weight: bold;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .data-table td {
            color: var(--text-color);
        }

        .data-table tr:hover {
            background: rgba(56, 189, 248, 0.05);
        }

        .view-btn {
            background: var(--info);
            color: white;
            border: none;
            padding: 0.2rem 0.6rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.7rem;
            transition: all 0.2s;
        }

        .view-btn:hover {
            opacity: 0.8;
            transform: scale(1.05);
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary-color);
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            visibility: hidden;
            opacity: 0;
            transition: visibility 0.3s, opacity 0.3s;
        }

        .modal-overlay.active {
            visibility: visible;
            opacity: 1;
        }

        .modal-container {
            background: var(--card-bg-color);
            border-radius: 16px;
            width: 90%;
            max-width: 800px;
            max-height: 85vh;
            overflow: auto;
            border: 1px solid var(--border-color);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        }

        .modal-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: var(--card-bg-color);
            z-index: 1;
        }

        .modal-header h3 {
            color: var(--accent);
            font-size: 1rem;
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--text-secondary-color);
            font-size: 1.5rem;
            cursor: pointer;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .detail-table {
            width: 100%;
            border-collapse: collapse;
        }

        .detail-table tr {
            border-bottom: 1px solid var(--border-color);
        }

        .detail-table th {
            width: 30%;
            padding: 0.75rem;
            text-align: left;
            font-weight: bold;
            color: var(--accent);
            background: var(--bg-color);
        }

        .detail-table td {
            width: 70%;
            padding: 0.75rem;
            text-align: left;
            word-break: break-word;
            color: var(--text-color);
        }

        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid var(--text-secondary-color);
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 0.6s linear infinite;
            margin-right: 8px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .filter-form {
                flex-wrap: wrap;
            }

            .filter-group {
                min-width: calc(25% - 1rem);
            }
        }

        @media (max-width: 768px) {
            .filter-group {
                min-width: calc(50% - 1rem);
            }

            .navbar-container {
                flex-direction: column;
                text-align: center;
            }

            .navbar-menu {
                justify-content: center;
            }

            .report-header {
                flex-direction: column;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .filter-group {
                min-width: 100%;
            }
        }

        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-color);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--accent-hover);
        }
    </style>
</head>

<body class="<?php echo $theme === 'light' ? 'light-theme' : ''; ?>">
    <nav class="navbar">
        <div class="navbar-container">
            <a href="QA_dashboard.php" class="navbar-brand">
                QA Dashboard
            </a>
            <div class="navbar-menu">

                <?php if ($_SESSION['user_role'] == 'director'): ?>
                    <a href="QA_dashboard.php" style="color: var(--accent);">QA Dashboard</a>
                    <a href="../director/director_dashboard.php">HR Dashboard</a>

                <?php endif; ?>
                <?php if ($_SESSION['user_role'] == 'qa auditor'): ?>
                    <a href="QA_dashboard.php" style="color: var(--accent);">QA Dashboard</a>
                    <a href="qa_report_entry.php">Upload Reports</a>

                <?php endif; ?>
                <div class="user-info">
                    <span class="role-badge"><?php echo strtoupper($userRole); ?></span>
                    <button id="themeToggle" class="theme-toggle"><?php echo $theme === 'light' ? '🌙 Dark' : '☀️ Light'; ?></button>
                    <span class="user-name"><?php echo htmlspecialchars($userFullName); ?></span>
                    <a href="#" onclick="openPasswordModal(); return false;" style="cursor: pointer;">🔑 Change Password</a>
                    <a href="../logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="dashboard-header">
            <h1>
                Quality Assurance Dashboard
            </h1>
            <p>View and analyze all QA reports including Investigation Recommendations, Occurrences, Hazards, Safety Reports, and SRB Action Items</p>
        </div>

        <!-- Filter Section - Auto-submit on change -->
        <div class="filter-section">
            <form method="GET" action="" class="filter-form" id="filterForm">
                <div class="filter-group">
                    <label>Month</label>
                    <select name="month" id="monthSelect">
                        <?php foreach ($months as $idx => $m): ?>
                            <option value="<?php echo $idx + 1; ?>" <?php echo $selectedMonth == ($idx + 1) ? 'selected' : ''; ?>>
                                <?php echo $m; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Year</label>
                    <select name="year" id="yearSelect">
                        <?php foreach ($years as $y): ?>
                            <option value="<?php echo $y; ?>" <?php echo $selectedYear == $y ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Department</label>
                    <select name="department" id="departmentSelect">
                        <option value="ALL" <?php echo $selectedDepartment == 'ALL' ? 'selected' : ''; ?>>ALL Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <?php if ($dept !== 'ALL'): ?>
                                <option value="<?php echo $dept; ?>" <?php echo $selectedDepartment == $dept ? 'selected' : ''; ?>>
                                    <?php echo $dept; ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" name="search" id="searchInput" placeholder="Search across all reports..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                </div>
            </form>
        </div>

        <!-- All Report Sections -->
        <?php foreach ($reports as $key => $report): ?>
            <?php $records = $allData[$key]; ?>
            <div class="report-section">
                <div class="report-header" style="border-bottom-color: <?php echo $report['color']; ?>;">
                    <div class="report-title">
                        <span><?php echo $report['icon']; ?></span>
                        <h2><?php echo $report['name']; ?></h2>
                        <span class="report-count"><?php echo count($records); ?> records</span>
                    </div>
                </div>
                <div class="table-wrapper">
                    <?php if (empty($records)): ?>
                        <div class="empty-state">
                            No data found for <?php echo $report['name']; ?> with the selected filters
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <?php foreach ($report['display_columns'] as $col): ?>
                                        <th><?php echo ucwords(str_replace('_', ' ', $col)); ?></th>
                                    <?php endforeach; ?>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($records as $index => $row): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?>
                </div>
                <?php foreach ($report['display_columns'] as $col): ?>
                    <td title="<?php echo htmlspecialchars(substr($row[$col] ?? '', 0, 200)); ?>">
                        <?php
                                        $value = $row[$col] ?? '-';
                                        if (strlen($value) > 50) {
                                            echo htmlspecialchars(substr($value, 0, 50)) . '...';
                                        } else {
                                            echo htmlspecialchars($value);
                                        }
                        ?>
            </div>
        <?php endforeach; ?>
        <td>
            <button class="view-btn" onclick="viewDetails('<?php echo $key; ?>', <?php echo $row['id']; ?>, '<?php echo addslashes($report['name']); ?>')">View</button>
    </div>
    </tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>
</div>

<!-- Detail Modal -->
<div id="detailModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3 id="modalTitle">Record Details</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="modalContent">
                <div style="text-align: center; padding: 2rem;"><span class="loading"></span> Loading details...</div>
            </div>
        </div>
    </div>
</div>

<!-- Password Modal -->
<div id="passwordModalOverlay" class="modal-overlay">
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Change Password</h3>
            <button class="modal-close" onclick="closePasswordModal()">&times;</button>
        </div>
        <div class="modal-body">
            <iframe src="../change_password.php" style="width: 100%; border: none; min-height: 350px;"></iframe>
        </div>
    </div>
</div>

<script>
    // Theme Manager with Cookie persistence
    class ThemeManager {
        constructor() {
            this.themeKey = 'dashboard_theme';
            this.loadTheme();
            this.initToggle();
        }

        loadTheme() {
            // Check for cookie first, then localStorage, then default to dark
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
            } else {
                document.body.classList.add('light-theme');
                localStorage.setItem(this.themeKey, 'light');
                this.setCookie(this.themeKey, 'light');
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

    // Initialize theme
    new ThemeManager();

    // Auto-submit on filter changes
    const monthSelect = document.getElementById('monthSelect');
    const yearSelect = document.getElementById('yearSelect');
    const departmentSelect = document.getElementById('departmentSelect');
    let searchTimeout;

    function submitFilters() {
        const form = document.getElementById('filterForm');
        form.submit();
    }

    if (monthSelect) monthSelect.addEventListener('change', submitFilters);
    if (yearSelect) yearSelect.addEventListener('change', submitFilters);
    if (departmentSelect) departmentSelect.addEventListener('change', submitFilters);

    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(submitFilters, 500);
        });
    }

    function viewDetails(reportType, id, reportName) {
        const modal = document.getElementById('detailModal');
        const modalContent = document.getElementById('modalContent');

        modal.classList.add('active');
        modalContent.innerHTML = '<div style="text-align: center; padding: 2rem;"><span class="loading"></span> Loading details...</div>';

        fetch(`get_record_details.php?report=${reportType}&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let html = '<table class="detail-table">';
                    // Sort keys to put important ones first, then metadata
                    const sortedKeys = Object.keys(data.record).sort((a, b) => {
                        const priorityOrder = ['id', 'Item', 'Number', 'Event', 'Occurrence', 'Description', 'Status'];
                        const aIndex = priorityOrder.indexOf(a);
                        const bIndex = priorityOrder.indexOf(b);
                        if (aIndex !== -1 && bIndex !== -1) return aIndex - bIndex;
                        if (aIndex !== -1) return -1;
                        if (bIndex !== -1) return 1;
                        return a.localeCompare(b);
                    });

                    for (const key of sortedKeys) {
                        if (key !== 'id') {
                            let displayValue = data.record[key] === null || data.record[key] === '' ? '-' : String(data.record[key]);
                            // Format date fields nicely
                            if (key === 'CreateDate' || key === 'EventDate' || key === 'TargetDate' || key === 'RaisedDate') {
                                if (displayValue !== '-' && displayValue !== '0000-00-00') {
                                    const date = new Date(displayValue);
                                    if (!isNaN(date.getTime())) {
                                        displayValue = date.toLocaleDateString('en-US', {
                                            year: 'numeric',
                                            month: 'short',
                                            day: 'numeric'
                                        });
                                    }
                                }
                            }
                            if (displayValue.length > 500) {
                                displayValue = displayValue.substring(0, 500) + '...';
                            }
                            html += `
                                    <tr>
                                        <th>${formatHeader(key)}</th>
                                        <td>${escapeHtml(displayValue)}</div>
                                    </tr>
                                `;
                        }
                    }
                    html += '</table>';
                    document.getElementById('modalTitle').innerHTML = `${escapeHtml(reportName)} - Record #${id}`;
                    modalContent.innerHTML = html;
                } else {
                    modalContent.innerHTML = `<div class="empty-state">❌ ${escapeHtml(data.message)}</div>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                modalContent.innerHTML = '<div class="empty-state">❌ Error loading record details</div>';
            });
    }

    function formatHeader(str) {
        return str.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    function closeModal() {
        const modal = document.getElementById('detailModal');
        modal.classList.remove('active');
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
            closePasswordModal();
        }
    });

    // Password Modal Functions
    function openPasswordModal() {
        const modal = document.getElementById('passwordModalOverlay');
        if (modal) {
            modal.classList.add('active');
        }
    }

    function closePasswordModal() {
        const modal = document.getElementById('passwordModalOverlay');
        if (modal) {
            modal.classList.remove('active');
        }
    }

    // Session keep-alive
    function keepSessionAlive() {
        fetch('../keep_alive.php', {
                method: 'GET',
                cache: 'no-cache'
            })
            .catch(error => console.log('Session keep-alive failed:', error));
    }
    setInterval(keepSessionAlive, 5 * 60 * 1000);
</script>
</body>

</html>