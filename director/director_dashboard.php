<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../session_config.php';
require_once '../includes/auth.php';
requireRole('director');

$conn = getConnection();
$currentMonth = $_GET['month'] ?? date('Y-m');
$dataMonth = $currentMonth . '-01';
$currentYear = date('Y', strtotime($dataMonth));
$currentMonthNum = date('m', strtotime($dataMonth));

// Get logged-in user's department from username
$username = $_SESSION['username'];
$userDept = '';

// Extract department from username (format: director_BMT, director_LMT, etc.)
if (preg_match('/director_([A-Z\/\s]+)/', $username, $matches)) {
    $userDept = trim($matches[1]);
}

// If no department found, redirect to login
if (empty($userDept)) {
    header('Location: ../index.php');
    exit();
}

// List of report types that should use cost_center_text from database instead of mapping
$useDatabaseTextReports = ['Crew Meeting Minutes Submission'];

// Define cost centers for the department (for non-Crew Meeting reports)
$costCenters = [
    'BMT' => [
        'ACS' => 'Mgr. A/C Structure Maint',
        'AVS' => 'Mgr. Avionics Sys Maint',
        'B787' => 'Mgr. B787/767 Mainten',
        'B737' => 'Mgr. B737 Maintenance',
        'CAB' => 'Mgr. Cabin Maint',
        'B777' => 'Mgr. B777/A350 Mainten',
        'APS' => 'Mgr. A/C Patch Svs.',
        'TEC' => 'Mgr. Technical Supp.',
        'DIR' => 'Dir. BMT'
    ],
    'LMT' => [
        'DMM' => 'Duty Manager MCC',
        'ADM' => 'MGR. Admin & Outstation Maint',
        'ALM' => 'Mgr. A/C Line Maint.',
        'GAM' => 'Mgr. General Ava. A/C Maint.',
        'TPL' => 'MGR. Turbo Prop & Light A/C Maint',
        'ACM' => 'Mgr. A/C Cabin Maint',
        'DIR' => 'Dir. LMT'
    ],
    'CMT' => [
        'WKH' => 'Mgr. Wire Kit & Harness Prod.',
        'CES' => 'Mgr. Computerized Equipment Shop',
        'NDT' => 'Mgr. NDT, Stand. & Part Recv. Insp.',
        'MES' => 'Comp. Maint. Engineering Support',
        'MCS' => 'Mgr. Mechanical Comp Shops',
        'ACS' => 'Mgr. Avionics Comp Shops',
        'DIR' => 'Dir. CMT'
    ],
    'EMT' => [
        'EMI' => 'Mgr. Engine Maint. Inspection',
        'ETS' => 'Mgr. Technical Support',
        'RNP' => 'Mgr. RNP PW4000/LEAP/APU Eng. Maint.',
        'CFM' => 'Mgr. CFM56/GE90/GENX & Turbo Prop. Engines',
        'RSH' => 'Mgr. Repair Shops',
        'DIR' => 'Dir. EMT'
    ],
    'AEP' => [
        'ALE' => 'MGR. A/C Lease, EIS & Special Projects',
        'AMP' => 'MGR. A/C Maint. Prog. & Task Card Engineer',
        'MPR' => 'MGR. Maint. Plng. & Record Control',
        'EQA' => 'MGR. Engineering Quality Assurance',
        'ASE' => 'Mgr. A/C Systems Eng',
        'ADO' => 'MGR. A/C Design Organization',
        'DIR' => 'Dir. AEP'
    ],
    'MSM' => [
        'MSM' => 'Mgr. MRO Sales and Marketing',
        'MCS' => 'Mgr. MRO Customer Support',
        'DIR' => 'Dir. MSM'
    ],
    'QA' => [
        'QAS' => 'Mgr. MRO Qty Ass & S/a',
        'DIR' => 'Dir. QA'
    ],
    'PSCM' => [
        'GWC' => 'Mgr. Grp Warp Cont Mgt',
        'TPU' => 'Mgr. Tactical Purchase',
        'MMP' => 'Mgr. MRO Material Planning',
        'EMP' => 'Mgr. Engine Maint/Tactical Pur',
        'WAP' => 'Mgr. Warehouse A/C Part',
        'EXT' => 'Extra Sourcing',
        'PLC' => 'Mgr. Purchase-LMT&CMT Maint.',
        'DIR' => 'Dir. Prop. & Supp. Chain Mgt'
    ],
    'MRO HR' => [
        'HR' => 'Mgr. Human Resources',
        'DIR' => 'Dir. MRO HR'
    ],
    'MD/DIV.' => [
        'FIN' => 'Mgr. Finance',
        'DIR' => 'Dir. MD/DIV.'
    ],
    'Remainder' => [
        'REM' => 'Remainder',
        'DIR' => 'Dir. Remainder'
    ]
];

