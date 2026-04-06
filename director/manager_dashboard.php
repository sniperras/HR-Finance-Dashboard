<?php
require_once '../session_config.php';
require_once '../includes/auth.php';
requireRole('manager');

$conn = getConnection();
$currentMonth = $_GET['month'] ?? date('Y-m');
$dataMonth = $currentMonth . '-01';
$currentYear = date('Y', strtotime($dataMonth));
$currentMonthNum = date('m', strtotime($dataMonth));

// Get logged-in user's info
$username = $_SESSION['username'];
$fullName = $_SESSION['full_name'];
$userRole = $_SESSION['user_role'];

// Get user's department and cost center from database
$userQuery = "SELECT section, costcenter, full_name FROM users WHERE username = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("s", $username);
$stmt->execute();
$userResult = $stmt->get_result();
$userData = $userResult->fetch_assoc();
$stmt->close();

$userDept = $userData['section'] ?? '';
$userCostCenter = $userData['costcenter'] ?? '';
$userFullName = $userData['full_name'] ?? $fullName;

// Define cost centers for display names
$costCenterNames = [
    // BMT
    'ACS' => 'Mgr. A/C Structure Maint',
    'AVS' => 'Mgr. Avionics Sys Maint',
    'B787' => 'Mgr. B787/767 Mainten',
    'B737' => 'Mgr. B737 Maintenance',
    'CAB' => 'Mgr. Cabin Maint',
    'B777' => 'Mgr. B777/A350 Mainten',
    'APS' => 'Mgr. A/C Patch Svs.',
    'TEC' => 'Mgr. Technical Supp.',
    // LMT
    'DMM' => 'Duty Manager MCC',
    'ADM' => 'MGR. Admin & Outstation Maint',
    'ALM' => 'Mgr. A/C Line Maint.',
    'GAM' => 'Mgr. General Ava. A/C Maint.',
    'TPL' => 'MGR. Turbo Prop & Light A/C Maint',
    'ACM' => 'Mgr. A/C Cabin Maint',
    // CMT
    'WKH' => 'Mgr. Wire Kit & Harness Prod.',
    'CES' => 'Mgr. Computerized Equipment Shop',
    'NDT' => 'Mgr. NDT, Stand. & Part Recv. Insp.',
    'MES' => 'Comp. Maint. Engineering Support',
    'MCS' => 'Mgr. Mechanical Comp Shops',
    // EMT
    'EMI' => 'Mgr. Engine Maint. Inspection',
    'ETS' => 'Mgr. Technical Support',
    'RNP' => 'Mgr. RNP PW4000/LEAP/APU Eng. Maint.',
    'CFM' => 'Mgr. CFM56/GE90/GENX & Turbo Prop. Engines',
    'RSH' => 'Mgr. Repair Shops',
    // AEP
    'ALE' => 'MGR. A/C Lease, EIS & Special Projects',
    'AMP' => 'MGR. A/C Maint. Prog. & Task Card Engineer',
    'MPR' => 'MGR. Maint. Plng. & Record Control',
    'EQA' => 'MGR. Engineering Quality Assurance',
    'ASE' => 'Mgr. A/C Systems Eng',
    'ADO' => 'MGR. A/C Design Organization',
    // MSM
    'MSM' => 'Mgr. MRO Sales and Marketing',
    'MCS' => 'Mgr. MRO Customer Support',
    // QA
    'QAS' => 'Mgr. MRO Qty Ass & S/a',
    // PSCM
    'GWC' => 'Mgr. Grp Warp Cont Mgt',
    'TPU' => 'Mgr. Tactical Purchase',
    'MMP' => 'Mgr. MRO Material Planning',
    'EMP' => 'Mgr. Engine Maint/Tactical Pur',
    'WAP' => 'Mgr. Warehouse A/C Part',
    'EXT' => 'Extra Sourcing',
    'PLC' => 'Mgr. Purchase-LMT&CMT Maint.',
    // MRO HR
    'HR' => 'Mgr. Human Resources',
];

// Get all indicators for this department from mro_cpr_report
$indicatorsQuery = "SELECT DISTINCT report_type FROM mro_cpr_report WHERE department = ? AND cost_center_code = ? ORDER BY report_type";
$stmt = $conn->prepare($indicatorsQuery);
$stmt->bind_param("ss", $userDept, $userCostCenter);
$stmt->execute();
$indicatorsResult = $stmt->get_result();

$indicators = [];
while ($row = $indicatorsResult->fetch_assoc()) {
    $indicators[] = $row['report_type'];
}
$stmt->close();

// If no indicators found, get from performance_indicators
if (empty($indicators)) {
    $fallbackQuery = "SELECT DISTINCT TRIM(indicator_name) as indicator_name FROM performance_indicators ORDER BY indicator_name";
    $fallbackResult = $conn->query($fallbackQuery);
    while ($row = $fallbackResult->fetch_assoc()) {
        $indicators[] = $row['indicator_name'];
    }
}

