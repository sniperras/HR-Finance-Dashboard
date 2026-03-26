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
    header('Location: ../login.php');
    exit();
}

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
            max-width: 1200px;
            margin: 0 auto;
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
        
        /* Department Header Card */
        .department-header-card {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-hover) 100%);
            color: var(--dark-bg);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .department-header-card h2 {
            font-size: 1.2rem;
            margin-bottom: 0.3rem;
        }
        
        .department-header-card p {
            font-size: 0.7rem;
            opacity: 0.9;
        }
        
        /* Table Layout */
        .dashboard-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            font-size: 0.8rem;
        }
        
        .dashboard-table th,
        .dashboard-table td {
            padding: 0.8rem 0.6rem;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
            vertical-align: middle;
        }
        
        .dashboard-table th {
            background: var(--dark-bg);
            color: var(--accent);
            font-weight: bold;
            font-size: 0.75rem;
        }
        
        .dashboard-table tr:hover {
            background: rgba(56,189,248,0.05);
        }
        
        .indicator-cell {
            font-weight: bold;
            color: var(--accent);
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .indicator-cell:hover {
            color: var(--accent-hover);
            text-decoration: underline;
        }
        
        .percentage-cell {
            font-weight: bold;
            text-align: center;
        }
        
        .progress-cell {
            min-width: 150px;
        }
        
        .progress-bar-container {
            background: var(--dark-bg);
            border-radius: 10px;
            overflow: hidden;
            height: 8px;
            width: 100%;
        }
        
        .progress-bar-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s;
        }
        
        .actual-cell, .target-cell {
            text-align: center;
            font-family: monospace;
            font-size: 0.75rem;
        }
        
        .no-data {
            text-align: center;
            padding: 2rem;
            color: var(--light-bg);
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
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .clickable {
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0.5rem;
            }
            
            .dashboard-table th,
            .dashboard-table td {
                padding: 0.5rem 0.3rem;
                font-size: 0.7rem;
            }
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
                    <span class="department-badge"><?php echo htmlspecialchars($userDept); ?></span>
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
                <h3 id="current-month"><?php echo date('F Y', strtotime($dataMonth)); ?></h3>
                <button onclick="changeMonth('next')">Next →</button>
            </div>
        </div>
        
        <div class="department-header-card">
            <h2><?php echo htmlspecialchars($userDept); ?> Department</h2>
            <p>Performance Metrics for <?php echo date('F Y', strtotime($dataMonth)); ?> | Click on any metric for detailed report</p>
        </div>
        
        <div id="dashboard-content">
            <div class="spinner"></div>
        </div>
    </div>
    
    <script>
        // Data passed from PHP
        const metricsData = <?php echo json_encode($metricsData); ?>;
        const currentMonth = '<?php echo $currentMonth; ?>';
        const userDepartment = '<?php echo $userDept; ?>';
        
        // Function to get color based on percentage
        function getScoreColor(percentage) {
            if (percentage >= 90) return '#10B981';
            if (percentage >= 70) return '#F59E0B';
            return '#EF4444';
        }
        
        // Function to handle click on indicator
        function onIndicatorClick(indicatorKey, indicatorName, recordId, actualValue, targetValue, percentage) {
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
            
            const table = document.createElement('table');
            table.className = 'dashboard-table';
            
            table.innerHTML = `
                <thead>
                    <tr>
                        <th>Performance Metric</th>
                        <th>Actual</th>
                        <th>Target</th>
                        <th>Percentage</th>
                        <th>Progress</th>
                    </tr>
                </thead>
                <tbody id="dashboard-table-body"></tbody>
            `;
            
            const tbody = table.querySelector('#dashboard-table-body');
            
            for (const [metricKey, metric] of Object.entries(metricsData)) {
                const percentage = metric.percentage;
                const actual = metric.actual;
                const target = metric.target;
                const percentageColor = getScoreColor(percentage);
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td class="indicator-cell" onclick="onIndicatorClick('${metricKey}', '${metric.display_name}', ${metric.record_id}, ${actual}, ${target}, ${percentage})">${metric.display_name}</td>
                    <td class="actual-cell">${actual}${actual > 0 ? '%' : ''}</td>
                    <td class="target-cell">${target}${target > 0 ? '%' : ''}</td>
                    <td class="percentage-cell" style="color: ${percentageColor}; font-weight: bold;">${percentage}%</td>
                    <td class="progress-cell">
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill" style="width: ${Math.min(percentage, 100)}%; background: ${percentageColor};"></div>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            }
            
            container.appendChild(table);
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
    </script>
</body>
</html>