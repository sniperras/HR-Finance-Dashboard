<?php
require_once '../session_config.php';
require_once '../includes/auth.php';

// Check if user is QA Auditor
requireRole('qa_auditor');

$conn = getConnection();

$success_message = '';
$error_message = '';
$report_type = $_GET['report_type'] ?? '';
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$department = $_GET['department'] ?? 'ALL';

// Define report types with their abbreviations and display names
$reportTypes = [
    'ir' => ['name' => 'Investigation Recommendations', 'file' => 'upload_ir_report.php', 'save' => 'save_ir_upload.php', 'abbr' => 'IR'],
    'loo' => ['name' => 'List of Occurrence', 'file' => 'upload_loo_report.php', 'save' => 'save_loo_upload.php', 'abbr' => 'LOO'],
    'looh' => ['name' => 'List of Open Hazards', 'file' => 'upload_looh_report.php', 'save' => 'save_looh_upload.php', 'abbr' => 'LOOH'],
    'sr' => ['name' => 'Safety Report', 'file' => 'upload_sr_report.php', 'save' => 'save_sr_upload.php', 'abbr' => 'SR'],
    'srbai' => ['name' => 'SRB Action Item', 'file' => 'upload_srbai_report.php', 'save' => 'save_srbai_upload.php', 'abbr' => 'SRBAI']
];

