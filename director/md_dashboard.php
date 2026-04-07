<?php
require_once '../session_config.php';
require_once '../includes/auth.php';
requireRole('director');

$conn = getConnection();
$currentMonth = $_GET['month'] ?? date('Y-m');
$dataMonth = $currentMonth . '-01';

// Get logged-in user's department from username
$username = $_SESSION['username'];
$userDept = 'MD';

// Extract department from username (format: director_BMT, director_LMT, etc.)
if (preg_match('/director_([A-Z\/\s]+)/', $username, $matches)) {
    $userDept = trim($matches[1]);
}
// Only Admin Director can access this page
$isAdminDirector = ($userDept === '' || $userDept === 'MD');

// If not admin director, redirect to department dashboard
if (!$isAdminDirector) {
    header('Location: director_dashboard.php?month=' . $currentMonth);
    exit();
}

// Define all departments (including PSCM)
$allDepartments = ['BMT', 'LMT', 'CMT', 'EMT', 'AEP', 'MSM', 'QA', 'PSCM', 'MRO HR', 'MD/DIV.', 'Remainder'];

// Define main departments for MD/DIV calculation (exclude MD/DIV and Remainder)
$mainDepartments = ['BMT', 'LMT', 'CMT', 'EMT', 'AEP', 'MSM', 'QA', 'PSCM', 'MRO HR'];

// Define cost center mapping for ALL departments (for displaying manager names)
$costCenterMapping = [
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
        $indicators[$indicatorName] = [
            'display_name' => $indicatorName,
            'short_name' => strlen($indicatorName) > 25 ? substr($indicatorName, 0, 22) . '...' : $indicatorName,
            'id' => 'ind_' . preg_replace('/[^a-zA-Z0-9]/', '_', $indicatorName)
        ];
        $indicatorOrder[] = $indicatorName;
    }
}

// Also include any indicators from mro_cpr_report that might not be in performance_indicators
$mroIndicatorsQuery = "SELECT DISTINCT report_type FROM mro_cpr_report WHERE report_type NOT IN (SELECT indicator_name FROM performance_indicators) ORDER BY report_type";
$mroIndicatorsResult = $conn->query($mroIndicatorsQuery);
if ($mroIndicatorsResult && $mroIndicatorsResult->num_rows > 0) {
    while ($row = $mroIndicatorsResult->fetch_assoc()) {
        $indicatorName = trim($row['report_type']);
        if (!isset($indicators[$indicatorName])) {
            $indicators[$indicatorName] = [
                'display_name' => $indicatorName,
                'short_name' => strlen($indicatorName) > 25 ? substr($indicatorName, 0, 22) . '...' : $indicatorName,
                'id' => 'ind_' . preg_replace('/[^a-zA-Z0-9]/', '_', $indicatorName)
            ];
            $indicatorOrder[] = $indicatorName;
        }
    }
}

// If no indicators found at all, create a default message
if (empty($indicators)) {
    $indicators['No Indicators Available'] = [
        'display_name' => 'No Indicators Available',
        'short_name' => 'No Data',
        'id' => 'ind_no_data'
    ];
    $indicatorOrder = ['No Indicators Available'];
}

// Color mapping for departments
$departmentColors = [
    'BMT' => '#00ADB5',
    'LMT' => '#4ECDC4',
    'CMT' => '#45B7D1',
    'EMT' => '#96CEB4',
    'AEP' => '#FFEAA7',
    'MSM' => '#DDA0DD',
    'QA' => '#98D8C8',
    'PSCM' => '#FF6B6B',
    'MRO HR' => '#F7B05E',
    'MD/DIV.' => '#E67E22',
    'Remainder' => '#95A5A6'
];

// Fetch actual data from mro_cpr_report for the selected month (only DIR level)
$dbData = [];
$query = "SELECT report_type as indicator_name, department, percentage, expected, completed 
          FROM mro_cpr_report 
          WHERE report_month = ? AND report_year = ? AND cost_center_code = 'DIR'";

