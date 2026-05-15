<?php
require_once '../session_config.php';
require_once '../includes/auth.php';

// Check if user is QA Auditor
requireRole('qa auditor');

$conn = getConnection();
$userRole = $_SESSION['user_role'];
$username = $_SESSION['username'];
$userFullName = $_SESSION['full_name'];

// Get theme from cookie or default to dark
$theme = isset($_COOKIE['dashboard_theme']) ? $_COOKIE['dashboard_theme'] : 'dark';

// Determine user's department from username (director_LMT, director_BMT, etc.)
$userDepartment = null;
if (($userRole === 'director' || $userRole === 'manager') && preg_match('/_([A-Z\/\s]+)/', $username, $matches)) {
    $userDepartment = trim($matches[1]);
}
$success_message = '';
$error_message = '';
$report_type = $_GET['report_type'] ?? '';
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$department = $_GET['department'] ?? 'ALL';

// Define report types with their abbreviations and display names
$reportTypes = [
    'ir' => ['name' => 'Investigation Recommendations', 'file' => 'upload_ir_report.php', 'save' => 'save_ir_upload.php', 'abbr' => 'IR'],
    'loo' => ['name' => 'List of Occurrence (Excel)', 'file' => 'upload_loo_report.php', 'save' => 'save_loo_upload.php', 'abbr' => 'LOO'],
    'looform' => ['name' => 'List of Occurrence Form', 'file' => null, 'save' => 'looform_save.php', 'abbr' => 'LOOFORM'],
    'looh' => ['name' => 'List of Open Hazards', 'file' => 'upload_looh_report.php', 'save' => 'save_looh_upload.php', 'abbr' => 'LOOH'],
    'sr' => ['name' => 'Safety Report', 'file' => 'upload_sr_report.php', 'save' => 'save_sr_upload.php', 'abbr' => 'SR'],
    'srbai' => ['name' => 'SRB Action Item', 'file' => 'upload_srbai_report.php', 'save' => 'save_srbai_upload.php', 'abbr' => 'SRBAI']
];

// Define departments
$departments = ['ALL'];

