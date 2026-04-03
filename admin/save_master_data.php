<?php
require_once '../session_config.php';
require_once '../includes/auth.php';
requireRole('hr');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: master_data.php');
    exit();
}

$month = $_POST['month'] ?? '';
$data = $_POST['data'] ?? [];
$remarks = $_POST['remarks'] ?? [];

// Validate month
if (empty($month) || !preg_match('/^\d{4}-\d{2}$/', $month)) {
    $_SESSION['error'] = "Invalid month format";
    header('Location: master_data.php');
    exit();
}

$dataMonth = $month . '-01';

// Check if month is editable
if ($month < date('Y-m')) {
    $_SESSION['error'] = "Cannot edit past months data";
    header('Location: master_data.php?month=' . $month);
    exit();
}

$conn = getConnection();
$userId = $_SESSION['user_id'];
$successCount = 0;
$errorCount = 0;
$skippedCount = 0;
$currentDateTime = date('Y-m-d H:i:s');

// ✅ Get ALL existing records with ONE query (including verification_status)
$existingRecords = [];
$query = "SELECT id, department, indicator_name, actual_value, target_value, percentage_achievement, remarks, verification_status 
          FROM master_performance_data 
          WHERE data_month = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $dataMonth);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $key = $row['department'] . '|' . $row['indicator_name'];
    $existingRecords[$key] = $row;
}
$stmt->close();

// ✅ Helper to check if a field was actually submitted (not just empty)
function wasFieldSubmitted($values, $field) {
    // Check if the field exists in the POST data
    return array_key_exists($field, $values);
}

// ✅ Helper to get submitted value (null if not submitted)
function getSubmittedValue($values, $field) {
    if (array_key_exists($field, $values)) {
        $val = $values[$field];
        if ($val !== '' && $val !== null) {
            return floatval($val);
        }
        return null; // Empty submitted means clear the field
    }
    return 'NOT_SUBMITTED'; // Special flag for fields not in POST
}

// ✅ Helper to compare existing vs new value
function hasValueChanged($existing, $newValue, $field) {
    // If field wasn't submitted at all, no change
    if ($newValue === 'NOT_SUBMITTED') {
        return false;
    }
    
    $existingVal = $existing[$field] ?? null;
    
    // Both null or empty
    if (($existingVal === null || $existingVal === '') && $newValue === null) {
        return false;
    }
    
    // One has value, other doesn't
    if (($existingVal === null || $existingVal === '') && $newValue !== null) {
        return true;
    }
    if (($existingVal !== null && $existingVal !== '') && $newValue === null) {
        return true;
    }
    
    // Both have numeric values
    if (is_numeric($existingVal) && is_numeric($newValue)) {
        return abs(floatval($existingVal) - floatval($newValue)) > 0.009;
    }
    
    return false;
}

$conn->begin_transaction();

