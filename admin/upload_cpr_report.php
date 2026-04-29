<?php
// ============================================
// upload_lockers.php - Handle Excel/CSV file upload for lockers
// ============================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $response = ['success' => false, 'message' => 'Unauthorized access'];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Include database connection
require_once 'db_connect.php';

// Include Composer autoloader for PhpSpreadsheet
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

// Set JSON response header
header('Content-Type: application/json');

$response = ['success' => false, 'data' => [], 'message' => '', 'errors' => [], 'uploaded' => 0, 'skipped' => 0];

// Check if this is a POST request with file
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
    $response['message'] = 'Please select a valid Excel or CSV file';
    echo json_encode($response);
    exit;
}

// Check if this is a manual entry (AJAX or form)
if (isset($_POST['manual_single'])) {
    $locker_id = trim($_POST['locker_id']);
    $name = trim($_POST['name']);
    $cost_center = trim($_POST['cost_center']);
    $code = trim($_POST['code']);
    $location = trim($_POST['location']);

    $result = insertLockerData($locker_id, $name, $cost_center, $code, $location);

    if ($result['success']) {
        $response['success'] = true;
        $response['message'] = "✓ Locker $locker_id added/updated successfully!";
    } else {
        $response['message'] = $result['error'];
    }
    echo json_encode($response);
    exit;
}

/**
 * Insert locker data into database
 */
