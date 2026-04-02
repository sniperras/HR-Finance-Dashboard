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

// Check if this is a department director (not admin)
$isAdminDirector = ($userDept === '' || $userDept === 'admin');

// If admin director, redirect to admin dashboard
if ($isAdminDirector) {
    header('Location: md_dashboard.php?month=' . $currentMonth);
    exit();
}

// If no department found, redirect to login
if (empty($userDept)) {
    header('Location: ../index.php');
    exit();
}

// Define all indicators with their display names
$indicators = [
    // 'Team Leaders Clock-in Data' => [
    //     'display_name' => 'Team Leaders Clock-in',
    //     'short_name' => 'Clock-in',
    //     'id' => 'ind_clockin'
    // ],
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

// Fetch actual data from database for the selected month and department
$dbData = [];
$query = "SELECT indicator_name, percentage_achievement, actual_value, target_value, id
          FROM master_performance_data 
          WHERE data_month = ? AND department = ? AND verification_status = 'verified'";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $dataMonth, $userDept);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $indicator = $row['indicator_name'];
    $dbData[$indicator] = [
        'percentage' => round($row['percentage_achievement'], 1),
        'actual' => $row['actual_value'],
        'target' => $row['target_value'],
        'record_id' => $row['id']
    ];
}
$stmt->close();