try {
    // Track which records were actually modified
    $modifiedRecords = [];
    
    foreach ($data as $department => $indicatorsData) {
        foreach ($indicatorsData as $indicator => $values) {
            if (!is_array($values)) continue;
            
            // ✅ Only process if ANY field was submitted for this cell
            $actualSubmitted = wasFieldSubmitted($values, 'actual');
            $targetSubmitted = wasFieldSubmitted($values, 'target');
            $percentageSubmitted = wasFieldSubmitted($values, 'percentage');
            
            // Skip if no fields were submitted for this cell
            if (!$actualSubmitted && !$targetSubmitted && !$percentageSubmitted) {
                continue;
            }
            
            // Get submitted values (null if empty, 'NOT_SUBMITTED' if not present)
            $newActual = getSubmittedValue($values, 'actual');
            $newTarget = getSubmittedValue($values, 'target');
            $newPercentage = getSubmittedValue($values, 'percentage');
            
            // Calculate percentage if actual and target were submitted and target != 0
            if ($actualSubmitted && $targetSubmitted && $newActual !== null && $newTarget !== null && $newTarget != 0) {
                $calculatedPercentage = ($newActual / $newTarget) * 100;
                // Only use calculated if percentage wasn't submitted or was submitted empty
                if (!$percentageSubmitted || $newPercentage === null) {
                    $newPercentage = $calculatedPercentage;
                }
            }
            
            // Handle Remainder department (special calculation)
            if ($department === 'Remainder') {
                $mdDivKey = "MD/DIV.";
                // Check if MD/DIV percentage was submitted
                if (isset($data[$mdDivKey][$indicator]['percentage'])) {
                    $mdDivPercentage = getSubmittedValue($data[$mdDivKey][$indicator], 'percentage');
                    if ($mdDivPercentage !== null && $mdDivPercentage !== 'NOT_SUBMITTED') {
                        $maxValue = max(100, $mdDivPercentage);
                        $newPercentage = $maxValue - $mdDivPercentage;
                        $newPercentage = max(0, $newPercentage);
                        $newActual = $newPercentage;
                        $newTarget = 100;
                    }
                }
            }
            
            // Get remark for this department
            $newRemarkValue = $remarks[$department] ?? '';
            
            $key = $department . '|' . $indicator;
            
            if (isset($existingRecords[$key])) {
                $existing = $existingRecords[$key];
                
                // Check which fields actually changed
                $actualChanged = hasValueChanged($existing, $newActual, 'actual_value');
                $targetChanged = hasValueChanged($existing, $newTarget, 'target_value');
                $percentageChanged = hasValueChanged($existing, $newPercentage, 'percentage_achievement');
                $remarkChanged = ($existing['remarks'] ?? '') !== $newRemarkValue;
                
                // Only update if something actually changed
                if ($actualChanged || $targetChanged || $percentageChanged || $remarkChanged) {
                    // Use existing values for unchanged fields
                    $finalActual = $actualChanged ? $newActual : ($existing['actual_value'] ?? null);
                    $finalTarget = $targetChanged ? $newTarget : ($existing['target_value'] ?? null);
                    $finalPercentage = $percentageChanged ? $newPercentage : ($existing['percentage_achievement'] ?? null);
                    
                    // ✅ UPDATE with verification_status = 'verified'
                    $updateStmt = $conn->prepare("UPDATE master_performance_data 
                                                  SET actual_value = ?, target_value = ?, percentage_achievement = ?, 
                                                      remarks = ?, 
                                                      verification_status = 'verified',
                                                      verified_by = ?,
                                                      verified_at = ?,
                                                      updated_by = ?, updated_at = ?
                                                  WHERE id = ?");
                    $updateStmt->bind_param("dddssissi", $finalActual, $finalTarget, $finalPercentage, $newRemarkValue, 
                                           $userId, $currentDateTime, $userId, $currentDateTime, $existing['id']);
                    
                    if ($updateStmt->execute()) {
                        $successCount++;
                        $modifiedRecords[] = $key;
                    } else {
                        $errorCount++;
                        error_log("Update failed for $key: " . $updateStmt->error);
                    }
                    $updateStmt->close();
                } else {
                    $skippedCount++;
                }
            } else {
                // Only insert if at least one value was submitted
                if ($actualSubmitted || $targetSubmitted || $percentageSubmitted) {
                    // ✅ INSERT with verification_status = 'verified'
                    $insertStmt = $conn->prepare("INSERT INTO master_performance_data 
                                                  (data_month, department, indicator_name, actual_value, target_value, 
                                                   percentage_achievement, remarks, 
                                                   verification_status, verified_by, verified_at,
                                                   created_by, created_at, updated_by, updated_at)
                                                  VALUES (?, ?, ?, ?, ?, ?, ?, 'verified', ?, ?, ?, NOW(), ?, NOW())");
                    $insertStmt->bind_param("sssdddsssss", $dataMonth, $department, $indicator, $newActual, $newTarget, $newPercentage, 
                                           $newRemarkValue, $userId, $currentDateTime, $userId, $userId);
                    
                    if ($insertStmt->execute()) {
                        $successCount++;
                        $modifiedRecords[] = $key;
                    } else {
                        $errorCount++;
                        error_log("Insert failed for $key: " . $insertStmt->error);
                    }
                    $insertStmt->close();
                }
            }
        }
    }
    
    // Commit if no errors
    if ($errorCount == 0) {
        $conn->commit();
        if ($successCount > 0) {
            $_SESSION['message'] = "✅ Updated $successCount record(s) successfully and marked as verified for " . date('F Y', strtotime($dataMonth));
        } else {
            $_SESSION['message'] = "ℹ️ No changes detected. $skippedCount record(s) were already up to date.";
        }
    } else {
        $conn->rollback();
        $_SESSION['error'] = "⚠️ Updated $successCount records, but $errorCount failed. Changes have been rolled back.";
    }
    
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    error_log("Save error: " . $e->getMessage());
}

$conn->close();
header('Location: master_data.php?month=' . $month);
exit();
?>