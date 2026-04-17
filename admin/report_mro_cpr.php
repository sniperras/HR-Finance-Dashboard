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

// Define cost centers for each department (with actual cost center codes)
$costCenters = [
    'BMT' => [
        ['code' => 'MRODR441', 'name' => 'Dir. BMT', 'isDirector' => true],
        ['code' => 'MROMG471', 'name' => 'Mgr. A/C Structure Main', 'isDirector' => false],
        ['code' => 'MROMG451', 'name' => 'Mgr. Avionics Sys Maint', 'isDirector' => false],
        ['code' => 'MROMG461', 'name' => 'Mgr. B787/767 Mainten', 'isDirector' => false],
        ['code' => 'MROMG463', 'name' => 'Mgr. B737 Maintenance', 'isDirector' => false],
        ['code' => 'MROMG521', 'name' => 'Mgr. Cabin Maint', 'isDirector' => false],
        ['code' => 'MRORFM462', 'name' => 'Mgr. B777/A350 Mainten', 'isDirector' => false],
        ['code' => 'MROFM525', 'name' => 'Mgr. A/C Paint Svs', 'isDirector' => false],
        ['code' => 'MROMG442', 'name' => 'Mgr. Technical Supp', 'isDirector' => false],
    ],
    'LMT' => [
        ['code' => 'MRODR446', 'name' => 'Dir. LMT', 'isDirector' => true],
        ['code' => 'MROMG481', 'name' => 'Duty Manager MCC', 'isDirector' => false],
        ['code' => 'MROSU483', 'name' => 'MGR. Admin & OutstationMaint', 'isDirector' => false],
        ['code' => 'MROMG531', 'name' => 'Mgr A/C Line Maint', 'isDirector' => false],
        ['code' => 'MROMG540', 'name' => 'Mgr. General Ava. A/C Maint', 'isDirector' => false],
        ['code' => 'MROMG533', 'name' => 'MGR. Turbo Prop & Light A/C Maint', 'isDirector' => false],
        ['code' => 'MRORSP572', 'name' => 'Mgr. A/C Cabin Maint', 'isDirector' => false],
    ],
    'CMT' => [
        ['code' => 'MRODR541', 'name' => 'Dir. CMT', 'isDirector' => true],
        ['code' => 'MROMG546', 'name' => 'Mgr. Wire Kit & Harness Prod', 'isDirector' => false],
        ['code' => 'MROMG551', 'name' => 'Mgr Computerized equipment shop', 'isDirector' => false],
        ['code' => 'MROMG413', 'name' => 'Mgr. NDT, Stand. & Part Recv. Insp', 'isDirector' => false],
        ['code' => 'MROSU547', 'name' => 'Comp.Maint.Engineering support', 'isDirector' => false],
        ['code' => 'MROMG561', 'name' => 'Mgr. Mechanical Comp shops', 'isDirector' => false],
        ['code' => 'MROMG571', 'name' => 'Mgr Avionics Comp shops', 'isDirector' => false],
    ],
    'EMT' => [
        ['code' => 'MRODR581', 'name' => 'Dir. EMT', 'isDirector' => true],
        ['code' => 'MROMG414', 'name' => 'Mgr. Engine Maint. Inspection', 'isDirector' => false],
        ['code' => 'MROMG582', 'name' => 'Mgr. Technical Support', 'isDirector' => false],
        ['code' => 'MROMG583', 'name' => 'Mgr. RR/PW4000/LEAP/APU eng. Maint', 'isDirector' => false],
        ['code' => 'MROMG584', 'name' => 'Mgr. CFM56/GE90/GENX & Turbo prop. Engines', 'isDirector' => false],
        ['code' => 'MROMG585', 'name' => 'Mgr. Repair Shops', 'isDirector' => false],
    ],
    'AEP' => [
        ['code' => 'MRODR341', 'name' => 'DIR. AEP', 'isDirector' => true],
        ['code' => 'MROMG332', 'name' => 'MGR. A/C LEASE , EIS & SPECIAL PROJECTS', 'isDirector' => false],
        ['code' => 'MROMG351', 'name' => 'MGR. A/C MAINT. PROG.& TASK CARD ENGINEE', 'isDirector' => false],
        ['code' => 'MROMG371', 'name' => 'MGR. MAINT. PLNG & RECORD CONTROL', 'isDirector' => false],
        ['code' => 'MROMG381', 'name' => 'MGR. ENGINEERING QUALITY ASSURANCE', 'isDirector' => false],
        ['code' => 'MRORMG361', 'name' => 'Mgr. A/C systems Eng', 'isDirector' => false],
        ['code' => 'MROMG382', 'name' => 'MGR. A/C Design Organization', 'isDirector' => false],
    ],
    'MSM' => [
        ['code' => 'MRODR321', 'name' => 'Dir MSM', 'isDirector' => true],
        ['code' => 'MROMG322', 'name' => 'Mgr. MRO Sales and Marketing', 'isDirector' => false],
        ['code' => 'MROMG323', 'name' => 'Mgr. MRO Customer Support', 'isDirector' => false],
    ],
    'QA' => [
        ['code' => 'MROMG421', 'name' => 'Mgr .MRO Qty Ass & Sa', 'isDirector' => false],
    ],
    'PSCM' => [
        ['code' => 'MRODR431', 'name' => 'Dir. Pro. & Supp. Chain Mgt', 'isDirector' => true],
        ['code' => 'MROMG430', 'name' => 'Mgr. Grp Warr,Cont Mg', 'isDirector' => false],
        ['code' => 'MROMG433', 'name' => 'Mgr. Tactical Purchase', 'isDirector' => false],
        ['code' => 'MROMG450', 'name' => 'Mgr. Group Material Planning', 'isDirector' => false],
        ['code' => 'MROMG434', 'name' => 'Mgr.Engine Maint.Tactical Pur', 'isDirector' => false],
        ['code' => 'MROMG437', 'name' => 'Mgr. Warehouse A/C Part', 'isDirector' => false],
        ['code' => 'MROMG439', 'name' => 'Mgr. Stra.Sourcing Te', 'isDirector' => false],
        ['code' => 'MROMG542', 'name' => 'Mgr.Tac. Purchase -LMT&CMT Maint', 'isDirector' => false],
    ],
];

