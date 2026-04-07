<?php
require_once '../session_config.php';
require_once '../includes/auth.php';
requireRole('hr');
$conn = getConnection();
$currentMonth = $_GET['month'] ?? date('m');
$currentYear = $_GET['year'] ?? date('Y');
$selectedReport = $_GET['report'] ?? '';
$selectedDept = $_GET['department'] ?? '';

// Get all available reports from performance_indicators table
$reportsQuery = "SELECT indicator_name FROM performance_indicators ORDER BY indicator_name";
$reportsResult = $conn->query($reportsQuery);
$reports = [];
while ($row = $reportsResult->fetch_assoc()) {
    $reports[$row['indicator_name']] = $row['indicator_name'];
}

// If no report selected and there are reports, select the first one
if (empty($selectedReport) && !empty($reports)) {
    $selectedReport = array_key_first($reports);
}

// Define departments
$departments = ['ALL', 'BMT', 'LMT', 'CMT', 'EMT', 'AEP', 'MSM', 'QA', 'PSCM'];

// Define cost centers for each department
$costCenters = [
    'BMT' => [
        ['code' => 'ACS', 'name' => 'Mgr. A/C Structure Maint', 'isDirector' => false],
        ['code' => 'AVS', 'name' => 'Mgr. Avionics Sys Maint', 'isDirector' => false],
        ['code' => 'B787', 'name' => 'Mgr. B787/767 Mainten', 'isDirector' => false],
        ['code' => 'B737', 'name' => 'Mgr. B737 Maintenance', 'isDirector' => false],
        ['code' => 'CAB', 'name' => 'Mgr. Cabin Maint', 'isDirector' => false],
        ['code' => 'B777', 'name' => 'Mgr. B777/A350 Mainten', 'isDirector' => false],
        ['code' => 'APS', 'name' => 'Mgr. A/C Patch Svs.', 'isDirector' => false],
        ['code' => 'TEC', 'name' => 'Mgr. Technical Supp.', 'isDirector' => false],
        ['code' => 'DIR', 'name' => 'Dir. BMT', 'isDirector' => true],
    ],
    'LMT' => [
        ['code' => 'DMM', 'name' => 'Duty Manager MCC', 'isDirector' => false],
        ['code' => 'ADM', 'name' => 'MGR. Admin & Outstation Maint', 'isDirector' => false],
        ['code' => 'ALM', 'name' => 'Mgr. A/C Line Maint.', 'isDirector' => false],
        ['code' => 'GAM', 'name' => 'Mgr. General Ava. A/C Maint.', 'isDirector' => false],
        ['code' => 'TPL', 'name' => 'MGR. Turbo Prop & Light A/C Maint', 'isDirector' => false],
        ['code' => 'ACM', 'name' => 'Mgr. A/C Cabin Maint', 'isDirector' => false],
        ['code' => 'DIR', 'name' => 'Dir. LMT', 'isDirector' => true],
    ],
    'CMT' => [
        ['code' => 'WKH', 'name' => 'Mgr. Wire Kit & Harness Prod.', 'isDirector' => false],
        ['code' => 'CES', 'name' => 'Mgr. Computerized Equipment Shop', 'isDirector' => false],
        ['code' => 'NDT', 'name' => 'Mgr. NDT, Stand. & Part Recv. Insp.', 'isDirector' => false],
        ['code' => 'MES', 'name' => 'Comp. Maint. Engineering Support', 'isDirector' => false],
        ['code' => 'MCS', 'name' => 'Mgr. Mechanical Comp Shops', 'isDirector' => false],
        ['code' => 'ACS', 'name' => 'Mgr. Avionics Comp Shops', 'isDirector' => false],
        ['code' => 'DIR', 'name' => 'Dir. CMT', 'isDirector' => true],
    ],
    'EMT' => [
        ['code' => 'EMI', 'name' => 'Mgr. Engine Maint. Inspection', 'isDirector' => false],
        ['code' => 'ETS', 'name' => 'Mgr. Technical Support', 'isDirector' => false],
        ['code' => 'RNP', 'name' => 'Mgr. RNP PW4000/LEAP/APU Eng. Maint.', 'isDirector' => false],
        ['code' => 'CFM', 'name' => 'Mgr. CFM56/GE90/GENX & Turbo Prop. Engines', 'isDirector' => false],
        ['code' => 'RSH', 'name' => 'Mgr. Repair Shops', 'isDirector' => false],
        ['code' => 'DIR', 'name' => 'Dir. EMT', 'isDirector' => true],
    ],
    'AEP' => [
        ['code' => 'ALE', 'name' => 'MGR. A/C Lease, EIS & Special Projects', 'isDirector' => false],
        ['code' => 'AMP', 'name' => 'MGR. A/C Maint. Prog. & Task Card Engineer', 'isDirector' => false],
        ['code' => 'MPR', 'name' => 'MGR. Maint. Plng. & Record Control', 'isDirector' => false],
        ['code' => 'EQA', 'name' => 'MGR. Engineering Quality Assurance', 'isDirector' => false],
        ['code' => 'ASE', 'name' => 'Mgr. A/C Systems Eng', 'isDirector' => false],
        ['code' => 'ADO', 'name' => 'MGR. A/C Design Organization', 'isDirector' => false],
        ['code' => 'DIR', 'name' => 'Dir. AEP', 'isDirector' => true],
    ],
    'MSM' => [
        ['code' => 'MSM', 'name' => 'Mgr. MRO Sales and Marketing', 'isDirector' => false],
        ['code' => 'MCS', 'name' => 'Mgr. MRO Customer Support', 'isDirector' => false],
        ['code' => 'DIR', 'name' => 'Dir. MSM', 'isDirector' => true],
    ],
    'QA' => [
        ['code' => 'QAS', 'name' => 'Mgr. MRO Qty Ass & S/a', 'isDirector' => false],
    ],
    'PSCM' => [
        ['code' => 'GWC', 'name' => 'Mgr. Grp Warp Cont Mgt', 'isDirector' => false],
        ['code' => 'TPU', 'name' => 'Mgr. Tactical Purchase', 'isDirector' => false],
        ['code' => 'MMP', 'name' => 'Mgr. MRO Material Planning', 'isDirector' => false],
        ['code' => 'EMP', 'name' => 'Mgr. Engine Maint/Tactical Pur', 'isDirector' => false],
        ['code' => 'WAP', 'name' => 'Mgr. Warehouse A/C Part', 'isDirector' => false],
        ['code' => 'EXT', 'name' => 'Extra Sourcing', 'isDirector' => false],
        ['code' => 'PLC', 'name' => 'Mgr. Purchase-LMT&CMT Maint.', 'isDirector' => false],
        ['code' => 'DIR', 'name' => 'Dir. Prop. & Supp. Chain Mgt', 'isDirector' => true],
    ]
];