function insertLockerData($locker_id, $name, $cost_center, $code, $location = null)
{
    global $pdo;

    if ($pdo === null) {
        return ['success' => false, 'error' => 'Database connection not available'];
    }

    if (empty($locker_id)) {
        return ['success' => false, 'error' => 'ID is required'];
    }
    if (empty($name)) {
        return ['success' => false, 'error' => 'NAME is required'];
    }
    if (empty($cost_center)) {
        return ['success' => false, 'error' => 'COST CENTER is required'];
    }
    if (empty($code)) {
        return ['success' => false, 'error' => 'CODE is required'];
    }

    $location = !empty($location) ? $location : null;

    try {
        $check_sql = "SELECT id FROM locker_list WHERE locker_id = :locker_id";
        $stmt = $pdo->prepare($check_sql);
        $stmt->execute([':locker_id' => $locker_id]);
        $exists = $stmt->fetch();

        if ($exists) {
            $sql = "UPDATE locker_list 
                    SET name = :name, cost_center = :cost_center, code = :code, location = :location, updated_at = NOW()
                    WHERE locker_id = :locker_id";
        } else {
            $sql = "INSERT INTO locker_list (locker_id, name, cost_center, code, location) 
                    VALUES (:locker_id, :name, :cost_center, :code, :location)";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':locker_id' => $locker_id,
            ':name' => $name,
            ':cost_center' => $cost_center,
            ':code' => $code,
            ':location' => $location
        ]);

        return ['success' => true, 'error' => null];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

// ============================================
// PROCESS FILE UPLOAD
// ============================================

$file = $_FILES['excel_file'];
$file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

try {
    // For CSV/TXT files, use traditional parsing
    if (in_array($file_ext, ['csv', 'txt'])) {
        $content = file_get_contents($file['tmp_name']);
        $lines = explode("\n", $content);

        if (empty($lines)) {
            $response['message'] = 'File is empty';
            echo json_encode($response);
            exit;
        }

        // Detect delimiter
        $first_line = $lines[0];
        $delimiter = "\t";
        if (strpos($first_line, ',') !== false) $delimiter = ',';
        elseif (strpos($first_line, ';') !== false) $delimiter = ';';

        // Parse headers
        $headers = str_getcsv($lines[0], $delimiter);
        $headers = array_map(function ($h) {
            return strtoupper(trim($h));
        }, $headers);

        // Find column indices
        $id_col = array_search('ID', $headers);
        $name_col = array_search('NAME', $headers);
        $cost_center_col = array_search('COST CENTER', $headers);
        $code_col = array_search('CODE', $headers);
        $loc_col = array_search('LOC', $headers);

        // Fallback to positions
        if ($id_col === false) $id_col = 0;
        if ($name_col === false) $name_col = 1;
        if ($cost_center_col === false) $cost_center_col = 2;
        if ($code_col === false) $code_col = 3;
        if ($loc_col === false) $loc_col = 4;

        $uploaded = 0;
        $skipped = 0;
        $errors = [];

        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) continue;

            $row = str_getcsv($line, $delimiter);
            $row = array_pad($row, 5, '');
            $row_num = $i + 1;

            $locker_id = trim($row[$id_col] ?? '');
            $name = trim($row[$name_col] ?? '');
            $cost_center = trim($row[$cost_center_col] ?? '');
            $code = trim($row[$code_col] ?? '');
            $location = trim($row[$loc_col] ?? '');

            if (empty($locker_id) && empty($name) && empty($cost_center) && empty($code)) {
                continue;
            }

            $result = insertLockerData($locker_id, $name, $cost_center, $code, $location);

            if ($result['success']) {
                $uploaded++;
            } else {
                $skipped++;
                $errors[] = "Row $row_num: " . $result['error'];
            }
        }

        $response['success'] = true;
        $response['uploaded'] = $uploaded;
        $response['skipped'] = $skipped;
        $response['errors'] = $errors;
        $response['message'] = "Upload completed! Imported: $uploaded, Skipped: $skipped";
    } else {
        // For Excel files (.xlsx, .xls) - Use PhpSpreadsheet properly
        $spreadsheet = IOFactory::load($file['tmp_name']);
        $worksheet = $spreadsheet->getActiveSheet();

        // Debug logging
        error_log("=== Locker Excel Upload Debug ===");

        // Get the highest row and column
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();

        error_log("Rows: $highestRow, Columns: $highestColumn");

        // Read headers from first row (row 1)
        $headers = [];
        $columnLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];

        for ($col = 0; $col < 7; $col++) {
            $cellValue = $worksheet->getCell($columnLetters[$col] . '1')->getValue();
            if ($cellValue) {
                $headers[$columnLetters[$col]] = strtoupper(trim($cellValue));
            }
        }

        error_log("Headers found: " . print_r($headers, true));

        // Map columns to letters
        $id_col = null;
        $name_col = null;
        $cost_center_col = null;
        $code_col = null;
        $loc_col = null;

        foreach ($headers as $col => $header) {
            if (in_array($header, ['ID', 'LOCKER_ID'])) $id_col = $col;
            if (in_array($header, ['NAME', 'LOCKER NAME'])) $name_col = $col;
            if (in_array($header, ['COST CENTER', 'COST_CENTER', 'CC'])) $cost_center_col = $col;
            if (in_array($header, ['CODE', 'LOCKER CODE'])) $code_col = $col;
            if (in_array($header, ['LOC', 'LOCATION'])) $loc_col = $col;
        }

        // Fallback to default column letters if not found
        if (!$id_col) $id_col = 'A';
        if (!$name_col) $name_col = 'B';
        if (!$cost_center_col) $cost_center_col = 'C';
        if (!$code_col) $code_col = 'D';
        if (!$loc_col) $loc_col = 'E';

        error_log("Column mapping - ID:$id_col, Name:$name_col, CostCenter:$cost_center_col, Code:$code_col, Loc:$loc_col");

        $uploaded = 0;
        $skipped = 0;
        $errors = [];

        // Process rows from row 2 to highest row
        for ($row = 2; $row <= $highestRow; $row++) {
            // Get values from each cell
            $locker_id = trim($worksheet->getCell($id_col . $row)->getValue() ?: '');
            $name = trim($worksheet->getCell($name_col . $row)->getValue() ?: '');
            $cost_center = trim($worksheet->getCell($cost_center_col . $row)->getValue() ?: '');
            $code = trim($worksheet->getCell($code_col . $row)->getValue() ?: '');
            $location = trim($worksheet->getCell($loc_col . $row)->getValue() ?: '');

            // Skip empty rows
            if (empty($locker_id) && empty($name) && empty($cost_center) && empty($code)) {
                continue;
            }

            error_log("Row $row - ID:$locker_id, Name:$name, CC:$cost_center, Code:$code, Loc:$location");

            $result = insertLockerData($locker_id, $name, $cost_center, $code, $location);

            if ($result['success']) {
                $uploaded++;
            } else {
                $skipped++;
                $errors[] = "Row $row: " . $result['error'];
                error_log("Row $row ERROR: " . $result['error']);
            }
        }

        $response['success'] = true;
        $response['uploaded'] = $uploaded;
        $response['skipped'] = $skipped;
        $response['errors'] = $errors;
        $response['message'] = "Upload completed! Imported: $uploaded, Skipped: $skipped";
        $response['debug'] = [
            'headers' => $headers,
            'mapping' => "ID:$id_col, Name:$name_col, CostCenter:$cost_center_col, Code:$code_col, Loc:$loc_col",
            'total_rows' => $highestRow - 1
        ];
    }
} catch (Exception $e) {
    error_log("Excel upload error: " . $e->getMessage());
    $response['message'] = 'Error reading file: ' . $e->getMessage();
    $response['debug'] = ['error' => $e->getMessage()];
}