// Get ALL indicators from performance_indicators table first (as master list)
$indicatorsQuery = "SELECT DISTINCT TRIM(indicator_name) as indicator_name FROM performance_indicators ORDER BY created_at ASC";
$indicatorsResult = $conn->query($indicatorsQuery);

$indicators = [];
$indicatorOrder = []; // To maintain order

if ($indicatorsResult && $indicatorsResult->num_rows > 0) {
    while ($row = $indicatorsResult->fetch_assoc()) {
        $indicatorName = trim($row['indicator_name']);
        $indicators[$indicatorName] = $indicatorName;
        $indicatorOrder[] = $indicatorName;
    }
}

// Also include any indicators from mro_cpr_report that might not be in performance_indicators
$mroIndicatorsQuery = "SELECT DISTINCT report_type FROM mro_cpr_report WHERE department = ? AND report_type NOT IN (SELECT indicator_name FROM performance_indicators) ORDER BY report_type";
$stmt = $conn->prepare($mroIndicatorsQuery);
$stmt->bind_param("s", $userDept);
$stmt->execute();
$mroIndicatorsResult = $stmt->get_result();

if ($mroIndicatorsResult && $mroIndicatorsResult->num_rows > 0) {
    while ($row = $mroIndicatorsResult->fetch_assoc()) {
        $indicatorName = trim($row['report_type']);
        if (!isset($indicators[$indicatorName])) {
            $indicators[$indicatorName] = $indicatorName;
            $indicatorOrder[] = $indicatorName;
        }
    }
}
$stmt->close();

// If no indicators found at all, create a default message
if (empty($indicators)) {
    $indicators['No Indicators Available'] = 'No Indicators Available';
    $indicatorOrder = ['No Indicators Available'];
}

// Fetch ALL data from mro_cpr_report for this department (all cost centers)
$reportData = [];      // For card display (will store AVERAGE of all percentages)
$managerData = [];     // For detail modal
$allPercentages = [];  // Track all percentages per indicator
$dbTextData = [];      // Store cost_center_text for Crew Meeting reports

$query = "SELECT report_type, cost_center_code, cost_center_text, expected, completed, percentage 
          FROM mro_cpr_report 
          WHERE report_month = ? AND report_year = ? AND department = ? AND verification_status = 'verified'";
$stmt = $conn->prepare($query);
$stmt->bind_param("iis", $currentMonthNum, $currentYear, $userDept);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $reportType = $row['report_type'];
    $costCenter = $row['cost_center_code'];
    $costCenterText = $row['cost_center_text'];
    $expected = (int)$row['expected'];
    $completed = (int)$row['completed'];
    $percentage = round((float)$row['percentage'], 1);

    // Store manager-level data for detail view
    if (!isset($managerData[$reportType])) {
        $managerData[$reportType] = [];
    }

    // Determine display name - use database text for Crew Meeting reports, otherwise use mapping
    $useDbText = in_array($reportType, $useDatabaseTextReports);
    if ($useDbText && !empty($costCenterText)) {
        $costCenterName = $costCenterText;
        // Store the text for later use
        if (!isset($dbTextData[$reportType])) {
            $dbTextData[$reportType] = [];
        }
        $dbTextData[$reportType][$costCenter] = $costCenterText;
    } else {
        $costCenterName = $costCenters[$userDept][$costCenter] ?? $costCenter;
    }

    $managerData[$reportType][$costCenter] = [
        'expected' => $expected,
        'completed' => $completed,
        'percentage' => $percentage,
        'name' => $costCenterName
    ];

    // Track percentages for this indicator (for calculating average)
    if (!isset($allPercentages[$reportType])) {
        $allPercentages[$reportType] = [];
    }
    $allPercentages[$reportType][] = $percentage;
}
$stmt->close();

