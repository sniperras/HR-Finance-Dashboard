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

// Define all indicators with their display names
$indicators = [
    'Team Leaders Clock-in Data' => [
        'display_name' => 'Team Leaders Clock-in',
        'short_name' => 'Clock-in',
        'id' => 'ind_clockin'
    ],
    'Crew Meeting Minutes Submission' => [
        'display_name' => 'Crew Meeting Minutes',
        'short_name' => 'Meeting Minutes',
        'id' => 'ind_meeting'
    ],
    'Exceptional Customer Experience Training' => [
        'display_name' => 'Customer Exp. Training',
        'short_name' => 'Cust. Training',
        'id' => 'ind_training'
    ],
    'CPR' => [
        'display_name' => 'CPR',
        'short_name' => 'CPR',
        'id' => 'ind_cpr'
    ],
    '2025/26 1st Semiannual BSCI/ISC Target Status' => [
        'display_name' => 'BSCI/ISC Target',
        'short_name' => 'BSCI Target',
        'id' => 'ind_bsci'
    ],
    'Activity Report Submission' => [
        'display_name' => 'Activity Report',
        'short_name' => 'Activity',
        'id' => 'ind_activity'
    ],
    'Cost Saving Report Submission' => [
        'display_name' => 'Cost Saving Report',
        'short_name' => 'Cost Saving',
        'id' => 'ind_cost'
    ],
    'Lost Time Justification' => [
        'display_name' => 'Lost Time Justification',
        'short_name' => 'Lost Time',
        'id' => 'ind_losttime'
    ],
    'Attendance Approval Status' => [
        'display_name' => 'Attendance Approval',
        'short_name' => 'Attendance',
        'id' => 'ind_attendance'
    ],
    'Productivity' => [
        'display_name' => 'Productivity',
        'short_name' => 'Productivity',
        'id' => 'ind_productivity'
    ],
    'Employees Training Gap Clearance' => [
        'display_name' => 'Training Gap',
        'short_name' => 'Training',
        'id' => 'ind_traininggap'
    ],
    'Employees Issue Resolution Rate' => [
        'display_name' => 'Issue Resolution',
        'short_name' => 'Issue Res.',
        'id' => 'ind_issue'
    ]
];

// Color mapping for departments - modern, comfortable UI colors
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

// Pie chart colors - vibrant but comfortable
$pieColors = [
    '#38BDF8', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6',
    '#EC4899', '#14B8A6', '#F97316', '#6366F1', '#A855F7',
    '#06B6D4', '#84CC16', '#D946EF', '#F43F5E', '#0EA5E9'
];

// Fetch actual data from database for the selected month
$dbData = [];
$query = "SELECT indicator_name, department, percentage_achievement, actual_value, target_value, id
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
        'target' => $row['target_value'],
        'record_id' => $row['id']
    ];
}
$stmt->close();

