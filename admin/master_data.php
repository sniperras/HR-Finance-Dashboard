<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../session_config.php';
require_once '../includes/auth.php';
requireRole('hr');

$conn = getConnection();

// Force clean duplicates on every page load for RamsisE and mgrhr_mro to ensure data integrity
if ($_SESSION['username'] === 'RamsisE' || $_SESSION['username'] === 'mgrhr_mro') {
    // First, update all master_performance_data to use the latest name for each indicator
    $syncQuery = "
        UPDATE master_performance_data mpd
        SET mpd.indicator_name = (
            SELECT indicator_name FROM performance_indicators pi 
            WHERE LOWER(TRIM(pi.indicator_name)) = LOWER(TRIM(mpd.indicator_name))
            ORDER BY pi.id DESC LIMIT 1
        )
        WHERE EXISTS (
            SELECT 1 FROM performance_indicators pi 
            WHERE LOWER(TRIM(pi.indicator_name)) = LOWER(TRIM(mpd.indicator_name))
            AND pi.indicator_name != mpd.indicator_name
        )
    ";
    $conn->query($syncQuery);

    // Then delete duplicate indicators
    $cleanQuery = "
        DELETE p1 FROM performance_indicators p1
        INNER JOIN performance_indicators p2 
        WHERE p1.id > p2.id 
        AND LOWER(TRIM(p1.indicator_name)) = LOWER(TRIM(p2.indicator_name))
    ";
    $conn->query($cleanQuery);
}

// Function to get user name by ID
function getUserNameById($conn, $userId)
{
    if (!$userId) return null;
    $query = "SELECT full_name FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return $row['full_name'];
    }
    $stmt->close();
    return null;
}

// Function to sync data from mro_cpr_report to master_performance_data
function syncFromMroCprReport($conn, $indicatorName, $month, $year)
{
    $dataMonth = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01';
    $currentDateForComparison = date('Y-m') . '-01';

    // Only sync for current and future months
    if ($dataMonth >= $currentDateForComparison) {
        // Get the DIRECTOR row data for each department (cost_center_code = 'DIR')
        $query = "SELECT department, expected, completed, percentage, updated_by, updated_at 
                  FROM mro_cpr_report 
                  WHERE report_type = ? AND report_month = ? AND report_year = ? 
                  AND cost_center_code = 'DIR'";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sii", $indicatorName, $month, $year);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $department = $row['department'];
            $totalExpected = (float)$row['expected'];
            $totalCompleted = (float)$row['completed'];
            $percentage = (float)$row['percentage'];
            $updatedBy = $row['updated_by'];
            $updatedAt = $row['updated_at'];

            // Use the EXACT indicator name from mro_cpr_report, not from performance_indicators
            $exactIndicatorName = $indicatorName;

            // Check if record exists in master_performance_data
            $checkStmt = $conn->prepare("SELECT id FROM master_performance_data 
                                          WHERE indicator_name = ? AND department = ? AND data_month = ?");
            $checkStmt->bind_param("sss", $exactIndicatorName, $department, $dataMonth);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                // Update existing record
                $updateStmt = $conn->prepare("UPDATE master_performance_data 
                                              SET target_value = ?, actual_value = ?, percentage_achievement = ?, 
                                                  updated_at = ?, updated_by = ?, remarks = CONCAT('Auto-synced from ', ?)
                                              WHERE indicator_name = ? AND department = ? AND data_month = ?");
                $updateStmt->bind_param(
                    "dddssssss",
                    $totalExpected,
                    $totalCompleted,
                    $percentage,
                    $updatedAt,
                    $updatedBy,
                    $exactIndicatorName,
                    $exactIndicatorName,
                    $department,
                    $dataMonth
                );
                $updateStmt->execute();
                $updateStmt->close();
            } else {
                // Insert new record
                $insertStmt = $conn->prepare("INSERT INTO master_performance_data 
                                              (indicator_name, department, target_value, actual_value, 
                                               percentage_achievement, data_month, created_by, created_at, 
                                               updated_by, updated_at, verification_status, remarks)
                                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'verified', CONCAT('Auto-synced from ', ?))");
                $insertStmt->bind_param(
                    "ssdddssisss",
                    $exactIndicatorName,
                    $department,
                    $totalExpected,
                    $totalCompleted,
                    $percentage,
                    $dataMonth,
                    $updatedBy,
                    $updatedAt,
                    $updatedBy,
                    $updatedAt,
                    $exactIndicatorName
                );
                $insertStmt->execute();
                $insertStmt->close();
            }
            $checkStmt->close();
        }
        $stmt->close();
    }
}

