<?php
require_once '../includes/auth.php';
requireRole('hr');

$conn = getConnection();

// Get filter parameters
$filterRecord = isset($_GET['record_id']) ? intval($_GET['record_id']) : null;
$filterUser = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
$filterAction = isset($_GET['action']) ? $_GET['action'] : null;
$filterDateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$filterDateTo = isset($_GET['date_to']) ? $_GET['date_to'] : null;

// Build query with filters
$query = "SELECT al.*, 
          u.full_name as user_name,
          m.department,
          m.indicator_name
          FROM data_audit_log al
          LEFT JOIN users u ON al.performed_by = u.id
          LEFT JOIN master_performance_data m ON al.record_id = m.id
          WHERE 1=1";

$params = [];
$types = "";

if ($filterRecord) {
    $query .= " AND al.record_id = ?";
    $params[] = $filterRecord;
    $types .= "i";
}

if ($filterUser) {
    $query .= " AND al.performed_by = ?";
    $params[] = $filterUser;
    $types .= "i";
}

if ($filterAction) {
    $query .= " AND al.action = ?";
    $params[] = $filterAction;
    $types .= "s";
}

if ($filterDateFrom) {
    $query .= " AND DATE(al.performed_at) >= ?";
    $params[] = $filterDateFrom;
    $types .= "s";
}

if ($filterDateTo) {
    $query .= " AND DATE(al.performed_at) <= ?";
    $params[] = $filterDateTo;
    $types .= "s";
}

$query .= " ORDER BY al.performed_at DESC LIMIT 500";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result();

// Get users for filter dropdown
$usersQuery = "SELECT id, full_name FROM users WHERE role = 'hr' ORDER BY full_name";
$users = $conn->query($usersQuery);

// Get statistics
$statsQuery = "SELECT 
                COUNT(*) as total_actions,
                COUNT(DISTINCT record_id) as records_affected,
                COUNT(DISTINCT performed_by) as users_involved,
                MIN(performed_at) as first_action,
                MAX(performed_at) as last_action
               FROM data_audit_log";
$stats = $conn->query($statsQuery)->fetch_assoc();

$conn->close();

// Helper function to format JSON data nicely
function formatChangeData($oldData, $newData) {
    $old = json_decode($oldData, true);
    $new = json_decode($newData, true);
    
    if (!$old && !$new) return '<span style="color: #888;">No data</span>';
    
    $html = '<table style="width:100%; font-size:0.7rem; border-collapse:collapse;">';
    
    if ($old) {
        $html .= '<tr><td style="padding:2px; color:#dc3545;"><strong>OLD:</strong></td><td style="padding:2px;">';
        foreach ($old as $key => $value) {
            $html .= "<span style='color:#dc3545;'>$key:</span> $value<br>";
        }
        $html .= '</td></tr>';
    }
    
    if ($new) {
        $html .= '<tr><td style="padding:2px; color:#28a745;"><strong>NEW:</strong></td><td style="padding:2px;">';
        foreach ($new as $key => $value) {
            $html .= "<span style='color:#28a745;'>$key:</span> $value<br>";
        }
        $html .= '</td></tr>';
    }
    
    $html .= '</table>';
    return $html;
}

// Helper function for action badge
function getActionBadge($action) {
    switch($action) {
        case 'insert':
            return '<span class="badge badge-insert">➕ Insert</span>';
        case 'update':
            return '<span class="badge badge-update">✏️ Update</span>';
        case 'verify':
            return '<span class="badge badge-verify">✓ Verify</span>';
        case 'reject':
            return '<span class="badge badge-reject">✗ Reject</span>';
        default:
            return '<span class="badge">' . $action . '</span>';
    }
}