// Define departments
$departments = ['ALL'];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QA Report Entry - QA Auditor Dashboard</title>
    <link rel="icon" type="image/png" href="../assets/images/ethiopian_logo.ico">
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
            --info: #8B5CF6;
            --border-light: #334155;
            --card-bg: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #94A3B8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', 'Inter', system-ui, sans-serif;
            background: var(--dark-bg);
            color: var(--text-primary);
            transition: background-color 0.3s, color 0.3s;
        }

        /* Navbar - Full width with right alignment */
        .navbar {
            background: var(--medium-bg);
            padding: 0.75rem 1.5rem;
            transition: background-color 0.3s;
            border-bottom: 1px solid var(--border-light);
            position: sticky;
            top: 0;
            z-index: 100;
            width: 100%;
        }

        .navbar-container {
            max-width: 100%;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .navbar-brand {
            font-size: 1.1rem;
            font-weight: bold;
            color: var(--accent);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .navbar-menu {
            display: flex;
            gap: 1.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .navbar-menu a {
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.85rem;
            transition: color 0.2s;
            padding: 0.4rem 0;
        }

        .navbar-menu a:hover {
            color: var(--accent);
        }

        .user-info {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .user-name {
            color: var(--accent);
            font-weight: bold;
            font-size: 0.85rem;
        }

        /* Change Password link styling */
        .change-password-link {
            color: var(--accent);
            text-decoration: none;
            font-size: 0.85rem;
            transition: color 0.2s;
            padding: 0.4rem 0;
            cursor: pointer;
        }

        .change-password-link:hover {
            color: var(--accent-hover);
            text-decoration: underline;
        }

        .theme-toggle {
            background: transparent;
            border: 1px solid var(--accent);
            color: var(--accent);
            padding: 0.3rem 0.8rem;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.3s;
        }

        .theme-toggle:hover {
            background: var(--accent);
            color: var(--dark-bg);
        }

        .btn {
            background: var(--accent);
            color: var(--dark-bg);
            border: none;
            padding: 0.4rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.8rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        /* Logout button with wider width */
        .logout-btn {
            background: var(--accent);
            color: var(--dark-bg);
            border: none;
            padding: 0.4rem 1.2rem;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.8rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            min-width: 70px;
            text-align: center;
        }

        .btn:hover,
        .logout-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(56, 189, 248, 0.3);
            background: var(--accent-hover);
        }

        .container {
            max-width: 1400px;
            margin: 1.5rem auto;
            padding: 0 1.5rem;
        }

        /* Header Section */
        .page-header {
            background: linear-gradient(135deg, var(--medium-bg) 0%, var(--dark-bg) 100%);
            padding: 1.5rem;
            border-radius: 16px;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-light);
        }

        .page-header h1 {
            font-size: 1.4rem;
            color: var(--accent);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header p {
            font-size: 0.8rem;
            opacity: 0.8;
            margin-top: 0.5rem;
        }

        /* Filter Section */
        .filter-section {
            background: var(--card-bg);
            padding: 1.25rem;
            border-radius: 12px;
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
        }

        .filter-group label {
            font-size: 0.7rem;
            font-weight: bold;
            color: var(--accent);
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group select {
            width: 100%;
            padding: 0.6rem;
            border-radius: 8px;
            border: 1px solid var(--border-light);
            background: var(--dark-bg);
            color: var(--text-primary);
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-group select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2);
        }

        /* Upload Section */
        .upload-section {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-light);
        }

        .upload-section h3 {
            font-size: 1rem;
            margin-bottom: 1rem;
            color: var(--accent);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .upload-form {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
        }

        .upload-group {
            flex: 1;
            min-width: 200px;
        }

        .upload-group label {
            font-size: 0.7rem;
            font-weight: bold;
            color: var(--accent);
            display: block;
            margin-bottom: 0.25rem;
            text-transform: uppercase;
        }

        .upload-group input[type="file"] {
            width: 100%;
            padding: 0.5rem;
            border-radius: 8px;
            border: 1px solid var(--border-light);
            background: var(--dark-bg);
            color: var(--text-primary);
            font-size: 0.8rem;
        }

        .file-info {
            margin-top: 0.5rem;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }

        .btn-upload {
            background: var(--warning);
            color: white;
        }

        .btn-upload:hover {
            background: #e67e22;
        }

        /* Alerts */
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.85rem;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid var(--success);
            color: var(--success);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid var(--danger);
            color: var(--danger);
        }

        .alert-warning {
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid var(--warning);
            color: var(--warning);
        }

        .alert-info {
            background: rgba(56, 189, 248, 0.15);
            border: 1px solid var(--accent);
            color: var(--accent);
        }

        /* Preview Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            visibility: hidden;
            opacity: 0;
            transition: visibility 0.3s, opacity 0.3s;
        }

        .modal-overlay.active {
            visibility: visible;
            opacity: 1;
        }

        .modal-container {
            background: var(--card-bg);
            border-radius: 16px;
            width: 90%;
            max-width: 1000px;
            max-height: 85vh;
            overflow: auto;
            border: 1px solid var(--border-light);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        }

        .modal-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: var(--card-bg);
            z-index: 1;
        }

        .modal-header h3 {
            color: var(--accent);
            font-size: 1rem;
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 1.5rem;
            cursor: pointer;
        }

        .modal-body {
            padding: 1rem;
        }

        .preview-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }

        .preview-table th,
        .preview-table td {
            padding: 0.5rem;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
        }

        .preview-table th {
            background: var(--dark-bg);
            color: var(--accent);
            position: sticky;
            top: 0;
        }

        .preview-actions {
            padding: 1rem;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            border-top: 1px solid var(--border-light);
        }

        .btn-confirm {
            background: var(--success);
        }

        .btn-cancel-modal {
            background: var(--danger);
        }

        /* Loading Spinner */
        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid var(--text-primary);
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 0.6s linear infinite;
            margin-right: 8px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Light Theme */
        body.light-theme {
            background: #F8FAFC;
            color: #0F172A;
        }

        body.light-theme .navbar,
        body.light-theme .page-header,
        body.light-theme .filter-section,
        body.light-theme .upload-section,
        body.light-theme .modal-container {
            background: white !important;
            border-color: #E2E8F0 !important;
        }

        body.light-theme .navbar {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        body.light-theme .filter-group select,
        body.light-theme .upload-group input[type="file"] {
            background: white;
            color: #0F172A;
            border-color: #CBD5E1;
        }

        body.light-theme .preview-table th {
            background: #F1F5F9;
            color: #0284C7;
        }

        body.light-theme .btn,
        body.light-theme .logout-btn {
            background: #0284C7;
            color: white;
        }

        body.light-theme .theme-toggle {
            border-color: #0284C7;
            color: #0284C7;
        }

        body.light-theme .theme-toggle:hover {
            background: #0284C7;
            color: white;
        }

        body.light-theme .user-name {
            color: #0284C7;
        }

        body.light-theme .change-password-link {
            color: #0284C7;
        }

        body.light-theme .change-password-link:hover {
            color: #0EA5E9;
        }

        /* Password Modal Override - No extra background */
        #passwordModalOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            z-index: 10001;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #passwordModalOverlay iframe {
            width: 100%;
            max-width: 480px;
            height: auto;
            min-height: 450px;
            border: none;
            border-radius: 16px;
            background: transparent;
        }

        /* Responsive */
        @media (max-width: 768px) {

            .filter-form,
            .upload-form {
                grid-template-columns: 1fr;
            }

            .navbar-container {
                flex-direction: column;
                text-align: center;
            }

            .navbar-menu {
                justify-content: center;
            }

            .container {
                padding: 0 1rem;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="qa_report_entry.php" class="navbar-brand">
                QA Report Entry System
            </a>
            <div class="navbar-menu">
                <a href="qa_report_entry.php" style="color: var(--accent);">Upload Reports</a>

                <div class="user-info">
                    <button id="themeToggle" class="theme-toggle">☀️ Light</button>
                    <span class="user-name"> <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="#" class="change-password-link" onclick="openPasswordModal(); return false;">🔑 Change Password</a>
                    <a href="../logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>
                QA Report Entry
            </h1>
            <p>Upload Excel files for Investigation Recommendations, Occurrences, Hazards, Safety Reports, and SRB Action Items</p>
        </div>

        <div class="filter-section">
            <form method="GET" action="" class="filter-form" id="filterForm">
                <div class="filter-group">
                    <label>Report Type *</label>
                    <select name="report_type" id="reportTypeSelect" required>
                        <option value="">Select Report Type</option>
                        <?php foreach ($reportTypes as $key => $type): ?>
                            <option value="<?php echo $key; ?>" <?php echo $report_type == $key ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['name']); ?> (<?php echo $type['abbr']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Month *</label>
                    <select name="month" id="monthSelect" required>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $month == $m ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Year *</label>
                    <select name="year" id="yearSelect" required>
                        <?php for ($y = 2024; $y <= 2026; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Department</label>
                    <select name="department" id="deptSelect">
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept; ?>" <?php echo $department == $dept ? 'selected' : ''; ?>>
                                <?php echo $dept; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <div id="alertContainer"></div>

        <div class="upload-section">
            <h3>Upload Excel File</h3>
            <div class="upload-form">
                <div class="upload-group">
                    <label>Excel File (.xlsx, .xls) *</label>
                    <input type="file" id="excelFile" accept=".xlsx,.xls">
                    <div class="file-info" id="fileInfo"></div>
                </div>
                <div class="upload-group">
                    <button type="button" class="btn btn-upload" id="uploadBtn">📎 Upload & Preview</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div id="previewModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h3 id="modalTitle">📋 Upload Preview</h3>
                <button class="modal-close" onclick="closePreviewModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="previewContent">
                    <div style="text-align: center; padding: 2rem;">Loading preview...</div>
                </div>
            </div>
            <div class="preview-actions">
                <button type="button" class="btn btn-cancel-modal" onclick="closePreviewModal()">Cancel</button>
                <button type="button" class="btn btn-confirm" id="confirmUploadBtn">✓ Confirm Import</button>
            </div>
        </div>
    </div>

    <script>
        // Theme Manager
        class ThemeManager {
            constructor() {
                this.themeKey = 'qa_dashboard_theme';
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

        // Initialize theme
        new ThemeManager();

        let currentPreviewData = null;
        let currentUploadHandler = null;

        // File input display
        const excelFile = document.getElementById('excelFile');
        const fileInfo = document.getElementById('fileInfo');

        if (excelFile) {
            excelFile.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    fileInfo.innerHTML = `📄 Selected: ${file.name} (${(file.size / 1024).toFixed(2)} KB)`;
                } else {
                    fileInfo.innerHTML = '';
                }
            });
        }

        // Show alert message
        function showAlert(type, message) {
            const alertContainer = document.getElementById('alertContainer');
            const alertClass = type === 'success' ? 'alert-success' : (type === 'error' ? 'alert-error' : 'alert-warning');
            alertContainer.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;
            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 5000);
        }

        // Upload file
        const uploadBtn = document.getElementById('uploadBtn');
        if (uploadBtn) {
            uploadBtn.addEventListener('click', function() {
                const reportType = document.getElementById('reportTypeSelect').value;
                const month = document.getElementById('monthSelect').value;
                const year = document.getElementById('yearSelect').value;
                const department = document.getElementById('deptSelect').value;
                const file = excelFile.files[0];

                // Validation
                if (!reportType) {
                    showAlert('error', 'Please select a report type');
                    return;
                }
                if (!month) {
                    showAlert('error', 'Please select a month');
                    return;
                }
                if (!year) {
                    showAlert('error', 'Please select a year');
                    return;
                }
                if (!file) {
                    showAlert('error', 'Please select an Excel file to upload');
                    return;
                }

                // Validate file extension
                const allowedExtensions = ['xlsx', 'xls'];
                const fileExtension = file.name.split('.').pop().toLowerCase();
                if (!allowedExtensions.includes(fileExtension)) {
                    showAlert('error', 'Only Excel files (.xlsx, .xls) are allowed');
                    return;
                }

                // Get the upload handler script based on report type
                const reportConfig = {
                    'ir': {
                        upload: 'upload_ir_report.php',
                        save: 'save_ir_upload.php',
                        name: 'Investigation Recommendations'
                    },
                    'loo': {
                        upload: 'upload_loo_report.php',
                        save: 'save_loo_upload.php',
                        name: 'List of Occurrence'
                    },
                    'looh': {
                        upload: 'upload_looh_report.php',
                        save: 'save_looh_upload.php',
                        name: 'List of Open Hazards'
                    },
                    'sr': {
                        upload: 'upload_sr_report.php',
                        save: 'save_sr_upload.php',
                        name: 'Safety Report'
                    },
                    'srbai': {
                        upload: 'upload_srbai_report.php',
                        save: 'save_srbai_upload.php',
                        name: 'SRB Action Item'
                    }
                };

                const config = reportConfig[reportType];
                if (!config) {
                    showAlert('error', 'Invalid report type selected');
                    return;
                }

                currentUploadHandler = config.save;

                // Prepare form data
                const formData = new FormData();
                formData.append('excel_file', file);
                formData.append('report_type', reportType);
                formData.append('month', month);
                formData.append('year', year);
                formData.append('department', department);

                // Show loading state
                uploadBtn.disabled = true;
                uploadBtn.innerHTML = '<span class="loading"></span> Uploading...';

                // Call upload script
                fetch(config.upload, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        uploadBtn.disabled = false;
                        uploadBtn.innerHTML = '📎 Upload & Preview';

                        if (data.success) {
                            currentPreviewData = data.data;
                            document.getElementById('modalTitle').innerHTML = `📋 Preview - ${config.name} (${data.record_count || 0} records)`;
                            displayPreview(data.data, data.columns);
                            openPreviewModal();
                        } else {
                            showAlert('error', data.message || 'Error uploading file');
                        }
                    })
                    .catch(error => {
                        uploadBtn.disabled = false;
                        uploadBtn.innerHTML = '📎 Upload & Preview';
                        console.error('Upload error details:', error);
                        showAlert('error', 'Error uploading file: ' + error.message);
                    });
            });
        }

        // Display preview in modal
        function displayPreview(data, columns) {
            const previewContent = document.getElementById('previewContent');

            if (!data || data.length === 0) {
                previewContent.innerHTML = '<div class="alert alert-warning">No data found in the uploaded file.</div>';
                return;
            }

            const previewRows = data.slice(0, 10);

            let html = `<div style="margin-bottom: 1rem;">
                            <span class="alert alert-info" style="display: inline-block; padding: 0.25rem 0.5rem;">Total records: ${data.length} | Showing first ${previewRows.length} rows</span>
                        </div>`;
            html += `<div style="overflow-x: auto;">
                        <table class="preview-table">
                            <thead>
                                <tr>`;

            if (previewRows.length > 0) {
                const headers = Object.keys(previewRows[0]);
                headers.forEach(header => {
                    if (!['upload_date', 'status'].includes(header)) {
                        html += `<th>${formatHeader(header)}</th>`;
                    }
                });
            }
            html += `</tr>
                    </thead>
                    <tbody>`;

            previewRows.forEach((row, index) => {
                html += `<tr>`;
                Object.keys(row).forEach(key => {
                    if (!['upload_date', 'status'].includes(key)) {
                        let value = row[key] || '';
                        if (value.length > 50) {
                            value = value.substring(0, 50) + '...';
                        }
                        html += `<td title="${escapeHtml(row[key] || '')}">${escapeHtml(value)}</td>`;
                    }
                });
                html += `</tr>`;
            });
            html += `</tbody>
                    </table>
                </div>`;

            previewContent.innerHTML = html;
        }

        function formatHeader(str) {
            return str.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }

        // Preview Modal functions
        function openPreviewModal() {
            const modal = document.getElementById('previewModal');
            if (modal) {
                modal.classList.add('active');
            }
        }

        function closePreviewModal() {
            const modal = document.getElementById('previewModal');
            if (modal) {
                modal.classList.remove('active');
            }
            currentPreviewData = null;
        }

        // Confirm import
        const confirmBtn = document.getElementById('confirmUploadBtn');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function() {
                if (!currentPreviewData || !currentUploadHandler) {
                    showAlert('error', 'No data to import');
                    return;
                }

                const reportType = document.getElementById('reportTypeSelect').value;
                const month = document.getElementById('monthSelect').value;
                const year = document.getElementById('yearSelect').value;
                const department = document.getElementById('deptSelect').value;

                const formData = new FormData();
                formData.append('data', JSON.stringify(currentPreviewData));
                formData.append('report_type', reportType);
                formData.append('month', month);
                formData.append('year', year);
                formData.append('department', department);

                confirmBtn.disabled = true;
                confirmBtn.innerHTML = '<span class="loading"></span> Importing...';

                fetch(currentUploadHandler, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        confirmBtn.disabled = false;
                        confirmBtn.innerHTML = '✓ Confirm Import';

                        if (data.success) {
                            closePreviewModal();
                            showAlert('success', `✅ Import successful! Imported: ${data.imported} records. Updated: ${data.updated || 0} | Failed: ${data.failed || 0} | Skipped: ${data.skipped || 0}`);
                            excelFile.value = '';
                            fileInfo.innerHTML = '';
                            currentPreviewData = null;
                        } else {
                            showAlert('error', data.message || 'Import failed');
                        }
                    })
                    .catch(error => {
                        confirmBtn.disabled = false;
                        confirmBtn.innerHTML = '✓ Confirm Import';
                        console.error('Import error:', error);
                        showAlert('error', 'Error importing data. Please try again.');
                    });
            });
        }

        // Close modals on escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePreviewModal();
                closePasswordModal();
            }
        });

        // Password Modal Functions
        let passwordModalOverlay = null;

        function openPasswordModal() {
            if (passwordModalOverlay) {
                return;
            }

            passwordModalOverlay = document.createElement('div');
            passwordModalOverlay.id = 'passwordModalOverlay';

            passwordModalOverlay.onclick = function(e) {
                if (e.target === passwordModalOverlay) {
                    closePasswordModal();
                }
            };

            const iframe = document.createElement('iframe');
            iframe.src = '../change_password.php';
            iframe.style.border = 'none';
            iframe.style.borderRadius = '16px';
            iframe.style.width = '100%';
            iframe.style.maxWidth = '480px';
            iframe.style.height = 'auto';
            iframe.style.minHeight = '450px';
            iframe.style.backgroundColor = 'transparent';

            passwordModalOverlay.appendChild(iframe);
            document.body.appendChild(passwordModalOverlay);

            const escapeHandler = function(e) {
                if (e.key === 'Escape') {
                    closePasswordModal();
                    document.removeEventListener('keydown', escapeHandler);
                }
            };
            document.addEventListener('keydown', escapeHandler);
        }

        function closePasswordModal() {
            if (passwordModalOverlay && passwordModalOverlay.parentNode) {
                passwordModalOverlay.remove();
                passwordModalOverlay = null;
            }
        }

        // Alias for closePasswordPopup (called from iframe)
        window.closePasswordPopup = function() {
            closePasswordModal();
        };

        // Session keep-alive
        function keepSessionAlive() {
            fetch('../keep_alive.php', {
                    method: 'GET',
                    cache: 'no-cache'
                })
                .catch(error => console.log('Session keep-alive failed:', error));
        }
        setInterval(keepSessionAlive, 5 * 60 * 1000);
    </script>
</body>

</html>