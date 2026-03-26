<?php
require_once '../includes/auth.php';
requireRole('hr');

$conn = getConnection();
$currentMonth = $_GET['month'] ?? date('m');
$currentYear = $_GET['year'] ?? date('Y');
$selectedReport = $_GET['report'] ?? 'MRO CPR REPORT';
$selectedDept = $_GET['department'] ?? '';

// Get all available reports
$reports = [
    'MRO CPR REPORT' => 'MRO CPR Report',
    'Team Leaders Clock-in Data' => 'Team Leaders Clock-in Data',
    'Crew Meeting Minutes Submission' => 'Crew Meeting Minutes Submission',
    'Exceptional Customer Experience Training' => 'Exceptional Customer Experience Training',
    '2025/26 1st Semiannual BSC/ISC Target Status' => '2025/26 1st Semiannual BSC/ISC Target Status',
    'Activity Report Submission' => 'Activity Report Submission',
    'Cost Saving Report Submission' => 'Cost Saving Report Submission',
    'Lost time Justification' => 'Lost time Justification',
    'Attendance Approval Status' => 'Attendance Approval Status',
    'Productivity' => 'Productivity',
    'Employees Training Gap Clearance' => 'Employees Training Gap Clearance',
    'Employees Issue Resolution Rate' => 'Employees Issue Resolution Rate'
];

// Define departments
$departments = ['BMT', 'LMT', 'CMT', 'EMT', 'AEP', 'NSM', 'QA', 'PSCM'];

// Define cost centers for each department
$costCenters = [
    'BMT' => [
        ['code' => 'DIR', 'name' => 'Dir. BMT'],
        ['code' => 'ACS', 'name' => 'Mgr. A/C Structure Maint'],
        ['code' => 'AVS', 'name' => 'Mgr. Avionics Sys Maint'],
        ['code' => 'B787', 'name' => 'Mgr. B787/767 Mainten'],
        ['code' => 'B737', 'name' => 'Mgr. B737 Maintenance'],
        ['code' => 'CAB', 'name' => 'Mgr. Cabin Maint'],
        ['code' => 'B777', 'name' => 'Mgr. B777/A350 Mainten'],
        ['code' => 'APS', 'name' => 'Mgr. A/C Patch Svs.'],
        ['code' => 'TEC', 'name' => 'Mgr. Technical Supp.']
    ],
    'LMT' => [
        ['code' => 'DIR', 'name' => 'Dir. LMT'],
        ['code' => 'DMM', 'name' => 'Duty Manager MCC'],
        ['code' => 'ADM', 'name' => 'MGR. Admin & Outstation Maint'],
        ['code' => 'ALM', 'name' => 'Mgr. A/C Line Maint.'],
        ['code' => 'GAM', 'name' => 'Mgr. General Ava. A/C Maint.'],
        ['code' => 'TPL', 'name' => 'MGR. Turbo Prop & Light A/C Maint'],
        ['code' => 'ACM', 'name' => 'Mgr. A/C Cabin Maint']
    ],
    'CMT' => [
        ['code' => 'DIR', 'name' => 'Dir. CMT'],
        ['code' => 'WKH', 'name' => 'Mgr. Wire Kit & Harness Prod.'],
        ['code' => 'CES', 'name' => 'Mgr. Computerized Equipment Shop'],
        ['code' => 'NDT', 'name' => 'Mgr. NDT, Stand. & Part Recv. Insp.'],
        ['code' => 'MES', 'name' => 'Comp. Maint. Engineering Support'],
        ['code' => 'MCS', 'name' => 'Mgr. Mechanical Comp Shops'],
        ['code' => 'ACS', 'name' => 'Mgr. Avionics Comp Shops']
    ],
    'EMT' => [
        ['code' => 'DIR', 'name' => 'Dir. EMT'],
        ['code' => 'EMI', 'name' => 'Mgr. Engine Maint. Inspection'],
        ['code' => 'ETS', 'name' => 'Mgr. Technical Support'],
        ['code' => 'RNP', 'name' => 'Mgr. RNP PW4000/LEAP/APU Eng. Maint.'],
        ['code' => 'CFM', 'name' => 'Mgr. CFM56/GE90/GENX & Turbo Prop. Engines'],
        ['code' => 'RSH', 'name' => 'Mgr. Repair Shops']
    ],
    'AEP' => [
        ['code' => 'DIR', 'name' => 'Dir. AEP'],
        ['code' => 'ALE', 'name' => 'MGR. A/C Lease, EIS & Special Projects'],
        ['code' => 'AMP', 'name' => 'MGR. A/C Maint. Prog. & Task Card Engineer'],
        ['code' => 'MPR', 'name' => 'MGR. Maint. Plng. & Record Control'],
        ['code' => 'EQA', 'name' => 'MGR. Engineering Quality Assurance'],
        ['code' => 'ASE', 'name' => 'Mgr. A/C Systems Eng'],
        ['code' => 'ADO', 'name' => 'MGR. A/C Design Organization']
    ],
    'NSM' => [
        ['code' => 'DIR', 'name' => 'Dir. MSM'],
        ['code' => 'MSM', 'name' => 'Mgr. MRO Sales and Marketing'],
        ['code' => 'MCS', 'name' => 'Mgr. MRO Customer Support']
    ],
    'QA' => [
        ['code' => 'QAS', 'name' => 'Mgr. MRO Qty Ass & S/a']
    ],
    'PSCM' => [
        ['code' => 'DIR', 'name' => 'Dir. Prop. & Supp. Chain Mgt'],
        ['code' => 'GWC', 'name' => 'Mgr. Grp Warp Cont Mgt'],
        ['code' => 'TPU', 'name' => 'Mgr. Tactical Purchase'],
        ['code' => 'MMP', 'name' => 'Mgr. MRO Material Planning'],
        ['code' => 'EMP', 'name' => 'Mgr. Engine Maint/Tactical Pur'],
        ['code' => 'WAP', 'name' => 'Mgr. Warehouse A/C Part'],
        ['code' => 'EXT', 'name' => 'Extra Sourcing'],
        ['code' => 'PLC', 'name' => 'Mgr. Purchase-LMT&CMT Maint.']
    ]
];