// Fetch existing data for List of Occurrence Form
$existingLooData = [];
if ($report_type === 'looform' && $month && $year) {
    $stmt = $conn->prepare("SELECT Item, Event, Number FROM ListofOccurrenceForm WHERE Month = ? AND Year = ? ORDER BY CAST(Item AS UNSIGNED) ASC");
    $stmt->bind_param("ii", $month, $year);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $existingLooData[] = $row;
    }
    $stmt->close();
}

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

        .department-badge {
            background: var(--accent);
            color: var(--dark-bg);
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: bold;
        }

        body.light-theme .department-badge {
            background: #0284C7;
            color: white;
        }

        .preview-actions {
            padding: 1rem;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            border-top: 1px solid var(--border-light);
        }

        .role-badge {
            background: rgba(56, 189, 248, 0.15);
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: bold;
            color: var(--accent);
        }

        .btn-confirm {
            background: var(--success);
        }

        .btn-cancel-modal {
            background: var(--danger);
        }

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

        /* List of Occurrence Form Section */
        .form-section {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1.5rem;
            border: 1px solid var(--border-light);
        }

        .section-header {
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--accent);
        }

        .section-header h3 {
            color: var(--accent);
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }

        .section-header p {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
            vertical-align: top;
        }

        .data-table th {
            background: var(--dark-bg);
            color: var(--accent);
            font-weight: bold;
            font-size: 0.8rem;
        }

        .data-table input,
        .data-table select {
            background: var(--dark-bg);
            border: 1px solid var(--border-light);
            color: var(--text-primary);
            border-radius: 6px;
            font-size: 0.8rem;
        }

        .data-table input:focus,
        .data-table select:focus {
            outline: none;
            border-color: var(--accent);
        }

        .total-row {
            background: rgba(56, 189, 248, 0.1);
            font-weight: bold;
        }

        .total-row td {
            padding: 0.75rem;
            border-top: 2px solid var(--accent);
        }

        .btn-remove-row {
            background: var(--danger);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            padding: 0.3rem 0.6rem;
        }

        .btn-remove-row:hover {
            opacity: 0.8;
            transform: scale(1.05);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .navbar-menu a:hover,
        .navbar-menu a.active {
            color: var(--accent);
        }

        .btn-success:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        /* Password Modal Override */
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

        /* Light Theme */
        body.light-theme {
            background: #F8FAFC;
            color: #0F172A;
        }

        body.light-theme .navbar,
        body.light-theme .page-header,
        body.light-theme .filter-section,
        body.light-theme .upload-section,
        body.light-theme .modal-container,
        body.light-theme .form-section {
            background: white !important;
            border-color: #E2E8F0 !important;
        }

        body.light-theme .navbar {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        body.light-theme .filter-group select,
        body.light-theme .upload-group input[type="file"],
        body.light-theme .data-table input,
        body.light-theme .data-table select {
            background: white;
            color: #0F172A;
            border-color: #CBD5E1;
        }

        body.light-theme .preview-table th,
        body.light-theme .data-table th {
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

        body.light-theme .user-name,
        body.light-theme .change-password-link {
            color: #0284C7;
        }

        body.light-theme .change-password-link:hover {
            color: #0EA5E9;
        }

        body.light-theme .total-row {
            background: rgba(2, 132, 199, 0.08);
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

            .data-table th,
            .data-table td {
                padding: 0.5rem;
            }
        }

        /* Add these to the existing CSS */

        /* Navbar links - base style */
        .navbar-menu a {
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.85rem;
            transition: color 0.2s;
            padding: 0.4rem 0;
        }

        .navbar-menu a:hover,
        .navbar-menu a.active {
            color: var(--accent);
        }

        /* User name and change password link */
        .user-name {
            color: var(--accent);
            font-weight: bold;
            font-size: 0.85rem;
        }

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
        }

        /* Light theme overrides for navbar text */
        body.light-theme .navbar-menu a {
            color: #1E293B;
        }

        body.light-theme .navbar-menu a:hover,
        body.light-theme .navbar-menu a.active {
            color: #0284C7;
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
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="qa_report_entry.php" class="navbar-brand">
                QA Report
            </a>
            <div class="navbar-menu">
                <?php if ($_SESSION['user_role'] == 'it_admin'): ?>
                    <a href="it_admin_dashboard.php">IT Dashboard</a>
                    <a href="qa_dashboard_tb.php">QA Summary Dashboard</a>
                    <a href="qa_dashboard.php">QA Dashboard</a>
                    <a href="qa_report_entry.php" class="active">Upload Reports</a>
                <?php endif; ?>
                <?php if ($_SESSION['user_role'] == 'director'): ?>
                    <a href="qa_dashboard_tb.php">QA Summary Dashboard</a>
                    <a href="qa_dashboard.php">QA Dashboard</a>
                    <a href="../director/director_dashboard.php">HR Dashboard</a>
                <?php endif; ?>
                <?php if ($_SESSION['user_role'] == 'qa auditor'): ?>
                    <a href="qa_dashboard_tb.php">QA Summary Dashboard</a>
                    <a href="qa_dashboard.php">QA Dashboard</a>
                    <a href="qa_report_entry.php" class="active">Upload Reports</a>
                <?php endif; ?>
                <div class="user-info">

                    <button id="themeToggle" class="theme-toggle"><?php echo $theme === 'light' ? '🌙 Dark' : '☀️ Light'; ?></button>
                    <span class="user-name"><?php echo htmlspecialchars($userFullName); ?></span>

                    <span class="department-badge"><?php echo strtoupper($userRole); ?></span>
                    <a href="#" onclick="openPasswordModal(); return false;" class="change-password-link">🔑 Change Password</a>
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

        <!-- List of Occurrence Form Section -->
        <div id="looFormSection" class="form-section" style="display: none;">
            <div class="section-header">
                <h3>List of Occurrence Form</h3>
                <p>Enter occurrence data manually</p>
            </div>

            <div id="looFormContainer">
                <div class="table-wrapper">
                    <table class="data-table" id="looTable">
                        <thead>
                            <tr>
                                <th style="width: 15%">Item</th>
                                <th style="width: 60%">Event</th>
                                <th style="width: 15%">Number</th>
                                <th style="width: 10%">Action</th>
                            </tr>
                        </thead>
                        <tbody id="looTableBody">
                            <!-- Rows will be populated by JavaScript -->
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="3" style="text-align: right;"><strong>Total:</strong></td>
                                <td id="totalNumber" style="font-weight: bold; text-align: left;">0</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="form-buttons" style="margin-top: 1rem; display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn" id="addRowBtn">➕ Add Item</button>
                    <button type="button" class="btn btn-success" id="saveLooFormBtn" style="background: var(--success);">💾 Save List of Occurrence</button>
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

        new ThemeManager();

        let currentPreviewData = null;
        let currentUploadHandler = null;

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

        function showAlert(type, message) {
            const alertContainer = document.getElementById('alertContainer');
            const alertClass = type === 'success' ? 'alert-success' : (type === 'error' ? 'alert-error' : 'alert-warning');
            alertContainer.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;
            setTimeout(() => {
                alertContainer.innerHTML = '';
            }, 5000);
        }

        const uploadBtn = document.getElementById('uploadBtn');
        if (uploadBtn) {
            uploadBtn.addEventListener('click', function() {
                const reportType = document.getElementById('reportTypeSelect').value;
                const month = document.getElementById('monthSelect').value;
                const year = document.getElementById('yearSelect').value;
                const department = document.getElementById('deptSelect').value;
                const file = excelFile.files[0];

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

                const allowedExtensions = ['xlsx', 'xls'];
                const fileExtension = file.name.split('.').pop().toLowerCase();
                if (!allowedExtensions.includes(fileExtension)) {
                    showAlert('error', 'Only Excel files (.xlsx, .xls) are allowed');
                    return;
                }

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

                const formData = new FormData();
                formData.append('excel_file', file);
                formData.append('report_type', reportType);
                formData.append('month', month);
                formData.append('year', year);
                formData.append('department', department);

                uploadBtn.disabled = true;
                uploadBtn.innerHTML = '<span class="loading"></span> Uploading...';

                fetch(config.upload, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
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
                            <thead><tr>`;

            if (previewRows.length > 0) {
                const headers = Object.keys(previewRows[0]);
                headers.forEach(header => {
                    if (!['upload_date', 'status'].includes(header)) {
                        html += `<th>${formatHeader(header)}</th>`;
                    }
                });
            }
            html += `</tr></thead><tbody>`;

            previewRows.forEach((row, index) => {
                html += `<tr>`;
                Object.keys(row).forEach(key => {
                    if (!['upload_date', 'status'].includes(key)) {
                        let value = row[key] || '';
                        if (value.length > 50) value = value.substring(0, 50) + '...';
                        html += `<td title="${escapeHtml(row[key] || '')}">${escapeHtml(value)}</td>`;
                    }
                });
                html += `</tr>`;
            });
            html += `</tbody></table></div>`;
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

        function openPreviewModal() {
            const modal = document.getElementById('previewModal');
            if (modal) modal.classList.add('active');
        }

        function closePreviewModal() {
            const modal = document.getElementById('previewModal');
            if (modal) modal.classList.remove('active');
            currentPreviewData = null;
        }

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

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePreviewModal();
                closePasswordModal();
            }
        });

        let passwordModalOverlay = null;

        function openPasswordModal() {
            if (passwordModalOverlay) return;
            passwordModalOverlay = document.createElement('div');
            passwordModalOverlay.id = 'passwordModalOverlay';
            passwordModalOverlay.onclick = function(e) {
                if (e.target === passwordModalOverlay) closePasswordModal();
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

        window.closePasswordPopup = function() {
            closePasswordModal();
        };

        function keepSessionAlive() {
            fetch('../keep_alive.php', {
                    method: 'GET',
                    cache: 'no-cache'
                })
                .catch(error => console.log('Session keep-alive failed:', error));
        }
        setInterval(keepSessionAlive, 5 * 60 * 1000);

        // ==================== List of Occurrence Form Functions ====================
        let eventOptions = [
            'ATB/DIV',
            'depressurization',
            'Maintenance Occ',
            'Mechanical & technical incident',
            'Air turn back/diversion due to Mechanical failure',
            'In flight emergency/depressurization',
            'Other'
        ];

        let nextItemNumber = 1;

        function calculateNextItemNumber() {
            let maxItem = 0;
            document.querySelectorAll('.item-input').forEach(input => {
                let val = parseInt(input.value) || 0;
                if (val > maxItem) maxItem = val;
            });
            nextItemNumber = maxItem + 1;
        }

        function updateTotal() {
            let total = 0;
            document.querySelectorAll('.number-input').forEach(input => {
                let val = parseInt(input.value) || 0;
                total += val;
            });
            document.getElementById('totalNumber').textContent = total;
        }

        function addRow(itemValue = '', eventValue = '', numberValue = 0) {
            const tbody = document.getElementById('looTableBody');
            const newRow = document.createElement('tr');
            newRow.className = 'loo-row';

            const itemDisplay = itemValue || nextItemNumber;

            newRow.innerHTML = `
                <td>
                    <input type="text" class="item-input" value="${itemDisplay}" style="width: 100%; padding: 0.5rem;" readonly>
                </td>
                <td>
                    <select class="event-select" style="width: 100%; padding: 0.5rem;">
                        <option value="">Select Event</option>
                        ${eventOptions.map(opt => `<option value="${opt}" ${eventValue === opt ? 'selected' : ''}>${opt}</option>`).join('')}
                    </select>
                    <input type="text" class="event-custom" placeholder="Enter custom event" value="${eventValue && !eventOptions.includes(eventValue) ? eventValue : ''}" style="width: 100%; padding: 0.5rem; margin-top: 0.5rem; display: ${eventValue && !eventOptions.includes(eventValue) ? 'block' : 'none'};">
                </td>
                <td>
                    <input type="number" class="number-input" value="${numberValue}" min="0" step="1" style="width: 100%; padding: 0.5rem;">
                </td>
                <td>
                    <button type="button" class="btn-remove-row" onclick="removeRow(this)">🗑️</button>
                </td>
            `;

            const select = newRow.querySelector('.event-select');
            const customInput = newRow.querySelector('.event-custom');
            const numberInput = newRow.querySelector('.number-input');

            select.addEventListener('change', function() {
                if (this.value === 'Other') {
                    customInput.style.display = 'block';
                } else {
                    customInput.style.display = 'none';
                    customInput.value = '';
                }
            });

            numberInput.addEventListener('input', function() {
                updateTotal();
                calculateNextItemNumber();
            });

            tbody.appendChild(newRow);
            updateTotal();
            calculateNextItemNumber();

            if (!itemValue) {
                nextItemNumber++;
            }
        }

        function removeRow(btn) {
            const row = btn.closest('tr');
            if (document.querySelectorAll('.loo-row').length > 1) {
                row.remove();
                updateTotal();
                calculateNextItemNumber();
                renumberItems();
            } else {
                showAlert('warning', 'You must keep at least one row');
            }
        }

        function renumberItems() {
            let counter = 1;
            document.querySelectorAll('.item-input').forEach(input => {
                input.value = counter++;
            });
            calculateNextItemNumber();
        }

        function loadExistingData() {
            const existingData = <?php echo json_encode($existingLooData); ?>;
            const tbody = document.getElementById('looTableBody');
            tbody.innerHTML = '';

            if (existingData && existingData.length > 0) {
                existingData.forEach(record => {
                    addRow(record.Item, record.Event, record.Number);
                });
                calculateNextItemNumber();
            } else {
                addRow();
            }
        }

        function saveLooForm() {
            const month = document.getElementById('monthSelect').value;
            const year = document.getElementById('yearSelect').value;
            const rows = document.querySelectorAll('.loo-row');
            const data = [];

            for (let row of rows) {
                let item = row.querySelector('.item-input')?.value.trim() || '';
                let event = row.querySelector('.event-select')?.value || '';
                const customEvent = row.querySelector('.event-custom')?.value.trim() || '';
                const number = parseInt(row.querySelector('.number-input')?.value) || 0;

                if (event === 'Other' && customEvent) {
                    event = customEvent;
                }

                if (!event) continue;

                data.push({
                    item: item,
                    event: event,
                    number: number
                });
            }

            if (data.length === 0) {
                showAlert('warning', 'No data to save');
                return;
            }

            const saveBtn = document.getElementById('saveLooFormBtn');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="loading"></span> Saving...';

            fetch('looform_save.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        data: data,
                        month: month,
                        year: year
                    })
                })
                .then(response => response.json())
                .then(result => {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '💾 Save List of Occurrence';

                    if (result.success) {
                        showAlert('success', `✅ ${result.message} - Saved: ${result.saved}, Updated: ${result.updated}, Skipped: ${result.skipped}`);
                        setTimeout(() => {
                            window.location.href = `qa_report_entry.php?report_type=looform&month=${month}&year=${year}&department=ALL`;
                        }, 1500);
                    } else {
                        showAlert('error', result.message || 'Error saving data');
                    }
                })
                .catch(error => {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '💾 Save List of Occurrence';
                    console.error('Save error:', error);
                    showAlert('error', 'Error saving data: ' + error.message);
                });
        }

        function toggleLooForm() {
            const reportType = document.getElementById('reportTypeSelect').value;
            const uploadSection = document.querySelector('.upload-section');
            const looFormSection = document.getElementById('looFormSection');

            if (reportType === 'looform') {
                uploadSection.style.display = 'none';
                looFormSection.style.display = 'block';
                loadExistingData();
            } else {
                uploadSection.style.display = 'block';
                if (looFormSection) looFormSection.style.display = 'none';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const reportTypeSelect = document.getElementById('reportTypeSelect');
            const monthSelect = document.getElementById('monthSelect');
            const yearSelect = document.getElementById('yearSelect');
            const filterForm = document.getElementById('filterForm');

            if (reportTypeSelect) {
                // Initial load - check if we should show the form
                toggleLooForm();

                // When report type changes, update URL and reload
                reportTypeSelect.addEventListener('change', function() {
                    filterForm.submit();
                });
            }

            // When month or year changes, reload the page to fetch new data
            if (monthSelect) {
                monthSelect.addEventListener('change', function() {
                    filterForm.submit();
                });
            }

            if (yearSelect) {
                yearSelect.addEventListener('change', function() {
                    filterForm.submit();
                });
            }

            const addRowBtn = document.getElementById('addRowBtn');
            if (addRowBtn) {
                addRowBtn.addEventListener('click', function() {
                    addRow();
                });
            }

            const saveBtn = document.getElementById('saveLooFormBtn');
            if (saveBtn) {
                saveBtn.addEventListener('click', saveLooForm);
            }

            updateTotal();
        });
    </script>
</body>

</html>