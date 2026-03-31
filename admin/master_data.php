<?php
require_once '../includes/auth.php';
requireRole('hr');

$conn = getConnection();
$currentMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$isEditable = ($currentMonth >= date('Y-m')) ? true : false;
$dataMonth = $currentMonth . '-01';

// Get existing data
$query = "SELECT m.id, m.indicator_name, m.department, m.actual_value, m.target_value, 
          m.percentage_achievement, m.remarks, m.updated_at, u.full_name as updated_by_name,
          m.created_at, c.full_name as created_by_name
          FROM master_performance_data m
          LEFT JOIN users u ON m.updated_by = u.id
          LEFT JOIN users c ON m.created_by = c.id
          WHERE m.data_month = ?
          ORDER BY 
            CASE m.indicator_name
              WHEN 'Team Leaders Clock-in Data' THEN 1
              WHEN 'Crew Meeting Minutes Submission' THEN 2
              WHEN 'Exceptional Customer Experience Training' THEN 3
              WHEN 'CPR' THEN 4
              WHEN '2025/26 1st Semiannual BSCI/ISC Target Status' THEN 5
              WHEN 'Activity Report Submission' THEN 6
              WHEN 'Cost Saving Report Submission' THEN 7
              WHEN 'Lost Time Justification' THEN 8
              WHEN 'Attendance Approval Status' THEN 9
              WHEN 'Productivity' THEN 10
              WHEN 'Employees Training Gap Clearance' THEN 11
              WHEN 'Employees Issue Resolution Rate' THEN 12
              ELSE 13
            END,
            FIELD(m.department, 'BMT', 'LMT', 'CMT', 'EMT', 'AEP', 'MSM', 'QA', 'MRO HR', 'MD/DIV.', 'Remainder')";
            
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $dataMonth);
$stmt->execute();
$existingData = $stmt->get_result();

// Store data in a 2D array
$dataMap = [];
while ($row = $existingData->fetch_assoc()) {
    $dept = $row['department'];
    $indicator = $row['indicator_name'];
    if (!isset($dataMap[$dept])) {
        $dataMap[$dept] = [];
    }
    $dataMap[$dept][$indicator] = $row;
}

// Define departments
$departments = ['BMT', 'LMT', 'CMT', 'EMT', 'AEP', 'MSM', 'QA', 'MRO HR', 'MD/DIV.', 'Remainder'];