$message = isset($_SESSION['message']) ? $_SESSION['message'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['message']);
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data History - Audit Trail</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .history-header {
            background: linear-gradient(135deg, var(--medium-bg) 0%, var(--dark-bg) 100%);
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .stat-card {
            background: var(--dark-bg);
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--accent);
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--light-bg);
            opacity: 0.8;
            margin-top: 0.25rem;
        }
        
        .filter-section {
            background: var(--medium-bg);
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .filter-group label {
            font-size: 0.7rem;
            color: var(--accent);
            font-weight: bold;
        }
        
        .filter-group select,
        .filter-group input {
            padding: 0.5rem;
            border-radius: 5px;
            border: 1px solid var(--dark-bg);
            background: var(--dark-bg);
            color: var(--light-bg);
            font-size: 0.8rem;
        }
        
        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: var(--accent);
        }
        
        .table-wrapper {
            overflow-x: auto;
            background: var(--medium-bg);
            border-radius: 15px;
            padding: 1rem;
        }
        
        .history-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }
        
        .history-table th,
        .history-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--dark-bg);
            vertical-align: top;
        }
        
        .history-table th {
            background: var(--dark-bg);
            color: var(--accent);
            font-weight: bold;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .history-table tr:hover {
            background: rgba(0,173,181,0.05);
        }
        
        .badge {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: bold;
        }
        
        .badge-insert {
            background: #28a745;
            color: white;
        }
        
        .badge-update {
            background: #ffc107;
            color: #222;
        }
        
        .badge-verify {
            background: #17a2b8;
            color: white;
        }
        
        .badge-reject {
            background: #dc3545;
            color: white;
        }
        
        .record-link {
            color: var(--accent);
            text-decoration: none;
            font-weight: bold;
        }
        
        .record-link:hover {
            text-decoration: underline;
        }
        
        .clear-filter {
            background: var(--accent);
            color: var(--dark-bg);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.7rem;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }
        
        .clear-filter:hover {
            transform: translateY(-1px);
        }
        
        .pagination {
            margin-top: 1.5rem;
            display: flex;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .pagination button {
            background: var(--dark-bg);
            color: var(--light-bg);
            border: 1px solid var(--accent);
            padding: 0.3rem 0.8rem;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .pagination button:hover {
            background: var(--accent);
            color: var(--dark-bg);
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .history-table {
                font-size: 0.65rem;
            }
            
            .history-table th,
            .history-table td {
                padding: 0.5rem;
            }
        }
        
        .timestamp {
            font-family: monospace;
            font-size: 0.7rem;
        }
        
        .change-details {
            max-width: 300px;
        }
        
        .export-btn {
            background: var(--accent);
            color: var(--dark-bg);
            padding: 0.5rem 1rem;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .export-btn:hover {
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="master_data.php" class="navbar-brand">HR & Finance Dashboard</a>
            <div class="navbar-menu">
                <a href="master_data.php">Master Data Entry</a>
                <a href="verify_data.php">Verify Data</a>
                <a href="data_history.php" style="color: var(--accent);">History</a>
                <div class="user-info">
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="../logout.php" class="btn" style="padding: 0.5rem 1rem;">Logout</a>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="history-header">
            <h2>📜 Data History & Audit Trail</h2>
            <p>Complete history of all changes made to master data</p>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['total_actions']); ?></div>
                    <div class="stat-label">Total Actions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['records_affected']); ?></div>
                    <div class="stat-label">Records Affected</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['users_involved']); ?></div>
                    <div class="stat-label">Users Involved</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo date('M d, Y', strtotime($stats['first_action'])); ?></div>
                    <div class="stat-label">First Action</div>
                </div>
            </div>
        </div>
        
        <div class="filter-section">
            <form method="GET" action="" class="filter-form">
                <div class="filter-group">
                    <label>Record ID</label>
                    <input type="number" name="record_id" placeholder="Filter by Record ID" value="<?php echo htmlspecialchars($filterRecord); ?>">
                </div>
                
                <div class="filter-group">
                    <label>User</label>
                    <select name="user_id">
                        <option value="">All Users</option>
                        <?php while ($user = $users->fetch_assoc()): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $filterUser == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['full_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Action</label>
                    <select name="action">
                        <option value="">All Actions</option>
                        <option value="insert" <?php echo $filterAction == 'insert' ? 'selected' : ''; ?>>Insert</option>
                        <option value="update" <?php echo $filterAction == 'update' ? 'selected' : ''; ?>>Update</option>
                        <option value="verify" <?php echo $filterAction == 'verify' ? 'selected' : ''; ?>>Verify</option>
                        <option value="reject" <?php echo $filterAction == 'reject' ? 'selected' : ''; ?>>Reject</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Date From</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($filterDateFrom); ?>">
                </div>
                
                <div class="filter-group">
                    <label>Date To</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($filterDateTo); ?>">
                </div>
                
                <div class="filter-group">
                    <button type="submit" class="btn">🔍 Apply Filters</button>
                    <a href="data_history.php" class="clear-filter">🗑️ Clear Filters</a>
                </div>
            </form>
        </div>
        
        <div class="table-wrapper">
            <table class="history-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date & Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Record</th>
                        <th>Changes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs->num_rows > 0): ?>
                        <?php while ($log = $logs->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $log['id']; ?></td>
                                <td class="timestamp"><?php echo date('Y-m-d H:i:s', strtotime($log['performed_at'])); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($log['user_name']); ?></strong>
                                </td>
                                <td><?php echo getActionBadge($log['action']); ?></td>
                                <td>
                                    <?php if ($log['record_id']): ?>
                                        <a href="master_data.php?record_id=<?php echo $log['record_id']; ?>" class="record-link">
                                            #<?php echo $log['record_id']; ?>
                                        </a>
                                        <?php if ($log['department']): ?>
                                            <div style="font-size: 0.65rem; color: #888;">
                                                <?php echo htmlspecialchars($log['department']); ?> - 
                                                <?php echo htmlspecialchars(substr($log['indicator_name'], 0, 30)); ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #888;">Record Deleted</span>
                                    <?php endif; ?>
                                </td>
                                <td class="change-details">
                                    <?php echo formatChangeData($log['old_data'], $log['new_data']); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2rem;">
                                <div style="color: #888;">No history records found</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 1rem; text-align: right;">
            <small style="color: #888;">Showing last 500 records. Use filters to narrow down results.</small>
        </div>
    </div>
    
    <script>
        // Auto-submit form when filters change (except for text inputs)
        document.querySelectorAll('select, input[type="date"]').forEach(element => {
            element.addEventListener('change', function() {
                this.closest('form').submit();
            });
        });
        
        // Add loading indicator on form submit
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.innerHTML = '⏳ Loading...';
                    submitBtn.disabled = true;
                }
            });
        });
    </script>
</body>
</html>