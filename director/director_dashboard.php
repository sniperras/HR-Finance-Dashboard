<?php
require_once '../includes/auth.php';
requireRole('director');

$conn = getConnection();
$currentMonth = $_GET['month'] ?? date('Y-m');
$dataMonth = $currentMonth . '-01';

// Get logged-in user's department from username
$username = $_SESSION['username'];
$userDept = '';

// Extract department from username (format: director_BMT, director_LMT, etc.)
if (preg_match('/director_([A-Z\/\s]+)/', $username, $matches)) {
    $userDept = trim($matches[1]);
}

// If no department found or user is admin director, show all departments
$isAdminDirector = ($userDept === '' || $userDept === 'admin');
$departmentFilter = $isAdminDirector ? null : $userDept;

// Define all departments
$allDepartments = ['BMT', 'LMT', 'CMT', 'EMT', 'AEP', 'MSM', 'QA', 'MRO HR', 'MD/DIV.', 'Remainder'];

// Define all indicators with their display names - shorter for compact view
$indicators = [
    'Team Leaders Clock-in Data' => [
        'display_name' => 'Team Leaders Clock-in',
        'short_name' => 'Clock-in'
    ],
    'Crew Meeting Minutes Submission' => [
        'display_name' => 'Crew Meeting Minutes',
        'short_name' => 'Meeting Minutes'
    ],
    'Exceptional Customer Experience Training' => [
        'display_name' => 'Customer Exp. Training',
        'short_name' => 'Cust. Training'
    ],
    'CPR' => [
        'display_name' => 'CPR',
        'short_name' => 'CPR'
    ],
    '2025/26 1st Semiannual BSCI/ISC Target Status' => [
        'display_name' => 'BSCI/ISC Target',
        'short_name' => 'BSCI Target'
    ],
    'Activity Report Submission' => [
        'display_name' => 'Activity Report',
        'short_name' => 'Activity'
    ],
    'Cost Saving Report Submission' => [
        'display_name' => 'Cost Saving Report',
        'short_name' => 'Cost Saving'
    ],
    'Lost Time Justification' => [
        'display_name' => 'Lost Time Justification',
        'short_name' => 'Lost Time'
    ],
    'Attendance Approval Status' => [
        'display_name' => 'Attendance Approval',
        'short_name' => 'Attendance'
    ],
    'Productivity' => [
        'display_name' => 'Productivity',
        'short_name' => 'Productivity'
    ],
    'Employees Training Gap Clearance' => [
        'display_name' => 'Training Gap',
        'short_name' => 'Training'
    ],
    'Employees Issue Resolution Rate' => [
        'display_name' => 'Issue Resolution',
        'short_name' => 'Issue Res.'
    ]
];

// Color mapping for departments
$departmentColors = [
    'BMT' => '#00ADB5',
    'LMT' => '#4ECDC4',
    'CMT' => '#45B7D1',
    'EMT' => '#96CEB4',
    'AEP' => '#FFEAA7',
    'MSM' => '#DDA0DD',
    'QA' => '#98D8C8',
    'MRO HR' => '#F7B05E',
    'MD/DIV.' => '#E67E22',
    'Remainder' => '#95A5A6'
];

// Fetch actual data from database for the selected month
$dbData = [];
$query = "SELECT indicator_name, department, percentage_achievement, actual_value, target_value 
          FROM master_performance_data 
          WHERE data_month = ? AND verification_status = 'verified'";

if (!$isAdminDirector) {
    $query .= " AND department = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $dataMonth, $userDept);
} else {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $dataMonth);
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $indicator = $row['indicator_name'];
    $dept = $row['department'];
    $percentage = round($row['percentage_achievement'], 1);
    
    if (!isset($dbData[$indicator])) {
        $dbData[$indicator] = [];
    }
    $dbData[$indicator][$dept] = [
        'percentage' => $percentage,
        'actual' => $row['actual_value'],
        'target' => $row['target_value']
    ];
}
$stmt->close();