// Define indicators with their full names and target defaults
$indicators = [
    // 'Team Leaders Clock-in Data' => [
    //     'name' => 'Team Leaders Clock in Data',
    //     'targets' => ['BMT' => 100, 'LMT' => 100, 'CMT' => 100, 'EMT' => 100, 'AEP' => 100, 'MSM' => 100, 'QA' => 100, 'MRO HR' => 100, 'MD/DIV.' => 100, 'Remainder' => 100]
    // ],
    'Crew Meeting Minutes Submission' => [
        'name' => 'Crew Meeting Minutes Submission',
        'targets' => ['BMT' => 14, 'LMT' => 5, 'CMT' => 18, 'EMT' => 10, 'AEP' => 5, 'MSM' => 1, 'QA' => 1, 'MRO HR' => 1, 'MD/DIV.' => 55, 'Remainder' => 100]
    ],
    'Exceptional Customer Experience Training' => [
        'name' => 'Exceptional Customer Experience Training',
        'targets' => ['BMT' => 100, 'LMT' => 100, 'CMT' => 100, 'EMT' => 100, 'AEP' => 100, 'MSM' => 100, 'QA' => 100, 'MRO HR' => 100, 'MD/DIV.' => 100, 'Remainder' => 100]
    ],
    'CPR' => [
        'name' => 'CPR',
        'targets' => ['BMT' => 1074, 'LMT' => 801, 'CMT' => 375, 'EMT' => 243, 'AEP' => 139, 'MSM' => 14, 'QA' => 35, 'MRO HR' => 14, 'MD/DIV.' => 2695, 'Remainder' => 100]
    ],
    '2025/26 1st Semiannual BSCI/ISC Target Status' => [
        'name' => '2025/26 1st Semiannual BSC/ISC Target Status',
        'targets' => ['BMT' => 100, 'LMT' => 100, 'CMT' => 100, 'EMT' => 100, 'AEP' => 100, 'MSM' => 100, 'QA' => 100, 'MRO HR' => 100, 'MD/DIV.' => 100, 'Remainder' => 100]
    ],
    'Activity Report Submission' => [
        'name' => 'Activity Report Submission',
        'targets' => ['BMT' => 1, 'LMT' => 1, 'CMT' => 1, 'EMT' => 1, 'AEP' => 1, 'MSM' => 1, 'QA' => 1, 'MRO HR' => 1, 'MD/DIV.' => 8, 'Remainder' => 100]
    ],
    'Cost Saving Report Submission' => [
        'name' => 'Cost Saving Report Submission',
        'targets' => ['BMT' => 1, 'LMT' => 1, 'CMT' => 1, 'EMT' => 1, 'AEP' => 1, 'MSM' => 1, 'QA' => 1, 'MRO HR' => 1, 'MD/DIV.' => 8, 'Remainder' => 100]
    ],
    'Lost Time Justification' => [
        'name' => 'Lost time Justification',
        'targets' => ['BMT' => 100, 'LMT' => 100, 'CMT' => 1001, 'EMT' => 100, 'AEP' => 100, 'MSM' => 100, 'QA' => 100, 'MRO HR' => 100, 'MD/DIV.' => 100, 'Remainder' => 100]
    ],
    'Attendance Approval Status' => [
        'name' => 'Attendance Approval Status',
        'targets' => ['BMT' => 100, 'LMT' => 100, 'CMT' => 100, 'EMT' => 100, 'AEP' => 100, 'MSM' => 100, 'QA' => 100, 'MRO HR' => 100, 'MD/DIV.' => 100, 'Remainder' => 100]
    ],
    'Productivity' => [
        'name' => 'Productivity',
        'targets' => ['BMT' => 100, 'LMT' => 100, 'CMT' => 100, 'EMT' => 100, 'AEP' => 100, 'MSM' => 100, 'QA' => 100, 'MRO HR' => 100, 'MD/DIV.' => 100, 'Remainder' => 100]
    ],
    'Employees Training Gap Clearance' => [
        'name' => 'Employees Training Gap Clearance',
        'targets' => ['BMT' => 100, 'LMT' => 100, 'CMT' => 100, 'EMT' => 100, 'AEP' => 100, 'MSM' => 100, 'QA' => 100, 'MRO HR' => 100, 'MD/DIV.' => 100, 'Remainder' => 100]
    ],
    'Employees Issue Resolution Rate' => [
        'name' => 'Employees Issue Resolution Rate',
        'targets' => ['BMT' => 100, 'LMT' => 100, 'CMT' => 100, 'EMT' => 100, 'AEP' => 100, 'MSM' => 100, 'QA' => 100, 'MRO HR' => 100, 'MD/DIV.' => 100, 'Remainder' => 100]
    ]
];

$stmt->close();
$conn->close();

// Helper function to safely get value
function getValue($dataMap, $department, $indicator, $field, $default = '') {
    if (isset($dataMap[$department]) && isset($dataMap[$department][$indicator]) && isset($dataMap[$department][$indicator][$field])) {
        return $dataMap[$department][$indicator][$field];
    }
    return $default;
}

function getLastUpdateInfo($dataMap, $indicator) {
    $updatedAt = getValue($dataMap, 'BMT', $indicator, 'updated_at');
    $createdAt = getValue($dataMap, 'BMT', $indicator, 'created_at');
    $updatedBy = getValue($dataMap, 'BMT', $indicator, 'updated_by_name');
    $createdBy = getValue($dataMap, 'BMT', $indicator, 'created_by_name');
    
    $time = $updatedAt ? date('Y-m-d H:i', strtotime($updatedAt)) : 
            ($createdAt ? date('Y-m-d H:i', strtotime($createdAt)) : 'Not set');
    $person = $updatedBy ? $updatedBy : ($createdBy ? $createdBy : 'N/A');
    
    return ['time' => $time, 'person' => $person];
}

