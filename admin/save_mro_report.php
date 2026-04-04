<?php
require_once '../session_config.php';
require_once '../includes/auth.php';
requireRole('hr');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: report_mro_cpr.php');
    exit();
}

$conn = getConnection();
$userId = $_SESSION['user_id'];
$currentDateTime = date('Y-m-d H:i:s');

$reportType = $_POST['report_type'] ?? '';
$reportMonth = $_POST['report_month'] ?? '';
$reportYear = $_POST['report_year'] ?? '';
$department = $_POST['department'] ?? '';
$costCenters = $_POST['cost_center'] ?? [];

// Define the cost center names for each department (same as in report_mro_cpr.php)
$departmentCostCenters = [
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
    'PSCM' => [
        'GWC' => 'Mgr. Grp Warp Cont Mgt',
        'TPU' => 'Mgr. Tactical Purchase',
        'MMP' => 'Mgr. MRO Material Planning',
        'EMP' => 'Mgr. Engine Maint/Tactical Pur',
        'WAP' => 'Mgr. Warehouse A/C Part',
        'EXT' => 'Extra Sourcing',
        'PLC' => 'Mgr. Purchase-LMT&CMT Maint.',
        'DIR' => 'Dir. Prop. & Supp. Chain Mgt'
    ]
];

// Validate inputs
if (empty($reportType) || empty($reportMonth) || empty($reportYear) || empty($department) || empty($costCenters)) {
    $_SESSION['error'] = "Missing required fields";
    header('Location: report_mro_cpr.php?report=' . urlencode($reportType) . '&month=' . $reportMonth . '&year=' . $reportYear . '&department=' . $department);
    exit();
}

$dataMonth = $reportYear . '-' . str_pad($reportMonth, 2, '0', STR_PAD_LEFT) . '-01';

// Dynamically get indicator name from performance_indicators table
function getIndicatorName($conn, $reportType)
{
    // First try exact match
    $stmt = $conn->prepare("SELECT indicator_name FROM performance_indicators WHERE indicator_name = ?");
    $stmt->bind_param("s", $reportType);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return $row['indicator_name'];
    }
    $stmt->close();

    // If no exact match, try case-insensitive match
    $stmt = $conn->prepare("SELECT indicator_name FROM performance_indicators WHERE LOWER(indicator_name) = LOWER(?)");
    $stmt->bind_param("s", $reportType);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return $row['indicator_name'];
    }
    $stmt->close();

    // If still not found, return the report type as is
    return $reportType;
}

// Get the actual indicator name from performance_indicators
$indicatorName = getIndicatorName($conn, $reportType);

// Get the cost center names for this department
$deptCostCenters = $departmentCostCenters[$department] ?? [];

$conn->begin_transaction();