// Calculate overall percentages and prepare data for display
$metricsData = [];
foreach ($indicators as $indicatorKey => $indicatorInfo) {
    $departmentData = [];
    
    if (isset($dbData[$indicatorKey]) && !empty($dbData[$indicatorKey])) {
        foreach ($dbData[$indicatorKey] as $dept => $values) {
            if ($isAdminDirector || $dept === $userDept) {
                $departmentData[$dept] = $values['percentage'];
            }
        }
        
        if (!empty($departmentData)) {
            $overall = round(array_sum($departmentData) / count($departmentData), 1);
        } else {
            $overall = 0;
        }
    } else {
        $overall = 0;
        $departmentData = [];
        
        if ($isAdminDirector) {
            foreach ($allDepartments as $dept) {
                $departmentData[$dept] = 0;
            }
        } else {
            $departmentData[$userDept] = 0;
        }
    }
    
    $metricsData[$indicatorKey] = [
        'display_name' => $indicatorInfo['display_name'],
        'short_name' => $indicatorInfo['short_name'],
        'overall' => $overall,
        'departments' => $departmentData
    ];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo $isAdminDirector ? 'Organizational' : $userDept; ?> Performance Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root {
            --dark-bg: #222831;
            --medium-bg: #393E46;
            --accent: #00ADB5;
            --light-bg: #EEEEEE;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--dark-bg);
            color: var(--light-bg);
            overflow-x: hidden;
        }
        
        /* Compact Navigation */
        .navbar {
            background: var(--medium-bg);
            padding: 0.5rem 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .navbar-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        /* Responsive padding for navbar */
        @media (min-width: 1920px) {
            .navbar-container {
                padding: 0 3rem;
            }
        }
        
        @media (max-width: 768px) {
            .navbar-container {
                padding: 0 1rem;
            }
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
            box-shadow: 0 2px 5px rgba(0,173,181,0.3);
        }
        
        /* Main Container with responsive padding */
        .container {
            width: 100%;
            max-width: 1600px;
            margin: 0.5rem auto;
            padding: 0 1.5rem;
        }
        
        /* Responsive container padding for different screen sizes */
        @media (min-width: 1920px) {
            .container {
                padding: 0 3rem;
            }
        }
        
        @media (min-width: 2560px) {
            .container {
                padding: 0 5rem;
            }
        }
        
        @media (max-width: 1366px) {
            .container {
                padding: 0 1rem;
            }
        }
        
        @media (max-width: 1024px) {
            .container {
                padding: 0 0.75rem;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 0.5rem;
            }
        }
        
        /* Compact Dashboard Header */
        .dashboard-header {
            background: linear-gradient(135deg, var(--medium-bg) 0%, var(--dark-bg) 100%);
            padding: 0.75rem 1.25rem;
            border-radius: 12px;
            margin-bottom: 0.75rem;
        }
        
        @media (max-width: 768px) {
            .dashboard-header {
                padding: 0.6rem 1rem;
            }
        }
        
        .dashboard-header h1 {
            color: var(--accent);
            margin-bottom: 0.2rem;
            font-size: 1.1rem;
        }
        
        @media (max-width: 768px) {
            .dashboard-header h1 {
                font-size: 1rem;
            }
        }
        
        .dashboard-header p {
            color: var(--light-bg);
            opacity: 0.8;
            font-size: 0.7rem;
        }
        
        .month-selector {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            margin-top: 0.5rem;
            flex-wrap: wrap;
        }
        
        .month-selector button {
            background: var(--accent);
            color: var(--dark-bg);
            border: none;
            padding: 0.25rem 0.75rem;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.7rem;
            transition: all 0.2s;
        }
        
        .month-selector button:hover {
            transform: scale(1.05);
        }
        
        .month-selector h3 {
            color: var(--light-bg);
            margin: 0;
            font-size: 0.9rem;
        }
        
        /* Responsive Metrics Grid */
        .metrics-grid {
            display: grid;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
        }
        
        /* Responsive grid columns based on screen size */
        @media (min-width: 1600px) {
            .metrics-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 1rem;
            }
        }
        
        @media (min-width: 1200px) and (max-width: 1599px) {
            .metrics-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 0.85rem;
            }
        }
        
        @media (min-width: 768px) and (max-width: 1199px) {
            .metrics-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }
        }
        
        @media (max-width: 767px) {
            .metrics-grid {
                grid-template-columns: 1fr;
                gap: 0.65rem;
            }
        }
        
        /* Compact Metric Card */
        .metric-card {
            background: var(--medium-bg);
            border-radius: 10px;
            padding: 0.75rem;
            transition: all 0.2s;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .metric-title {
            font-size: 0.7rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: var(--accent);
            border-bottom: 1px solid var(--accent);
            padding-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        @media (max-width: 768px) {
            .metric-title {
                font-size: 0.65rem;
                white-space: normal;
                line-height: 1.2;
            }
        }
        
        /* Compact Progress Ring */
        .overall-progress {
            text-align: center;
            margin-bottom: 0.5rem;
            flex-shrink: 0;
        }
        
        .progress-ring-container {
            position: relative;
            width: 70px;
            height: 70px;
            margin: 0 auto;
        }
        
        @media (max-width: 480px) {
            .progress-ring-container {
                width: 60px;
                height: 60px;
            }
        }
        
        .progress-ring-svg {
            transform: rotate(-90deg);
            width: 100%;
            height: 100%;
        }
        
        .progress-ring-circle-bg {
            fill: none;
            stroke: var(--dark-bg);
            stroke-width: 6;
        }
        
        .progress-ring-circle {
            fill: none;
            stroke: var(--accent);
            stroke-width: 6;
            stroke-linecap: round;
            transition: stroke-dasharray 0.5s;
        }
        
        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 0.8rem;
            font-weight: bold;
            color: var(--accent);
        }
        
        @media (max-width: 480px) {
            .progress-text {
                font-size: 0.7rem;
            }
        }
        
        .overall-percentage-text {
            margin-top: 0.25rem;
            font-size: 0.65rem;
            color: var(--light-bg);
        }
        
        .overall-percentage-text span {
            color: var(--accent);
            font-weight: bold;
            font-size: 0.75rem;
        }
        
        /* Compact Bar Chart */
        .department-bars {
            margin-top: 0.5rem;
            flex: 1;
            overflow-y: auto;
            max-height: 160px;
            padding-right: 0.25rem;
        }
        
        @media (max-width: 768px) {
            .department-bars {
                max-height: 140px;
            }
        }
        
        .department-bars::-webkit-scrollbar {
            width: 3px;
        }
        
        .department-bars::-webkit-scrollbar-track {
            background: var(--dark-bg);
            border-radius: 3px;
        }
        
        .department-bars::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 3px;
        }
        
        .bar-item {
            margin-bottom: 0.4rem;
            cursor: pointer;
            position: relative;
        }
        
        .bar-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.15rem;
            font-size: 0.6rem;
        }
        
        @media (max-width: 768px) {
            .bar-label {
                font-size: 0.55rem;
            }
        }
        
        .bar-label span:first-child {
            font-weight: bold;
        }
        
        .bar-label span:last-child {
            color: var(--accent);
            font-weight: bold;
        }
        
        .bar-container {
            background: var(--dark-bg);
            border-radius: 4px;
            overflow: hidden;
            height: 16px;
            position: relative;
        }
        
        @media (max-width: 480px) {
            .bar-container {
                height: 14px;
            }
        }
        
        .bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent), #00d4dd);
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 4px;
            color: var(--dark-bg);
            font-size: 0.55rem;
            font-weight: bold;
            border-radius: 4px;
        }
        
        @media (max-width: 480px) {
            .bar-fill {
                font-size: 0.5rem;
                padding-right: 2px;
            }
        }
        
        /* Tooltip */
        .bar-item:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: var(--dark-bg);
            color: var(--light-bg);
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            white-space: nowrap;
            z-index: 1000;
            font-size: 0.6rem;
            margin-bottom: 3px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            pointer-events: none;
        }
        
        /* Welcome Banner Compact */
        .welcome-banner {
            background: rgba(0,173,181,0.1);
            border-left: 3px solid var(--accent);
            padding: 0.4rem 0.75rem;
            border-radius: 6px;
            margin-bottom: 0.75rem;
            font-size: 0.7rem;
        }
        
        @media (max-width: 768px) {
            .welcome-banner {
                font-size: 0.65rem;
                padding: 0.35rem 0.6rem;
            }
        }
        
        .welcome-banner strong {
            color: var(--accent);
        }
        
        /* No Data Message */
        .no-data {
            text-align: center;
            padding: 1.5rem;
            color: var(--light-bg);
            opacity: 0.7;
            background: var(--medium-bg);
            border-radius: 10px;
            font-size: 0.8rem;
        }
        
        @media (max-width: 768px) {
            .no-data {
                padding: 1rem;
                font-size: 0.7rem;
            }
        }
        
        /* Score indicators */
        .score-high {
            color: var(--success);
        }
        
        .score-medium {
            color: var(--warning);
        }
        
        .score-low {
            color: var(--danger);
        }
        
        /* Loading Spinner Compact */
        .spinner {
            border: 2px solid var(--light-bg);
            border-top: 2px solid var(--accent);
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 1rem auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Scrollbar styling */
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
        
        ::-webkit-scrollbar-thumb:hover {
            background: #00d4dd;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="director_dashboard.php" class="navbar-brand">HR & Finance Dashboard</a>
            <div class="navbar-menu">
                <a href="director_dashboard.php" class="btn" style="background: transparent; color: var(--accent);">Dashboard</a>
                <div class="user-info">
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <?php if (!$isAdminDirector): ?>
                        <span class="department-badge"><?php echo htmlspecialchars($userDept); ?></span>
                    <?php endif; ?>
                    <a href="../logout.php" class="btn">Logout</a>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="dashboard-header">
            <h1>
                <?php 
                if ($isAdminDirector) {
                    echo "📊 Organizational Performance Dashboard";
                } else {
                    echo "📈 " . htmlspecialchars($userDept) . " Department";
                }
                ?>
            </h1>
            <div class="month-selector">
                <button onclick="changeMonth('prev')">←</button>
                <h3 id="current-month"><?php echo date('M Y', strtotime($dataMonth)); ?></h3>
                <button onclick="changeMonth('next')">→</button>
            </div>
        </div>
        
        <?php if (!$isAdminDirector): ?>
            <div class="welcome-banner">
                <strong>👋 <?php echo htmlspecialchars($userDept); ?> Department</strong> - Performance metrics for <?php echo date('F Y', strtotime($dataMonth)); ?>
            </div>
        <?php endif; ?>
        
        <div id="dashboard-content">
            <div class="spinner"></div>
        </div>
    </div>
    
    <script>
        // Data passed from PHP
        const metricsData = <?php echo json_encode($metricsData); ?>;
        const departmentColors = <?php echo json_encode($departmentColors); ?>;
        const currentMonth = '<?php echo $currentMonth; ?>';
        const isAdminDirector = <?php echo $isAdminDirector ? 'true' : 'false'; ?>;
        const userDepartment = '<?php echo $userDept; ?>';
        
        // Function to get color based on percentage
        function getScoreColor(percentage) {
            if (percentage >= 90) return 'var(--success)';
            if (percentage >= 70) return 'var(--warning)';
            return 'var(--danger)';
        }
        
        // Function to render compact progress ring
        function renderProgressRing(containerId, percentage) {
            const container = document.getElementById(containerId);
            if (!container) return;
            
            const radius = 32;
            const circumference = 2 * Math.PI * radius;
            const offset = circumference - (percentage / 100) * circumference;
            const color = getScoreColor(percentage);
            
            container.innerHTML = `
                <div class="progress-ring-container">
                    <svg width="70" height="70" class="progress-ring-svg">
                        <circle cx="35" cy="35" r="${radius}" class="progress-ring-circle-bg"/>
                        <circle cx="35" cy="35" r="${radius}" class="progress-ring-circle"
                                stroke="${color}"
                                stroke-dasharray="${circumference}" stroke-dashoffset="${offset}"/>
                    </svg>
                    <div class="progress-text">${percentage}%</div>
                </div>
                <div class="overall-percentage-text">
                    <span>${percentage}%</span>
                </div>
            `;
        }
        
        // Function to render compact bar chart
        function renderBarChart(containerId, departments, title) {
            const container = document.getElementById(containerId);
            if (!container) return;
            
            if (Object.keys(departments).length === 0) {
                container.innerHTML = '<div style="text-align: center; padding: 0.5rem; font-size: 0.6rem; color: #888;">No data</div>';
                return;
            }
            
            let html = `<div class="department-bars">`;
            
            for (const [dept, value] of Object.entries(departments)) {
                const percentageValue = parseFloat(value);
                const barWidth = Math.min(percentageValue, 100);
                const color = departmentColors[dept] || '#00ADB5';
                const scoreColor = getScoreColor(percentageValue);
                
                html += `
                    <div class="bar-item" data-tooltip="${dept}: ${percentageValue}%">
                        <div class="bar-label">
                            <span>${dept}</span>
                            <span style="color: ${scoreColor}">${percentageValue}%</span>
                        </div>
                        <div class="bar-container">
                            <div class="bar-fill" style="width: ${barWidth}%; background: linear-gradient(90deg, ${color}, ${color}dd);">
                                ${percentageValue > 25 ? percentageValue + '%' : ''}
                            </div>
                        </div>
                    </div>
                `;
            }
            
            html += `</div>`;
            container.innerHTML = html;
        }
        
        // Function to render the dashboard
        function renderDashboard() {
            const container = document.getElementById('dashboard-content');
            container.innerHTML = '';
            
            const metricsGrid = document.createElement('div');
            metricsGrid.className = 'metrics-grid';
            
            let hasData = false;
            
            for (const [metricKey, metric] of Object.entries(metricsData)) {
                // Filter departments for non-admin directors
                let filteredDepartments = {};
                if (!isAdminDirector) {
                    if (metric.departments[userDepartment] !== undefined) {
                        filteredDepartments[userDepartment] = metric.departments[userDepartment];
                    }
                } else {
                    for (const [dept, value] of Object.entries(metric.departments)) {
                        if (value > 0) {
                            filteredDepartments[dept] = value;
                        }
                    }
                }
                
                // Skip if no departments to display
                if (Object.keys(filteredDepartments).length === 0 && metric.overall === 0) continue;
                hasData = true;
                
                // Create metric card
                const card = document.createElement('div');
                card.className = 'metric-card';
                
                const ringId = `ring-${metricKey.replace(/\s+/g, '-').replace(/[\/]/g, '-')}`;
                const barsId = `bars-${metricKey.replace(/\s+/g, '-').replace(/[\/]/g, '-')}`;
                
                card.innerHTML = `
                    <div class="metric-title" title="${metric.display_name}">${metric.display_name}</div>
                    <div class="overall-progress">
                        <div id="${ringId}"></div>
                    </div>
                    <div id="${barsId}"></div>
                `;
                
                metricsGrid.appendChild(card);
                
                // Render after adding to DOM
                setTimeout(() => {
                    renderProgressRing(ringId, metric.overall);
                    renderBarChart(barsId, filteredDepartments, metric.display_name);
                }, 0);
            }
            
            if (!hasData) {
                container.innerHTML = `
                    <div class="no-data">
                        <h4>📭 No Performance Data Available</h4>
                        <p>No verified data for ${document.getElementById('current-month').innerText}</p>
                    </div>
                `;
            } else {
                container.appendChild(metricsGrid);
            }
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
            window.location.href = `director_dashboard.php?month=${newMonth}`;
        }
        
        // Initialize dashboard when page loads
        document.addEventListener('DOMContentLoaded', function() {
            renderDashboard();
        });
        
        // Handle window resize for responsive adjustments
        window.addEventListener('resize', function() {
            renderDashboard();
        });
    </script>
</body>
</html>