// Calculate AVERAGE percentage for each indicator (sum of all percentages / number of rows)
foreach ($indicators as $indicatorKey => $indicatorDisplay) {
    if (isset($allPercentages[$indicatorKey]) && !empty($allPercentages[$indicatorKey])) {
        $totalPercentage = array_sum($allPercentages[$indicatorKey]);
        $rowCount = count($allPercentages[$indicatorKey]);
        $reportData[$indicatorKey] = round($totalPercentage / $rowCount, 1);
    } else {
        $reportData[$indicatorKey] = 0;
    }
}

// Also get data from master_performance_data for this department (fallback for missing data)
$masterQuery = "SELECT indicator_name, percentage_achievement 
                FROM master_performance_data 
                WHERE data_month = ? AND department = ? AND verification_status = 'verified'";
$masterStmt = $conn->prepare($masterQuery);
$masterStmt->bind_param("ss", $dataMonth, $userDept);
$masterStmt->execute();
$masterResult = $masterStmt->get_result();

while ($row = $masterResult->fetch_assoc()) {
    $indicatorName = $row['indicator_name'];
    $percentage = round((float)$row['percentage_achievement'], 1);

    // Only use master data if we have no data for this indicator
    if (!isset($reportData[$indicatorName]) || $reportData[$indicatorName] == 0) {
        $reportData[$indicatorName] = $percentage;
    }
}
$masterStmt->close();

// Set default values for ALL indicators (even those with no data)
foreach ($indicators as $indicatorKey => $indicatorDisplay) {
    if (!isset($reportData[$indicatorKey])) {
        $reportData[$indicatorKey] = 0;
    }
}

$conn->close();

