<?php
// save_annualleave_upload.php - Save Annual Vacation Utilization uploaded data
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

if ($report_type !== 'Annual Vacation Utilization Status') {
    $response['message'] = 'This save is only for Annual Vacation Utilization report';
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
    'MRODR441' => 'DIR',
    'MROMG451' => 'AVS',
    'MRORFM462' => 'B777',
    'MROMG461' => 'B787',
    'MROMG463' => 'B737',
    'MROMG471' => 'ACS',
    'MROMG521' => 'CAB',
    'MROMG442' => 'TEC',
    'MROFM525' => 'APS',

    // CMT
    'MRODR541' => 'DIR',
    'MROMG546' => 'WKH',
    'MROMG551' => 'CES',
    'MROMG413' => 'NDT',
    'MROSU547' => 'MES',
    'MROMG561' => 'MCS',
    'MROMG571' => 'ACS',

    // LMT
    'MRODR446' => 'DIR',
    'MROMG481' => 'DMM',
    'MROMG533' => 'TPL',
    'MROMG540' => 'GAM',
    'MRORSP572' => 'ACM',
    'MROMG531' => 'ALM',
    'MROSU483' => 'ADM',

    // EMT
    'MRODR581' => 'DIR',
    'MROMG414' => 'EMI',
    'MROMG582' => 'ETS',
    'MROMG583' => 'RNP',
    'MROMG584' => 'CFM',
    'MROMG585' => 'RSH',

    // AEP
    'MRODR341' => 'DIR',
    'MROMG351' => 'AMP',
    'MRORMG361' => 'ASE',
    'MROMG371' => 'MPR',
    'MROMG332' => 'ALE',
    'MROMG381' => 'EQA',
    'MROMG382' => 'ADO',

    // PSCM
    'MRODR431' => 'DIR',
    'MROMG434' => 'EMP',
    'MROMG437' => 'WAP',
    'MROMG433' => 'TPU',
    'MROMG430' => 'GWC',
    'MROMG439' => 'EXT',
    'MROMG450' => 'MMP',
    'MROMG542' => 'PLC',

    // MSM
    'MRODR321' => 'DIR',
    'MROMG322' => 'MSM',
    'MROMG323' => 'MCS',

    // QA
    'MROMG421' => 'QAS',
];

// Also map by name for rows that don't have CC codes
$nameMapping = [
    // BMT
    'Dir. A/C Base Maint.' => 'DIR',
    'Mgr Avionics Sys Maint' => 'AVS',
    'Mgr. 757/777/A350' => 'B777',
    'Mgr. 767/787' => 'B787',
    'Mgr Sched Main737' => 'B737',
    'Mgr. Aircraft Structure Maintenance' => 'ACS',
    'Mgr. Cabin Maintenance' => 'CAB',
    'Mgr. Technical Support ABM' => 'TEC',
    'Mgr. A/C Paint SVCS.' => 'APS',

    // CMT
    'Dir. CMT' => 'DIR',
    'Mgr. Wire Kit & Harness Prod.' => 'WKH',
    'Mgr Computerized equipment shop' => 'CES',
    'Mgr. NDT, Stand. & Part Recv. Insp.' => 'NDT',
    'Comp.Maint.Engineering support' => 'MES',
    'Mgr Mechanical Comp shops' => 'MCS',
    'Mgr Avionics Comp shops' => 'ACS',

    // LMT
    'Dir Line Maintenance' => 'DIR',
    'Mgr. MCC' => 'DMM',
    'Mgr. Maintenance Turboprop & Light A/c' => 'TPL',
    'Mgr. General Aviation A/C Maint. Services' => 'GAM',
    'Mgr. Cabin Maintenance' => 'ACM',
    'Mgr. Line Maintenance' => 'ALM',
    'Mgr. Outstation Maint. Admin.' => 'ADM',

    // EMT
    'Director Engine Maintenance' => 'DIR',
    'Mgr. Engine Maintenance Inspection' => 'EMI',
    'Mgr Technical Support' => 'ETS',
    'Mgr.PW2000/PW4000/RB211/CF6&APU' => 'RNP',
    'Mgr CFM56/GE90/GENX &T.P ENGINES' => 'CFM',
    'Mgr Repair Shops' => 'RSH',

    // AEP
    'Dir. A/C Engineering & Planning' => 'DIR',
    'Mgr A/C MP & TCE' => 'AMP',
    'Mgr A/C Sys. Eng\'g' => 'ASE',
    'Mgr. Maint. Plng & Rec.' => 'MPR',
    'Mgr. EIS & Spec. Proj.' => 'ALE',
    'Mgr. Engineering QA' => 'EQA',
    'Mgr. Design Organization' => 'ADO',

    // PSCM
    'Dir. PSCM -Technical' => 'DIR',
    'Mgr Tact Purchase En' => 'EMP',
    'Mgr.Warehouse A/C Pa' => 'WAP',
    'Mgr Tact Purchase' => 'TPU',
    'Mgr.Grp Warr,Cont Mg' => 'GWC',
    'Mgr.Stra.Sourcing Te' => 'EXT',
    'Mgr.GrpMaterial Plan' => 'MMP',
    'Mgr Tact Purchase CmT' => 'PLC',

    // MSM
    'Dir. MRO Sls & Marketing' => 'DIR',
    'Mgr. MRO Market Development' => 'MSM',
    'Mgr. MRO Customer Support' => 'MCS',

    // QA
    'Mgr. MRO QMS & SMS' => 'QAS',
];

$conn = getConnection();
$userId = $_SESSION['user_id'];
$currentDateTime = date('Y-m-d H:i:s');

// Start transaction
$conn->begin_transaction();

try {
    $totalRows = 0;

    foreach ($data as $department => $records) {
        // Delete existing data for this department
        $deleteStmt = $conn->prepare("DELETE FROM mro_cpr_report WHERE report_type = ? AND report_month = ? AND report_year = ? AND department = ?");
        $deleteStmt->bind_param("siis", $report_type, $report_month, $report_year, $department);
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
                error_log("Mapped CC: $excelCC -> $shortCode");
            }
            // Then try mapping by section name
            elseif (!empty($sectionName) && isset($nameMapping[$sectionName])) {
                $shortCode = $nameMapping[$sectionName];
                error_log("Mapped by name: $sectionName -> $shortCode");
            }
            // If no mapping found, use the Excel CC code
            else {
                $shortCode = !empty($excelCC) ? $excelCC : 'UNK';
                error_log("No mapping found for: CC=$excelCC, Name=$sectionName, using: $shortCode");
            }

            // Get the cost center text (use the mapped code's name if available, otherwise use section name)
            $costCenterText = $sectionName;

            error_log("Saving: Dept=$department, Code=$shortCode, Name=$costCenterText, E=$expected, C=$completed");

            $insertStmt->bind_param(
                "siisssiiidis",
                $report_type,
                $report_month,
                $report_year,
                $department,
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
    error_log("Save Annual Leave upload error: " . $e->getMessage());
    $response['message'] = 'Error saving data: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
exit;
