<?php
// save_crew_meeting.php - Save Crew Meeting uploaded data
require_once '../session_config.php';
require_once '../includes/auth.php';
requireRole('hr');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

$report_type = $_POST['report_type'] ?? '';
$report_month = $_POST['report_month'] ?? '';
$report_year = $_POST['report_year'] ?? '';
$jsonData = $_POST['data'] ?? '';

if ($report_type !== 'Crew Meeting Minutes Submission') {
    $response['message'] = 'This save is only for Crew Meeting Minutes Submission report';
    echo json_encode($response);
    exit;
}

if (empty($report_month) || empty($report_year) || empty($jsonData)) {
    $response['message'] = 'Missing required parameters';
    echo json_encode($response);
    exit;
}

$data = json_decode($jsonData, true);
if (!$data || !is_array($data)) {
    $response['message'] = 'Invalid data format';
    echo json_encode($response);
    exit;
}

// Define the mapping from Excel CC codes to short codes
$ccMapping = [
    // BMT
    'MROMG451' => 'AVS',
    'MROMG461' => 'B787',
    'MROMG463' => 'B737',
    'MROMG471' => 'ACS',
    'MRORFM462' => 'B777',
    'MROFM525' => 'APS',
    'MROMG521' => 'CAB',
    'MROFM522' => 'CAB',
    'MROFM524' => 'CAB',
    'MROMG442' => 'TEC',
    'MROFM644' => 'TEC',
    'MROSU527' => 'TEC',

    // EMT
    'MROMG584' => 'CFM',
    'MROMG583' => 'RNP',
    'MROMG414' => 'EMI',
    'MROMG585' => 'RSH',
    'MROFM586' => 'RSH',
    'MROFM587' => 'RSH',
    'MROFM588' => 'RSH',
    'MROMG582' => 'ETS',

    // AEP
    'MROMG332' => 'ALE',
    'MROMG351' => 'AMP',
    'MROMG371' => 'MPR',
    'MROMG381' => 'EQA',
    'MROMG382' => 'ADO',
    'MRORMG361' => 'ASE',

    // CMT
    'MROMG546' => 'WKH',
    'MROSU547' => 'MES',
    'MROMG413' => 'NDT',
    'MROFM559' => 'MCS',
    'MROFM560' => 'MCS',
    'MROFM563' => 'MCS',
    'MROFM565' => 'MCS',
    'MROFM564' => 'MCS',
    'MROFM570' => 'MCS',
    'MRORFM562' => 'MCS',
    'MROTL566' => 'MCS',
    'MROFM555' => 'CES',
    'MROFM552' => 'CES',
    'MROFM553' => 'ACS',
    'MROFM573' => 'ACS',
    'MROFM574' => 'ACS',
    'MROFM575' => 'ACS',
    'MROFM576' => 'ACS',
    'MROFM577' => 'ACS',

    // LMT
    'MRORSP572' => 'ACM',
    'MROSU483' => 'ADM',
    'MROMG540' => 'GAM',
    'MROMG531' => 'ALM',
    'MROMG481' => 'DMM',
    'MROMG533' => 'TPL',

    // PSCM
    'MROMG430' => 'GWC',
    'MROMG433' => 'TPU',
    'MROMG434' => 'EMP',
    'MROMG439' => 'EXT',
    'MROMG450' => 'MMP',
    'MROMG542' => 'PLC',
    'MROMG437' => 'WAP',

    // MSM
    'MROMG322' => 'MSM',
    'MROMG323' => 'MCS',

    // QA
    'MROMG421' => 'QAS',

    // HR
    'MROMG335' => 'HR',
];

$conn = getConnection();
$userId = $_SESSION['user_id'];
$currentDateTime = date('Y-m-d H:i:s');

// Start transaction
$conn->begin_transaction();

try {
    $totalRows = 0;

    foreach ($data as $department => $records) {
        // Map department name (BMT, EMT, AEP, etc.)
        $dbDepartment = $department;

        error_log("Processing department: $dbDepartment with " . count($records) . " records");

        // Delete existing data for this department for this report
        $deleteStmt = $conn->prepare("DELETE FROM mro_cpr_report WHERE report_type = ? AND report_month = ? AND report_year = ? AND department = ?");
        $deleteStmt->bind_param("siis", $report_type, $report_month, $report_year, $dbDepartment);
        $deleteStmt->execute();
        $deleteStmt->close();

        // Insert new records
        $insertStmt = $conn->prepare("INSERT INTO mro_cpr_report 
            (report_type, report_month, report_year, department, cost_center_code, cost_center_text, 
             expected, completed, not_completed, percentage, created_by, created_at, verification_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'verified')");

        foreach ($records as $record) {
            $excelCC = $record['cost_center_code'] ?? '';
            $sectionName = $record['cost_center_text'] ?? '';
            $expected = intval($record['expected'] ?? 0);
            $completed = intval($record['completed'] ?? 0);
            $notCompleted = intval($record['not_completed'] ?? ($expected - $completed));
            $percentage = floatval($record['percentage'] ?? ($expected > 0 ? round(($completed / $expected) * 100, 2) : 0));

            // Map the CC code to short code
            $shortCode = '';

            // First try mapping by Excel CC code
            if (!empty($excelCC) && isset($ccMapping[$excelCC])) {
                $shortCode = $ccMapping[$excelCC];
            }
            // If no mapping found, use a default or keep original
            else {
                $shortCode = !empty($excelCC) ? $excelCC : 'OTHER';
            }

            $costCenterText = $sectionName;

            error_log("Saving: Dept=$dbDepartment, Code=$shortCode, Name=$costCenterText, Expected=$expected, Completed=$completed");

            $insertStmt->bind_param(
                "siisssiiidis",
                $report_type,
                $report_month,
                $report_year,
                $dbDepartment,
                $shortCode,
                $costCenterText,
                $expected,
                $completed,
                $notCompleted,
                $percentage,
                $userId,
                $currentDateTime
            );
            $insertStmt->execute();
            $totalRows++;
        }

        $insertStmt->close();
    }

    $conn->commit();

    $response['success'] = true;
    $response['message'] = "Successfully saved data for " . count($data) . " departments ($totalRows records)";
} catch (Exception $e) {
    $conn->rollback();
    error_log("Save Crew Meeting upload error: " . $e->getMessage());
    $response['message'] = 'Error saving data: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
exit;