// Sync all data when page loads (for RamsisE and mgrhr_mro)
if ($_SESSION['username'] === 'RamsisE' || $_SESSION['username'] === 'mgrhr_mro') {
    // Get all distinct report data for current and future months - use exact report_type
    $syncQuery = "SELECT DISTINCT report_type, report_month, report_year 
                  FROM mro_cpr_report 
                  WHERE CONCAT(report_year, '-', LPAD(report_month, 2, '0'), '-01') >= CURDATE()
                  AND cost_center_code = 'DIR'";
    $syncResult = $conn->query($syncQuery);

    if ($syncResult) {
        while ($row = $syncResult->fetch_assoc()) {
            // Use the exact report_type from the database
            syncFromMroCprReport($conn, $row['report_type'], $row['report_month'], $row['report_year']);
        }
    }

    // Also sync for the current selected month if it's current/future
    $currentMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
    if ($currentMonth >= date('Y-m')) {
        $currentYear = date('Y', strtotime($currentMonth));
        $currentMonthNum = date('m', strtotime($currentMonth));

        // Get all report types for this month with DIR records
        $currentSyncQuery = "SELECT DISTINCT report_type 
                             FROM mro_cpr_report 
                             WHERE report_month = ? AND report_year = ? AND cost_center_code = 'DIR'";
        $currentStmt = $conn->prepare($currentSyncQuery);
        if ($currentStmt) {
            $currentStmt->bind_param("ii", $currentMonthNum, $currentYear);
            $currentStmt->execute();
            $currentResult = $currentStmt->get_result();

            while ($row = $currentResult->fetch_assoc()) {
                syncFromMroCprReport($conn, $row['report_type'], $currentMonthNum, $currentYear);
            }
            $currentStmt->close();
        }
    }
}

$currentMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$isEditable = ($currentMonth >= date('Y-m')) ? true : false;
$dataMonth = $currentMonth . '-01';
$currentDateForComparison = date('Y-m') . '-01';

// Get existing data
$query = "SELECT m.id, m.indicator_name, m.department, m.actual_value, m.target_value, 
          m.percentage_achievement, m.remarks, m.updated_at, u.full_name as updated_by_name,
          m.created_at, c.full_name as created_by_name
          FROM master_performance_data m
          LEFT JOIN users u ON m.updated_by = u.id
          LEFT JOIN users c ON m.created_by = c.id
          WHERE m.data_month = ?
          ORDER BY m.indicator_name, FIELD(m.department, 'BMT', 'LMT', 'CMT', 'EMT', 'AEP', 'MSM', 'QA', 'MRO HR', 'MD/DIV.', 'Remainder')";

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
$stmt->close();

// Get indicators from master list
$indicatorsResult = $conn->query("SELECT DISTINCT TRIM(indicator_name) as indicator_name FROM performance_indicators ORDER BY created_at ASC");
$indicators = [];
$seenNames = [];

if ($indicatorsResult) {
    while ($row = $indicatorsResult->fetch_assoc()) {
        $indicatorName = trim($row['indicator_name']);
        $lowercaseName = strtolower($indicatorName);

        // Only add if we haven't seen this indicator name before
        if (!in_array($lowercaseName, $seenNames)) {
            $seenNames[] = $lowercaseName;
            $indicators[$indicatorName] = [
                'name' => $indicatorName,
                'targets' => []
            ];
        }
    }
}