$year = date('Y', strtotime($dataMonth));
$month = date('m', strtotime($dataMonth));
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $month, $year);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $indicator = $row['indicator_name'];
    $dept = $row['department'];
    $percentage = round($row['percentage'], 1);
    $actual = $row['completed'];
    $target = $row['expected'];

    if (!isset($dbData[$indicator])) {
        $dbData[$indicator] = [];
    }
    $dbData[$indicator][$dept] = [
        'percentage' => $percentage,
        'actual' => $actual,
        'target' => $target
    ];
}
$stmt->close();

// Also fetch from master_performance_data for fallback
$masterQuery = "SELECT indicator_name, department, percentage_achievement, actual_value, target_value
                FROM master_performance_data 
                WHERE data_month = ? AND verification_status = 'verified'";
$masterStmt = $conn->prepare($masterQuery);
$masterStmt->bind_param("s", $dataMonth);
$masterStmt->execute();
$masterResult = $masterStmt->get_result();

while ($row = $masterResult->fetch_assoc()) {
    $indicator = $row['indicator_name'];
    $dept = $row['department'];
    $percentage = round($row['percentage_achievement'], 1);

    if (!isset($dbData[$indicator]) || !isset($dbData[$indicator][$dept])) {
        if (!isset($dbData[$indicator])) {
            $dbData[$indicator] = [];
        }
        $dbData[$indicator][$dept] = [
            'percentage' => $percentage,
            'actual' => $row['actual_value'],
            'target' => $row['target_value']
        ];
    }
}
$masterStmt->close();

/// Calculate overall percentages and prepare data for display
$metricsData = [];
foreach ($indicators as $indicatorKey => $indicatorInfo) {
    $departmentData = [];
    $departmentActuals = [];
    $departmentTargets = [];
    $validPercentages = [];
    $hasAnyActualData = false;

    // Initialize ALL departments with 0 or null values
    foreach ($allDepartments as $dept) {
        if (isset($dbData[$indicatorKey]) && isset($dbData[$indicatorKey][$dept])) {
            $departmentData[$dept] = $dbData[$indicatorKey][$dept]['percentage'];
            $departmentActuals[$dept] = $dbData[$indicatorKey][$dept]['actual'];
            $departmentTargets[$dept] = $dbData[$indicatorKey][$dept]['target'];
            if ($dbData[$indicatorKey][$dept]['percentage'] > 0) {
                $validPercentages[] = $dbData[$indicatorKey][$dept]['percentage'];
                $hasAnyActualData = true;
            }
        } else {
            // For departments with no data, set percentage to 0 but keep them visible
            $departmentData[$dept] = 0;
            $departmentActuals[$dept] = null;
            $departmentTargets[$dept] = null;
        }
    }

    // Calculate MD/DIV. as average of ALL main departments (including those with 0%)
    $mainDeptPercentages = [];
    foreach ($mainDepartments as $mainDept) {
        // Include ALL main departments, even if they have 0%
        $percentage = isset($departmentData[$mainDept]) ? $departmentData[$mainDept] : 0;
        $mainDeptPercentages[] = $percentage;

        // Track if ANY main department has actual data
        if ($percentage > 0) {
            $hasAnyActualData = true;
        }
    }

    // Calculate MD/DIV. as average of ALL main departments (including zeros)
    if (!empty($mainDeptPercentages)) {
        $mdDivPercentage = round(array_sum($mainDeptPercentages) / count($mainDeptPercentages), 1);
    } else {
        $mdDivPercentage = 0;
    }

    // Calculate Remainder (what's left to reach 100% from MD/DIV.)
    $remainderPercentage = round(max(0, 100 - $mdDivPercentage), 1);

    // Update department data with calculated values (always show them)
    $departmentData['MD/DIV.'] = $mdDivPercentage;
    $departmentData['Remainder'] = $remainderPercentage;

    // Calculate overall percentage - ONLY if there is actual data
    if ($hasAnyActualData) {
        // Include all main departments plus MD/DIV. and Remainder in average
        $allPercentagesForOverall = [];
        foreach ($mainDepartments as $mainDept) {
            $allPercentagesForOverall[] = $departmentData[$mainDept];
        }
        $allPercentagesForOverall[] = $mdDivPercentage;
        $allPercentagesForOverall[] = $remainderPercentage;
        
        $overall = round(array_sum($allPercentagesForOverall) / count($allPercentagesForOverall), 1);
    } else {
        // No data at all - overall should be 0
        $overall = 0;
        // Also set MD/DIV. and Remainder to 0 when no data
        $departmentData['MD/DIV.'] = 0;
        $departmentData['Remainder'] = 0;
        $mdDivPercentage = 0;
        $remainderPercentage = 0;
    }

    $metricsData[$indicatorKey] = [
        'display_name' => $indicatorInfo['display_name'],
        'short_name' => $indicatorInfo['short_name'],
        'id' => $indicatorInfo['id'],
        'overall' => $overall,
        'has_data' => $hasAnyActualData,
        'departments' => $departmentData,
        'actuals' => $departmentActuals,
        'targets' => $departmentTargets
    ];
}

