<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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
    header('Location: ../login.php');
    exit();
}

// Define cost centers for the department
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
        'QAS' => 'Mgr. MRO Qty Ass & S/a'
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

// Define report types (indicators)
$indicators = [
    'Crew Meeting Minutes Submission' => 'Crew Meeting Minutes',
    'Exceptional Customer Experience Training' => 'Customer Exp. Training',
    'CPR' => 'CPR',
    '2025/26 1st Semiannual BSCI/ISC Target Status' => 'BSCI/ISC Target',
    'Activity Report Submission' => 'Activity Report',
    'Cost Saving Report Submission' => 'Cost Saving Report',
    'Lost time Justification' => 'Lost Time Justification',
    'Attendance Approval Status' => 'Attendance Approval',
    'Productivity' => 'Productivity',
    'Employees Training Gap Clearance' => 'Training Gap',
    'Employees Issue Resolution Rate' => 'Issue Resolution'
];

// Fetch ALL data from mro_cpr_report for this department (all cost centers)
$reportData = [];
$managerData = [];
$query = "SELECT report_type, cost_center_code, expected, completed, percentage 
          FROM mro_cpr_report 
          WHERE report_month = ? AND report_year = ? AND department = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("iis", $currentMonthNum, $currentYear, $userDept);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $reportType = $row['report_type'];
    $costCenter = $row['cost_center_code'];
    $expected = (int)$row['expected'];
    $completed = (int)$row['completed'];
    $percentage = round((float)$row['percentage'], 1);
    
    // Store manager-level data for detail view
    if (!isset($managerData[$reportType])) {
        $managerData[$reportType] = [];
    }
    $managerData[$reportType][$costCenter] = [
        'expected' => $expected,
        'completed' => $completed,
        'percentage' => $percentage,
        'name' => $costCenters[$userDept][$costCenter] ?? $costCenter
    ];
    
    // Store director-level data (for the card)
    if ($costCenter === 'DIR') {
        $reportData[$reportType] = $percentage;
    }
}
$stmt->close();

// Also get data from master_performance_data for this department (fallback)
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
    
    // Find matching report type
    foreach ($indicators as $key => $displayName) {
        if (strpos($indicatorName, $key) !== false || strpos($key, $indicatorName) !== false) {
            if (!isset($reportData[$key]) || $reportData[$key] == 0) {
                $reportData[$key] = $percentage;
            }
            break;
        }
    }
}
$masterStmt->close();