// Also include indicators that have data in mro_cpr_report but not in performance_indicators
$mroIndicatorsQuery = "SELECT DISTINCT report_type FROM mro_cpr_report WHERE report_type NOT IN (SELECT indicator_name FROM performance_indicators)";
$mroIndicatorsResult = $conn->query($mroIndicatorsQuery);
if ($mroIndicatorsResult) {
    while ($row = $mroIndicatorsResult->fetch_assoc()) {
        $indicatorName = trim($row['report_type']);
        $lowercaseName = strtolower($indicatorName);

        if (!in_array($lowercaseName, $seenNames)) {
            $seenNames[] = $lowercaseName;
            $indicators[$indicatorName] = [
                'name' => $indicatorName,
                'targets' => []
            ];
        }
    }
}

$departments = ['BMT', 'LMT', 'CMT', 'EMT', 'AEP', 'MSM', 'QA', 'PSCM', 'MRO HR', 'MD/DIV.', 'Remainder'];

// Load default targets
$targetsQuery = "SELECT indicator_name, department, default_target FROM indicator_target_defaults";
$targetsResult = $conn->query($targetsQuery);
if ($targetsResult) {
    while ($row = $targetsResult->fetch_assoc()) {
        $targetIndicatorName = trim($row['indicator_name']);
        foreach ($indicators as $key => &$info) {
            if (strtolower($key) === strtolower($targetIndicatorName)) {
                $info['targets'][$row['department']] = (float)$row['default_target'];
                break;
            }
        }
    }
}

// Set default targets for indicators that don't have them
foreach ($indicators as $key => &$info) {
    foreach ($departments as $dept) {
        if (!isset($info['targets'][$dept])) {
            $info['targets'][$dept] = 100;
        }
    }
}

$conn->close();

// Helper functions
function getValue($dataMap, $dept, $indicator, $field, $default = null)
{
    if (isset($dataMap[$dept]) && isset($dataMap[$dept][$indicator]) && isset($dataMap[$dept][$indicator][$field])) {
        return $dataMap[$dept][$indicator][$field];
    }
    // Try case-insensitive match
    foreach ($dataMap[$dept] ?? [] as $key => $value) {
        if (strtolower(trim($key)) === strtolower(trim($indicator))) {
            return $value[$field] ?? $default;
        }
    }
    return $default;
}

function getCellLastUpdateInfo($dataMap, $dept, $indicator)
{
    if (isset($dataMap[$dept]) && isset($dataMap[$dept][$indicator])) {
        $record = $dataMap[$dept][$indicator];
    } else {
        // Try case-insensitive match
        foreach ($dataMap[$dept] ?? [] as $key => $value) {
            if (strtolower(trim($key)) === strtolower(trim($indicator))) {
                $record = $value;
                break;
            }
        }
    }

    if (!isset($record)) return null;

    $updatedAt = $record['updated_at'] ?? null;
    $updatedBy = $record['updated_by_name'] ?? null;

    if (empty($updatedAt) || $updatedAt === '0000-00-00 00:00:00') {
        $updatedAt = $record['created_at'] ?? null;
        $updatedBy = $record['created_by_name'] ?? null;
    }

    if (!$updatedAt || $updatedAt === '0000-00-00 00:00:00') return null;

    return [
        'time' => date('Y-m-d H:i', strtotime($updatedAt)),
        'person' => $updatedBy ?: 'N/A'
    ];
}

// Determine if user can edit (only RamsisE and mgrhr_mro can edit, and only for current/future months)
$canEdit = ($_SESSION['username'] === 'RamsisE' || $_SESSION['username'] === 'mgrhr_mro') && $isEditable;