// Fetch data for this manager's cost center
$reportData = [];
$query = "SELECT report_type, expected, completed, percentage 
          FROM mro_cpr_report 
          WHERE report_month = ? AND report_year = ? AND department = ? AND cost_center_code = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("iiss", $currentMonthNum, $currentYear, $userDept, $userCostCenter);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $reportData[$row['report_type']] = [
        'expected' => (int)$row['expected'],
        'completed' => (int)$row['completed'],
        'percentage' => round((float)$row['percentage'], 1)
    ];
}
$stmt->close();

// Also get from master_performance_data for fallback
$masterQuery = "SELECT indicator_name, actual_value, target_value, percentage_achievement 
                FROM master_performance_data 
                WHERE data_month = ? AND department = ? AND verification_status = 'verified'";
$masterStmt = $conn->prepare($masterQuery);
$masterStmt->bind_param("ss", $dataMonth, $userDept);
$masterStmt->execute();
$masterResult = $masterStmt->get_result();

while ($row = $masterResult->fetch_assoc()) {
    $indicator = $row['indicator_name'];
    if (!isset($reportData[$indicator])) {
        $reportData[$indicator] = [
            'expected' => (int)$row['target_value'],
            'completed' => (int)$row['actual_value'],
            'percentage' => round((float)$row['percentage_achievement'], 1)
        ];
    }
}
$masterStmt->close();

// Set default values for missing indicators
foreach ($indicators as $indicator) {
    if (!isset($reportData[$indicator])) {
        $reportData[$indicator] = [
            'expected' => 0,
            'completed' => 0,
            'percentage' => 0
        ];
    }
}