// Calculate overall percentages and prepare data for display
$metricsData = [];
foreach ($indicators as $indicatorKey => $indicatorInfo) {
    $departmentData = [];
    $departmentRecordIds = [];
    
    if (isset($dbData[$indicatorKey]) && !empty($dbData[$indicatorKey])) {
        foreach ($dbData[$indicatorKey] as $dept => $values) {
            if ($isAdminDirector || $dept === $userDept) {
                $departmentData[$dept] = $values['percentage'];
                $departmentRecordIds[$dept] = $values['record_id'];
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
        $departmentRecordIds = [];
        
        if ($isAdminDirector) {
            foreach ($allDepartments as $dept) {
                $departmentData[$dept] = 0;
                $departmentRecordIds[$dept] = null;
            }
        } else {
            $departmentData[$userDept] = 0;
            $departmentRecordIds[$userDept] = null;
        }
    }
    
    $metricsData[$indicatorKey] = [
        'display_name' => $indicatorInfo['display_name'],
        'short_name' => $indicatorInfo['short_name'],
        'id' => $indicatorInfo['id'],
        'overall' => $overall,
        'departments' => $departmentData,
        'record_ids' => $departmentRecordIds
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
            --card-bg: #1E293B;
            --border-light: #334155;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--dark-bg);
            color: var(--light-bg);
        }
        
        /* Navigation */
        .navbar {
            background: var(--medium-bg);
            padding: 0.6rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid var(--border-light);
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
            box-shadow: 0 2px 5px rgba(56,189,248,0.3);
            background: var(--accent-hover);
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
        }
        
        .dashboard-header h1 {
            color: var(--accent);
            margin-bottom: 0.2rem;
            font-size: 1rem;
        }
        
        .month-selector {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            margin-top: 0.3rem;
        }
        
        .month-selector button {
            background: var(--accent);
            color: var(--dark-bg);
            border: none;
            padding: 0.2rem 0.6rem;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.7rem;
        }
        
        .month-selector h3 {
            color: var(--light-bg);
            margin: 0;
            font-size: 0.85rem;
        }
        
        /* Responsive Grid Layout */
        .metrics-grid {
            display: grid;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        /* Responsive grid columns based on screen size */
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
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
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
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .metric-title:hover {
            color: var(--accent-hover);
            text-decoration: underline;
        }
        
        .overall-score {
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            padding: 0.2rem 0.5rem;
            border-radius: 20px;
            transition: all 0.2s;
        }
        
        .overall-score:hover {
            transform: scale(1.05);
            background: rgba(56,189,248,0.1);
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
        
        .dept-bar-item:hover {
            background: rgba(56,189,248,0.1);
            transform: translateX(3px);
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
        
        /* Welcome Banner */
        .welcome-banner {
            background: rgba(56,189,248,0.1);
            border-left: 3px solid var(--accent);
            padding: 0.4rem 0.75rem;
            border-radius: 6px;
            margin-bottom: 0.75rem;
            font-size: 0.7rem;
        }
        
        /* No Data */
        .no-data {
            text-align: center;
            padding: 2rem;
            color: var(--light-bg);
            opacity: 0.7;
            background: var(--card-bg);
            border-radius: 12px;
        }
        
        /* Spinner */
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
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Scrollbar */
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
        
        /* Tooltip */
        [data-tooltip] {
            position: relative;
            cursor: help;
        }
        
        [data-tooltip]:before {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: var(--dark-bg);
            color: var(--light-bg);
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.6rem;
            white-space: nowrap;
            z-index: 1000;
            display: none;
            pointer-events: none;
        }
        
        [data-tooltip]:hover:before {
            display: block;
        }
        
        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container {
                padding: 0.5rem;
            }
            
            .metric-card {
                padding: 0.6rem;
            }
            
            .metric-title {
                font-size: 0.7rem;
            }
            
            .overall-score {
                font-size: 0.85rem;
            }
            
            .chart-container {
                max-width: 140px;
            }
            
            .dept-bar-label {
                font-size: 0.55rem;
            }
        }
        
        @media (max-width: 480px) {
            .chart-container {
                max-width: 120px;
            }
            
            .dept-bar-item {
                margin-bottom: 0.3rem;
            }
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
                    echo "📈 " . htmlspecialchars($userDept) . " Department Performance";
                }
                ?>
            </h1>
            <div class="month-selector">
                <button onclick="changeMonth('prev')">← Prev</button>
                <h3 id="current-month"><?php echo date('F Y', strtotime($dataMonth)); ?></h3>
                <button onclick="changeMonth('next')">Next →</button>
            </div>
        </div>
        
        <?php if (!$isAdminDirector): ?>
            <div class="welcome-banner">
                <strong>👋 <?php echo htmlspecialchars($userDept); ?> Department</strong> - Click on any metric or department for detailed view | Hover over charts for values
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
        const pieColors = <?php echo json_encode($pieColors); ?>;
        const currentMonth = '<?php echo $currentMonth; ?>';
        const isAdminDirector = <?php echo $isAdminDirector ? 'true' : 'false'; ?>;
        const userDepartment = '<?php echo $userDept; ?>';
        const allDepartments = <?php echo json_encode($allDepartments); ?>;
        
        // Store chart instances for cleanup
        const chartInstances = {};
        
        // Function to get color based on percentage
        function getScoreColor(percentage) {
            if (percentage >= 90) return 'var(--success)';
            if (percentage >= 70) return 'var(--warning)';
            return 'var(--danger)';
        }
        
        // Function to handle click on indicator
        function onIndicatorClick(indicatorKey, indicatorName) {
            console.log(`Clicked on indicator: ${indicatorName} (${indicatorKey})`);
            sessionStorage.setItem('selectedIndicator', indicatorKey);
            sessionStorage.setItem('selectedIndicatorName', indicatorName);
            sessionStorage.setItem('selectedMonth', currentMonth);
            window.location.href = `indicator_detail.php?indicator=${encodeURIComponent(indicatorKey)}&month=${currentMonth}`;
        }
        
        // Function to handle click on department
        function onDepartmentClick(department, indicatorKey, recordId, actualValue, targetValue, percentage) {
            console.log(`Clicked on ${department} - ${indicatorKey} (Record ID: ${recordId})`);
            sessionStorage.setItem('selectedDepartment', department);
            sessionStorage.setItem('selectedIndicator', indicatorKey);
            sessionStorage.setItem('selectedRecordId', recordId);
            sessionStorage.setItem('selectedMonth', currentMonth);
            sessionStorage.setItem('actualValue', actualValue);
            sessionStorage.setItem('targetValue', targetValue);
            sessionStorage.setItem('percentageValue', percentage);
            window.location.href = `department_detail.php?dept=${encodeURIComponent(department)}&indicator=${encodeURIComponent(indicatorKey)}&record=${recordId}&month=${currentMonth}`;
        }
        
        // Create pie chart
        function createPieChart(canvasId, percentage, metricName) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return null;
            
            const achieved = percentage;
            const remaining = Math.max(0, 100 - percentage);
            
            return new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Achieved', 'Remaining'],
                    datasets: [{
                        data: [achieved, remaining],
                        backgroundColor: [getScoreColor(percentage), 'rgba(51, 65, 85, 0.6)'],
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
                            },
                            backgroundColor: 'rgba(15, 23, 42, 0.9)',
                            titleColor: '#38BDF8',
                            bodyColor: '#F1F5F9'
                        }
                    },
                    onClick: function(event, elements) {
                        if (elements.length > 0) {
                            onIndicatorClick(metricName.replace(/\s+/g, '-').toLowerCase(), metricName);
                        }
                    }
                }
            });
        }
        
        // Render department bars
        function renderDepartmentBars(containerId, departments, indicatorKey) {
            const container = document.getElementById(containerId);
            if (!container) return;
            
            if (Object.keys(departments).length === 0) {
                container.innerHTML = '<div style="text-align: center; padding: 0.5rem; font-size: 0.6rem; color: #888;">No data</div>';
                return;
            }
            
            let html = '';
            for (const [dept, value] of Object.entries(departments)) {
                const percentageValue = parseFloat(value);
                const barWidth = Math.min(percentageValue, 100);
                const color = departmentColors[dept] || '#38BDF8';
                const scoreColor = getScoreColor(percentageValue);
                
                html += `
                    <div class="dept-bar-item" onclick="onDepartmentClick('${dept}', '${indicatorKey}', null, ${percentageValue}, 100, ${percentageValue})" data-tooltip="Click for details">
                        <div class="dept-bar-label">
                            <span class="dept-name" style="color: ${color};">${dept}</span>
                            <span class="dept-percentage" style="color: ${scoreColor};">${percentageValue}%</span>
                        </div>
                        <div class="dept-bar-container">
                            <div class="dept-bar-fill" style="width: ${barWidth}%; background: ${color};"></div>
                        </div>
                    </div>
                `;
            }
            
            container.innerHTML = html;
        }
        
        // Render the dashboard
        function renderDashboard() {
            const container = document.getElementById('dashboard-content');
            container.innerHTML = '';
            
            // Check if there's data
            let hasData = false;
            for (const [metricKey, metric] of Object.entries(metricsData)) {
                if (metric.overall > 0 || Object.keys(metric.departments).length > 0) {
                    hasData = true;
                    break;
                }
            }
            
            if (!hasData) {
                container.innerHTML = `
                    <div class="no-data">
                        <h4>📭 No Performance Data Available</h4>
                        <p>No verified data for ${document.getElementById('current-month').innerText}</p>
                        <p style="margin-top: 0.5rem; font-size: 0.7rem;">Please check back later or select a different month.</p>
                    </div>
                `;
                return;
            }
            
            const metricsGrid = document.createElement('div');
            metricsGrid.className = 'metrics-grid';
            
            for (const [metricKey, metric] of Object.entries(metricsData)) {
                // Filter departments for display
                let departmentsToShow = {};
                if (!isAdminDirector) {
                    if (metric.departments[userDepartment] !== undefined) {
                        departmentsToShow[userDepartment] = metric.departments[userDepartment];
                    }
                } else {
                    departmentsToShow = metric.departments;
                }
                
                if (Object.keys(departmentsToShow).length === 0 && metric.overall === 0) continue;
                
                const cardId = `card-${metricKey.replace(/\s+/g, '-').replace(/[\/]/g, '-')}`;
                const chartId = `chart-${metricKey.replace(/\s+/g, '-').replace(/[\/]/g, '-')}`;
                const barsId = `bars-${metricKey.replace(/\s+/g, '-').replace(/[\/]/g, '-')}`;
                const overallColor = getScoreColor(metric.overall);
                
                const card = document.createElement('div');
                card.className = 'metric-card';
                card.id = cardId;
                card.innerHTML = `
                    <div class="metric-header">
                        <div class="metric-title" onclick="onIndicatorClick('${metricKey}', '${metric.display_name}')" data-tooltip="Click for full report">
                            ${metric.display_name}
                        </div>
                        <div class="overall-score" style="color: ${overallColor};" onclick="onIndicatorClick('${metricKey}', '${metric.display_name}')" data-tooltip="Overall: ${metric.overall}%">
                            ${metric.overall}%
                        </div>
                    </div>
                    <div class="chart-container" onclick="onIndicatorClick('${metricKey}', '${metric.display_name}')">
                        <canvas id="${chartId}" width="180" height="140"></canvas>
                    </div>
                    <div id="${barsId}" class="dept-bars"></div>
                `;
                
                metricsGrid.appendChild(card);
                
                // Render chart and bars after DOM update
                setTimeout(() => {
                    const chart = createPieChart(chartId, metric.overall, metric.display_name);
                    if (chart) chartInstances[chartId] = chart;
                    renderDepartmentBars(barsId, departmentsToShow, metricKey);
                }, 10);
            }
            
            container.appendChild(metricsGrid);
        }
        
        // Cleanup charts on page unload
        function cleanupCharts() {
            for (const [id, chart] of Object.entries(chartInstances)) {
                if (chart && typeof chart.destroy === 'function') {
                    chart.destroy();
                }
            }
        }
        
        // Change month function
        function changeMonth(direction) {
            cleanupCharts();
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
        
        // Cleanup on page unload
        window.addEventListener('beforeunload', cleanupCharts);
        
        // Handle window resize for responsive adjustments
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                renderDashboard();
            }, 250);
        });
    </script>
</body>
</html>