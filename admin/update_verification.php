<?php
require_once '../session_config.php';
require_once '../includes/auth.php';

// Only allow RamsisE to verify data
if ($_SESSION['username'] !== 'RamsisE') {
    $_SESSION['error'] = "Access denied. Only RamsisE can verify data.";
    header('Location: master_data.php');
    exit();
}

requireRole('hr');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: verify_data.php');
    exit();
}

$month = $_POST['month'] ?? date('Y-m');
$dataMonth = $month . '-01';
$userId = $_SESSION['user_id'];
$userName = $_SESSION['full_name'];

// Handle batch action
if (isset($_POST['batch_ids']) && isset($_POST['batch_action'])) {
    $batchIds = json_decode($_POST['batch_ids'], true);
    $action = $_POST['batch_action'];
    $notes = $_POST['notes'] ?? '';
    
    if (!is_array($batchIds) || empty($batchIds)) {
        $_SESSION['error'] = "No records selected";
        header('Location: verify_data.php?month=' . $month);
        exit();
    }
    
    $conn = getConnection();
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($batchIds as $recordId) {
        $result = processVerification($conn, $recordId, $action, $notes, $userId, $userName);
        if ($result) {
            $successCount++;
        } else {
            $errorCount++;
        }
    }
    
    $conn->close();
    
    $_SESSION['message'] = "$action completed for $successCount records. Errors: $errorCount";
    header('Location: verify_data.php?month=' . $month);
    exit();
}

// Handle single record verification
if (isset($_POST['record_id']) && isset($_POST['action'])) {
    $recordId = $_POST['record_id'];
    $action = $_POST['action'];
    $notes = $_POST['notes'] ?? '';
    
    $conn = getConnection();
    $result = processVerification($conn, $recordId, $action, $notes, $userId, $userName);
    $conn->close();
    
    if ($result) {
        $_SESSION['message'] = "Record $action successfully";
    } else {
        $_SESSION['error'] = "Failed to $action record";
    }
    
    header('Location: verify_data.php?month=' . $month);
    exit();
}

function processVerification($conn, $recordId, $action, $notes, $userId, $userName) {
    // First, get current verification status
    $checkStmt = $conn->prepare("SELECT verification_status, actual_value, target_value, percentage_achievement, remarks FROM master_performance_data WHERE id = ?");
    $checkStmt->bind_param("i", $recordId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $record = $result->fetch_assoc();
    $checkStmt->close();
    
    if (!$record) {
        return false;
    }
    
    if ($action === 'verify') {
        if ($record['verification_status'] === 'pending') {
            $updateStmt = $conn->prepare("UPDATE master_performance_data 
                                          SET verification_status = 'verified', 
                                              verified_by = ?, 
                                              verified_at = NOW(),
                                              remarks = CONCAT(IFNULL(remarks, ''), '\n[VERIFIED] By: ', ?, ' on ', NOW(), '\nNotes: ', ?)
                                          WHERE id = ?");
            $updateStmt->bind_param("issi", $userId, $userName, $notes, $recordId);
            
            if ($updateStmt->execute()) {
                // Log to audit table
                $oldData = json_encode([
                    'status' => 'pending',
                    'actual' => $record['actual_value'],
                    'target' => $record['target_value'],
                    'percentage' => $record['percentage_achievement']
                ]);
                $newData = json_encode([
                    'status' => 'verified',
                    'actual' => $record['actual_value'],
                    'target' => $record['target_value'],
                    'percentage' => $record['percentage_achievement'],
                    'verified_by' => $userName,
                    'notes' => $notes
                ]);
                logAction($recordId, 'verify', $oldData, $newData);
                $updateStmt->close();
                return true;
            }
            $updateStmt->close();
        }
    } elseif ($action === 'reject') {
        $updateStmt = $conn->prepare("UPDATE master_performance_data 
                                      SET verification_status = 'rejected',
                                          remarks = CONCAT(IFNULL(remarks, ''), '\n[REJECTED] By: ', ?, ' on ', NOW(), '\nReason: ', ?)
                                      WHERE id = ?");
        $updateStmt->bind_param("ssi", $userName, $notes, $recordId);
        
        if ($updateStmt->execute()) {
            // Log to audit table
            $oldData = json_encode([
                'status' => $record['verification_status'],
                'actual' => $record['actual_value'],
                'target' => $record['target_value'],
                'percentage' => $record['percentage_achievement']
            ]);
            $newData = json_encode([
                'status' => 'rejected',
                'reason' => $notes,
                'rejected_by' => $userName
            ]);
            logAction($recordId, 'reject', $oldData, $newData);
            $updateStmt->close();
            return true;
        }
        $updateStmt->close();
    }
    
    return false;
}
?>