// Set default values for missing data
foreach ($indicators as $indicatorKey => $indicatorDisplay) {
    if (!isset($reportData[$indicatorKey])) {
        $reportData[$indicatorKey] = 0;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo htmlspecialchars($userDept); ?> Department - Performance Dashboard</title>
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
        
        .navbar {
            background: var(--medium-bg);
            padding: 0.5rem 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
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
        
        .info-banner {
            background: rgba(56,189,248,0.1);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
            font-size: 0.7rem;
            color: var(--accent);
        }
        
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .metric-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 0.8rem;
            border: 1px solid var(--border-light);
            transition: all 0.2s;
            text-align: center;
            cursor: pointer;
        }
        
        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-color: var(--accent);
        }
        
        .metric-title {
            font-size: 0.85rem;
            font-weight: bold;
            color: var(--accent);
            margin-bottom: 0.5rem;
            padding-bottom: 0.3rem;
            border-bottom: 1px solid var(--border-light);
        }
        
        .chart-container {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0.5rem auto;
        }
        
        .chart-container canvas {
            width: 100% !important;
            height: 100% !important;
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
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
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
        }
        
        .detail-table th {
            background: var(--dark-bg);
            color: var(--accent);
            font-weight: bold;
            position: sticky;
            top: 60px;
        }
        
        .detail-table tr:hover {
            background: rgba(56,189,248,0.05);
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
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="director_dashboard.php" class="navbar-brand">HR & Finance Dashboard</a>
            <div class="navbar-menu">
                <a href="director_dashboard.php" style="color: var(--accent);">My Dashboard</a>
                <!-- <a href="report_mro_cpr.php">Data Entry</a> -->
                <div class="user-info">
                    <button id="themeToggle" class="theme-toggle">☀️ Light</button>
                    <span class="user-name">👤 <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <span class="department-badge"><?php echo htmlspecialchars($userDept); ?></span>
                    <a href="#" onclick="openPasswordModal(); return false;" style="cursor: pointer;">🔑 Change Password</a>
                    <a href="../logout.php" class="btn">Logout</a>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="dashboard-header">
            <h1>📊 <?php echo htmlspecialchars($userDept); ?> Department - Performance Dashboard</h1>
            <div class="month-selector">
                <button onclick="changeMonth('prev')">← Prev</button>
                <h3>📅 <?php echo date('F Y', strtotime($dataMonth)); ?></h3>
                <button onclick="changeMonth('next')">Next →</button>
            </div>
        </div>
        
        <div class="info-banner">
            📈 Welcome <?php echo htmlspecialchars($_SESSION['full_name']); ?> - Click on any card to view detailed manager breakdown
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
                <div class="metric-card" onclick="showDetail('<?php echo htmlspecialchars($indicatorKey); ?>', '<?php echo htmlspecialchars($indicatorDisplay); ?>')">
                    <div class="metric-title"><?php echo htmlspecialchars($indicatorDisplay); ?></div>
                    <div class="chart-container">
                        <canvas id="<?php echo $chartId; ?>" width="120" height="120"></canvas>
                    </div>
                    <div class="percentage-display" style="color: <?php echo $percentageColor; ?>;">
                        <?php echo $percentage; ?>%
                    </div>
                    <div class="actual-target">
                        <span>Target: 100%</span>
                        <span>Achieved: <?php echo $percentage; ?>%</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Detail Modal -->
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
        
        let chartInstances = {};
        
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
                        legend: { display: false },
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
        
        function showDetail(indicatorKey, indicatorDisplay) {
            const modal = document.getElementById('detailModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalBody = document.getElementById('modalBody');
            
            modalTitle.innerHTML = `${indicatorDisplay} - ${userDept} Department Details`;
            
            const data = managerData[indicatorKey] || {};
            
            // Calculate totals
            let totalExpected = 0;
            let totalCompleted = 0;
            let directorData = null;
            const managerRows = [];
            
            for (const [code, manager] of Object.entries(costCenters)) {
                const record = data[code] || { expected: 0, completed: 0, percentage: 0, name: manager };
                
                if (code === 'DIR') {
                    directorData = record;
                } else {
                    totalExpected += record.expected;
                    totalCompleted += record.completed;
                    managerRows.push({
                        name: manager,
                        code: code,
                        expected: record.expected,
                        completed: record.completed,
                        percentage: record.percentage
                    });
                }
            }
            
            const totalPercentage = totalExpected > 0 ? (totalCompleted / totalExpected) * 100 : 0;
            
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
            
            // Manager rows
            for (const manager of managerRows) {
                const notCompleted = manager.expected - manager.completed;
                const percentageColor = getColor(manager.percentage);
                tableHtml += `
                    <tr>
                        <td>${escapeHtml(manager.name)}</td>
                        <td>${manager.expected}</td>
                        <td>${manager.completed}</td>
                        <td>${notCompleted}</td>
                        <td style="color: ${percentageColor}; font-weight: bold;">${manager.percentage}%</td>
                        <td>
                            <div class="progress-bar-modal">
                                <div class="progress-fill-modal" style="width: ${manager.percentage}%; background: ${percentageColor};"></div>
                            </div>
                        </td>
                    </tr>
                `;
            }
            
            // Director row
            if (directorData || totalExpected > 0) {
                const dirExpected = directorData ? directorData.expected : totalExpected;
                const dirCompleted = directorData ? directorData.completed : totalCompleted;
                const dirPercentage = directorData ? directorData.percentage : totalPercentage;
                const dirNotCompleted = dirExpected - dirCompleted;
                const dirColor = getColor(dirPercentage);
                tableHtml += `
                    <tr class="director-row">
                        <td><strong>${userDept === 'BMT' ? 'Dir. BMT' : 'Dir. ' + userDept}</strong></td>
                        <td><strong>${dirExpected}</strong></td>
                        <td><strong>${dirCompleted}</strong></td>
                        <td><strong>${dirNotCompleted}</strong></td>
                        <td style="color: ${dirColor}; font-weight: bold;"><strong>${dirPercentage.toFixed(1)}%</strong></td>
                        <td>
                            <div class="progress-bar-modal">
                                <div class="progress-fill-modal" style="width: ${dirPercentage}%; background: ${dirColor};"></div>
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
        
        function initializeCharts() {
            let chartIndex = 0;
            for (const [indicatorKey, indicatorDisplay] of Object.entries(<?php echo json_encode($indicators); ?>)) {
                const percentage = reportData[indicatorKey] || 0;
                const chartId = `chart-${chartIndex}`;
                const chart = createGaugeChart(chartId, percentage);
                if (chart) chartInstances[chartId] = chart;
                chartIndex++;
            }
        }
        
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
        });

    // Function to open password change modal
function openPasswordModal() {
    // Check if modal already exists
    if (document.getElementById('passwordModalOverlay')) {
        return;
    }
    
    // Create modal container
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
    
    // Create iframe to load the password change page
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
    
    // Store reference to close function
    window.closePasswordPopup = function() {
        if (modalOverlay && modalOverlay.parentNode) {
            modalOverlay.remove();
        }
        delete window.closePasswordPopup;
    };
    
    // Close on Escape key
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

// Send heartbeat every 5 minutes
setInterval(keepSessionAlive, 5 * 60 * 1000);
    </script>
</body>
</html>