// Fetch existing data if any
$existingData = [];
if ($selectedDept && $currentMonth && $currentYear) {
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MRO CPR Report - Director Dashboard</title>
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
            --border-light: #334155;
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
        }
        
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
            max-width: 1400px;
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
        }
        
        .user-name {
            color: var(--accent);
            font-weight: bold;
            font-size: 0.8rem;
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
            box-shadow: 0 2px 5px rgba(56,189,248,0.3);
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
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }
        
        .filter-group label {
            font-size: 0.7rem;
            font-weight: bold;
            color: var(--accent);
        }
        
        .filter-group select,
        .filter-group input {
            padding: 0.5rem;
            border-radius: 6px;
            border: 1px solid var(--border-light);
            background: var(--dark-bg);
            color: var(--light-bg);
            font-size: 0.8rem;
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
        
        .percentage-cell {
            font-weight: bold;
            text-align: center;
        }
        
        .total-row {
            background: rgba(56,189,248,0.1);
            font-weight: bold;
        }
        
        .total-row td {
            border-top: 2px solid var(--accent);
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
        
        .btn-save:hover {
            background: #0e9f6e;
        }
        
        .alert {
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: rgba(16,185,129,0.2);
            border: 1px solid var(--success);
            color: var(--success);
        }
        
        .alert-error {
            background: rgba(239,68,68,0.2);
            border: 1px solid var(--danger);
            color: var(--danger);
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
        
        @media (max-width: 768px) {
            .filter-form {
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
            <a href="report_mro_cpr.php" class="navbar-brand">HR & Finance Dashboard</a>
            <div class="navbar-menu">
                <a href="master_data.php" class="btn" style="background: transparent; color: var(--accent);">Dashboard</a>
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                <a href="../logout.php" class="btn">Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="report-header">
            <h1 style="color: var(--accent); font-size: 1.2rem;">📊 MRO Performance Report</h1>
            <p style="font-size: 0.7rem; opacity: 0.8;">Enter expected and completed tasks to calculate completion percentage</p>
        </div>
        
        <div class="filter-section">
            <form method="GET" action="" class="filter-form" id="filterForm">
                <div class="filter-group">
                    <label>Report Type</label>
                    <select name="report" onchange="this.form.submit()">
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
                    <select name="department" onchange="this.form.submit()">
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
        
        <?php if ($selectedDept): ?>
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
                            </thead>
                        <tbody id="tableBody">
                            <?php 
                            $totalExpected = 0;
                            $totalCompleted = 0;
                            $costCentersList = $costCenters[$selectedDept] ?? [];
                            
                            foreach ($costCentersList as $cc):
                                $code = $cc['code'];
                                $name = $cc['name'];
                                $expected = isset($existingData[$code]) ? $existingData[$code]['expected'] : 0;
                                $completed = isset($existingData[$code]) ? $existingData[$code]['completed'] : 0;
                                $percentage = $expected > 0 ? round(($completed / $expected) * 100, 1) : 0;
                                $notCompleted = $expected - $completed;
                                
                                $totalExpected += $expected;
                                $totalCompleted += $completed;
                                
                                $percentageColor = $percentage >= 90 ? 'var(--success)' : ($percentage >= 70 ? 'var(--warning)' : 'var(--danger)');
                            ?>
                                <tr data-code="<?php echo $code; ?>">
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
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td><strong>TOTAL</strong></td>
                                <td id="total-expected"><?php echo $totalExpected; ?></td>
                                <td id="total-completed"><?php echo $totalCompleted; ?></td>
                                <td id="total-not-completed"><?php echo $totalExpected - $totalCompleted; ?></td>
                                <td id="total-percentage" class="percentage-cell">
                                    <?php 
                                    $totalPercentage = $totalExpected > 0 ? round(($totalCompleted / $totalExpected) * 100, 1) : 0;
                                    $totalColor = $totalPercentage >= 90 ? 'var(--success)' : ($totalPercentage >= 70 ? 'var(--warning)' : 'var(--danger)');
                                    ?>
                                    <span style="color: <?php echo $totalColor; ?>;"><?php echo $totalPercentage; ?>%</span>
                                </td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $totalPercentage; ?>%; background: <?php echo $totalColor; ?>;"></div>
                                    </div>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <div class="save-section">
                    <button type="submit" class="btn btn-save">💾 Save Report</button>
                </div>
            </form>
        <?php else: ?>
            <div style="text-align: center; padding: 3rem; background: var(--card-bg); border-radius: 12px;">
                <p>Please select a department to view and enter report data.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Auto-calculate not completed and percentage
        function calculateRow(row) {
            const expectedInput = row.querySelector('.expected-input');
            const completedInput = row.querySelector('.completed-input');
            const notCompletedCell = row.querySelector('.not-completed-cell');
            const percentageSpan = row.querySelector('.percentage-value');
            const progressFill = row.querySelector('.progress-fill');
            
            if (expectedInput && completedInput) {
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
        
        // Calculate totals
        function calculateTotals() {
            const rows = document.querySelectorAll('#tableBody tr');
            let totalExpected = 0;
            let totalCompleted = 0;
            
            rows.forEach(row => {
                const expected = parseInt(row.querySelector('.expected-input')?.value) || 0;
                const completed = parseInt(row.querySelector('.completed-input')?.value) || 0;
                totalExpected += expected;
                totalCompleted += completed;
            });
            
            const totalNotCompleted = totalExpected - totalCompleted;
            const totalPercentage = totalExpected > 0 ? (totalCompleted / totalExpected) * 100 : 0;
            const totalColor = totalPercentage >= 90 ? '#10B981' : (totalPercentage >= 70 ? '#F59E0B' : '#EF4444');
            
            document.getElementById('total-expected').textContent = totalExpected;
            document.getElementById('total-completed').textContent = totalCompleted;
            document.getElementById('total-not-completed').textContent = totalNotCompleted;
            
            const totalPercentSpan = document.querySelector('#total-percentage span');
            if (totalPercentSpan) {
                totalPercentSpan.textContent = totalPercentage.toFixed(1) + '%';
                totalPercentSpan.style.color = totalColor;
            }
            
            const totalProgressFill = document.querySelector('#total-percentage + td .progress-fill');
            if (totalProgressFill) {
                totalProgressFill.style.width = totalPercentage + '%';
                totalProgressFill.style.background = totalColor;
            }
        }
        
        // Add event listeners to all inputs
        document.querySelectorAll('.expected-input, .completed-input').forEach(input => {
            input.addEventListener('input', function() {
                const row = this.closest('tr');
                calculateRow(row);
                calculateTotals();
            });
        });
        
        // Show message if exists
        const urlParams = new URLSearchParams(window.location.search);
        const message = urlParams.get('message');
        if (message) {
            const msgDiv = document.getElementById('message');
            msgDiv.innerHTML = `<div class="alert alert-success">✓ ${decodeURIComponent(message)}</div>`;
            setTimeout(() => {
                msgDiv.innerHTML = '';
            }, 3000);
        }
        
        // Initial calculation
        document.querySelectorAll('#tableBody tr').forEach(row => calculateRow(row));
        calculateTotals();
    </script>
</body>
</html>