echo json_encode($response);
exit;
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Lockers | Locker System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0f172a; color: #f1f5f9; }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        .header { background: #1e293b; padding: 1.5rem; border-radius: 1rem; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
        .header h1 { font-size: 1.8rem; }
        .logout-btn { background: #ef4444; color: white; padding: 0.5rem 1rem; border-radius: 0.5rem; text-decoration: none; transition: background 0.2s; }
        .logout-btn:hover { background: #dc2626; }
        .nav-links { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
        .nav-link { background: #3b82f6; color: white; padding: 0.5rem 1rem; border-radius: 0.5rem; text-decoration: none; transition: background 0.2s; }
        .nav-link:hover { background: #2563eb; }
        .card { background: #1e293b; border-radius: 1rem; padding: 2rem; margin-bottom: 2rem; }
        .upload-area { border: 2px dashed #3b82f6; border-radius: 1rem; padding: 2rem; text-align: center; transition: all 0.3s; cursor: pointer; }
        .upload-area:hover { border-color: #60a5fa; background: rgba(59, 130, 246, 0.1); }
        .upload-area i { font-size: 2.5rem; color: #3b82f6; margin-bottom: 1rem; }
        .file-input { display: none; }
        .btn { background: #3b82f6; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; font-size: 1rem; cursor: pointer; transition: background 0.2s; margin-top: 1rem; }
        .btn:hover { background: #2563eb; }
        .btn-primary { background: #10b981; }
        .btn-primary:hover { background: #059669; }
        .btn-success { background: #10b981; }
        .btn-success:hover { background: #059669; }
        .message { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; animation: slideIn 0.3s ease; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .message.success { background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #6ee7b7; }
        .message.error { background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #fca5a5; }
        .message.warning { background: rgba(245, 158, 11, 0.2); border: 1px solid #f59e0b; color: #fcd34d; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: #1e293b; padding: 1.5rem; border-radius: 1rem; text-align: center; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card i { font-size: 2rem; color: #3b82f6; margin-bottom: 0.5rem; }
        .stat-card h3 { font-size: 2rem; margin: 0.5rem 0; }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #cbd5e1; }
        .required::after { content: " *"; color: #ef4444; }
        input { width: 100%; padding: 0.75rem; background: #0f172a; border: 1px solid #334155; border-radius: 0.5rem; color: #f1f5f9; font-size: 1rem; transition: border-color 0.2s; }
        input:focus { outline: none; border-color: #3b82f6; }
        .section-title { margin-bottom: 1.5rem; padding-bottom: 0.75rem; border-bottom: 2px solid #3b82f6; }
        .two-columns { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        .loading { display: none; text-align: center; padding: 1rem; }
        .loading i { font-size: 2rem; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        @media (max-width: 768px) { .container { padding: 1rem; } .header { flex-direction: column; text-align: center; } .two-columns { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-database"></i> Manage Lockers</h1>
            <div class="nav-links">
                <a href="admin_dashboard.php" class="nav-link"><i class="fas fa-chart-line"></i> Dashboard</a>
                <a href="view_lockers.php" class="nav-link"><i class="fas fa-list"></i> View Lockers</a>
                <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <div id="messageContainer"></div>

        <div class="stats" id="stats">
            <div class="stat-card">
                <i class="fas fa-database"></i>
                <h3 id="totalLockers">0</h3>
                <p>Total Lockers in Database</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-upload"></i>
                <h3 id="importedCount">0</h3>
                <p>Imported This Session</p>
            </div>
        </div>

        <div class="two-columns">
            <!-- File Upload Section -->
            <div class="card">
                <h2 class="section-title"><i class="fas fa-file-excel"></i> Upload Excel/CSV File</h2>
                <p style="margin: 1rem 0; color: #94a3b8;">Upload your Excel (.xlsx, .xls) or CSV file with columns: <strong>ID, NAME, COST CENTER, CODE, LOC</strong></p>

                <div class="upload-area" id="upload-area">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p>Click or drag file to upload</p>
                    <p style="font-size: 0.875rem; color: #94a3b8;">Supports .xlsx, .xls, .csv, .txt</p>
                    <input type="file" name="excel_file" id="file-input" class="file-input" accept=".xlsx,.xls,.csv,.txt">
                </div>
                <div class="loading" id="uploadLoading">
                    <i class="fas fa-spinner fa-pulse"></i> Processing file...
                </div>
                <div style="text-align: center;">
                    <button type="button" class="btn btn-primary" id="upload-btn">
                        <i class="fas fa-upload"></i> Upload & Process
                    </button>
                </div>
            </div>

            <!-- Single Manual Entry Form -->
            <div class="card">
                <h2 class="section-title"><i class="fas fa-plus-circle"></i> Add Single Locker Manually</h2>
                <form id="manualForm">
                    <div class="form-group">
                        <label class="required">Locker ID</label>
                        <input type="text" name="locker_id" id="locker_id" required placeholder="e.g., 992187">
                    </div>
                    <div class="form-group">
                        <label class="required">Name</label>
                        <input type="text" name="name" id="name" required placeholder="e.g., SHIMELS JOTE BALCHA">
                    </div>
                    <div class="form-group">
                        <label class="required">Cost Center</label>
                        <input type="text" name="cost_center" id="cost_center" required placeholder="e.g., MRORSP532">
                    </div>
                    <div class="form-group">
                        <label class="required">Code</label>
                        <input type="text" name="code" id="code" required placeholder="e.g., 669854JYZDVCJAFKVSRCTYR">
                    </div>
                    <div class="form-group">
                        <label>Location <span class="optional-badge">OPTIONAL</span></label>
                        <input type="text" name="location" id="location" placeholder="e.g., A1T2">
                    </div>
                    <div class="loading" id="manualLoading" style="display: none;">
                        <i class="fas fa-spinner fa-pulse"></i> Saving...
                    </div>
                    <button type="submit" name="manual_single" class="btn btn-success" id="manualBtn">
                        <i class="fas fa-save"></i> Save Locker
                    </button>
                </form>
            </div>
        </div>

        <div id="errorContainer"></div>
        <div id="recentLockers"></div>
    </div>

    <script>
        // Load initial data
        function loadStats() {
            fetch('get_lockers_stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('totalLockers').innerText = data.total;
                    }
                })
                .catch(error => console.error('Error:', error));
        }
        
        function loadRecentLockers() {
            fetch('get_recent_lockers.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.lockers.length > 0) {
                        let html = `
                            <div class="card">
                                <h2 class="section-title"><i class="fas fa-history"></i> Recently Added/Updated Lockers</h2>
                                <div style="overflow-x: auto;">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Cost Center</th>
                                                <th>Code (truncated)</th>
                                                <th>Location</th>
                                                <th>Last Updated</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                        `;
                        
                        data.lockers.forEach(locker => {
                            html += `
                                <tr>
                                    <td>${escapeHtml(locker.locker_id)}</td>
                                    <td>${escapeHtml(locker.name.substring(0, 35))}</td>
                                    <td>${escapeHtml(locker.cost_center)}</td>
                                    <td><code>${escapeHtml(locker.code.substring(0, 20))}...</code></td>
                                    <td>${escapeHtml(locker.location || '-')}</td>
                                    <td>${locker.updated_at}</td>
                                </tr>
                            `;
                        });
                        
                        html += `
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        `;
                        
                        document.getElementById('recentLockers').innerHTML = html;
                    }
                })
                .catch(error => console.error('Error:', error));
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function showMessage(message, type) {
            const container = document.getElementById('messageContainer');
            const icon = type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle');
            container.innerHTML = `
                <div class="message ${type}">
                    <i class="fas ${icon}"></i>
                    ${escapeHtml(message)}
                </div>
            `;
            setTimeout(() => {
                container.innerHTML = '';
            }, 5000);
        }
        
        function showErrors(errors) {
            if (errors && errors.length > 0) {
                let html = `
                    <div class="card">
                        <h3><i class="fas fa-exclamation-triangle"></i> Errors Encountered (${errors.length})</h3>
                        <div class="error-list" style="max-height: 300px; overflow-y: auto; margin-top: 1rem;">
                `;
                errors.forEach(error => {
                    html += `<div class="error-item" style="background: rgba(239, 68, 68, 0.1); padding: 0.5rem; margin-bottom: 0.5rem; border-radius: 0.25rem; font-size: 0.875rem; color: #fca5a5; font-family: monospace;">${escapeHtml(error)}</div>`;
                });
                html += `</div></div>`;
                document.getElementById('errorContainer').innerHTML = html;
            }
        }
        
        // File upload handling
        const uploadArea = document.getElementById('upload-area');
        const fileInput = document.getElementById('file-input');
        const uploadBtn = document.getElementById('upload-btn');
        const uploadLoading = document.getElementById('uploadLoading');
        
        if (uploadArea) {
            uploadArea.addEventListener('click', () => fileInput.click());
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.style.borderColor = '#60a5fa';
                uploadArea.style.background = 'rgba(59, 130, 246, 0.1)';
            });
            uploadArea.addEventListener('dragleave', (e) => {
                e.preventDefault();
                uploadArea.style.borderColor = '#3b82f6';
                uploadArea.style.background = 'transparent';
            });
            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.style.borderColor = '#3b82f6';
                uploadArea.style.background = 'transparent';
                const file = e.dataTransfer.files[0];
                if (file) {
                    fileInput.files = e.dataTransfer.files;
                    uploadArea.innerHTML = `<i class="fas fa-file-excel" style="color: #10b981;"></i><p>Selected: ${file.name}</p><p style="font-size: 0.875rem; color: #94a3b8;">Ready to upload</p>`;
                }
            });
            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    uploadArea.innerHTML = `<i class="fas fa-file-excel" style="color: #10b981;"></i><p>Selected: ${e.target.files[0].name}</p><p style="font-size: 0.875rem; color: #94a3b8;">Ready to upload</p>`;
                } else {
                    uploadArea.innerHTML = `<i class="fas fa-cloud-upload-alt"></i><p>Click or drag file to upload</p><p style="font-size: 0.875rem; color: #94a3b8;">Supports .xlsx, .xls, .csv, .txt</p>`;
                }
            });
        }
        
        uploadBtn.addEventListener('click', () => {
            const file = fileInput.files[0];
            if (!file) {
                showMessage('Please select a file first', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('excel_file', file);
            
            uploadLoading.style.display = 'block';
            uploadBtn.disabled = true;
            
            fetch('upload_lockers.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                uploadLoading.style.display = 'none';
                uploadBtn.disabled = false;
                
                if (data.success) {
                    showMessage(data.message, 'success');
                    document.getElementById('importedCount').innerText = data.uploaded || 0;
                    if (data.errors && data.errors.length > 0) {
                        showErrors(data.errors);
                    }
                    loadStats();
                    loadRecentLockers();
                    
                    // Reset file input
                    fileInput.value = '';
                    uploadArea.innerHTML = `<i class="fas fa-cloud-upload-alt"></i><p>Click or drag file to upload</p><p style="font-size: 0.875rem; color: #94a3b8;">Supports .xlsx, .xls, .csv, .txt</p>`;
                } else {
                    showMessage(data.message, 'error');
                    if (data.debug) {
                        console.log('Debug info:', data.debug);
                    }
                }
            })
            .catch(error => {
                uploadLoading.style.display = 'none';
                uploadBtn.disabled = false;
                showMessage('Error uploading file: ' + error.message, 'error');
                console.error('Error:', error);
            });
        });
        
        // Manual form handling
        const manualForm = document.getElementById('manualForm');
        const manualLoading = document.getElementById('manualLoading');
        const manualBtn = document.getElementById('manualBtn');
        
        manualForm.addEventListener('submit', (e) => {
            e.preventDefault();
            
            const formData = new FormData(manualForm);
            formData.append('manual_single', '1');
            
            manualLoading.style.display = 'block';
            manualBtn.disabled = true;
            
            fetch('upload_lockers.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                manualLoading.style.display = 'none';
                manualBtn.disabled = false;
                
                if (data.success) {
                    showMessage(data.message, 'success');
                    manualForm.reset();
                    loadStats();
                    loadRecentLockers();
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => {
                manualLoading.style.display = 'none';
                manualBtn.disabled = false;
                showMessage('Error: ' + error.message, 'error');
                console.error('Error:', error);
            });
        });
        
        // Initial load
        loadStats();
        loadRecentLockers();
    </script>
</body>
</html>