// Fetch existing data if a specific department is selected (not ALL)
$existingData = [];
if ($selectedDept && $selectedDept !== 'ALL' && $currentMonth && $currentYear && $selectedReport) {
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

// Check if CPR is selected
$isCPRSelected = ($selectedReport === 'CPR');
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
            border-radius: 5px;
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
            display: block;
            margin-bottom: 0.25rem;
        }

        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 0.5rem;
            border-radius: 6px;
            border: 1px solid var(--border-light);
            background: var(--dark-bg);
            color: var(--light-bg);
            font-size: 0.8rem;
        }

        /* Upload Section - only for CPR with ALL department */
        .upload-section {
            background: var(--card-bg);
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-light);
        }

        .upload-section h3 {
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
            color: var(--accent);
        }

        .upload-form {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
        }

        .upload-group {
            flex: 1;
            min-width: 250px;
        }

        .upload-group label {
            font-size: 0.7rem;
            font-weight: bold;
            color: var(--accent);
            display: block;
            margin-bottom: 0.25rem;
        }

        .upload-group input[type="file"] {
            width: 100%;
            padding: 0.5rem;
            border-radius: 6px;
            border: 1px solid var(--border-light);
            background: var(--dark-bg);
            color: var(--light-bg);
            font-size: 0.8rem;
        }

        .btn-upload {
            background: var(--warning);
            color: white;
        }

        .btn-upload:hover {
            background: #e67e22;
        }

        .btn-preview {
            background: var(--accent);
        }

        .btn-submit-upload {
            background: var(--success);
        }

        /* Preview Modal */
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
            background: var(--card-bg);
            border-radius: 16px;
            width: 90%;
            max-width: 900px;
            max-height: 85vh;
            overflow: auto;
            border: 1px solid var(--border-light);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        }

        .modal-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: var(--card-bg);
            z-index: 1;
        }

        .modal-header h3 {
            color: var(--accent);
            font-size: 1rem;
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--light-bg);
            font-size: 1.5rem;
            cursor: pointer;
        }

        .modal-body {
            padding: 1rem;
        }

        .preview-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }

        .preview-table th,
        .preview-table td {
            padding: 0.5rem;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
        }

        .preview-table th {
            background: var(--dark-bg);
            color: var(--accent);
            position: sticky;
            top: 0;
        }

        .preview-actions {
            padding: 1rem;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            border-top: 1px solid var(--border-light);
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

        .alert-warning {
            background: rgba(245, 158, 11, 0.2);
            border: 1px solid var(--warning);
            color: var(--warning);
        }

        .alert-info {
            background: rgba(56, 189, 248, 0.2);
            border: 1px solid var(--accent);
            color: var(--accent);
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
            padding: 0.2rem 0.6rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.6rem;
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
        body.light-theme .upload-section,
        body.light-theme .data-table,
        body.light-theme .no-dept-message,
        body.light-theme .modal-container {
            background: white !important;
            border-color: #E2E8F0 !important;
        }

        body.light-theme .navbar {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        body.light-theme .data-table th,
        body.light-theme .preview-table th {
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
        body.light-theme .filter-group input,
        body.light-theme .upload-group input[type="file"] {
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

        @media (max-width: 768px) {
            .filter-form,
            .upload-form {
                grid-template-columns: 1fr;
            }
            .data-table {
                font-size: 0.7rem;
            }
            .data-table input {
                width: 70px;
            }
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
                    <select name="department" id="deptSelect" onchange="this.form.submit()">
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

        <?php if ($selectedDept === 'ALL' && $isCPRSelected): ?>
            <!-- Excel Upload Section - Only for CPR with ALL department -->
            <div class="upload-section">
                <h3>📂 Upload Excel File (CPR - All Departments)</h3>
                <div class="upload-form">
                    <div class="upload-group">
                        <label>Excel File (.xlsx, .xls)</label>
                        <input type="file" id="excelFile" accept=".xlsx,.xls">
                    </div>
                    <div class="upload-group">
                        <button type="button" class="btn btn-upload" id="uploadBtn">📤 Upload & Preview</button>
                    </div>
                </div>
                <div id="uploadMessage"></div>
                <div class="sync-info" style="margin-top: 0.75rem;">
                    ℹ️ The Excel file must contain data for all departments (BMT, LMT, CMT, EMT, AEP, MSM, QA, PSCM) in the standard CPR format.
                </div>
            </div>
            <div id="message"></div>

        <?php elseif ($selectedDept && $selectedDept !== 'ALL'): ?>
            <!-- Manual Entry Form - For specific departments -->
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
        <?php elseif ($selectedDept === 'ALL' && !$isCPRSelected): ?>
            <div class="no-dept-message">
                <p>Excel upload is only available for CPR report type. Please select a specific department to enter data for <?php echo htmlspecialchars($selectedReport); ?>.</p>
            </div>
        <?php elseif ($selectedDept === ''): ?>
            <div class="no-dept-message">
                <p>Please select a department to view and enter report data.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Preview Modal -->
    <div id="previewModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h3>📋 Upload Preview - Review Data by Department</h3>
                <button class="modal-close" onclick="closePreviewModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="previewContent"></div>
            </div>
            <div class="preview-actions">
                <button type="button" class="btn" onclick="closePreviewModal()">Cancel</button>
                <button type="button" class="btn btn-submit-upload" id="confirmUploadBtn">✓ Apply to Database</button>
            </div>
        </div>
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

        let uploadedData = null;

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
            fetch('get_indicators.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.indicators) {
                        const reportSelect = document.getElementById('reportSelect');
                        const currentValue = reportSelect.value;

                        reportSelect.innerHTML = '';
                        data.indicators.forEach(indicator => {
                            const option = document.createElement('option');
                            option.value = indicator;
                            option.textContent = indicator;
                            if (indicator === currentValue) {
                                option.selected = true;
                            }
                            reportSelect.appendChild(option);
                        });

                        const msgDiv = document.getElementById('message');
                        if (msgDiv) {
                            msgDiv.innerHTML = `<div class="alert alert-success">✓ Indicators refreshed from database (${data.indicators.length} indicators)</div>`;
                            setTimeout(() => {
                                msgDiv.innerHTML = '';
                            }, 3000);
                        }

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

        function openPreviewModal(data) {
            const modal = document.getElementById('previewModal');
            const previewContent = document.getElementById('previewContent');

            if (!modal || !previewContent) return;

            // Build preview HTML grouped by department
            let html = '';
            for (const [dept, departmentsData] of Object.entries(data)) {
                html += `<h4 style="color: var(--accent); margin: 1rem 0 0.5rem 0; border-bottom: 1px solid var(--border-light); padding-bottom: 0.25rem;">📁 ${escapeHtml(dept)}</h4>`;
                html += `<table class="preview-table" style="width:100%; margin-bottom:1rem;">`;
                html += `<thead><tr><th>Cost Center</th><th>Expected</th><th>Completed</th><th>Not Completed</th><th>%</th></tr></thead><tbody>`;

                for (const [code, rowData] of Object.entries(departmentsData)) {
                    const expected = rowData.expected || 0;
                    const completed = rowData.completed || 0;
                    const notCompleted = expected - completed;
                    const percentage = expected > 0 ? ((completed / expected) * 100).toFixed(1) : 0;

                    html += `<tr>
                        <td>${escapeHtml(rowData.name || code)}</td>
                        <td>${expected}</td>
                        <td>${completed}</td>
                        <td>${notCompleted}</td>
                        <td>${percentage}%</td>
                    </tr>`;
                }
                html += `</tbody></table>`;
            }

            previewContent.innerHTML = html;
            modal.classList.add('active');
            uploadedData = data;
        }

        function closePreviewModal() {
            const modal = document.getElementById('previewModal');
            if (modal) {
                modal.classList.remove('active');
            }
            uploadedData = null;
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            new ThemeManager();

            // Set up input event listeners for manual entry forms
            const tableBody = document.getElementById('tableBody');
            if (tableBody) {
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
            }

            // Check for saved message
            const urlParams = new URLSearchParams(window.location.search);
            const saved = urlParams.get('saved');
            if (saved === '1') {
                const msgDiv = document.getElementById('message');
                if (msgDiv) {
                    msgDiv.innerHTML = `<div class="alert alert-success">✓ Report saved successfully!</div>`;
                    setTimeout(() => {
                        msgDiv.innerHTML = '';
                    }, 3000);
                }
            }

            // Excel Upload functionality - only for CPR with ALL department
            const uploadBtn = document.getElementById('uploadBtn');
            const excelFileInput = document.getElementById('excelFile');

            if (uploadBtn && excelFileInput) {
                uploadBtn.addEventListener('click', function() {
                    const file = excelFileInput.files[0];
                    if (!file) {
                        const uploadMsg = document.getElementById('uploadMessage');
                        uploadMsg.innerHTML = `<div class="alert alert-warning">Please select an Excel file first.</div>`;
                        setTimeout(() => {
                            if (uploadMsg.innerHTML.includes('Please select')) {
                                uploadMsg.innerHTML = '';
                            }
                        }, 3000);
                        return;
                    }

                    const formData = new FormData();
                    formData.append('excel_file', file);
                    formData.append('report_type', 'CPR');
                    formData.append('month', '<?php echo $currentMonth; ?>');
                    formData.append('year', '<?php echo $currentYear; ?>');

                    uploadBtn.disabled = true;
                    uploadBtn.textContent = '⏳ Uploading...';

                    fetch('upload_cpr_report.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        uploadBtn.disabled = false;
                        uploadBtn.textContent = '📤 Upload & Preview';

                        if (data.success) {
                            openPreviewModal(data.data);
                        } else {
                            const uploadMsg = document.getElementById('uploadMessage');
                            uploadMsg.innerHTML = `<div class="alert alert-error">✗ ${escapeHtml(data.message)}</div>`;
                            setTimeout(() => {
                                if (uploadMsg.innerHTML.includes('✗')) {
                                    uploadMsg.innerHTML = '';
                                }
                            }, 5000);
                        }
                    })
                    .catch(error => {
                        uploadBtn.disabled = false;
                        uploadBtn.textContent = '📤 Upload & Preview';
                        console.error('Upload error:', error);
                        const uploadMsg = document.getElementById('uploadMessage');
                        uploadMsg.innerHTML = `<div class="alert alert-error">✗ Error uploading file. Please try again.</div>`;
                        setTimeout(() => {
                            if (uploadMsg.innerHTML.includes('Error uploading')) {
                                uploadMsg.innerHTML = '';
                            }
                        }, 3000);
                    });
                });
            }

            // Confirm upload button
            const confirmUploadBtn = document.getElementById('confirmUploadBtn');
            if (confirmUploadBtn) {
                confirmUploadBtn.addEventListener('click', function() {
                    if (uploadedData) {
                        // Submit the data to save_cpr_upload.php
                        const formData = new FormData();
                        formData.append('report_type', 'CPR');
                        formData.append('report_month', '<?php echo $currentMonth; ?>');
                        formData.append('report_year', '<?php echo $currentYear; ?>');
                        formData.append('data', JSON.stringify(uploadedData));

                        confirmUploadBtn.disabled = true;
                        confirmUploadBtn.textContent = '⏳ Saving...';

                        fetch('save_cpr_upload.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            confirmUploadBtn.disabled = false;
                            confirmUploadBtn.textContent = '✓ Apply to Database';

                            if (data.success) {
                                closePreviewModal();
                                const msgDiv = document.getElementById('message');
                                if (msgDiv) {
                                    msgDiv.innerHTML = `<div class="alert alert-success">✓ ${data.message}</div>`;
                                    setTimeout(() => {
                                        msgDiv.innerHTML = '';
                                    }, 5000);
                                }
                                // Optionally reload the page to show updated data
                                setTimeout(() => {
                                    window.location.href = window.location.href.split('?')[0] + '?report=CPR&month=<?php echo $currentMonth; ?>&year=<?php echo $currentYear; ?>&department=ALL&saved=1';
                                }, 1500);
                            } else {
                                const uploadMsg = document.getElementById('uploadMessage');
                                if (uploadMsg) {
                                    uploadMsg.innerHTML = `<div class="alert alert-error">✗ ${escapeHtml(data.message)}</div>`;
                                }
                            }
                        })
                        .catch(error => {
                            confirmUploadBtn.disabled = false;
                            confirmUploadBtn.textContent = '✓ Apply to Database';
                            console.error('Save error:', error);
                            const uploadMsg = document.getElementById('uploadMessage');
                            if (uploadMsg) {
                                uploadMsg.innerHTML = `<div class="alert alert-error">✗ Error saving data. Please try again.</div>`;
                            }
                        });
                    }
                });
            }

            // Close modal on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closePreviewModal();
                }
            });
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

            modalOverlay.onclick = function(e) {
                if (e.target === modalOverlay) {
                    closePasswordPopup();
                }
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

        function closePasswordPopup() {
            if (window.closePasswordPopup) {
                window.closePasswordPopup();
            }
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