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
            overflow-x: auto;
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
        
        /* Main Container - No horizontal scroll */
        .container {
            width: 100%;
            margin: 0;
            padding: 0.75rem 1rem;
            overflow-x: auto;
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
        
        /* Table Layout - No scrolling, all visible */
        .dashboard-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            font-size: 0.7rem;
        }
        
        .dashboard-table th,
        .dashboard-table td {
            padding: 0.5rem 0.4rem;
            text-align: center;
            border: 1px solid var(--border-light);
            vertical-align: middle;
        }
        
        .dashboard-table th {
            background: var(--dark-bg);
            color: var(--accent);
            font-weight: bold;
            font-size: 0.7rem;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .indicator-cell {
            background: var(--dark-bg);
            font-weight: bold;
            text-align: left;
            position: sticky;
            left: 0;
            z-index: 5;
            min-width: 120px;
        }
        
        .indicator-name {
            font-size: 0.7rem;
            font-weight: bold;
            color: var(--accent);
        }
        
        .overall-cell {
            background: rgba(56,189,248,0.1);
            font-weight: bold;
            min-width: 60px;
        }
        
        .overall-value {
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .dept-cell {
            cursor: pointer;
            transition: all 0.2s;
            min-width: 70px;
        }
        
        .dept-cell:hover {
            background: rgba(56,189,248,0.2);
            transform: scale(1.02);
        }
        
        .percentage-value {
            font-weight: bold;
            font-size: 0.75rem;
        }
        
        .percentage-high {
            color: var(--success);
        }
        
        .percentage-medium {
            color: var(--warning);
        }
        
        .percentage-low {
            color: var(--danger);
        }
        
        .progress-bar-container {
            width: 100%;
            height: 4px;
            background: var(--dark-bg);
            border-radius: 2px;
            margin-top: 4px;
            overflow: hidden;
        }
        
        .progress-bar-fill {
            height: 100%;
            border-radius: 2px;
            transition: width 0.3s;
        }
        
        /* Welcome Banner */
        .welcome-banner {
            background: rgba(56,189,248,0.1);
            border-left: 3px solid var(--accent);
            padding: 0.3rem 0.75rem;
            border-radius: 6px;
            margin-bottom: 0.75rem;
            font-size: 0.7rem;
        }
        
        /* Clickable indicator styling */
        .clickable {
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .clickable:hover {
            background: rgba(56,189,248,0.15);
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
            padding: 0.3rem 0.6rem;
            border-radius: 6px;
            font-size: 0.65rem;
            white-space: nowrap;
            z-index: 1000;
            display: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            pointer-events: none;
        }
        
        [data-tooltip]:hover:before {
            display: block;
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
        
        /* Responsive font sizes */
        @media (max-width: 1200px) {
            .dashboard-table th,
            .dashboard-table td {
                padding: 0.4rem 0.3rem;
                font-size: 0.65rem;
            }
            .indicator-cell {
                min-width: 100px;
            }
            .percentage-value {
                font-size: 0.7rem;
            }
        }
        
        @media (max-width: 900px) {
            .dashboard-table th,
            .dashboard-table td {
                padding: 0.3rem 0.2rem;
                font-size: 0.6rem;
            }
            .indicator-cell {
                min-width: 90px;
            }
        }
        
        /* Custom scrollbar for table only if needed */
        .table-wrapper {
            overflow-x: auto;
            border-radius: 12px;
        }
        
        .table-wrapper::-webkit-scrollbar {
            height: 6px;
        }
        
        .table-wrapper::-webkit-scrollbar-track {
            background: var(--dark-bg);
            border-radius: 3px;
        }
        
        .table-wrapper::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="md_dashboard.php" class="navbar-brand">HR & Finance Dashboard</a>
            <div class="navbar-menu">
                <a href="md_dashboard.php" class="btn" style="background: transparent; color: var(--accent);">Dashboard</a>
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
                <strong>👋 <?php echo htmlspecialchars($userDept); ?> Department</strong> - Click on any metric or department for detailed view
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
        const allDepartments = <?php echo json_encode($allDepartments); ?>;
        
        // Function to get color based on percentage
        function getScoreColor(percentage) {
            if (percentage >= 90) return 'var(--success)';
            if (percentage >= 70) return 'var(--warning)';
            return 'var(--danger)';
        }
        
        // Function to handle click on indicator
        function onIndicatorClick(indicatorKey, indicatorName) {
            console.log(`Clicked on indicator: ${indicatorName} (${indicatorKey})`);
            // Store clicked indicator in session for detail page
            sessionStorage.setItem('selectedIndicator', indicatorKey);
            sessionStorage.setItem('selectedIndicatorName', indicatorName);
            sessionStorage.setItem('selectedMonth', currentMonth);
            // Redirect to detail page (to be built later)
            window.location.href = `indicator_detail.php?indicator=${encodeURIComponent(indicatorKey)}&month=${currentMonth}`;
        }
        
        // Function to handle click on department cell
        function onDepartmentClick(department, indicatorKey, recordId, actualValue, targetValue, percentage) {
            console.log(`Clicked on ${department} - ${indicatorKey} (Record ID: ${recordId})`);
            // Store clicked data for detail page
            sessionStorage.setItem('selectedDepartment', department);
            sessionStorage.setItem('selectedIndicator', indicatorKey);
            sessionStorage.setItem('selectedRecordId', recordId);
            sessionStorage.setItem('selectedMonth', currentMonth);
            sessionStorage.setItem('actualValue', actualValue);
            sessionStorage.setItem('targetValue', targetValue);
            sessionStorage.setItem('percentageValue', percentage);
            // Redirect to detail page (to be built later)
            window.location.href = `department_detail.php?dept=${encodeURIComponent(department)}&indicator=${encodeURIComponent(indicatorKey)}&record=${recordId}&month=${currentMonth}`;
        }
        
        // Render the dashboard as a table (no scroll, all visible)
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
            
            // Create table wrapper for horizontal scroll if needed (but with all columns visible)
            const tableWrapper = document.createElement('div');
            tableWrapper.className = 'table-wrapper';
            
            const table = document.createElement('table');
            table.className = 'dashboard-table';
            
            // Build table header
            const thead = document.createElement('thead');
            const headerRow = document.createElement('tr');
            
            // Indicator column header
            const indicatorHeader = document.createElement('th');
            indicatorHeader.textContent = 'Performance Metrics';
            indicatorHeader.style.textAlign = 'left';
            indicatorHeader.style.position = 'sticky';
            indicatorHeader.style.left = '0';
            indicatorHeader.style.background = 'var(--dark-bg)';
            headerRow.appendChild(indicatorHeader);
            
            // Overall column header
            const overallHeader = document.createElement('th');
            overallHeader.textContent = 'Overall';
            overallHeader.className = 'overall-cell';
            headerRow.appendChild(overallHeader);
            
            // Department column headers
            const departmentsToShow = isAdminDirector ? allDepartments : [userDepartment];
            for (const dept of departmentsToShow) {
                const deptHeader = document.createElement('th');
                deptHeader.textContent = dept;
                deptHeader.style.background = departmentColors[dept] + '20';
                deptHeader.style.color = departmentColors[dept];
                headerRow.appendChild(deptHeader);
            }
            
            thead.appendChild(headerRow);
            table.appendChild(thead);
            
            // Build table body
            const tbody = document.createElement('tbody');
            
            for (const [metricKey, metric] of Object.entries(metricsData)) {
                const row = document.createElement('tr');
                
                // Indicator cell (clickable)
                const indicatorCell = document.createElement('td');
                indicatorCell.className = 'indicator-cell clickable';
                indicatorCell.setAttribute('data-tooltip', `Click to view detailed report for ${metric.display_name}`);
                indicatorCell.style.cursor = 'pointer';
                indicatorCell.onclick = () => onIndicatorClick(metricKey, metric.display_name);
                indicatorCell.innerHTML = `
                    <div class="indicator-name">${metric.display_name}</div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: ${Math.min(metric.overall, 100)}%; background: ${getScoreColor(metric.overall)};"></div>
                    </div>
                `;
                row.appendChild(indicatorCell);
                
                // Overall cell
                const overallCell = document.createElement('td');
                overallCell.className = 'overall-cell';
                const overallColor = getScoreColor(metric.overall);
                overallCell.innerHTML = `
                    <div class="overall-value" style="color: ${overallColor};">${metric.overall}%</div>
                `;
                row.appendChild(overallCell);
                
                // Department cells (clickable)
                for (const dept of departmentsToShow) {
                    const deptCell = document.createElement('td');
                    deptCell.className = 'dept-cell clickable';
                    const percentage = metric.departments[dept] || 0;
                    const recordId = metric.record_ids ? metric.record_ids[dept] : null;
                    const actualValue = percentage; // Will be enhanced later
                    const targetValue = 100;
                    const percentageColor = getScoreColor(percentage);
                    
                    deptCell.setAttribute('data-tooltip', `Click to view details for ${dept}: ${percentage}%`);
                    deptCell.style.cursor = 'pointer';
                    deptCell.onclick = () => onDepartmentClick(dept, metricKey, recordId, actualValue, targetValue, percentage);
                    deptCell.innerHTML = `
                        <div class="percentage-value" style="color: ${percentageColor};">${percentage}%</div>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill" style="width: ${Math.min(percentage, 100)}%; background: ${percentageColor};"></div>
                        </div>
                    `;
                    row.appendChild(deptCell);
                }
                
                tbody.appendChild(row);
            }
            
            table.appendChild(tbody);
            tableWrapper.appendChild(table);
            container.appendChild(tableWrapper);
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
        
        // Initialize dashboard when page loads
        document.addEventListener('DOMContentLoaded', function() {
            renderDashboard();
        });
    </script>
</body>
</html>