// Calculate overall percentage
$overallPercentage = 0;
$totalExpected = 0;
$totalCompleted = 0;
foreach ($reportData as $data) {
    $totalExpected += $data['expected'];
    $totalCompleted += $data['completed'];
}
$overallPercentage = $totalExpected > 0 ? round(($totalCompleted / $totalExpected) * 100, 1) : 0;

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../assets/images/ethiopian_logo.ico">
    <title>Manager Dashboard - <?php echo htmlspecialchars($userDept); ?></title>
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
            font-family: 'Segoe UI', 'Inter', system-ui, sans-serif;
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

        body.fullscreen-mode .overall-card {
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
        }

        .floating-controls .exit-fullscreen-btn:hover {
            transform: translateY(-1px);
            opacity: 0.9;
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

        .dept-badge {
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
        }

        .btn:hover {
            transform: translateY(-1px);
            opacity: 0.9;
        }

        .fullscreen-btn {
            background: var(--accent);
            color: var(--dark-bg);
            border: none;
            padding: 0.3rem 0.8rem;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.7rem;
            transition: all 0.3s;
        }

        .fullscreen-btn:hover {
            transform: translateY(-1px);
            opacity: 0.9;
        }

        .theme-toggle {
            background: transparent;
            border: 1px solid var(--accent);
            color: var(--accent);
            padding: 0.3rem 0.8rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.7rem;
        }

        .container {
            max-width: 1400px;
            margin: 1rem auto;
            padding: 0 1rem;
        }

        .dashboard-header {
            background: linear-gradient(135deg, var(--medium-bg) 0%, var(--dark-bg) 100%);
            padding: 0.8rem 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            border: 1px solid var(--border-light);
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

        .fullscreen-header-btn {
            background: var(--accent);
            color: var(--dark-bg);
            border: none;
            padding: 0.3rem 0.8rem;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.7rem;
            transition: all 0.3s;
        }

        .fullscreen-header-btn:hover {
            transform: translateY(-1px);
            opacity: 0.9;
        }

        .welcome-banner {
            background: rgba(56, 189, 248, 0.1);
            border-left: 3px solid var(--accent);
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-size: 0.75rem;
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

        .overall-card {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-hover) 100%);
            color: var(--dark-bg);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            text-align: center;
        }

        .overall-card h3 {
            font-size: 0.85rem;
            margin-bottom: 0.3rem;
        }

        .overall-percentage {
            font-size: 2rem;
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

        body.light-theme .navbar,
        body.light-theme .dashboard-header,
        body.light-theme .metric-card {
            background: white !important;
            border-color: #E2E8F0 !important;
        }

        body.light-theme .navbar {
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
        }

        body.light-theme .dashboard-header {
            background: linear-gradient(135deg, #F1F5F9 0%, #E2E8F0 100%) !important;
        }

        body.light-theme .theme-toggle {
            border-color: #0284C7;
            color: #0284C7;
        }

        body.light-theme .theme-toggle:hover {
            background: #0284C7;
            color: white;
        }

        body.light-theme .fullscreen-btn,
        body.light-theme .fullscreen-header-btn {
            background: #0284C7;
            color: white;
        }

        body.light-theme .floating-controls {
            background: rgba(255, 255, 255, 0.95);
            border-bottom-color: #E2E8F0;
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
    <!-- Floating controls for fullscreen mode -->
    <div class="floating-controls" id="floatingControls">
        <div class="dashboard-title">Performance Dashboard</div>
        <div class="overall-mini">
            Overall: <span id="floatingOverall"><?php echo $overallPercentage; ?>%</span>
        </div>
        <button class="exit-fullscreen-btn" id="exitFullscreenBtn">Exit Full Screen</button>
    </div>

    <nav class="navbar">
        <div class="navbar-container">
            <a href="manager_dashboard.php" class="navbar-brand">HR & Finance Dashboard</a>
            <div class="navbar-menu">
                <div class="user-info">
                    <button id="themeToggle" class="theme-toggle">☀️ Light</button>

                    <span class="user-name">👤 <?php echo htmlspecialchars($userFullName); ?></span>
                    <span class="dept-badge"><?php echo htmlspecialchars($userDept); ?></span>
                    <a href="#" onclick="openPasswordModal(); return false;" style="cursor: pointer;">🔑 Change Password</a>
                    <a href="../logout.php" class="btn">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container" id="mainContainer">
        <div class="dashboard-header">
            <div class="header-left">
                <h1>Performance Dashboard</h1>
                <div class="month-selector" id="monthSelector">
                    <button onclick="changeMonth('prev')">← Prev</button>
                    <h3>📅 <?php echo date('F Y', strtotime($dataMonth)); ?></h3>
                    <button onclick="changeMonth('next')">Next →</button>
                </div>
            </div>
            <div class="header-right">
                <button id="fullscreenHeaderBtn2" class="fullscreen-header-btn">🖥️ Full Screen</button>
            </div>
        </div>

        <!-- Overall Performance Card -->
        <div class="overall-card" id="overallCard">
            <h3>Overall Performance Score</h3>
            <div class="overall-percentage"><?php echo $overallPercentage; ?>%</div>
            <div style="font-size: 0.75rem;">Target: 100%</div>
        </div>

        <?php if (!empty($indicators) && count($indicators) > 0): ?>
            <div class="metrics-grid" id="metricsGrid">
                <?php
                $chartIndex = 0;
                foreach ($indicators as $indicator):
                    $data = $reportData[$indicator];
                    $percentage = $data['percentage'];
                    $expected = $data['expected'];
                    $completed = $data['completed'];
                    $percentageColor = $percentage >= 90 ? 'var(--success)' : ($percentage >= 70 ? 'var(--warning)' : 'var(--danger)');
                    $chartId = 'chart-' . $chartIndex;
                    $chartIndex++;
                ?>
                    <div class="metric-card">
                        <div class="metric-title"><?php echo htmlspecialchars($indicator); ?></div>
                        <div class="chart-container">
                            <canvas id="<?php echo $chartId; ?>" width="120" height="120"></canvas>
                        </div>
                        <div class="percentage-display" style="color: <?php echo $percentageColor; ?>;">
                            <?php echo $percentage; ?>%
                        </div>
                        <div class="actual-target">
                            <span>Expected: <?php echo $expected; ?></span>
                            <span>Completed: <?php echo $completed; ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-data">
                <p>No performance data available for your department yet.</p>
                <p>Please check back later or contact your administrator.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Data passed from PHP
        const indicators = <?php echo json_encode($indicators); ?>;
        const reportData = <?php echo json_encode($reportData); ?>;
        const overallPercentage = <?php echo $overallPercentage; ?>;

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

        function initializeCharts() {
            let chartIndex = 0;
            for (const indicator of indicators) {
                const percentage = reportData[indicator]?.percentage || 0;
                const chartId = `chart-${chartIndex}`;
                const chart = createGaugeChart(chartId, percentage);
                if (chart) chartInstances[chartId] = chart;
                chartIndex++;
            }
        }

        // Fullscreen functionality with enhanced auto-scroll and pauses
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
            window.location.href = `manager_dashboard.php?month=${newMonth}`;
        }

        document.addEventListener('DOMContentLoaded', function() {
            new ThemeManager();
            initializeCharts();

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
                floatingOverall.textContent = overallPercentage + '%';
            }

            // Pause auto-scroll on user interaction
            const container = document.getElementById('mainContainer');
            if (container) {
                let userScrollTimeout = null;

                container.addEventListener('wheel', function() {
                    if (document.body.classList.contains('fullscreen-mode')) {
                        // Temporarily pause auto-scroll on user scroll
                        stopAutoScroll();
                        // Restart after 5 seconds of inactivity
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

        // Keep session alive
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