// Prepare metrics data for display
$metricsData = [];
foreach ($indicators as $indicatorKey => $indicatorInfo) {
    if (isset($dbData[$indicatorKey])) {
        $data = $dbData[$indicatorKey];
        $metricsData[$indicatorKey] = [
            'display_name' => $indicatorInfo['display_name'],
            'short_name' => $indicatorInfo['short_name'],
            'id' => $indicatorInfo['id'],
            'percentage' => $data['percentage'],
            'actual' => $data['actual'],
            'target' => $data['target'],
            'record_id' => $data['record_id']
        ];
    } else {
        $metricsData[$indicatorKey] = [
            'display_name' => $indicatorInfo['display_name'],
            'short_name' => $indicatorInfo['short_name'],
            'id' => $indicatorInfo['id'],
            'percentage' => 0,
            'actual' => 0,
            'target' => 100,
            'record_id' => null
        ];
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo htmlspecialchars($userDept); ?> Department Performance Dashboard</title>
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
        
        /* Navigation */
        .navbar {
            background: var(--medium-bg);
            padding: 0.5rem 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
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
        
        @media (max-width: 768px) {
            .navbar-container {
                padding: 0 1rem;
            }
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
            background: rgba(56,189,248,0.1);
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
            box-shadow: 0 2px 5px rgba(56,189,248,0.3);
            background: var(--accent-hover);
        }
        
        /* Theme Toggle Button */
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
        
        /* Main Container */
        .container {
            width: 100%;
            max-width: 100%;
            margin: 0;
            padding: 0.75rem 1rem;
        }
        
        @media (min-width: 1400px) {
            .container {
                padding: 0.75rem 2rem;
            }
        }
        
        @media (min-width: 1920px) {
            .container {
                padding: 0.75rem 4rem;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0.5rem;
            }
        }
        
        /* Dashboard Header - Compact */
        .dashboard-header {
            background: linear-gradient(135deg, var(--medium-bg) 0%, var(--dark-bg) 100%);
            padding: 0.6rem 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            border: 1px solid var(--border-light);
            transition: background 0.3s;
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
            color: var(--text-primary);
            margin: 0;
            font-size: 0.85rem;
        }
        
        /* Department Header Card - Compact */
        .department-header-card {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-hover) 100%);
            color: var(--dark-bg);
            padding: 0.6rem 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .department-header-card h2 {
            font-size: 1rem;
            margin-bottom: 0.2rem;
        }
        
        .department-header-card p {
            font-size: 0.65rem;
            opacity: 0.9;
        }
        
        /* Metrics Grid - 4 columns on large screens for all 12 metrics in one view */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.8rem;
            margin-bottom: 0.5rem;
        }
        
        @media (max-width: 1200px) {
            .metrics-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 0.7rem;
            }
        }
        
        @media (max-width: 900px) {
            .metrics-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.6rem;
            }
        }
        
        @media (max-width: 600px) {
            .metrics-grid {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
        }
        
        /* Metric Card - Compact */
        .metric-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 0.6rem;
            transition: all 0.2s;
            border: 1px solid var(--border-light);
            display: flex;
            flex-direction: column;
            cursor: pointer;
        }
        
        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-color: var(--accent);
        }
        
        .metric-title {
            font-size: 0.7rem;
            font-weight: bold;
            color: var(--accent);
            margin-bottom: 0.5rem;
            padding-bottom: 0.3rem;
            border-bottom: 1px solid var(--accent);
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Chart Container - Smaller */
        .chart-container {
            position: relative;
            width: 100%;
            max-width: 130px;
            margin: 0 auto;
            cursor: pointer;
        }
        
        .chart-container canvas {
            width: 100% !important;
            height: auto !important;
            max-height: 110px;
        }
        
        /* Metric Values - Compact */
        .metric-values {
            display: flex;
            justify-content: space-between;
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 1px solid var(--border-light);
            font-size: 0.6rem;
        }
        
        .metric-values div {
            text-align: center;
            flex: 1;
        }
        
        .metric-values .value-label {
            color: var(--text-secondary);
            font-size: 0.55rem;
            margin-bottom: 0.15rem;
        }
        
        .metric-values .value-number {
            font-weight: bold;
            font-size: 0.7rem;
        }
        
        .actual-value {
            color: var(--accent);
        }
        
        .target-value {
            color: var(--warning);
        }
        
        .percentage-value {
            font-weight: bold;
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
            border: 2px solid var(--border-light);
            border-top: 2px solid var(--accent);
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 1.5rem auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Scrollbar */
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
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        }
        
        body.light-theme .dashboard-header {
            background: linear-gradient(135deg, #F8FAFC 0%, #F1F5F9 100%);
        }
        
        body.light-theme .department-header-card {
            background: linear-gradient(135deg, #0284C7 0%, #0EA5E9 100%);
        }
        
        body.light-theme .theme-toggle {
            border-color: #0284C7;
            color: #0284C7;
        }
        
        body.light-theme .theme-toggle:hover {
            background: #0284C7;
            color: white;
        }
        
        /* Tooltip on hover */
        .metric-card {
            position: relative;
        }
        
        .metric-card:hover::after {
            content: "Click for detailed report";
            position: absolute;
            bottom: -25px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--dark-bg);
            color: var(--accent);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.6rem;
            white-space: nowrap;
            z-index: 100;
            pointer-events: none;
            border: 1px solid var(--accent);
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="director_dashboard.php" class="navbar-brand">HR & Finance Dashboard</a>
            <div class="navbar-menu">
                <a href="director_dashboard.php" style="color: var(--accent);">Dashboard</a>
                <div class="user-info">
                    <button id="themeToggle" class="theme-toggle">☀️ Light</button>
                    <span class="user-name">👤 <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <span class="department-badge"> <?php echo htmlspecialchars($userDept); ?></span>
                    <a href="../logout.php" class="btn">Logout</a>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="dashboard-header">
            <h1>📈 <?php echo htmlspecialchars($userDept); ?> Department Performance Dashboard</h1>
            <div class="month-selector">
                <button onclick="changeMonth('prev')">← Prev</button>
                <h3 id="current-month">📅 <?php echo date('F Y', strtotime($dataMonth)); ?></h3>
                <button onclick="changeMonth('next')">Next →</button>
            </div>
        </div>
        
        <div class="department-header-card">
            <h2>🎯 <?php echo htmlspecialchars($userDept); ?> Department</h2>
            <p>Performance Metrics for <?php echo date('F Y', strtotime($dataMonth)); ?> | Click any card for details</p>
        </div>
        
        <div id="dashboard-content">
            <div class="spinner"></div>
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
        
        // Data passed from PHP
        const metricsData = <?php echo json_encode($metricsData); ?>;
        const currentMonth = '<?php echo $currentMonth; ?>';
        const userDepartment = '<?php echo $userDept; ?>';
        
        // Store chart instances
        let chartInstances = {};
        
        // Function to get color based on percentage
        function getScoreColor(percentage) {
            if (percentage >= 90) return '#10B981';
            if (percentage >= 70) return '#F59E0B';
            return '#EF4444';
        }
        
        // Function to handle click on metric card
        function onMetricClick(indicatorKey, indicatorName, recordId, actualValue, targetValue, percentage) {
            sessionStorage.setItem('selectedIndicator', indicatorKey);
            sessionStorage.setItem('selectedIndicatorName', indicatorName);
            sessionStorage.setItem('selectedRecordId', recordId);
            sessionStorage.setItem('selectedMonth', currentMonth);
            sessionStorage.setItem('selectedDepartment', userDepartment);
            sessionStorage.setItem('actualValue', actualValue);
            sessionStorage.setItem('targetValue', targetValue);
            sessionStorage.setItem('percentageValue', percentage);
            window.location.href = `indicator_detail.php?indicator=${encodeURIComponent(indicatorKey)}&month=${currentMonth}&dept=${encodeURIComponent(userDepartment)}`;
        }
        
        // Create pie chart
        function createPieChart(canvasId, percentage, indicatorKey, indicatorName, recordId, actualValue, targetValue) {
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
                        backgroundColor: [color, 'rgba(51, 65, 85, 0.5)'],
                        borderWidth: 0,
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    cutout: '65%',
                    plugins: {
                        legend: { display: false },
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
                    onClick: function() {
                        onMetricClick(indicatorKey, indicatorName, recordId, actualValue, targetValue, percentage);
                    }
                }
            });
        }
        
        function renderDashboard() {
            const container = document.getElementById('dashboard-content');
            container.innerHTML = '';
            
            let hasData = false;
            for (const [metricKey, metric] of Object.entries(metricsData)) {
                if (metric.percentage > 0) {
                    hasData = true;
                    break;
                }
            }
            
            if (!hasData) {
                container.innerHTML = `<div class="no-data"><h4>📭 No Performance Data Available</h4><p>No verified data for ${document.getElementById('current-month').innerText}</p></div>`;
                return;
            }
            
            const metricsGrid = document.createElement('div');
            metricsGrid.className = 'metrics-grid';
            
            for (const [metricKey, metric] of Object.entries(metricsData)) {
                const percentage = metric.percentage;
                const actual = metric.actual;
                const target = metric.target;
                const percentageColor = getScoreColor(percentage);
                const chartId = `chart-${metricKey.replace(/\s+/g, '-').replace(/[\/]/g, '-')}`;
                
                const card = document.createElement('div');
                card.className = 'metric-card';
                card.onclick = () => onMetricClick(metricKey, metric.display_name, metric.record_id, actual, target, percentage);
                
                card.innerHTML = `
                    <div class="metric-title" title="${metric.display_name}">${metric.display_name}</div>
                    <div class="chart-container">
                        <canvas id="${chartId}" width="120" height="120"></canvas>
                    </div>
                    <div class="metric-values">
                        <div>
                            <div class="value-label">Actual</div>
                            <div class="value-number actual-value">${actual}${actual > 0 ? '%' : ''}</div>
                        </div>
                        <div>
                            <div class="value-label">Target</div>
                            <div class="value-number target-value">${target}${target > 0 ? '%' : ''}</div>
                        </div>
                        <div>
                            <div class="value-label">%</div>
                            <div class="value-number percentage-value" style="color: ${percentageColor};">${percentage}%</div>
                        </div>
                    </div>
                `;
                
                metricsGrid.appendChild(card);
                
                // Create chart after card is added to DOM
                setTimeout(() => {
                    const chart = createPieChart(chartId, percentage, metricKey, metric.display_name, metric.record_id, actual, target);
                    if (chart) chartInstances[chartId] = chart;
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
            window.location.href = `director_dashboard.php?month=${newMonth}`;
        }
        
        // Cleanup charts on page unload
        function cleanupCharts() {
            for (const [id, chart] of Object.entries(chartInstances)) {
                if (chart && typeof chart.destroy === 'function') {
                    chart.destroy();
                }
            }
        }
        
        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            new ThemeManager();
            renderDashboard();
        });
        
        window.addEventListener('beforeunload', cleanupCharts);
        
        // Handle window resize
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