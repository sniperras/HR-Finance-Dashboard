<?php
require_once '../session_config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$department = $_GET['dept'] ?? '';
$indicator = $_GET['indicator'] ?? '';
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

if (empty($department) || empty($indicator)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

// Cost center mapping for ALL departments
$costCenterMapping = [
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
        'QAS' => 'Mgr. MRO Qty Ass & S/a',
        'DIR' => 'Dir. QA'
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
    ],
    'MRO HR' => [
        'HR' => 'Mgr. Human Resources',
        'DIR' => 'Dir. MRO HR'
    ]
];

$conn = getConnection();

$query = "SELECT cost_center_code, expected, completed, percentage 
          FROM mro_cpr_report 
          WHERE department = ? AND report_type = ? AND report_month = ? AND report_year = ?
          ORDER BY FIELD(cost_center_code, 'DIR'), cost_center_code";

$stmt = $conn->prepare($query);
$stmt->bind_param("ssii", $department, $indicator, $month, $year);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $costCenterCode = $row['cost_center_code'];
    // Get the display name from mapping
    $costCenterText = isset($costCenterMapping[$department][$costCenterCode]) 
        ? $costCenterMapping[$department][$costCenterCode] 
        : $costCenterCode;
    
    $data[] = [
        'cost_center_code' => $costCenterCode,
        'cost_center_text' => $costCenterText,
        'expected' => $row['expected'],
        'completed' => $row['completed'],
        'percentage' => $row['percentage']
    ];
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'data' => $data]);
?>