try {
    $successCount = 0;

    foreach ($costCenters as $code => $data) {
        $costCenterCode = $data['code'] ?? $code;

        // Get the proper cost center name from the department definition
        // First try to get from the data array, then from the department definition
        $costCenterName = $data['name'] ?? '';

        // If the name is empty or just "0", try to get from the department definition
        if (empty($costCenterName) || $costCenterName === '0') {
            $costCenterName = $deptCostCenters[$costCenterCode] ?? $costCenterCode;
        }

        $expected = isset($data['expected']) && $data['expected'] !== '' ? (int)$data['expected'] : 0;
        $completed = isset($data['completed']) && $data['completed'] !== '' ? (int)$data['completed'] : 0;
        $percentage = $expected > 0 ? round(($completed / $expected) * 100, 2) : 0;
        $notCompleted = $expected - $completed;

        // Check if record exists in mro_cpr_report
        $checkStmt = $conn->prepare("SELECT id FROM mro_cpr_report 
                                     WHERE report_type = ? AND report_month = ? AND report_year = ? 
                                     AND department = ? AND cost_center_code = ?");
        $checkStmt->bind_param("siiss", $reportType, $reportMonth, $reportYear, $department, $costCenterCode);
        $checkStmt->execute();
        $result = $checkStmt->get_result();

        if ($result->num_rows > 0) {
            // Update existing with verification_status = 'verified'
            $row = $result->fetch_assoc();
            $updateStmt = $conn->prepare("UPDATE mro_cpr_report 
                                          SET expected = ?, completed = ?, not_completed = ?, 
                                              percentage = ?, updated_by = ?, updated_at = ?,
                                              cost_center_text = ?,
                                              verification_status = 'verified'
                                          WHERE id = ?");
            $updateStmt->bind_param("iiiddssi", $expected, $completed, $notCompleted, $percentage, $userId, $currentDateTime, $costCenterName, $row['id']);
            $updateStmt->execute();
            $updateStmt->close();
        } else {
            // Insert new with verification_status = 'verified'
            $insertStmt = $conn->prepare("INSERT INTO mro_cpr_report 
                                          (report_type, report_month, report_year, department, 
                                           cost_center_code, cost_center_text, expected, completed, 
                                           not_completed, percentage, created_by, created_at, verification_status)
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'verified')");
            $insertStmt->bind_param(
                "siissiiiiiss",
                $reportType,
                $reportMonth,
                $reportYear,
                $department,
                $costCenterCode,
                $costCenterName,
                $expected,
                $completed,
                $notCompleted,
                $percentage,
                $userId,
                $currentDateTime
            );
            $insertStmt->execute();
            $insertStmt->close();
        }
        $checkStmt->close();

        // Sync DIRECTOR data to master_performance_data
        // Also sync for all cost centers if the report type is not a special case
        $shouldSync = ($costCenterCode === 'DIR') || ($reportType !== 'MRO CPR REPORT');

        if ($shouldSync) {
            $actualValue = (float)$completed;
            $targetValue = (float)$expected;
            $percentageAchievement = (float)$percentage;
            $remarks = "Auto-synced from " . $reportType . " - " . $costCenterName;

            // Determine which department to use in master_performance_data
            $masterDepartment = $department;

            // Map NSM to MSM (if needed)
            if ($masterDepartment === 'NSM') {
                $masterDepartment = 'MSM';
            }

            // Get the indicator name from performance_indicators (use the exact one)
            $masterIndicatorName = $indicatorName;

            // Check if record exists in master_performance_data
            $checkMasterStmt = $conn->prepare("SELECT id FROM master_performance_data 
                                                WHERE data_month = ? AND department = ? AND indicator_name = ?");
            $checkMasterStmt->bind_param("sss", $dataMonth, $masterDepartment, $masterIndicatorName);
            $checkMasterStmt->execute();
            $masterResult = $checkMasterStmt->get_result();

            if ($masterResult->num_rows > 0) {
                // Update existing
                $masterRow = $masterResult->fetch_assoc();
                $updateMasterStmt = $conn->prepare("UPDATE master_performance_data 
                                                    SET actual_value = ?, target_value = ?, 
                                                        percentage_achievement = ?, 
                                                        remarks = ?,
                                                        verification_status = 'verified',
                                                        verified_by = ?, verified_at = ?,
                                                        updated_by = ?, updated_at = ?
                                                    WHERE id = ?");
                $updateMasterStmt->bind_param(
                    "dddssssii",
                    $actualValue,
                    $targetValue,
                    $percentageAchievement,
                    $remarks,
                    $userId,
                    $currentDateTime,
                    $userId,
                    $currentDateTime,
                    $masterRow['id']
                );
                $updateMasterStmt->execute();
                $updateMasterStmt->close();
            } else {
                // Insert new
                $insertMasterStmt = $conn->prepare("INSERT INTO master_performance_data 
                                                    (data_month, department, indicator_name, 
                                                     actual_value, target_value, percentage_achievement,
                                                     remarks, verification_status, verified_by, verified_at,
                                                     created_by, created_at, updated_by, updated_at)
                                                    VALUES (?, ?, ?, ?, ?, ?, ?, 'verified', ?, ?, ?, ?, ?, ?)");
                $insertMasterStmt->bind_param(
                    "sssdddsssssss",
                    $dataMonth,
                    $masterDepartment,
                    $masterIndicatorName,
                    $actualValue,
                    $targetValue,
                    $percentageAchievement,
                    $remarks,
                    $userId,
                    $currentDateTime,
                    $userId,
                    $currentDateTime,
                    $userId,
                    $currentDateTime
                );
                $insertMasterStmt->execute();
                $insertMasterStmt->close();
            }
            $checkMasterStmt->close();
        }

        $successCount++;
    }

    $conn->commit();

    $message = urlencode("✓ Successfully saved $successCount record(s) and synced to Master Performance Data");
    header('Location: report_mro_cpr.php?report=' . urlencode($reportType) . '&month=' . $reportMonth . '&year=' . $reportYear . '&department=' . $department . '&message=' . $message);
} catch (Exception $e) {
    $conn->rollback();
    error_log("Save error: " . $e->getMessage());
    $error = urlencode("Error saving data: " . $e->getMessage());
    header('Location: report_mro_cpr.php?report=' . urlencode($reportType) . '&month=' . $reportMonth . '&year=' . $reportYear . '&department=' . $department . '&error=' . $error);
}

$conn->close();
exit();