$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['message']);
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Master Data Entry - HR Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        * {
            box-sizing: border-box;
        }
        
        body {
            background: var(--dark-bg);
            overflow-x: hidden;
            transition: background-color 0.3s, color 0.3s;
        }
        
        .container {
            width: 100%;
            max-width: 100%;
            margin: 0;
            padding: 0.5rem 1.5rem;
        }
        
        @media (min-width: 1920px) {
            .container { padding: 0.5rem 3rem; }
        }
        
        @media (max-width: 1366px) {
            .container { padding: 0.5rem 1rem; }
        }
        
        .compact-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
            background: var(--medium-bg);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        .compact-table th,
        .compact-table td {
            border: 1px solid var(--border-light);
            padding: 0.5rem 0.35rem;
            vertical-align: middle;
        }
        
        .compact-table th {
            background: var(--dark-bg);
            color: var(--accent);
            font-weight: bold;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 20;
            font-size: 0.7rem;
        }
        
        .compact-table th:first-child,
        .compact-table td:first-child {
            width: 180px;
            min-width: 180px;
            max-width: 200px;
        }
        
        .compact-table th:last-child,
        .compact-table td:last-child {
            width: 90px;
            min-width: 90px;
        }
        
        .indicator-cell {
            background: var(--medium-bg);
            font-weight: bold;
            color: var(--text-primary);
            position: sticky;
            left: 0;
            z-index: 10;
            font-size: 0.7rem;
            word-wrap: break-word;
            line-height: 1.3;
            transition: background-color 0.3s, color 0.3s;
        }
        
        .department-header {
            font-weight: bold;
            font-size: 0.75rem;
            white-space: nowrap;
            color: var(--text-primary);
        }
        
        .input-cell {
            padding: 0.3rem 0.2rem !important;
        }
        
        .compact-table input {
            width: 100%;
            padding: 0.4rem 0.2rem;
            border: 1px solid var(--border-light);
            border-radius: 3px;
            background: var(--input-bg);
            color: var(--text-primary);
            text-align: center;
            font-size: 0.7rem;
            transition: background-color 0.2s, border-color 0.2s;
        }
        
        .compact-table input:focus {
            outline: none;
            border-color: var(--accent);
            background: var(--input-bg);
        }
        
        .compact-table input.editable {
            background: rgba(2,177,170,0.1);
            border-color: var(--accent);
        }
        
        .compact-table input.editable:hover {
            background: rgba(2,177,170,0.2);
        }
        
        .compact-table input.non-editable {
            background: var(--input-bg);
            color: var(--text-secondary);
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        .target-input {
            background: rgba(43,53,62,0.1);
        }
        
        .percentage-input {
            background: rgba(2,177,170,0.15);
            font-weight: bold;
        }
        
        .remainder-input {
            background: rgba(239,242,77,0.15);
            color: var(--warning);
            font-weight: bold;
        }
        
        .last-update-mini {
            font-size: 0.6rem;
            color: var(--accent);
            text-align: center;
            line-height: 1.2;
        }
        
        .save-section {
            margin-top: 1.5rem;
            text-align: center;
            padding: 1rem;
            position: sticky;
            bottom: 0;
            background: var(--dark-bg);
            z-index: 30;
            border-radius: 10px;
            transition: background-color 0.3s;
        }
        
        .month-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.5rem;
            background: var(--medium-bg);
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            transition: background-color 0.3s;
        }
        
        .month-navigation h3 {
            margin: 0;
            color: var(--accent);
            font-size: 1rem;
        }
        
        .btn {
            background: var(--accent);
            color: var(--text-primary);
            border: none;
            padding: 0.4rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.8rem;
            transition: all 0.3s;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            opacity: 0.9;
        }
        
        .info-banner {
            background: rgba(2,177,170,0.1);
            padding: 0.6rem 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            text-align: center;
            font-size: 0.75rem;
            color: var(--accent);
        }
        
        .table-wrapper {
            max-height: calc(100vh - 200px);
            overflow-y: auto;
            overflow-x: auto;
            border-radius: 10px;
            position: relative;
        }
        
        .table-wrapper::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }
        
        .table-wrapper::-webkit-scrollbar-track {
            background: var(--border-light);
            border-radius: 5px;
        }
        
        .table-wrapper::-webkit-scrollbar-thumb {
            background: var(--accent);
            border-radius: 5px;
        }
        
        @media (max-width: 1400px) {
            .compact-table th:first-child,
            .compact-table td:first-child {
                width: 160px;
                min-width: 160px;
            }
        }
        
        .dept-abbr {
            font-size: 0.6rem;
            font-weight: normal;
            display: block;
            color: var(--text-secondary);
            margin-top: 2px;
        }
        
        .calc-hint {
            font-size: 0.55rem;
            color: var(--accent);
            margin-top: 2px;
        }
        
        .warning-banner, .success-banner, .error-banner {
            padding: 0.6rem 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            text-align: center;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        .warning-banner { background: var(--warning); color: var(--text-primary); }
        .success-banner { background: var(--success); color: white; }
        .error-banner { background: var(--danger); color: white; }
        
        /* Theme Toggle Button Styles */
        .theme-toggle {
            background: transparent;
            border: 1px solid var(--accent);
            color: var(--accent);
            padding: 0.3rem 0.8rem;
            cursor: pointer;
        }
        
        .theme-toggle:hover {
            background: var(--accent);
            color: var(--dark-bg);
            transform: translateY(-1px);
        }
        
        /* Navbar styles */
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
        
        /* Light theme specific styles using new color palette */
        body.light-theme {
            --dark-bg: #F2F2F2;
            --medium-bg: #FFFFFF;
            --accent: #02B1AA;
            --accent-hover: #26AE62;
            --text-primary: #2B353E;
            --text-secondary: #5A6B7C;
            --border-light: #EFF24D;
            --input-bg: #F2F2F2;
            --success: #26AE62;
            --warning: #EFF24D;
            --danger: #C51713;
        }
        
        body.light-theme .indicator-cell {
            background: var(--medium-bg);
            color: var(--text-primary);
        }
        
        body.light-theme .compact-table th {
            background: var(--dark-bg);
            color: var(--accent);
        }
        
        body.light-theme .compact-table td {
            background: var(--medium-bg);
            border-color: var(--border-light);
        }
        
        body.light-theme .compact-table tr:hover td {
            background: rgba(2,177,170,0.05);
        }
        
        body.light-theme .btn {
            background: var(--accent);
            color: white;
        }
        
        body.light-theme .btn:hover {
            background: var(--accent-hover);
        }
        
        body.light-theme .theme-toggle {
            border-color: var(--accent);
            color: var(--accent);
        }
        
        body.light-theme .theme-toggle {
            background: var(--accent);
            color: white;
        }
        
        body.light-theme .percentage-input {
            background: rgba(2,177,170,0.1);
        }
        
        body.light-theme .remainder-input {
            background: rgba(239,242,77,0.2);
        }
        
        body.light-theme .month-navigation {
            background: var(--medium-bg);
            border: 1px solid var(--border-light);
        }
        
        body.light-theme .save-section {
            background: var(--dark-bg);
        }
        
        body.light-theme .table-wrapper::-webkit-scrollbar-track {
            background: var(--border-light);
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="master_data.php" class="navbar-brand">HR & Finance Dashboard</a>
            <div class="navbar-menu">
                <a href="master_data.php" style="color: var(--accent);">Master Data</a>
                <a href="../admin/report_mro_cpr.php">Director Data Entry</a>
                <a href="verify_data.php">Verify</a>
                <a href="data_history.php">History</a>
                <div class="user-info">
                    <button id="themeToggle" class="btn theme-toggle">☀️ Light</button>
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="../logout.php" class="btn">Logout</a>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="month-navigation">
            <button onclick="changeMonth('prev')" class="btn">&larr; Previous Month</button>
            
            <h3><img src="..\assets\images\online-data.png" alt="MRO Icon" style="width:20px; height:20px; vertical-align:middle; margin-right:8px;">
            Master Data Entry - <?php echo date('F Y', strtotime($dataMonth)); ?></h3>
            <button onclick="changeMonth('next')" class="btn">Next Month &rarr;</button>
        </div>
        
        <?php if ($message): ?>
            <div class="success-banner">✓ <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-banner">⚠ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (!$isEditable): ?>
            <div class="warning-banner">⚠ View Only Mode - Past month cannot be edited</div>
        <?php endif; ?>
        
        <div class="info-banner">
            <strong>✏️ Targets are editable</strong> for each department | 🧮 <strong>Remainder:</strong> Auto-calculated from MD/DIV.
        </div>
        
        <div class="form-container" style="padding: 0;">
            <form id="masterDataForm" method="POST" action="save_master_data.php">
                <input type="hidden" name="month" value="<?php echo $currentMonth; ?>">
                
                <div class="table-wrapper">
                    <table class="compact-table">
                        <thead>
                            <tr>
                                <th class="indicator-cell">Indicator</th>
                                <?php foreach ($departments as $dept): ?>
                                    <th>
                                        <div class="department-header"><?php echo htmlspecialchars($dept); ?></div>
                                        <div class="dept-abbr">Actual | Target | %</div>
                                    </th>
                                <?php endforeach; ?>
                                <th>Last Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($indicators as $indicatorKey => $indicatorInfo): 
                                $lastUpdate = getLastUpdateInfo($dataMap, $indicatorKey);
                            ?>
                                <tr>
                                    <td class="indicator-cell">
                                        <?php echo htmlspecialchars($indicatorInfo['name']); ?>
                                    </td>
                                    
                                    <?php foreach ($departments as $dept):
                                        $actualValue = getValue($dataMap, $dept, $indicatorKey, 'actual_value');
                                        $targetValue = getValue($dataMap, $dept, $indicatorKey, 'target_value');
                                        $percentageValue = getValue($dataMap, $dept, $indicatorKey, 'percentage_achievement');
                                        
                                        // Use saved target or default from array
                                        if ($targetValue === null || $targetValue === '') {
                                            $targetValue = $indicatorInfo['targets'][$dept] ?? 100;
                                        }
                                        
                                        $actualFormatted = $actualValue !== null && $actualValue !== '' ? number_format((float)$actualValue, 2) : '';
                                        $targetFormatted = $targetValue !== null && $targetValue !== '' ? number_format((float)$targetValue, 2) : '';
                                        $percentageFormatted = $percentageValue !== null && $percentageValue !== '' ? number_format((float)$percentageValue, 2) : '';
                                        
                                        $isRemainder = ($dept === 'Remainder');
                                    ?>
                                        <td class="input-cell">
                                            <?php if ($isRemainder): ?>
                                                <!-- Remainder - Auto-calculated -->
                                                <input type="text" 
                                                       class="remainder-actual remainder-input non-editable"
                                                       data-md-div-indicator="<?php echo $indicatorKey; ?>"
                                                       value="<?php echo $percentageFormatted; ?>"
                                                       readonly
                                                       placeholder="Auto">
                                                <input type="hidden" 
                                                       name="data[Remainder][<?php echo $indicatorKey; ?>][target]" 
                                                       value="100">
                                                <input type="hidden" 
                                                       name="data[Remainder][<?php echo $indicatorKey; ?>][percentage]" 
                                                       class="remainder-hidden"
                                                       value="<?php echo $percentageFormatted; ?>">
                                                <input type="hidden" 
                                                       name="data[Remainder][<?php echo $indicatorKey; ?>][actual]" 
                                                       value="<?php echo $percentageFormatted; ?>">
                                            <?php else: ?>
                                                <!-- Actual Input -->
                                                <input type="number" step="0.01" 
                                                       name="data[<?php echo $dept; ?>][<?php echo $indicatorKey; ?>][actual]" 
                                                       value="<?php echo htmlspecialchars($actualFormatted); ?>" 
                                                       <?php echo !$isEditable ? 'disabled' : ''; ?>
                                                       class="actual-input <?php echo $isEditable ? 'editable' : 'non-editable'; ?>"
                                                       data-dept="<?php echo $dept; ?>"
                                                       data-indicator="<?php echo $indicatorKey; ?>"
                                                       placeholder="Actual">
                                                
                                                <!-- Target Input (Editable) -->
                                                <input type="number" step="0.01" 
                                                       name="data[<?php echo $dept; ?>][<?php echo $indicatorKey; ?>][target]" 
                                                       value="<?php echo htmlspecialchars($targetFormatted); ?>" 
                                                       <?php echo !$isEditable ? 'disabled' : ''; ?>
                                                       class="target-input <?php echo $isEditable ? 'editable' : 'non-editable'; ?>"
                                                       data-dept="<?php echo $dept; ?>"
                                                       data-indicator="<?php echo $indicatorKey; ?>"
                                                       placeholder="Target"
                                                       style="margin-top: 2px;">
                                                
                                                <!-- Percentage (Auto-calculated, Read-only) -->
                                                <input type="number" step="0.01" 
                                                       name="data[<?php echo $dept; ?>][<?php echo $indicatorKey; ?>][percentage]" 
                                                       value="<?php echo htmlspecialchars($percentageFormatted); ?>" 
                                                       class="percentage-input percentage-field"
                                                       data-dept="<?php echo $dept; ?>"
                                                       data-indicator="<?php echo $indicatorKey; ?>"
                                                       readonly
                                                       style="margin-top: 2px;"
                                                       placeholder="%">
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                    
                                    <td class="last-update-mini">
                                        <div><?php echo htmlspecialchars($lastUpdate['person']); ?></div>
                                        <div style="font-size: 0.55rem;"><?php echo htmlspecialchars($lastUpdate['time']); ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <!-- Remarks Row -->
                            <tr style="background: var(--dark-bg);">
                                <td class="indicator-cell">
                                    <strong>📝 Remarks</strong>
                                </td>
                                <?php foreach ($departments as $dept): 
                                    $firstIndicator = array_key_first($indicators);
                                    $remarksValue = getValue($dataMap, $dept, $firstIndicator, 'remarks');
                                ?>
                                    <td class="input-cell">
                                        <input type="text" 
                                               name="remarks[<?php echo $dept; ?>]" 
                                               value="<?php echo htmlspecialchars($remarksValue ?? ''); ?>" 
                                               <?php echo !$isEditable ? 'disabled' : ''; ?>
                                               class="<?php echo $isEditable ? 'editable' : 'non-editable'; ?>"
                                               placeholder="Add remarks..."
                                               style="width: 100%; text-align: left; font-size: 0.65rem;">
                                    </td>
                                <?php endforeach; ?>
                                <td class="last-update-mini">-</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($isEditable): ?>
                    <div class="save-section">
                        <button type="submit" class="btn" style="font-size: 0.9rem; padding: 0.6rem 1.5rem;">
                            💾 Save All Changes
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <script>
        // Theme Management
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
        
        // Calculate percentage based on actual and target values
        function calculatePercentage(actualInput, targetInput, percentageInput) {
            let actual = parseFloat(actualInput.value);
            let target = parseFloat(targetInput.value);
            
            if (!isNaN(actual) && !isNaN(target) && target !== 0) {
                let percentage = (actual / target) * 100;
                percentageInput.value = percentage.toFixed(2);
                
                // If this is MD/DIV., update Remainder calculation
                const dept = actualInput.getAttribute('data-dept');
                const indicator = actualInput.getAttribute('data-indicator');
                if (dept === 'MD/DIV.') {
                    updateRemainderForIndicator(indicator);
                }
            } else {
                percentageInput.value = '';
                const dept = actualInput.getAttribute('data-dept');
                const indicator = actualInput.getAttribute('data-indicator');
                if (dept === 'MD/DIV.') {
                    updateRemainderForIndicator(indicator);
                }
            }
        }
        
        // Recalculate when target changes
        function recalculateFromTarget(targetInput) {
            const dept = targetInput.getAttribute('data-dept');
            const indicator = targetInput.getAttribute('data-indicator');
            const actualInput = document.querySelector(`input.actual-input[data-dept="${dept}"][data-indicator="${indicator}"]`);
            const percentageInput = document.querySelector(`input.percentage-field[data-dept="${dept}"][data-indicator="${indicator}"]`);
            
            if (actualInput && percentageInput) {
                calculatePercentage(actualInput, targetInput, percentageInput);
            }
        }
        
        // Update Remainder based on MD/DIV. percentage using formula: MAX(100%, MD/DIV%) - MD/DIV%
        function updateRemainderForIndicator(indicator) {
            const mdDivPercentageInput = document.querySelector(`input.percentage-field[data-dept="MD/DIV."][data-indicator="${indicator}"]`);
            
            if (mdDivPercentageInput) {
                let mdDivPercentage = parseFloat(mdDivPercentageInput.value);
                let remainderValue = '';
                
                if (!isNaN(mdDivPercentage)) {
                    // Formula: MAX(100%, MD/DIV%) - MD/DIV%
                    let maxValue = Math.max(100, mdDivPercentage);
                    remainderValue = (maxValue - mdDivPercentage).toFixed(2);
                }
                
                // Update remainder display field
                const remainderDisplay = document.querySelector(`.remainder-actual[data-md-div-indicator="${indicator}"]`);
                const remainderHidden = document.querySelector(`input.remainder-hidden[name*="${indicator}"]`);
                
                if (remainderDisplay) {
                    remainderDisplay.value = remainderValue;
                }
                if (remainderHidden) {
                    remainderHidden.value = remainderValue;
                }
            }
        }
        
        // Initialize all remainder calculations
        function initializeRemainders() {
            const indicators = <?php echo json_encode(array_keys($indicators)); ?>;
            indicators.forEach(indicator => {
                updateRemainderForIndicator(indicator);
            });
        }
        
        // Add event listeners to all inputs
        document.querySelectorAll('.actual-input').forEach(input => {
            const newInput = input.cloneNode(true);
            input.parentNode.replaceChild(newInput, input);
            
            newInput.addEventListener('change', function() {
                const dept = this.getAttribute('data-dept');
                const indicator = this.getAttribute('data-indicator');
                const targetInput = document.querySelector(`input.target-input[data-dept="${dept}"][data-indicator="${indicator}"]`);
                const percentageInput = document.querySelector(`input.percentage-field[data-dept="${dept}"][data-indicator="${indicator}"]`);
                if (targetInput && percentageInput) {
                    calculatePercentage(this, targetInput, percentageInput);
                }
            });
            
            newInput.addEventListener('input', function() {
                const dept = this.getAttribute('data-dept');
                const indicator = this.getAttribute('data-indicator');
                const targetInput = document.querySelector(`input.target-input[data-dept="${dept}"][data-indicator="${indicator}"]`);
                const percentageInput = document.querySelector(`input.percentage-field[data-dept="${dept}"][data-indicator="${indicator}"]`);
                if (targetInput && percentageInput) {
                    calculatePercentage(this, targetInput, percentageInput);
                }
            });
        });
        
        // Add event listeners to target inputs
        document.querySelectorAll('.target-input').forEach(input => {
            const newInput = input.cloneNode(true);
            input.parentNode.replaceChild(newInput, input);
            
            newInput.addEventListener('change', function() {
                recalculateFromTarget(this);
            });
            
            newInput.addEventListener('input', function() {
                recalculateFromTarget(this);
            });
        });
        
        // Change month function
        function changeMonth(direction) {
            let currentUrl = new URL(window.location.href);
            let currentMonth = currentUrl.searchParams.get('month') || '<?php echo $currentMonth; ?>';
            let date = new Date(currentMonth + '-01');
            
            if (direction === 'prev') {
                date.setMonth(date.getMonth() - 1);
            } else {
                date.setMonth(date.getMonth() + 1);
            }
            
            let newMonth = date.toISOString().slice(0, 7);
            window.location.href = `master_data.php?month=${newMonth}`;
        }
        
        // Initialize theme and remainder calculations when page loads
        document.addEventListener('DOMContentLoaded', function() {
            new ThemeManager();
            initializeRemainders();
        });
    </script>
</body>
</html>