// Fetch existing data if any
$existingData = [];
if ($selectedDept && $currentMonth && $currentYear && $selectedReport) {
    $query = "SELECT * FROM mro_cpr_report
              WHERE report_type = ? AND report_month = ? AND report_year = ? AND department = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("siis", $selectedReport, $currentMonth, $currentYear, $selectedDept);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $existingData[$row['cost_center_code']] = $row;
    }
    $stmt->close();
}
$conn->close();

// Helper function to check if department has director
function hasDirector($costCentersList)
{
    foreach ($costCentersList as $cc) {
        if (isset($cc['isDirector']) && $cc['isDirector'] === true) {
            return true;
        }
    }
    return false;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MRO CPR Report - Director Dashboard</title>
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

        .navbar {
            background: var(--medium-bg);
            padding: 0.5rem 0;
            transition: background-color 0.3s;
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

        .theme-toggle {
            background: transparent;
            border: 1px solid var(--accent);
            color: var(--accent);
            padding: 0.3rem 0.8rem;
            cursor: pointer;
        }

        .theme-toggle:hover {
            background: var(--accent);
            color: var(--dark-bg);
            transform: translateY(-1px);
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

        .container {
            max-width: 1400px;
            margin: 1rem auto;
            padding: 0 1rem;
        }

        .report-header {
            background: linear-gradient(135deg, var(--medium-bg) 0%, var(--dark-bg) 100%);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            border: 1px solid var(--border-light);
        }

        .filter-section {
            background: var(--card-bg);
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-light);
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .filter-group label {
            font-size: 0.7rem;
            font-weight: bold;
            color: var(--accent);
        }

        .filter-group select,
        .filter-group input {
            padding: 0.5rem;
            border-radius: 6px;
            border: 1px solid var(--border-light);
            background: var(--dark-bg);
            color: var(--light-bg);
            font-size: 0.8rem;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            margin-top: 1rem;
        }

        .data-table th,
        .data-table td {
            padding: 0.8rem 0.6rem;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
        }

        .data-table th {
            background: var(--dark-bg);
            color: var(--accent);
            font-weight: bold;
            font-size: 0.75rem;
        }

        .data-table input {
            width: 100px;
            padding: 0.4rem;
            border-radius: 4px;
            border: 1px solid var(--border-light);
            background: var(--dark-bg);
            color: var(--light-bg);
            text-align: center;
        }

        .data-table input:focus {
            outline: none;
            border-color: var(--accent);
        }

        .data-table input:disabled {
            background: var(--medium-bg);
            opacity: 0.7;
            cursor: not-allowed;
        }

        .percentage-cell {
            font-weight: bold;
            text-align: center;
        }

        .total-row {
            background: rgba(56, 189, 248, 0.15);
            font-weight: bold;
        }

        .total-row td {
            border-top: 2px solid var(--accent);
        }

        .director-row {
            background: rgba(16, 185, 129, 0.1);
        }

        .director-row td {
            border-top: 1px solid var(--success);
        }

        .save-section {
            margin-top: 1.5rem;
            text-align: center;
            padding: 1rem;
        }

        .btn-save {
            background: var(--success);
            color: white;
            padding: 0.6rem 2rem;
            font-size: 0.9rem;
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

        .progress-bar {
            width: 80px;
            height: 6px;
            background: var(--dark-bg);
            border-radius: 3px;
            overflow: hidden;
            margin-top: 4px;
        }

        .progress-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.3s;
        }

        .no-dept-message {
            text-align: center;
            padding: 3rem;
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-light);
        }

        .refresh-btn {
            background: var(--accent);
            color: white;
            border: none;
            padding: 0.3rem 0.8rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.7rem;
            margin-left: 0.5rem;
        }

        /* Light Theme */
        body.light-theme {
            background: #F8FAFC;
            color: #0F172A;
        }

        body.light-theme .navbar,
        body.light-theme .report-header,
        body.light-theme .filter-section,
        body.light-theme .data-table,
        body.light-theme .no-dept-message {
            background: white !important;
            border-color: #E2E8F0 !important;
        }

        body.light-theme .navbar {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        body.light-theme .data-table th {
            background: #F1F5F9;
            color: #0284C7;
        }

        body.light-theme .data-table td {
            background: white;
            border-bottom: 1px solid #E2E8F0;
        }

        body.light-theme .data-table input {
            background: #F1F5F9;
            color: #0F172A;
            border-color: #CBD5E1;
        }

        body.light-theme .filter-group select,
        body.light-theme .filter-group input {
            background: white;
            color: #0F172A;
            border-color: #CBD5E1;
        }

        body.light-theme .total-row {
            background: rgba(2, 132, 199, 0.08);
        }

        body.light-theme .btn {
            background: #0284C7;
            color: white;
        }

        body.light-theme .theme-toggle {
            border-color: #0284C7;
            color: #ffffff;
        }

        body.light-theme .theme-toggle:hover {
            background: #0284C7;
            color: white;
        }

        body.light-theme .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #10B981;
        }

        body.light-theme .report-header {
            background: linear-gradient(135deg, #F1F5F9 0%, #E2E8F0 100%) !important;
            color: #0F172A;
        }

        .sync-info {
            font-size: 0.7rem;
            color: var(--accent);
            margin-top: 0.5rem;
            padding: 0.5rem;
            background: rgba(56, 189, 248, 0.1);
            border-radius: 8px;
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
                <a href="../admin/report_mro_cpr.php" style="color: var(--accent);">Director Data Entry</a>
                <a href="data_history.php">History</a>
                <div class="user-info">
                    <button id="themeToggle" class="btn theme-toggle">☀️ Light</button>
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="#" onclick="openPasswordModal(); return false;" style="cursor: pointer;">🔑 Change Password</a>
                    <a href="../logout.php" class="btn">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="report-header">
            <h1 style="color: var(--accent); font-size: 1.2rem;">
                <img src="..\assets\images\online-data.png" alt="MRO Icon" style="width:20px; height:20px; vertical-align:middle; margin-right:8px;">
                MRO Performance Report
            </h1>
            <p style="font-size: 0.7rem; opacity: 0.8;">Enter expected and completed tasks to calculate completion percentage</p>
        </div>

        <div class="filter-section">
            <form method="GET" action="" class="filter-form" id="filterForm">
                <div class="filter-group">
                    <label>Report Type
                        <button type="button" class="refresh-btn" onclick="refreshReports()" title="Refresh indicators from database">⟳</button>
                    </label>
                    <select name="report" id="reportSelect" onchange="this.form.submit()">
                        <?php foreach ($reports as $key => $name): ?>
                            <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $selectedReport == $key ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Month</label>
                    <select name="month" onchange="this.form.submit()">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $currentMonth == $m ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Year</label>
                    <select name="year" onchange="this.form.submit()">
                        <?php for ($y = 2024; $y <= 2026; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $currentYear == $y ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Department</label>
                    <select name="department" onchange="this.form.submit()">
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept; ?>" <?php echo $selectedDept == $dept ? 'selected' : ''; ?>>
                                <?php echo $dept; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <?php if ($selectedDept): ?>
            <div id="message"></div>

            <form id="reportForm" method="POST" action="save_mro_report.php">
                <input type="hidden" name="report_type" value="<?php echo htmlspecialchars($selectedReport); ?>">
                <input type="hidden" name="report_month" value="<?php echo $currentMonth; ?>">
                <input type="hidden" name="report_year" value="<?php echo $currentYear; ?>">
                <input type="hidden" name="department" value="<?php echo htmlspecialchars($selectedDept); ?>">

                <div class="table-wrapper" style="overflow-x: auto;">
                    <table class="data-table" id="reportTable">
                        <thead>
                            <tr>
                                <th>Cost Center</th>
                                <th>Expected Tasks</th>
                                <th>Completed Tasks</th>
                                <th>Not Completed</th>
                                <th>Completion %</th>
                                <th>Progress</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php
                            $costCentersList = $costCenters[$selectedDept] ?? [];
                            $hasDirectorDept = hasDirector($costCentersList);
                            $directorRowIndex = -1;

                            // First pass: display all non-director rows and track director position
                            foreach ($costCentersList as $index => $cc):
                                $code = $cc['code'];
                                $name = $cc['name'];
                                $isDirector = isset($cc['isDirector']) && $cc['isDirector'] === true;

                                if ($isDirector) {
                                    $directorRowIndex = $index;
                                    continue; // Skip director row for now, will add after calculating totals
                                }

                                $expected = isset($existingData[$code]) ? $existingData[$code]['expected'] : 0;
                                $completed = isset($existingData[$code]) ? $existingData[$code]['completed'] : 0;
                                $percentage = $expected > 0 ? round(($completed / $expected) * 100, 1) : 0;
                                $notCompleted = $expected - $completed;
                                $percentageColor = $percentage >= 90 ? 'var(--success)' : ($percentage >= 70 ? 'var(--warning)' : 'var(--danger)');
                            ?>
                                <tr data-code="<?php echo $code; ?>" data-is-director="false">
                                    <td>
                                        <?php echo htmlspecialchars($name); ?>
                                        <input type="hidden" name="cost_center[<?php echo $code; ?>][code]" value="<?php echo $code; ?>">
                                        <input type="hidden" name="cost_center[<?php echo $code; ?>][name]" value="<?php echo htmlspecialchars($name); ?>">
                                    </td>
                                    <td>
                                        <input type="number" name="cost_center[<?php echo $code; ?>][expected]"
                                            class="expected-input" value="<?php echo $expected; ?>" min="0" step="1">
                                    </td>
                                    <td>
                                        <input type="number" name="cost_center[<?php echo $code; ?>][completed]"
                                            class="completed-input" value="<?php echo $completed; ?>" min="0" step="1">
                                    </td>
                                    <td class="not-completed-cell"><?php echo $notCompleted; ?></td>
                                    <td class="percentage-cell" style="color: <?php echo $percentageColor; ?>;">
                                        <span class="percentage-value"><?php echo $percentage; ?></span>%
                                    </td>
                                    <td>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $percentage; ?>%; background: <?php echo $percentageColor; ?>;"></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php
                            // Calculate totals for director row if department has director
                            if ($hasDirectorDept && $directorRowIndex >= 0):
                                $directorCC = $costCentersList[$directorRowIndex];
                                $directorCode = $directorCC['code'];
                                $directorName = $directorCC['name'];

                                // Calculate totals from all non-director rows
                                $totalExpected = 0;
                                $totalCompleted = 0;
                                foreach ($costCentersList as $cc):
                                    if (isset($cc['isDirector']) && $cc['isDirector'] === true) continue;
                                    $code = $cc['code'];
                                    $totalExpected += isset($existingData[$code]) ? $existingData[$code]['expected'] : 0;
                                    $totalCompleted += isset($existingData[$code]) ? $existingData[$code]['completed'] : 0;
                                endforeach;

                                $totalPercentage = $totalExpected > 0 ? round(($totalCompleted / $totalExpected) * 100, 1) : 0;
                                $totalNotCompleted = $totalExpected - $totalCompleted;
                                $totalColor = $totalPercentage >= 90 ? 'var(--success)' : ($totalPercentage >= 70 ? 'var(--warning)' : 'var(--danger)');
                            ?>
                                <tr data-code="<?php echo $directorCode; ?>" data-is-director="true" class="director-row">
                                    <td>
                                        <?php echo htmlspecialchars($directorName); ?>
                                        <input type="hidden" name="cost_center[<?php echo $directorCode; ?>][code]" value="<?php echo $directorCode; ?>">
                                        <input type="hidden" name="cost_center[<?php echo $directorCode; ?>][name]" value="<?php echo htmlspecialchars($directorName); ?>">
                                    </td>
                                    <td>
                                        <input type="number" name="cost_center[<?php echo $directorCode; ?>][expected]"
                                            class="expected-input director-input" value="<?php echo $totalExpected; ?>"
                                            min="0" step="1" readonly style="background: var(--medium-bg);">
                                    </td>
                                    <td>
                                        <input type="number" name="cost_center[<?php echo $directorCode; ?>][completed]"
                                            class="completed-input director-input" value="<?php echo $totalCompleted; ?>"
                                            min="0" step="1" readonly style="background: var(--medium-bg);">
                                    </td>
                                    <td class="not-completed-cell"><?php echo $totalNotCompleted; ?></td>
                                    <td class="percentage-cell" style="color: <?php echo $totalColor; ?>;">
                                        <span class="percentage-value"><?php echo $totalPercentage; ?></span>%
                                    </td>
                                    <td>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $totalPercentage; ?>%; background: <?php echo $totalColor; ?>;"></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <?php if (!$hasDirectorDept): ?>
                            <tfoot>
                                <tr class="total-row">
                                    <td><strong>TOTAL</strong></td>
                                    <td id="total-expected">0</td>
                                    <td id="total-completed">0</td>
                                    <td id="total-not-completed">0</td>
                                    <td id="total-percentage" class="percentage-cell">
                                        <span>0%</span>
                                    </td>
                                    <td>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: 0%;"></div>
                                        </div>
                                    </td>
                                </tr>
                            </tfoot>
                        <?php endif; ?>
                    </table>
                </div>

                <div class="save-section">
                    <button type="submit" class="btn btn-save">💾 Save Report</button>
                </div>
            </form>
        <?php else: ?>
            <div class="no-dept-message">
                <p>Please select a department to view and enter report data.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
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

        function calculateRow(row) {
            const expectedInput = row.querySelector('.expected-input');
            const completedInput = row.querySelector('.completed-input');
            const notCompletedCell = row.querySelector('.not-completed-cell');
            const percentageSpan = row.querySelector('.percentage-value');
            const progressFill = row.querySelector('.progress-fill');

            if (expectedInput && completedInput && !expectedInput.disabled) {
                let expected = parseInt(expectedInput.value) || 0;
                let completed = parseInt(completedInput.value) || 0;
                let notCompleted = Math.max(0, expected - completed);
                let percentage = expected > 0 ? (completed / expected) * 100 : 0;

                notCompletedCell.textContent = notCompleted;
                percentageSpan.textContent = percentage.toFixed(1);

                let color = percentage >= 90 ? '#10B981' : (percentage >= 70 ? '#F59E0B' : '#EF4444');
                percentageSpan.parentElement.style.color = color;
                progressFill.style.width = percentage + '%';
                progressFill.style.background = color;
            }
        }

        function calculateDirectorAndTotals() {
            const rows = document.querySelectorAll('#tableBody tr');
            let totalExpected = 0;
            let totalCompleted = 0;
            let directorRow = null;

            rows.forEach(row => {
                const isDirector = row.getAttribute('data-is-director') === 'true';
                const expectedInput = row.querySelector('.expected-input');
                const completedInput = row.querySelector('.completed-input');

                if (expectedInput && completedInput) {
                    if (!isDirector) {
                        totalExpected += parseInt(expectedInput.value) || 0;
                        totalCompleted += parseInt(completedInput.value) || 0;
                    } else {
                        directorRow = row;
                    }
                }
            });

            if (directorRow) {
                const directorExpected = directorRow.querySelector('.expected-input');
                const directorCompleted = directorRow.querySelector('.completed-input');
                const directorNotCompleted = directorRow.querySelector('.not-completed-cell');
                const directorPercentageSpan = directorRow.querySelector('.percentage-value');
                const directorProgressFill = directorRow.querySelector('.progress-fill');

                if (directorExpected && directorCompleted) {
                    directorExpected.value = totalExpected;
                    directorCompleted.value = totalCompleted;

                    let notCompleted = Math.max(0, totalExpected - totalCompleted);
                    let percentage = totalExpected > 0 ? (totalCompleted / totalExpected) * 100 : 0;

                    if (directorNotCompleted) directorNotCompleted.textContent = notCompleted;
                    if (directorPercentageSpan) {
                        directorPercentageSpan.textContent = percentage.toFixed(1);
                        let color = percentage >= 90 ? '#10B981' : (percentage >= 70 ? '#F59E0B' : '#EF4444');
                        directorPercentageSpan.parentElement.style.color = color;
                    }
                    if (directorProgressFill) {
                        directorProgressFill.style.width = percentage + '%';
                        let color = percentage >= 90 ? '#10B981' : (percentage >= 70 ? '#F59E0B' : '#EF4444');
                        directorProgressFill.style.background = color;
                    }
                }
            }

            const totalRowExists = document.querySelector('tfoot .total-row');
            if (totalRowExists) {
                document.getElementById('total-expected').textContent = totalExpected;
                document.getElementById('total-completed').textContent = totalCompleted;
                document.getElementById('total-not-completed').textContent = totalExpected - totalCompleted;

                const totalPercentage = totalExpected > 0 ? (totalCompleted / totalExpected) * 100 : 0;
                const totalPercentSpan = document.querySelector('#total-percentage span');
                if (totalPercentSpan) {
                    totalPercentSpan.textContent = totalPercentage.toFixed(1);
                    let color = totalPercentage >= 90 ? '#10B981' : (totalPercentage >= 70 ? '#F59E0B' : '#EF4444');
                    totalPercentSpan.style.color = color;
                }

                const totalProgressFill = document.querySelector('#total-percentage + td .progress-fill');
                if (totalProgressFill) {
                    totalProgressFill.style.width = totalPercentage + '%';
                    let color = totalPercentage >= 90 ? '#10B981' : (totalPercentage >= 70 ? '#F59E0B' : '#EF4444');
                    totalProgressFill.style.background = color;
                }
            }
        }

        function refreshReports() {
            // AJAX call to refresh the report dropdown without page reload
            fetch('get_indicators.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.indicators) {
                        const reportSelect = document.getElementById('reportSelect');
                        const currentValue = reportSelect.value;

                        // Clear existing options
                        reportSelect.innerHTML = '';

                        // Add new options
                        data.indicators.forEach(indicator => {
                            const option = document.createElement('option');
                            option.value = indicator;
                            option.textContent = indicator;
                            if (indicator === currentValue) {
                                option.selected = true;
                            }
                            reportSelect.appendChild(option);
                        });

                        // Show success message
                        const msgDiv = document.getElementById('message');
                        if (msgDiv) {
                            msgDiv.innerHTML = `<div class="alert alert-success">✓ Indicators refreshed from database (${data.indicators.length} indicators)</div>`;
                            setTimeout(() => {
                                msgDiv.innerHTML = '';
                            }, 3000);
                        }

                        // If the current selected value is no longer available, submit the form to reload
                        if (!data.indicators.includes(currentValue) && data.indicators.length > 0) {
                            reportSelect.value = data.indicators[0];
                            reportSelect.form.submit();
                        }
                    }
                })
                .catch(error => {
                    console.error('Error refreshing indicators:', error);
                    const msgDiv = document.getElementById('message');
                    if (msgDiv) {
                        msgDiv.innerHTML = `<div class="alert alert-error">✗ Failed to refresh indicators</div>`;
                        setTimeout(() => {
                            msgDiv.innerHTML = '';
                        }, 3000);
                    }
                });
        }

        document.addEventListener('DOMContentLoaded', function() {
            new ThemeManager();

            document.querySelectorAll('#tableBody tr .expected-input, #tableBody tr .completed-input').forEach(input => {
                if (!input.disabled) {
                    input.addEventListener('input', function() {
                        const row = this.closest('tr');
                        calculateRow(row);
                        calculateDirectorAndTotals();
                    });
                }
            });

            document.querySelectorAll('#tableBody tr').forEach(row => {
                if (row.getAttribute('data-is-director') !== 'true') {
                    calculateRow(row);
                }
            });
            calculateDirectorAndTotals();

            const urlParams = new URLSearchParams(window.location.search);
            const message = urlParams.get('message');
            if (message) {
                const msgDiv = document.getElementById('message');
                msgDiv.innerHTML = `<div class="alert alert-success">✓ ${decodeURIComponent(message)}</div>`;
                setTimeout(() => {
                    msgDiv.innerHTML = '';
                }, 3000);
            }
        });

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
// Add the onclick handler
modalOverlay.onclick = function() {
    parent.closePasswordPopup();
};
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