// Calculate average overall percentage (for the header)
$totalPercentage = 0;
$count = 0;
foreach ($reportData as $percentage) {
    $totalPercentage += $percentage;
    $count++;
}
$averagePercentage = $count > 0 ? round($totalPercentage / $count, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo htmlspecialchars($userDept); ?> Department - Performance Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="icon" type="image/png" href="../assets/images/ethiopian_logo.ico">
    <style>
        /* Keep all existing styles - same as before */
        :root {
            --dark-bg: #0F172A;
            --medium-bg: #1E293B;
            --accent: #38BDF8;
            --accent-hover: #60A5FA;
            --light-bg: #F1F5F9;
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
            --card-bg: #1E293B;
            --border-light: #334155;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--dark-bg);
            color: var(--text-primary);
            transition: background-color 0.3s, color 0.3s;
            overflow-x: hidden;
        }

        body.fullscreen-mode {
            overflow: hidden;
        }

        body.fullscreen-mode .navbar {
            display: none !important;
        }

        body.fullscreen-mode .floating-controls {
            display: flex !important;
        }

        body.fullscreen-mode .dashboard-header {
            display: none !important;
        }

        body.fullscreen-mode .info-banner {
            display: none !important;
        }

        body.fullscreen-mode .container {
            height: 100vh;
            overflow-y: auto;
            scroll-behavior: smooth;
            margin: 0;
            padding: 20px;
            padding-top: 70px;
            max-width: 100%;
            width: 100%;
        }

        .floating-controls {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(30, 41, 59, 0.95);
            backdrop-filter: blur(10px);
            padding: 10px 20px;
            display: none;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            border-bottom: 1px solid var(--border-light);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .floating-controls .dashboard-title {
            font-size: 1rem;
            font-weight: bold;
            color: var(--accent);
        }

        .floating-controls .overall-mini {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-hover) 100%);
            color: var(--dark-bg);
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .floating-controls .overall-mini span {
            font-size: 1.2rem;
            font-weight: bold;
        }

        .floating-controls .exit-fullscreen-btn {
            background: var(--accent);
            color: var(--dark-bg);
            border: none;
            padding: 5px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.8rem;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .floating-controls .exit-fullscreen-btn img {
            width: 16px;
            height: 16px;
        }

        .floating-controls .exit-fullscreen-btn:hover {
            transform: translateY(-1px);
            opacity: 0.9;
        }

        .navbar {
            background: var(--medium-bg);
            padding: 0.5rem 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid var(--border-light);
        }

        .navbar-container {
            max-width: 100%;
            width: 100%;
            margin: 0;
            padding: 0 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .navbar-brand {
            font-size: 0.95rem;
            font-weight: bold;
            color: var(--accent);
            text-decoration: none;
        }

        .navbar-menu {
            display: flex;
            gap: 0.8rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .navbar-menu a {
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.75rem;
            transition: color 0.2s;
            padding: 0.25rem 0.4rem;
            border-radius: 5px;
        }

        .navbar-menu a:hover {
            color: var(--accent);
            background: rgba(56, 189, 248, 0.1);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .user-name {
            color: var(--accent);
            font-weight: bold;
            font-size: 0.75rem;
        }

        .department-badge {
            background: var(--accent);
            color: var(--dark-bg);
            padding: 0.15rem 0.5rem;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: bold;
        }

        .btn {
            background: var(--accent);
            color: var(--dark-bg);
            border: none;
            padding: 0.25rem 0.7rem;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.65rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(56, 189, 248, 0.3);
            background: var(--accent-hover);
        }

        .theme-toggle {
            background: transparent;
            border: 1px solid var(--accent);
            color: var(--accent);
            padding: 0.25rem 0.7rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.65rem;
            transition: all 0.3s;
        }

        .theme-toggle:hover {
            background: var(--accent);
            color: var(--dark-bg);
        }

        .fullscreen-header-btn {
            background: transparent;
            border: 1px solid var(--accent);
            color: var(--accent);
            padding: 0.25rem 0.7rem;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.65rem;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .fullscreen-header-btn img {
            width: 14px;
            height: 14px;
        }

        .fullscreen-header-btn:hover {
            background: var(--accent);
            color: var(--dark-bg);
        }

        .fullscreen-header-btn:hover img {
            filter: brightness(0);
        }

        .container {
            width: 100%;
            max-width: 100%;
            margin: 0;
            padding: 0.75rem 1rem;
        }

        .dashboard-header {
            background: linear-gradient(135deg, var(--medium-bg) 0%, var(--dark-bg) 100%);
            padding: 0.6rem 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            border: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            width: 100%;
        }

        .dashboard-header .header-left h1 {
            color: var(--accent);
            margin-bottom: 0.2rem;
            font-size: 1rem;
        }

        .dashboard-header .month-selector {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            margin-top: 0.3rem;
        }

        .dashboard-header .month-selector button {
            background: var(--accent);
            color: var(--dark-bg);
            border: none;
            padding: 0.2rem 0.6rem;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.7rem;
        }

        .dashboard-header .month-selector h3 {
            color: var(--text-primary);
            margin: 0;
            font-size: 0.85rem;
        }

        .header-right {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .info-banner {
            background: rgba(56, 189, 248, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
            font-size: 0.7rem;
            color: var(--accent);
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        @media (min-width: 1920px) {
            .metrics-grid {
                grid-template-columns: repeat(5, 1fr);
                gap: 1.2rem;
            }
        }

        @media (min-width: 1600px) and (max-width: 1919px) {
            .metrics-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 1.2rem;
            }
        }

        @media (min-width: 1200px) and (max-width: 1599px) {
            .metrics-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 1rem;
            }
        }

        @media (min-width: 768px) and (max-width: 1199px) {
            .metrics-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.9rem;
            }
        }

        @media (max-width: 767px) {
            .metrics-grid {
                grid-template-columns: 1fr;
                gap: 0.8rem;
            }
        }

        .metric-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 0.8rem;
            border: 1px solid var(--border-light);
            transition: all 0.2s;
            text-align: center;
            cursor: pointer;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border-color: var(--accent);
        }

        .metric-title {
            font-size: 0.85rem;
            font-weight: bold;
            color: var(--accent);
            margin-bottom: 0.5rem;
            padding-bottom: 0.3rem;
            border-bottom: 1px solid var(--border-light);
            word-break: break-word;
        }

        .chart-container {
            position: relative;
            width: 100%;
            max-width: 140px;
            margin: 0.5rem auto;
        }

        .chart-container canvas {
            width: 100% !important;
            height: auto !important;
            max-height: 140px;
        }

        .percentage-display {
            text-align: center;
            margin-top: 0.5rem;
            font-size: 1.2rem;
            font-weight: bold;
        }

        .actual-target {
            display: flex;
            justify-content: space-around;
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 1px solid var(--border-light);
            font-size: 0.7rem;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--medium-bg);
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            border: 1px solid var(--border-light);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid var(--accent);
            position: sticky;
            top: 0;
            background: var(--medium-bg);
        }

        .modal-header h3 {
            color: var(--accent);
            font-size: 0.9rem;
        }

        .close-modal {
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 1.5rem;
            cursor: pointer;
        }

        .close-modal:hover {
            color: var(--danger);
        }

        .detail-table {
            width: 100%;
            border-collapse: collapse;
        }

        .detail-table th,
        .detail-table td {
            padding: 0.6rem;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
            font-size: 0.7rem;
        }

        .detail-table th {
            background: var(--dark-bg);
            color: var(--accent);
            font-weight: bold;
            position: sticky;
            top: 60px;
        }

        .detail-table tr:hover {
            background: rgba(56, 189, 248, 0.05);
        }

        .director-row {
            background: rgba(16, 185, 129, 0.1);
            font-weight: bold;
        }

        .progress-bar-modal {
            width: 80px;
            height: 6px;
            background: var(--dark-bg);
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-fill-modal {
            height: 100%;
            border-radius: 3px;
        }

        .no-data {
            text-align: center;
            padding: 2rem;
            color: var(--text-primary);
            opacity: 0.7;
        }

        .refresh-btn {
            background: var(--success);
            color: white;
            border: none;
            padding: 0.2rem 0.6rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.65rem;
            margin-left: 0.5rem;
        }

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

        body.light-theme .navbar {
            background: white;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
        }

        body.light-theme .dashboard-header {
            background: linear-gradient(135deg, #F8FAFC 0%, #F1F5F9 100%);
        }

        body.light-theme .floating-controls {
            background: rgba(255, 255, 255, 0.95);
            border-bottom-color: #E2E8F0;
        }

        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }

        ::-webkit-scrollbar-track {
            background: var(--dark-bg);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 3px;
        }

        @media (max-width: 768px) {
            .metrics-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-header {
                flex-direction: column;
                align-items: stretch;
            }

            .header-right {
                justify-content: flex-end;
            }

            .floating-controls {
                flex-wrap: wrap;
                gap: 10px;
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <div class="floating-controls" id="floatingControls">
        <div class="dashboard-title"><?php echo htmlspecialchars($userDept); ?> Department Dashboard</div>
        <div class="overall-mini">
            Overall: <span id="floatingOverall"><?php echo $averagePercentage; ?>%</span>
        </div>
        <button class="exit-fullscreen-btn" id="exitFullscreenBtn">
            <img src="../assets/images/expand.png" alt="exit" style="transform: rotate(180deg);"> Exit Full Screen
        </button>
    </div>

    <nav class="navbar">
        <div class="navbar-container">
            <a href="director_dashboard.php" class="navbar-brand">MRO Dashboard</a>
            <div class="navbar-menu">
                <a href="../qa/qa_dashboard_tb.php">QA Summary Dashboard</a>
                <a href="../qa/qa_dashboard.php">QA Dashboard</a>
                <a href="director_dashboard.php" style="color: var(--accent);">HR Dashboard</a>

                <div class="user-info">
                    <button id="themeToggle" class="theme-toggle">☀️ Light</button>
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <span class="department-badge"><?php echo htmlspecialchars($userDept); ?></span>
                    <a href="#" onclick="openPasswordModal(); return false;" style="cursor: pointer;">🔑 Change Password</a>
                    <a href="../logout.php" class="btn">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container" id="mainContainer">
        <div class="dashboard-header">
            <div class="header-left">
                <h1><?php echo htmlspecialchars($userDept); ?> Department - Performance Dashboard</h1>
                <div class="month-selector">
                    <button onclick="changeMonth('prev')">← Prev</button>
                    <h3>📅 <?php echo date('F Y', strtotime($dataMonth)); ?></h3>
                    <button onclick="changeMonth('next')">Next →</button>
                    <button class="refresh-btn" onclick="refreshDashboard()">⟳ Refresh</button>
                </div>
            </div>

            <div class="header-right">
                <button id="fullscreenHeaderBtn" class="fullscreen-header-btn">
                    <img src="../assets/images/expand.png" alt="expand"> Full Screen
                </button>
            </div>
        </div>

        <div class="info-banner">
            <span>Department Performance Overview (Average of all Manager + Director percentages)</span>
            <span style="display: inline-block; margin-left: 10px; font-size: 0.6rem;">Click on any card to view detailed manager breakdown</span>
        </div>

        <div class="metrics-grid" id="metricsGrid">
            <?php
            $chartIndex = 0;
            foreach ($indicators as $indicatorKey => $indicatorDisplay):
                $percentage = $reportData[$indicatorKey] ?? 0;
                $percentageColor = $percentage >= 90 ? 'var(--success)' : ($percentage >= 70 ? 'var(--warning)' : 'var(--danger)');
                $chartId = 'chart-' . $chartIndex;
                $chartIndex++;
            ?>
                <div class="metric-card" onclick="showDetail('<?php echo htmlspecialchars(addslashes($indicatorKey)); ?>', '<?php echo htmlspecialchars(addslashes($indicatorDisplay)); ?>')">
                    <div class="metric-title"><?php echo htmlspecialchars($indicatorDisplay); ?></div>
                    <div class="chart-container">
                        <canvas id="<?php echo $chartId; ?>" width="120" height="120"></canvas>
                    </div>
                    <div class="percentage-display" style="color: <?php echo $percentageColor; ?>;">
                        <?php echo $percentage; ?>%
                    </div>
                    <div class="actual-target">
                        <span>Target: 100%</span>
                        <span>Avg of All: <?php echo $percentage; ?>%</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="detailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Department Details</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div id="modalBody" style="padding: 1rem;">
                <div class="spinner">Loading...</div>
            </div>
        </div>
    </div>

    <script>
        // Data passed from PHP
        const managerData = <?php echo json_encode($managerData); ?>;
        const reportData = <?php echo json_encode($reportData); ?>;
        const costCenters = <?php echo json_encode($costCenters[$userDept] ?? []); ?>;
        const userDept = '<?php echo $userDept; ?>';
        const currentMonth = '<?php echo $currentMonth; ?>';
        const averagePercentage = <?php echo $averagePercentage; ?>;

        // For Crew Meeting reports, we have the actual text from database
        const dbTextData = <?php echo json_encode($dbTextData ?? []); ?>;
        const useDatabaseTextReports = <?php echo json_encode($useDatabaseTextReports); ?>;

        let chartInstances = {};
        let autoScrollInterval = null;
        let isScrolling = false;

        function getColor(percentage) {
            if (percentage >= 90) return '#10B981';
            if (percentage >= 70) return '#F59E0B';
            return '#EF4444';
        }

        function createGaugeChart(canvasId, percentage) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return null;

            const remaining = Math.max(0, 100 - percentage);
            const color = getColor(percentage);

            return new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Achieved', 'Remaining'],
                    datasets: [{
                        data: [percentage, remaining],
                        backgroundColor: [color, 'rgba(51, 65, 85, 0.5)'],
                        borderWidth: 0,
                        hoverOffset: 6,
                        cutout: '70%'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.label}: ${context.raw}%`;
                                }
                            }
                        }
                    }
                }
            });
        }

        function toggleFullscreen() {
            const body = document.body;
            const container = document.getElementById('mainContainer');

            if (!body.classList.contains('fullscreen-mode')) {
                body.classList.add('fullscreen-mode');
                startAutoScroll();
                if (document.documentElement.requestFullscreen) {
                    document.documentElement.requestFullscreen().catch(err => {
                        console.log(`Fullscreen error: ${err.message}`);
                    });
                }
            } else {
                exitFullscreen();
            }
        }

        function exitFullscreen() {
            const body = document.body;
            body.classList.remove('fullscreen-mode');
            stopAutoScroll();
            if (document.exitFullscreen) {
                document.exitFullscreen();
            }
        }

        function startAutoScroll() {
            const container = document.getElementById('mainContainer');
            if (!container) return;
            stopAutoScroll();
            let isPaused = false;
            let scrollTimeout = null;

            function performScroll() {
                if (isScrolling || isPaused) return;
                isScrolling = true;
                const maxScroll = container.scrollHeight - container.clientHeight;
                const currentScroll = container.scrollTop;
                const isAtBottom = currentScroll >= maxScroll - 10;

                if (isAtBottom && maxScroll > 0) {
                    isPaused = true;
                    isScrolling = false;
                    if (scrollTimeout) clearTimeout(scrollTimeout);
                    scrollTimeout = setTimeout(() => {
                        container.scrollTo({
                            top: 0,
                            behavior: 'smooth'
                        });
                        setTimeout(() => {
                            isPaused = false;
                            isScrolling = false;
                        }, 2000);
                    }, 2000);
                    return;
                }
                if (currentScroll <= 10 && isPaused) {
                    isScrolling = false;
                    return;
                }
                let targetScroll = currentScroll + 2;
                container.scrollTo({
                    top: targetScroll,
                    behavior: 'smooth'
                });
                setTimeout(() => {
                    isScrolling = false;
                }, 50);
            }
            autoScrollInterval = setInterval(performScroll, 50);
        }

        function stopAutoScroll() {
            if (autoScrollInterval) {
                clearInterval(autoScrollInterval);
                autoScrollInterval = null;
            }
        }

        document.addEventListener('fullscreenchange', function() {
            if (!document.fullscreenElement) {
                const body = document.body;
                if (body.classList.contains('fullscreen-mode')) {
                    body.classList.remove('fullscreen-mode');
                    stopAutoScroll();
                }
            }
        });

        function showDetail(indicatorKey, indicatorDisplay) {
            const modal = document.getElementById('detailModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalBody = document.getElementById('modalBody');

            modalTitle.innerHTML = `${indicatorDisplay} - ${userDept} Department Details`;

            const data = managerData[indicatorKey] || {};
            const isCrewMeeting = useDatabaseTextReports.includes(indicatorKey);

            // Collect ALL rows (both managers and director)
            let allRows = [];
            let directorData = null;

            for (const [code, manager] of Object.entries(costCenters)) {
                const record = data[code] || {
                    expected: 0,
                    completed: 0,
                    percentage: 0,
                    name: manager
                };

                // For Crew Meeting reports, try to use the actual database text if available
                let displayName = manager;
                if (isCrewMeeting && dbTextData[indicatorKey] && dbTextData[indicatorKey][code]) {
                    displayName = dbTextData[indicatorKey][code];
                } else if (record.name) {
                    displayName = record.name;
                }

                if (code === 'DIR') {
                    directorData = {
                        code: code,
                        name: displayName,
                        expected: record.expected,
                        completed: record.completed,
                        percentage: record.percentage
                    };
                } else {
                    allRows.push({
                        code: code,
                        name: displayName,
                        expected: record.expected,
                        completed: record.completed,
                        percentage: record.percentage
                    });
                }
            }

            // Add director to allRows if it exists
            if (directorData) {
                allRows.push(directorData);
            }

            // Calculate totals
            let totalExpected = 0;
            let totalCompleted = 0;
            let totalPercentageSum = 0;
            let rowCount = allRows.length;

            for (const row of allRows) {
                totalExpected += row.expected;
                totalCompleted += row.completed;
                totalPercentageSum += row.percentage;
            }

            const averageOfPercentages = rowCount > 0 ? (totalPercentageSum / rowCount) : 0;
            const totalNotCompleted = totalExpected - totalCompleted;
            const totalColor = getColor(averageOfPercentages);

            let tableHtml = `
                <table class="detail-table">
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
                    <tbody>
            `;

            // Display all rows (managers + director)
            for (const row of allRows) {
                const notCompleted = row.expected - row.completed;
                const percentageColor = getColor(row.percentage);
                const isDirector = (row.code === 'DIR');
                const rowClass = isDirector ? 'director-row' : '';

                tableHtml += `
                    <tr class="${rowClass}">
                        <td>${escapeHtml(row.name)}</div>
                        <td>${row.expected}</div>
                        <td>${row.completed}</div>
                        <td>${notCompleted}</div>
                        <td style="color: ${percentageColor}; font-weight: bold;">${row.percentage}%</div>
                        <td>
                            <div class="progress-bar-modal">
                                <div class="progress-fill-modal" style="width: ${row.percentage}%; background: ${percentageColor};"></div>
                            </div>
                        </div>
                    </tr>
                `;
            }

            // Add TOTAL row
            tableHtml += `
                <tr style="background: rgba(56, 189, 248, 0.2); font-weight: bold;">
                    <td><strong>TOTAL (Avg of %)</strong></div>
                    <td><strong>${totalExpected}</strong></div>
                    <td><strong>${totalCompleted}</strong></div>
                    <td><strong>${totalNotCompleted}</strong></div>
                    <td style="color: ${totalColor}; font-weight: bold; font-size: 1.1rem;">
                        <strong>${averageOfPercentages.toFixed(1)}%</strong>
                    </div>
                    <td>
                        <div class="progress-bar-modal">
                            <div class="progress-fill-modal" style="width: ${averageOfPercentages}%; background: ${totalColor};"></div>
                        </div>
                    </div>
                </tr>
            `;

            tableHtml += `</tbody></div>`;
            modalBody.innerHTML = tableHtml;
            modal.style.display = 'flex';
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function closeModal() {
            document.getElementById('detailModal').style.display = 'none';
        }

        function changeMonth(direction) {
            let currentUrl = new URL(window.location.href);
            let currentMonthParam = currentUrl.searchParams.get('month') || '<?php echo $currentMonth; ?>';
            let date = new Date(currentMonthParam + '-01');

            if (direction === 'prev') {
                date.setMonth(date.getMonth() - 1);
            } else {
                date.setMonth(date.getMonth() + 1);
            }

            let newMonth = date.toISOString().slice(0, 7);
            window.location.href = `director_dashboard.php?month=${newMonth}`;
        }

        function refreshDashboard() {
            window.location.reload();
        }

        function initializeCharts() {
            let chartIndex = 0;
            const indicators = <?php echo json_encode($indicators); ?>;
            for (const [indicatorKey, indicatorDisplay] of Object.entries(indicators)) {
                const percentage = reportData[indicatorKey] || 0;
                const chartId = `chart-${chartIndex}`;
                const chart = createGaugeChart(chartId, percentage);
                if (chart) chartInstances[chartId] = chart;
                chartIndex++;
            }
        }

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
                    this.refreshCharts();
                } else {
                    document.body.classList.remove('light-theme');
                    this.updateToggleButton(false);
                    this.refreshCharts();
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
                this.refreshCharts();
            }

            updateToggleButton(isLight) {
                const toggleBtn = document.getElementById('themeToggle');
                if (toggleBtn) {
                    toggleBtn.innerHTML = isLight ? '🌙 Dark' : '☀️ Light';
                }
            }

            refreshCharts() {
                setTimeout(() => {
                    for (const [id, chart] of Object.entries(chartInstances)) {
                        if (chart && typeof chart.destroy === 'function') {
                            chart.destroy();
                        }
                    }
                    chartInstances = {};
                    initializeCharts();
                }, 100);
            }

            initToggle() {
                const toggleBtn = document.getElementById('themeToggle');
                if (toggleBtn) {
                    toggleBtn.addEventListener('click', () => this.toggleTheme());
                }
            }
        }

        window.onclick = function(event) {
            const modal = document.getElementById('detailModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            new ThemeManager();
            initializeCharts();

            const fullscreenBtn = document.getElementById('fullscreenHeaderBtn');
            const exitFullscreenBtn = document.getElementById('exitFullscreenBtn');

            if (fullscreenBtn) {
                fullscreenBtn.addEventListener('click', toggleFullscreen);
            }

            if (exitFullscreenBtn) {
                exitFullscreenBtn.addEventListener('click', exitFullscreen);
            }

            const floatingOverall = document.getElementById('floatingOverall');
            if (floatingOverall) {
                floatingOverall.textContent = averagePercentage + '%';
            }

            const container = document.getElementById('mainContainer');
            if (container) {
                let userScrollTimeout = null;

                container.addEventListener('wheel', function() {
                    if (document.body.classList.contains('fullscreen-mode')) {
                        stopAutoScroll();
                        if (userScrollTimeout) clearTimeout(userScrollTimeout);
                        userScrollTimeout = setTimeout(() => {
                            if (document.body.classList.contains('fullscreen-mode')) {
                                startAutoScroll();
                            }
                        }, 5000);
                    }
                });

                container.addEventListener('touchmove', function() {
                    if (document.body.classList.contains('fullscreen-mode')) {
                        stopAutoScroll();
                        if (userScrollTimeout) clearTimeout(userScrollTimeout);
                        userScrollTimeout = setTimeout(() => {
                            if (document.body.classList.contains('fullscreen-mode')) {
                                startAutoScroll();
                            }
                        }, 5000);
                    }
                });
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