$conn->close();

// Calculate average overall percentage (only for indicators that have data)
$totalOverall = 0;
$countOverall = 0;
foreach ($metricsData as $metric) {
    // Only include in average if there is actual data
    if ($metric['has_data']) {
        $totalOverall += $metric['overall'];
        $countOverall++;
    }
}
$averageOverall = $countOverall > 0 ? round($totalOverall / $countOverall, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Organizational Performance Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        }

        /* Fullscreen mode styles */
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

        body.fullscreen-mode .welcome-banner {
            display: none !important;
        }

        body.fullscreen-mode .container {
            height: 100vh;
            overflow-y: auto;
            scroll-behavior: smooth;
            margin: 0;
            padding: 20px;
            padding-top: 70px;
        }

        /* Floating controls for fullscreen mode */
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

        /* Navigation */
        .navbar {
            background: var(--medium-bg);
            padding: 0.6rem 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid var(--border-light);
            transition: background 0.3s;
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
            align-items: center;
            gap: 0.5rem;
        }

        .user-name {
            color: var(--accent);
            font-weight: bold;
            font-size: 0.8rem;
        }

        .department-badge {
            background: var(--accent);
            color: var(--dark-bg);
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: bold;
        }

        .btn {
            background: var(--accent);
            color: var(--dark-bg);
            border: none;
            padding: 0.3rem 0.8rem;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.7rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(56, 189, 248, 0.3);
            background: var(--accent-hover);
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

        /* Fullscreen Button */
        .fullscreen-header-btn {
            background: transparent;
            border: 1px solid var(--accent);
            color: var(--accent);
            padding: 0.35rem 0.9rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.8rem;
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

        /* Main Container */
        .container {
            width: 100%;
            max-width: 100%;
            margin: 0;
            padding: 0.75rem 1rem;
        }

        /* Dashboard Header */
        .dashboard-header {
            background: linear-gradient(135deg, var(--medium-bg) 0%, var(--dark-bg) 100%);
            padding: 0.6rem 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            border: 1px solid var(--border-light);
            transition: background 0.3s;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
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

        /* Responsive Grid Layout */
        .metrics-grid {
            display: grid;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        @media (min-width: 1600px) {
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

        /* Metric Card */
        .metric-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 0.8rem;
            transition: all 0.3s;
            border: 1px solid var(--border-light);
            display: flex;
            flex-direction: column;
        }

        .metric-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
            border-color: var(--accent);
        }

        .metric-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.6rem;
            padding-bottom: 0.4rem;
            border-bottom: 2px solid var(--accent);
        }

        .metric-title {
            font-size: 0.75rem;
            font-weight: bold;
            color: var(--accent);
            cursor: default;
            transition: none;
        }

        .metric-title:hover {
            color: var(--accent);
            text-decoration: none;
        }

        .overall-score {
            font-size: 1rem;
            font-weight: bold;
            cursor: default;
            padding: 0.2rem 0.5rem;
            border-radius: 20px;
            transition: none;
        }

        .overall-score:hover {
            transform: none;
            background: none;
        }

        /* Chart Container */
        .chart-container {
            position: relative;
            width: 100%;
            max-width: 180px;
            margin: 0 auto 0.6rem;
            cursor: pointer;
        }

        .chart-container canvas {
            width: 100% !important;
            height: auto !important;
            max-height: 140px;
        }

        /* Department Bars */
        .dept-bars {
            margin-top: 0.5rem;
            flex: 1;
        }

        .dept-bar-item {
            margin-bottom: 0.4rem;
            cursor: pointer;
            transition: all 0.2s;
            padding: 0.2rem 0.3rem;
            border-radius: 6px;
        }

        .dept-bar-item.clickable:hover {
            background: rgba(56, 189, 248, 0.1);
            transform: translateX(3px);
        }

        .dept-bar-item.disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }

        .dept-bar-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.15rem;
            font-size: 0.6rem;
        }

        .dept-name {
            font-weight: bold;
        }

        .dept-percentage {
            font-weight: bold;
        }

        .dept-bar-container {
            background: var(--dark-bg);
            border-radius: 4px;
            overflow: hidden;
            height: 6px;
        }

        .dept-bar-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s;
        }

        .no-data-bar {
            text-align: center;
            font-size: 0.55rem;
            color: var(--text-secondary);
            padding: 0.25rem;
            font-style: italic;
        }

        .welcome-banner {
            background: rgba(56, 189, 248, 0.1);
            border-left: 3px solid var(--accent);
            padding: 0.4rem 0.75rem;
            border-radius: 6px;
            margin-bottom: 0.75rem;
            font-size: 0.7rem;
        }

        .no-data {
            text-align: center;
            padding: 2rem;
            color: var(--text-primary);
            opacity: 0.7;
            background: var(--card-bg);
            border-radius: 12px;
        }

        .spinner {
            border: 2px solid var(--light-bg);
            border-top: 2px solid var(--accent);
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 2rem auto;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1001;
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

        body.light-theme .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        body.light-theme .dashboard-header {
            background: linear-gradient(135deg, #F1F5F9 0%, #E2E8F0 100%);
        }

        body.light-theme .metric-card {
            background: white;
        }

        body.light-theme .dept-bar-container {
            background: #E2E8F0;
        }

        body.light-theme .theme-toggle {
            border-color: #0284C7;
            color: #0284C7;
        }

        body.light-theme .theme-toggle:hover {
            background: #0284C7;
            color: white;
        }

        body.light-theme .fullscreen-header-btn {
            border-color: #0284C7;
            color: #0284C7;
        }

        body.light-theme .fullscreen-header-btn:hover {
            background: #0284C7;
            color: white;
        }

        body.light-theme .btn {
            background: #0284C7;
            color: white;
        }

        body.light-theme .department-badge {
            background: #0284C7;
            color: white;
        }

        body.light-theme .floating-controls {
            background: rgba(255, 255, 255, 0.95);
            border-bottom-color: #E2E8F0;
        }
    </style>
</head>

<body>
    <!-- Floating controls for fullscreen mode -->
    <div class="floating-controls" id="floatingControls">
        <div class="dashboard-title">Organizational Performance Dashboard</div>
        <div class="overall-mini">
            Overall: <span id="floatingOverall"><?php echo $averageOverall; ?>%</span>
        </div>
        <button class="exit-fullscreen-btn" id="exitFullscreenBtn">
            <img src="../assets/images/expand_black.png" alt="exit" style="transform: rotate(180deg);"> Exit Full Screen
        </button>
    </div>

    <nav class="navbar">
        <div class="navbar-container">
            <a href="md_dashboard.php" class="navbar-brand">HR & Finance Dashboard</a>
            <div class="navbar-menu">

                <?php if ($_SESSION['user_role'] !== 'director'): ?>
                    <a href="../admin/master_data.php">Master Data</a>
                <?php endif; ?>

                <a href="../director/md_dashboard.php" style="color: var(--accent);">Dashboard</a>
                <?php if ($_SESSION['user_role'] !== 'director'): ?>
                    <a href="../admin/report_mro_cpr.php">Director Data Entry</a>
                    <a href="../admin/data_history.php">History</a>
                <?php endif; ?>

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
                <h1>Organizational Performance Dashboard</h1>
                <div class="month-selector">
                    <button onclick="changeMonth('prev')">← Prev</button>
                    <h3 id="current-month"><?php echo date('F Y', strtotime($dataMonth)); ?></h3>
                    <button onclick="changeMonth('next')">Next →</button>
                </div>
            </div>
            <div class="header-right">
                <button id="fullscreenHeaderBtn2" class="fullscreen-header-btn">
                    <img src="../assets/images/expand.png" alt="expand"> Full Screen
                </button>
            </div>
        </div>

        <div class="welcome-banner">
            Viewing all departments performance metrics | Click on any department bar for detailed report
        </div>

        <div id="dashboard-content">
            <div class="spinner"></div>
        </div>
    </div>

    <!-- Department Detail Modal -->
    <div id="deptModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="deptModalTitle">Department Details</h3>
                <button class="close-modal" onclick="closeDeptModal()">&times;</button>
            </div>
            <div id="deptModalBody" style="padding: 1rem;">
                <div class="spinner"></div>
            </div>
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

        // Fullscreen functionality with auto-scroll and pauses
        let autoScrollInterval = null;
        let isScrolling = false;

        function toggleFullscreen() {
            const body = document.body;
            const container = document.getElementById('mainContainer');

            if (!body.classList.contains('fullscreen-mode')) {
                // Enter fullscreen mode
                body.classList.add('fullscreen-mode');

                // Start auto-scrolling
                startAutoScroll();

                // Request actual browser fullscreen if available
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

            // Stop auto-scrolling
            stopAutoScroll();

            // Exit browser fullscreen
            if (document.exitFullscreen) {
                document.exitFullscreen();
            }
        }

        function startAutoScroll() {
            const container = document.getElementById('mainContainer');
            if (!container) return;

            stopAutoScroll(); // Clear any existing interval

            let isPaused = false;
            let scrollTimeout = null;

            function performScroll() {
                if (isScrolling || isPaused) return;

                isScrolling = true;

                const maxScroll = container.scrollHeight - container.clientHeight;
                const currentScroll = container.scrollTop;

                // Check if we're at the bottom
                const isAtBottom = currentScroll >= maxScroll - 10;

                if (isAtBottom && maxScroll > 0) {
                    // Pause at bottom for 2 seconds
                    isPaused = true;
                    isScrolling = false;

                    // Clear any existing timeout
                    if (scrollTimeout) clearTimeout(scrollTimeout);

                    scrollTimeout = setTimeout(() => {
                        // Smooth scroll to top
                        container.scrollTo({
                            top: 0,
                            behavior: 'smooth'
                        });

                        // Pause at top for 2 seconds after reaching top
                        setTimeout(() => {
                            isPaused = false;
                            isScrolling = false;
                        }, 2000);
                    }, 2000);

                    return;
                }

                // Check if we're at the top and just finished scrolling (handled by the pause flag)
                if (currentScroll <= 10 && isPaused) {
                    isScrolling = false;
                    return;
                }

                // Normal scroll down
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

        // Listen for fullscreen change events
        document.addEventListener('fullscreenchange', function() {
            if (!document.fullscreenElement) {
                // User exited fullscreen via ESC key
                const body = document.body;

                if (body.classList.contains('fullscreen-mode')) {
                    body.classList.remove('fullscreen-mode');
                    stopAutoScroll();
                }
            }
        });

        // Data passed from PHP
        const metricsData = <?php echo json_encode($metricsData); ?>;
        const departmentColors = <?php echo json_encode($departmentColors); ?>;
        const currentMonth = '<?php echo $currentMonth; ?>';
        const currentYear = '<?php echo date('Y', strtotime($dataMonth)); ?>';
        const currentMonthNum = '<?php echo date('m', strtotime($dataMonth)); ?>';
        const allDepartments = <?php echo json_encode($allDepartments); ?>;
        const averageOverall = <?php echo $averageOverall; ?>;
        const costCenterMapping = <?php echo json_encode($costCenterMapping); ?>;

        // Departments that are NOT clickable (MD/DIV. and Remainder)
        const nonClickableDepts = ['MD/DIV.', 'Remainder'];

        // Function to get color based on percentage
        function getScoreColor(percentage) {
            if (percentage >= 90) return '#10B981';
            if (percentage >= 70) return '#F59E0B';
            return '#EF4444';
        }

        // Function to show department details modal
        async function showDepartmentDetails(department, indicatorKey, indicatorName) {
            const modal = document.getElementById('deptModal');
            const modalTitle = document.getElementById('deptModalTitle');
            const modalBody = document.getElementById('deptModalBody');

            modalTitle.innerHTML = `${department} Department - ${indicatorName} Details`;
            modalBody.innerHTML = '<div class="spinner"></div>';
            modal.style.display = 'flex';

            try {
                // Fetch data for this department and indicator from mro_cpr_report
                const response = await fetch(`get_dept_indicator_data.php?dept=${encodeURIComponent(department)}&indicator=${encodeURIComponent(indicatorKey)}&month=${currentMonthNum}&year=${currentYear}`);
                const data = await response.json();

                if (data.success && data.data.length > 0) {
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

                    let directorData = null;
                    let managerRows = [];

                    // Separate director row from manager rows
                    for (const record of data.data) {
                        if (record.cost_center_code === 'DIR') {
                            directorData = record;
                        } else {
                            managerRows.push(record);
                        }
                    }

                    // Display manager rows first
                    for (const record of managerRows) {
                        const expected = parseInt(record.expected) || 0;
                        const completed = parseInt(record.completed) || 0;
                        const percentage = parseFloat(record.percentage) || 0;
                        const notCompleted = expected - completed;
                        const percentageColor = getScoreColor(percentage);

                        tableHtml += `
                            <tr>
                                <td>${record.cost_center_text || record.cost_center_code}</td>
                                <td>${expected}</td>
                                <td>${completed}</td>
                                <td>${notCompleted}</td>
                                <td style="color: ${percentageColor}; font-weight: bold;">${percentage}%</td>
                                <td>
                                    <div class="progress-bar-modal">
                                        <div class="progress-fill-modal" style="width: ${percentage}%; background: ${percentageColor};"></div>
                                    </div>
                                </td>
                            </tr>
                        `;
                    }

                    // Display director row only once (from database)
                    if (directorData) {
                        const dirExpected = parseInt(directorData.expected) || 0;
                        const dirCompleted = parseInt(directorData.completed) || 0;
                        const dirPercentage = parseFloat(directorData.percentage) || 0;
                        const dirNotCompleted = dirExpected - dirCompleted;
                        const dirColor = getScoreColor(dirPercentage);

                        tableHtml += `
                            <tr class="director-row">
                                <td><strong>${directorData.cost_center_text || 'Director/Total'}</strong></td>
                                <td><strong>${dirExpected}</strong></td>
                                <td><strong>${dirCompleted}</strong></td>
                                <td><strong>${dirNotCompleted}</strong></td>
                                <td style="color: ${dirColor}; font-weight: bold;"><strong>${dirPercentage}%</strong></td>
                                <td>
                                    <div class="progress-bar-modal">
                                        <div class="progress-fill-modal" style="width: ${dirPercentage}%; background: ${dirColor};"></div>
                                    </div>
                                </td>
                            </tr>
                        `;
                    } else if (managerRows.length > 0) {
                        // If no director row in database, calculate totals from manager rows
                        let totalExpected = 0;
                        let totalCompleted = 0;
                        for (const record of managerRows) {
                            totalExpected += parseInt(record.expected) || 0;
                            totalCompleted += parseInt(record.completed) || 0;
                        }
                        const totalPercentage = totalExpected > 0 ? (totalCompleted / totalExpected) * 100 : 0;
                        const totalColor = getScoreColor(totalPercentage);
                        const totalNotCompleted = totalExpected - totalCompleted;

                        tableHtml += `
                            <tr class="director-row">
                                <td><strong>TOTAL (Calculated)</strong></td>
                                <td><strong>${totalExpected}</strong></td>
                                <td><strong>${totalCompleted}</strong></td>
                                <td><strong>${totalNotCompleted}</strong></td>
                                <td style="color: ${totalColor}; font-weight: bold;"><strong>${totalPercentage.toFixed(1)}%</strong></td>
                                <td>
                                    <div class="progress-bar-modal">
                                        <div class="progress-fill-modal" style="width: ${totalPercentage}%; background: ${totalColor};"></div>
                                    </div>
                                </td>
                            </tr>
                        `;
                    }

                    tableHtml += `
                            </tbody>
                        </table>
                    `;

                    modalBody.innerHTML = tableHtml;
                } else {
                    modalBody.innerHTML = '<div class="no-data">No data available for this department and indicator.</div>';
                }
            } catch (error) {
                console.error('Error fetching department data:', error);
                modalBody.innerHTML = '<div class="no-data">Error loading data. Please try again.</div>';
            }
        }

        function closeDeptModal() {
            document.getElementById('deptModal').style.display = 'none';
        }

        // Function to handle click on department
        function onDepartmentClick(department, indicatorKey, indicatorName) {
            // Check if department is clickable (exclude MD/DIV. and Remainder)
            if (nonClickableDepts.includes(department)) {
                return;
            }
            showDepartmentDetails(department, indicatorKey, indicatorName);
        }

        let chartInstances = {};

        function createPieChart(canvasId, percentage, metricName, indicatorKey) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return null;

            const achieved = percentage;
            const remaining = Math.max(0, 100 - percentage);
            const color = getScoreColor(percentage);

            return new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Achieved', 'Remaining'],
                    datasets: [{
                        data: [achieved, remaining],
                        backgroundColor: [color, 'rgba(51, 65, 85, 0.6)'],
                        borderWidth: 0,
                        hoverOffset: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    cutout: '65%',
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
                    },
                    onClick: null
                }
            });
        }

        function renderDepartmentBars(containerId, departments, indicatorKey, indicatorName, actuals, targets, hasData) {
            const container = document.getElementById(containerId);
            if (!container) return;

            let html = '';
            let hasAnyDepartmentData = false;

            // Define the order of departments to display
            const displayOrder = ['BMT', 'LMT', 'CMT', 'EMT', 'AEP', 'MSM', 'QA', 'PSCM', 'MRO HR', 'MD/DIV.', 'Remainder'];

            for (const dept of displayOrder) {
                // Skip if department doesn't exist in the data
                if (!departments.hasOwnProperty(dept)) continue;

                const percentageValue = parseFloat(departments[dept]) || 0;
                const barWidth = Math.min(percentageValue, 100);
                const color = departmentColors[dept] || '#38BDF8';
                const scoreColor = getScoreColor(percentageValue);
                const isClickable = !nonClickableDepts.includes(dept);
                const clickableClass = isClickable ? 'clickable' : 'disabled';
                const onclickAttr = isClickable ? `onclick="onDepartmentClick('${dept}', '${indicatorKey}', '${indicatorName}')"` : '';

                const actualVal = (actuals[dept] !== undefined && actuals[dept] !== null && actuals[dept] !== '') ? actuals[dept] : '-';
                const targetVal = (targets[dept] !== undefined && targets[dept] !== null && targets[dept] !== '') ? targets[dept] : '-';

                // For calculated departments (MD/DIV., Remainder), always show with calculated values
                // For regular departments, show even with 0%
                const isCalculatedDept = (dept === 'MD/DIV.' || dept === 'Remainder');
                const showDept = true; // Show ALL departments always

                if (showDept) {
                    hasAnyDepartmentData = true;
                    const displayPercentage = percentageValue;
                    const displayBarWidth = Math.min(barWidth, 100);
                    const displayScoreColor = scoreColor;

                    // Format actual and target for display (handle decimals properly)
                    let displayActual = actualVal;
                    let displayTarget = targetVal;

                    // For calculated departments, show dash
                    if (isCalculatedDept) {
                        displayActual = '-';
                        displayTarget = '-';
                    } else if (actualVal !== '-' && actualVal !== null && !isNaN(parseFloat(actualVal))) {
                        // Format numbers nicely (remove .00 if it's a whole number)
                        const numActual = parseFloat(actualVal);
                        const numTarget = parseFloat(targetVal);
                        displayActual = Number.isInteger(numActual) ? numActual : numActual.toFixed(1);
                        displayTarget = Number.isInteger(numTarget) ? numTarget : numTarget.toFixed(1);
                    }

                    html += `
                <div class="dept-bar-item ${clickableClass}" ${onclickAttr}>
                    <div class="dept-bar-label">
                        <span class="dept-name" style="color: ${color};">${dept}</span>
                        <span class="dept-percentage" style="color: ${displayScoreColor};">${displayPercentage}%</span>
                    </div>
                    <div class="dept-bar-container">
                        <div class="dept-bar-fill" style="width: ${displayBarWidth}%; background: ${color};"></div>
                    </div>
                    <div style="font-size: 0.55rem; margin-top: 0.2rem; display: flex; justify-content: space-between;">
                        <span>Actual: ${displayActual}</span>
                        <span>Target: ${displayTarget}</span>
                    </div>
                </div>
            `;
                }
            }

            if (!hasAnyDepartmentData) {
                container.innerHTML = '<div class="no-data-bar">No data available for this period</div>';
            } else {
                container.innerHTML = html;
            }
        }

        function renderDashboard() {
            const container = document.getElementById('dashboard-content');
            container.innerHTML = '';

            const metricsGrid = document.createElement('div');
            metricsGrid.className = 'metrics-grid';

            for (const [metricKey, metric] of Object.entries(metricsData)) {
                const chartId = `chart-${metric.id}`;
                const barsId = `bars-${metric.id}`;
                const overallColor = getScoreColor(metric.overall);

                // Always show the overall percentage, even if 0
                const displayOverall = metric.overall;
                const displayOverallColor = overallColor;

                const card = document.createElement('div');
                card.className = 'metric-card';
                card.innerHTML = `
            <div class="metric-header">
                <div class="metric-title" style="cursor: default;">${metric.display_name}</div>
                <div class="overall-score" style="color: ${displayOverallColor}; cursor: default;">${displayOverall}%</div>
            </div>
            <div class="chart-container">
                <canvas id="${chartId}" width="180" height="140"></canvas>
            </div>
            <div id="${barsId}" class="dept-bars"></div>
        `;
                metricsGrid.appendChild(card);

                setTimeout(() => {
                    const chart = createPieChart(chartId, displayOverall, metric.display_name, metricKey);
                    if (chart) chartInstances[chartId] = chart;
                    renderDepartmentBars(barsId, metric.departments, metricKey, metric.display_name, metric.actuals || {}, metric.targets || {}, metric.has_data);
                }, 10);
            }
            container.appendChild(metricsGrid);
        }

        // Change month function
        function changeMonth(direction) {
            let currentUrl = new URL(window.location.href);
            let currentMonthParam = currentUrl.searchParams.get('month') || currentMonth;
            let date = new Date(currentMonthParam + '-01');

            if (direction === 'prev') {
                date.setMonth(date.getMonth() - 1);
            } else {
                date.setMonth(date.getMonth() + 1);
            }

            let newMonth = date.toISOString().slice(0, 7);
            window.location.href = `md_dashboard.php?month=${newMonth}`;
        }

        // Initialize theme manager and dashboard when page loads
        document.addEventListener('DOMContentLoaded', function() {
            new ThemeManager();
            renderDashboard();

            // Initialize fullscreen buttons
            const fullscreenBtns = document.querySelectorAll('#fullscreenHeaderBtn, #fullscreenHeaderBtn2');
            const exitFullscreenBtn = document.getElementById('exitFullscreenBtn');

            fullscreenBtns.forEach(btn => {
                if (btn) {
                    btn.addEventListener('click', toggleFullscreen);
                }
            });

            if (exitFullscreenBtn) {
                exitFullscreenBtn.addEventListener('click', exitFullscreen);
            }

            // Update floating overall percentage
            const floatingOverall = document.getElementById('floatingOverall');
            if (floatingOverall) {
                floatingOverall.textContent = averageOverall + '%';
            }

            // Pause auto-scroll on user interaction
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

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('deptModal');
            if (event.target === modal) {
                closeDeptModal();
            }
        }

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