$message = $_SESSION['message'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['message'], $_SESSION['error']);
?>
<!-- HTML continues same as before... -->
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Master Data Entry - HR Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="icon" type="image/png" href="../assets/images/ethiopian_logo.ico">
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
            .container {
                padding: 0.5rem 3rem;
            }
        }

        @media (max-width: 1366px) {
            .container {
                padding: 0.5rem 1rem;
            }
        }

        .compact-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
            background: var(--medium-bg);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
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
            width: 220px;
            min-width: 220px;
            max-width: 280px;
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

        .indicator-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .indicator-name {
            flex: 1;
            cursor: pointer;
            padding: 2px 4px;
            border-radius: 3px;
        }

        .indicator-name:hover {
            background: rgba(2, 177, 170, 0.1);
        }

        .indicator-actions {
            display: flex;
            gap: 5px;
        }

        .indicator-btn {
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 0.7rem;
            padding: 2px 5px;
            border-radius: 3px;
            transition: all 0.2s;
        }

        .indicator-btn.edit-btn {
            color: var(--accent);
        }

        .indicator-btn.edit-btn:hover {
            background: rgba(2, 177, 170, 0.2);
            transform: scale(1.05);
        }

        .indicator-btn.delete-btn {
            color: var(--danger);
        }

        .indicator-btn.delete-btn:hover {
            background: rgba(197, 23, 19, 0.2);
            transform: scale(1.05);
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
            background: rgba(2, 177, 170, 0.1);
            border-color: var(--accent);
        }

        .compact-table input.editable:hover {
            background: rgba(2, 177, 170, 0.2);
        }

        .compact-table input.non-editable {
            background: var(--input-bg);
            color: var(--text-secondary);
            cursor: not-allowed;
            opacity: 0.7;
        }

        .target-input {
            background: rgba(43, 53, 62, 0.1);
        }

        .percentage-input {
            background: rgba(2, 177, 170, 0.15);
            font-weight: bold;
        }

        .remainder-input {
            background: rgba(239, 242, 77, 0.15);
            color: var(--warning);
            font-weight: bold;
        }

        .audit-info {
            font-size: 0.55rem;
            color: var(--accent);
            text-align: center;
            margin-top: 4px;
            padding-top: 2px;
            border-top: 1px dashed var(--border-light);
            line-height: 1.2;
        }

        .audit-info-missing {
            font-size: 0.55rem;
            color: #999;
            text-align: center;
            margin-top: 4px;
            padding-top: 2px;
            border-top: 1px dashed var(--border-light);
            line-height: 1.2;
            font-style: italic;
        }

        .audit-info span {
            display: inline-block;
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
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            opacity: 0.9;
        }

        .info-banner {
            background: rgba(2, 177, 170, 0.1);
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
                width: 200px;
                min-width: 200px;
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

        .warning-banner,
        .success-banner,
        .error-banner {
            padding: 0.6rem 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            text-align: center;
            font-size: 0.75rem;
            font-weight: bold;
        }

        .warning-banner {
            background: var(--warning);
            color: var(--text-primary);
        }

        .success-banner {
            background: var(--success);
            color: white;
        }

        .error-banner {
            background: var(--danger);
            color: white;
        }

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

        .add-indicator-row {
            background: var(--dark-bg);
        }

        .add-indicator-cell {
            padding: 0.5rem !important;
        }

        .add-indicator-form {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .add-indicator-input {
            flex: 1;
            padding: 0.4rem 0.5rem;
            border: 1px solid var(--border-light);
            border-radius: 4px;
            background: var(--input-bg);
            color: var(--text-primary);
            font-size: 0.7rem;
        }

        .add-indicator-input:focus {
            outline: none;
            border-color: var(--accent);
        }

        .add-indicator-btn {
            background: var(--accent);
            color: white;
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.7rem;
            font-weight: bold;
            white-space: nowrap;
        }

        .add-indicator-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--medium-bg);
            padding: 1.5rem;
            border-radius: 10px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .modal-content h3 {
            margin-top: 0;
            color: var(--accent);
        }

        .modal-content input {
            width: 100%;
            padding: 0.5rem;
            margin: 1rem 0;
            border: 1px solid var(--border-light);
            border-radius: 4px;
            background: var(--input-bg);
            color: var(--text-primary);
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .modal-buttons button {
            padding: 0.4rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .modal-buttons .confirm-btn {
            background: var(--accent);
            color: white;
        }

        .modal-buttons .cancel-btn {
            background: #666;
            color: white;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }

            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        #temporaryMessage {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            padding: 15px 20px;
            border-radius: 5px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            font-weight: bold;
            animation: slideIn 0.3s ease-out;
        }

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
            background: rgba(2, 177, 170, 0.05);
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
            background: rgba(2, 177, 170, 0.1);
        }

        body.light-theme .remainder-input {
            background: rgba(239, 242, 77, 0.2);
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

        .sync-badge {
            background: var(--success);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.65rem;
            margin-left: 10px;
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="master_data.php" class="navbar-brand">HR & Finance Dashboard</a>
            <div class="navbar-menu">
                <a href="master_data.php" style="color: var(--accent);">Master Data</a>
                <a href="../director/md_dashboard.php">Dashboard</a>
                <a href="../admin/report_mro_cpr.php">Director Data Entry</a>
                <a href="data_history.php">History</a>
                <div class="user-info">
                    <button id="themeToggle" class="btn theme-toggle">☀️ Light</button>
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="#" onclick="openPasswordModal(); return false;" style="cursor: pointer;">🔑 Change Password</a>
                    <a href="../logout.php" class="btn">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="month-navigation">
            <button onclick="changeMonth('prev')" class="btn">&larr; Previous Month</button>
            <h3>Master Data Entry - <?php echo date('F Y', strtotime($dataMonth)); ?></h3>
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
            <strong>Data is automatically synced from Director Data Entry</strong>
            <!-- <?php if ($_SESSION['username'] === 'RamsisE' || $_SESSION['username'] === 'mgrhr_mro'): ?>
                <span class="sync-badge">🔄 Auto-sync enabled</span>
            <?php endif; ?> -->
            <!-- <br><span style="font-size: 0.65rem;">Target = Sum of Expected across all cost centers | Actual = Sum of Completed across all cost centers</span> -->
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($indicators as $indicatorName => $indicatorInfo): ?>
                                <tr>
                                    <td class="indicator-cell">
                                        <div class="indicator-header">
                                            <span class="indicator-name" <?php if ($canEdit): ?>onclick="editIndicator('<?php echo htmlspecialchars($indicatorName); ?>')" style="cursor: pointer;" <?php endif; ?>>
                                                <?php echo htmlspecialchars($indicatorInfo['name']); ?>
                                            </span>
                                            <?php if ($canEdit): ?>
                                                <div class="indicator-actions">
                                                    <button type="button" class="indicator-btn edit-btn" onclick="editIndicator('<?php echo htmlspecialchars($indicatorName); ?>')" title="Edit Indicator">✏️</button>
                                                    <button type="button" class="indicator-btn delete-btn" onclick="deleteIndicator('<?php echo htmlspecialchars($indicatorName); ?>')" title="Delete Indicator">🗑️</button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <?php foreach ($departments as $dept):
                                        $actualValue = getValue($dataMap, $dept, $indicatorName, 'actual_value');
                                        $targetValue = getValue($dataMap, $dept, $indicatorName, 'target_value');
                                        $percentageValue = getValue($dataMap, $dept, $indicatorName, 'percentage_achievement');

                                        $actualFormatted = $actualValue !== null ? number_format((float)$actualValue, 2) : '';
                                        $targetFormatted = $targetValue !== null ? number_format((float)$targetValue, 2) : '';
                                        $percentageFormatted = $percentageValue !== null ? number_format((float)$percentageValue, 2) : '';

                                        $isRemainder = ($dept === 'Remainder');
                                        $cellAudit = getCellLastUpdateInfo($dataMap, $dept, $indicatorName);
                                    ?>
                                        <td class="input-cell">
                                            <?php if ($isRemainder): ?>
                                                <input type="text"
                                                    class="remainder-actual remainder-input non-editable"
                                                    data-md-div-indicator="<?php echo htmlspecialchars($indicatorName); ?>"
                                                    value="<?php echo htmlspecialchars($percentageFormatted); ?>"
                                                    readonly
                                                    placeholder="Auto">
                                                <input type="hidden"
                                                    name="data[Remainder][<?php echo htmlspecialchars($indicatorName); ?>][target]"
                                                    value="100">
                                                <input type="hidden"
                                                    name="data[Remainder][<?php echo htmlspecialchars($indicatorName); ?>][percentage]"
                                                    class="remainder-hidden"
                                                    value="<?php echo htmlspecialchars($percentageFormatted); ?>">
                                                <input type="hidden"
                                                    name="data[Remainder][<?php echo htmlspecialchars($indicatorName); ?>][actual]"
                                                    value="<?php echo htmlspecialchars($percentageFormatted); ?>">

                                                <?php if ($cellAudit): ?>
                                                    <div class="audit-info">
                                                        <span>🕒 <?php echo htmlspecialchars($cellAudit['time']); ?></span><br>
                                                        <span><?php echo htmlspecialchars($cellAudit['person']); ?></span>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="audit-info-missing">
                                                        <span>📝 Not yet saved</span>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <input type="number" step="0.01"
                                                    name="data[<?php echo $dept; ?>][<?php echo htmlspecialchars($indicatorName); ?>][actual]"
                                                    value="<?php echo htmlspecialchars($actualFormatted); ?>"
                                                    <?php echo !$canEdit ? 'disabled' : ''; ?>
                                                    class="actual-input <?php echo $canEdit ? 'editable' : 'non-editable'; ?>"
                                                    data-dept="<?php echo $dept; ?>"
                                                    data-indicator="<?php echo htmlspecialchars($indicatorName); ?>"
                                                    placeholder="Actual">

                                                <input type="number" step="0.01"
                                                    name="data[<?php echo $dept; ?>][<?php echo htmlspecialchars($indicatorName); ?>][target]"
                                                    value="<?php echo htmlspecialchars($targetFormatted); ?>"
                                                    <?php echo !$canEdit ? 'disabled' : ''; ?>
                                                    class="target-input <?php echo $canEdit ? 'editable' : 'non-editable'; ?>"
                                                    data-dept="<?php echo $dept; ?>"
                                                    data-indicator="<?php echo htmlspecialchars($indicatorName); ?>"
                                                    placeholder="Target"
                                                    style="margin-top: 2px;">

                                                <input type="number" step="0.01"
                                                    name="data[<?php echo $dept; ?>][<?php echo htmlspecialchars($indicatorName); ?>][percentage]"
                                                    value="<?php echo htmlspecialchars($percentageFormatted); ?>"
                                                    class="percentage-input percentage-field"
                                                    data-dept="<?php echo $dept; ?>"
                                                    data-indicator="<?php echo htmlspecialchars($indicatorName); ?>"
                                                    readonly
                                                    style="margin-top: 2px;"
                                                    placeholder="%">

                                                <?php if ($cellAudit): ?>
                                                    <div class="audit-info">
                                                        <span>🕒 <?php echo htmlspecialchars($cellAudit['time']); ?></span><br>
                                                        <span><?php echo htmlspecialchars($cellAudit['person']); ?></span>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="audit-info-missing">
                                                        <span>📝 Not yet saved</span>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>

                            <?php if ($canEdit): ?>
                                <tr class="add-indicator-row">
                                    <td class="add-indicator-cell indicator-cell">
                                        <div class="add-indicator-form">
                                            <input type="text" id="newIndicatorName" class="add-indicator-input" placeholder="Enter new indicator name...">
                                            <button type="button" class="add-indicator-btn" onclick="addIndicator()">➕ Add Indicator</button>
                                        </div>
                                    </td>
                                    <?php foreach ($departments as $dept): ?>
                                        <td class="add-indicator-cell" style="text-align: center; color: #999; font-size: 0.6rem;">➕</td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endif; ?>

                            <tr style="background: var(--dark-bg);">
                                <td class="indicator-cell"><strong>📝 Remarks</strong></td>
                                <?php foreach ($departments as $dept):
                                    $firstIndicator = array_key_first($indicators);
                                    $remarksValue = getValue($dataMap, $dept, $firstIndicator, 'remarks', '');
                                ?>
                                    <td class="input-cell">
                                        <input type="text"
                                            name="remarks[<?php echo $dept; ?>]"
                                            value="<?php echo htmlspecialchars($remarksValue ?? ''); ?>"
                                            <?php echo !$canEdit ? 'disabled' : ''; ?>
                                            class="<?php echo $canEdit ? 'editable' : 'non-editable'; ?>"
                                            placeholder="Add remarks..."
                                            style="width: 100%; text-align: left; font-size: 0.65rem;">
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <?php if ($canEdit): ?>
                    <div class="save-section">
                        <button type="submit" class="btn" style="font-size: 0.9rem; padding: 0.6rem 1.5rem;">
                            💾 Save All Changes
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3>Edit Indicator Name</h3>
            <input type="text" id="editIndicatorInput" placeholder="Enter new indicator name...">
            <div class="modal-buttons">
                <button class="cancel-btn" onclick="closeModal()">Cancel</button>
                <button class="confirm-btn" onclick="confirmEdit()">Save Changes</button>
            </div>
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

        // Update Remainder based on MD/DIV. percentage
        function updateRemainderForIndicator(indicator) {
            const mdDivPercentageInput = document.querySelector(`input.percentage-field[data-dept="MD/DIV."][data-indicator="${indicator}"]`);

            if (mdDivPercentageInput) {
                let mdDivPercentage = parseFloat(mdDivPercentageInput.value);
                let remainderValue = '';

                if (!isNaN(mdDivPercentage)) {
                    let maxValue = Math.max(100, mdDivPercentage);
                    remainderValue = (maxValue - mdDivPercentage).toFixed(2);
                }

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

        // Only add event listeners if user can edit
        <?php if ($canEdit): ?>
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
        <?php endif; ?>

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

        <?php if ($canEdit): ?>
            // Global variables for indicator management
            let currentEditIndicator = null;
            let currentEditOriginalName = null;

            function editIndicator(indicatorName) {
                currentEditIndicator = indicatorName;
                currentEditOriginalName = indicatorName;
                document.getElementById('editIndicatorInput').value = indicatorName;
                document.getElementById('editModal').style.display = 'flex';
            }

            // Indicator Management Functions
            function showTemporaryMessage(message, type = 'success') {
                const existingMsg = document.getElementById('temporaryMessage');
                if (existingMsg) {
                    existingMsg.remove();
                }

                const msgDiv = document.createElement('div');
                msgDiv.id = 'temporaryMessage';
                msgDiv.className = type === 'success' ? 'success-banner' : 'error-banner';
                msgDiv.style.position = 'fixed';
                msgDiv.style.top = '20px';
                msgDiv.style.right = '20px';
                msgDiv.style.zIndex = '9999';
                msgDiv.style.maxWidth = '400px';
                msgDiv.innerHTML = message;

                document.body.appendChild(msgDiv);

                setTimeout(() => {
                    if (msgDiv) {
                        msgDiv.style.animation = 'slideOut 0.3s ease-out';
                        setTimeout(() => {
                            if (msgDiv && msgDiv.parentNode) {
                                msgDiv.remove();
                            }
                        }, 300);
                    }
                }, 3000);
            }

            function addIndicator() {
                const input = document.getElementById('newIndicatorName');
                const indicatorName = input.value.trim();

                if (!indicatorName) {
                    alert('Please enter an indicator name');
                    return;
                }

                const addBtn = document.querySelector('.add-indicator-btn');
                const originalText = addBtn.textContent;
                addBtn.disabled = true;
                addBtn.textContent = 'Adding...';

                const formData = new URLSearchParams();
                formData.append('action', 'add');
                formData.append('indicator_name', indicatorName);

                fetch('manage_indicators.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: formData.toString()
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showTemporaryMessage('✓ Indicator "' + indicatorName + '" added successfully!', 'success');
                            input.value = '';
                            setTimeout(() => {
                                location.reload();
                            }, 1000);
                        } else if (data.error) {
                            alert('Error: ' + data.error);
                            addBtn.disabled = false;
                            addBtn.textContent = originalText;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Failed to add indicator: ' + error.message);
                        addBtn.disabled = false;
                        addBtn.textContent = originalText;
                    });
            }

            function confirmEdit() {
                const newName = document.getElementById('editIndicatorInput').value.trim();

                if (!newName) {
                    alert('Please enter an indicator name');
                    return;
                }

                if (newName === currentEditIndicator) {
                    closeModal();
                    return;
                }

                const existingIndicators = <?php echo json_encode(array_keys($indicators)); ?>;
                const isDuplicate = existingIndicators.some(existing =>
                    existing.toLowerCase() === newName.toLowerCase() &&
                    existing.toLowerCase() !== currentEditIndicator.toLowerCase()
                );

                if (isDuplicate) {
                    alert('This indicator name already exists! Please use a different name.');
                    return;
                }

                const confirmBtn = document.querySelector('.modal .confirm-btn');
                const originalText = confirmBtn.textContent;
                confirmBtn.disabled = true;
                confirmBtn.textContent = 'Updating...';

                const formData = new URLSearchParams();
                formData.append('action', 'edit');
                formData.append('old_name', currentEditOriginalName);
                formData.append('new_name', newName);

                fetch('manage_indicators.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: formData.toString()
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showTemporaryMessage('✓ Indicator renamed from "' + currentEditOriginalName + '" to "' + newName + '" successfully!', 'success');
                            setTimeout(() => {
                                location.reload();
                            }, 1000);
                        } else if (data.error) {
                            alert('Error: ' + data.error);
                            confirmBtn.disabled = false;
                            confirmBtn.textContent = originalText;
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Failed to edit indicator: ' + error.message);
                        confirmBtn.disabled = false;
                        confirmBtn.textContent = originalText;
                    });
            }

            function deleteIndicator(indicatorName) {
                let confirmMessage = `Are you sure you want to delete "${indicatorName}"?\n\n`;
                confirmMessage += `This will remove the indicator from CURRENT and FUTURE months only.\n`;
                confirmMessage += `Past months data will be preserved.\n\n`;
                confirmMessage += `Click OK to delete, Cancel to abort.`;

                if (!confirm(confirmMessage)) {
                    return;
                }

                const deleteBtns = document.querySelectorAll(`.delete-btn`);
                deleteBtns.forEach(btn => {
                    if (btn.closest('tr') && btn.closest('tr').querySelector('.indicator-name')?.innerText === indicatorName) {
                        btn.disabled = true;
                        btn.textContent = '⏳';
                    }
                });

                const formData = new URLSearchParams();
                formData.append('action', 'delete');
                formData.append('indicator_name', indicatorName);
                formData.append('force', '1');

                fetch('manage_indicators.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: formData.toString()
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showTemporaryMessage(data.message || `✓ Indicator "${indicatorName}" deleted from current/future months!`, 'success');
                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        } else if (data.error) {
                            alert('Error: ' + data.error);
                            deleteBtns.forEach(btn => {
                                btn.disabled = false;
                                btn.textContent = '🗑️';
                            });
                        } else if (data.warning) {
                            if (confirm(data.message)) {
                                // Retry with force
                                const forceFormData = new URLSearchParams();
                                forceFormData.append('action', 'delete');
                                forceFormData.append('indicator_name', indicatorName);
                                forceFormData.append('force', '1');

                                fetch('manage_indicators.php', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/x-www-form-urlencoded',
                                        },
                                        body: forceFormData.toString()
                                    })
                                    .then(response2 => response2.json())
                                    .then(data2 => {
                                        if (data2.success) {
                                            showTemporaryMessage(data2.message || `✓ Indicator "${indicatorName}" deleted!`, 'success');
                                            setTimeout(() => {
                                                location.reload();
                                            }, 1500);
                                        } else {
                                            alert('Error: ' + data2.error);
                                        }
                                        deleteBtns.forEach(btn => {
                                            btn.disabled = false;
                                            btn.textContent = '🗑️';
                                        });
                                    });
                            } else {
                                deleteBtns.forEach(btn => {
                                    btn.disabled = false;
                                    btn.textContent = '🗑️';
                                });
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Failed to delete indicator: ' + error.message);
                        deleteBtns.forEach(btn => {
                            btn.disabled = false;
                            btn.textContent = '🗑️';
                        });
                    });
            }

            function closeModal() {
                document.getElementById('editModal').style.display = 'none';
                currentEditIndicator = null;
                currentEditOriginalName = null;
            }

            window.onclick = function(event) {
                const modal = document.getElementById('editModal');
                if (event.target === modal) {
                    closeModal();
                }
            }

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    const modal = document.getElementById('editModal');
                    if (modal && modal.style.display === 'flex') {
                        closeModal();
                    }
                }
            });

            document.getElementById('editIndicatorInput')?.addEventListener('keypress', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    confirmEdit();
                }
            });

            document.getElementById('newIndicatorName')?.addEventListener('keypress', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    addIndicator();
                }
            });
        <?php endif; ?>

        // Initialize theme and remainder calculations when page loads
        document.addEventListener('DOMContentLoaded', function() {
            new ThemeManager();
            initializeRemainders();
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