<?php
require_once '../includes/auth.php';

// Only allow RamsisE to access this page
if ($_SESSION['username'] !== 'RamsisE') {
    $_SESSION['error'] = "Access denied. Only RamsisE can verify data.";
    header('Location: master_data.php');
    exit();
}

requireRole('hr');

$conn = getConnection();
$currentMonth = $_GET['month'] ?? date('Y-m');
$dataMonth = $currentMonth . '-01';
$userId = $_SESSION['user_id'];
$userName = $_SESSION['full_name'];

// Get pending and verified data for the selected month
$query = "SELECT m.id, m.indicator_name, m.department, m.actual_value, m.target_value, 
          m.percentage_achievement, m.remarks, m.verification_status,
          m.created_at, m.updated_at,
          m.verified_by, m.verified_at,
          u1.full_name as verified_by_name,
          c.full_name as created_by_name,
          u.full_name as updated_by_name
          FROM master_performance_data m
          LEFT JOIN users u1 ON m.verified_by = u1.id
          LEFT JOIN users c ON m.created_by = c.id
          LEFT JOIN users u ON m.updated_by = u.id
          WHERE m.data_month = ?
          ORDER BY 
            CASE m.verification_status
              WHEN 'pending' THEN 1
              WHEN 'verified' THEN 2
              WHEN 'rejected' THEN 3
            END,
            m.department,
            m.indicator_name";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $dataMonth);
$stmt->execute();
$records = $stmt->get_result();

// Get verification statistics
$statsQuery = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected
               FROM master_performance_data 
               WHERE data_month = ?";
$statsStmt = $conn->prepare($statsQuery);
$statsStmt->bind_param("s", $dataMonth);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc();

$stmt->close();
$statsStmt->close();
$conn->close();

// Helper function to get status badge
function getStatusBadge($status) {
    switch($status) {
        case 'pending':
            return '<span class="status-badge status-pending">⏳ Pending</span>';
        case 'verified':
            return '<span class="status-badge status-verified">✓ Verified</span>';
        case 'rejected':
            return '<span class="status-badge status-rejected">✗ Rejected</span>';
        default:
            return '<span class="status-badge">Unknown</span>';
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
    <title>Data Verification - HR Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .verification-header {
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
            font-size: 2rem;
            font-weight: bold;
            color: var(--accent);
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: var(--light-bg);
            opacity: 0.8;
            margin-top: 0.25rem;
        }
        
        .verification-table-wrapper {
            overflow-x: auto;
            background: var(--medium-bg);
            border-radius: 15px;
            padding: 1rem;
        }
        
        .verification-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        
        .verification-table th,
        .verification-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--dark-bg);
            vertical-align: top;
        }
        
        .verification-table th {
            background: var(--dark-bg);
            color: var(--accent);
            font-weight: bold;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .verification-table tr:hover {
            background: rgba(0,173,181,0.05);
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        .status-pending {
            background: var(--warning);
            color: var(--dark-bg);
        }
        
        .status-verified {
            background: var(--success);
            color: white;
        }
        
        .status-rejected {
            background: var(--danger);
            color: white;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 0.3rem 0.8rem;
            font-size: 0.75rem;
        }
        
        .btn-verify {
            background: var(--success);
            color: white;
        }
        
        .btn-reject {
            background: var(--danger);
            color: white;
        }
        
        .btn-view {
            background: var(--info);
            color: white;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background: var(--medium-bg);
            margin: 5% auto;
            padding: 2rem;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--accent);
            padding-bottom: 0.5rem;
        }
        
        .modal-header h3 {
            color: var(--accent);
            margin: 0;
        }
        
        .close {
            color: var(--light-bg);
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: var(--accent);
        }
        
        .detail-row {
            margin-bottom: 1rem;
            padding: 0.5rem;
            background: var(--dark-bg);
            border-radius: 8px;
        }
        
        .detail-label {
            font-weight: bold;
            color: var(--accent);
            margin-bottom: 0.25rem;
            font-size: 0.8rem;
        }
        
        .detail-value {
            color: var(--light-bg);
            font-size: 0.9rem;
        }
        
        .remarks-text {
            background: var(--dark-bg);
            padding: 0.5rem;
            border-radius: 5px;
            margin-top: 0.5rem;
            font-style: italic;
        }
        
        .verification-info {
            font-size: 0.7rem;
            color: var(--accent);
            margin-top: 0.25rem;
        }
        
        .filter-section {
            margin-bottom: 1rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-btn {
            background: var(--dark-bg);
            color: var(--light-bg);
            border: 1px solid var(--accent);
            padding: 0.4rem 1rem;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filter-btn.active {
            background: var(--accent);
            color: var(--dark-bg);
        }
        
        .filter-btn:hover {
            background: var(--accent);
            color: var(--dark-bg);
        }
        
        .month-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
            
            .verification-table {
                font-size: 0.7rem;
            }
            
            .verification-table th,
            .verification-table td {
                padding: 0.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
        
        .department-badge {
            background: var(--accent);
            color: var(--dark-bg);
            padding: 0.2rem 0.5rem;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: bold;
            display: inline-block;
        }
        
        .percentage-high {
            color: var(--success);
            font-weight: bold;
        }
        
        .percentage-medium {
            color: var(--warning);
            font-weight: bold;
        }
        
        .percentage-low {
            color: var(--danger);
            font-weight: bold;
        }
        
        textarea {
            width: 100%;
            padding: 0.5rem;
            border-radius: 5px;
            background: var(--dark-bg);
            color: var(--light-bg);
            border: 1px solid var(--accent);
            font-family: inherit;
            resize: vertical;
        }
        
        .modal-footer {
            margin-top: 1.5rem;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }
        
        .batch-actions {
            margin-top: 1rem;
            padding: 1rem;
            background: var(--dark-bg);
            border-radius: 10px;
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .batch-actions select {
            background: var(--medium-bg);
            color: var(--light-bg);
            padding: 0.5rem;
            border-radius: 5px;
            border: 1px solid var(--accent);
        }
        
        .warning-banner {
            background: var(--warning);
            color: var(--dark-bg);
            padding: 0.6rem 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            text-align: center;
            font-weight: bold;
        }
        
        .success-banner {
            background: var(--success);
            color: white;
            padding: 0.6rem 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .error-banner {
            background: var(--danger);
            color: white;
            padding: 0.6rem 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="master_data.php" class="navbar-brand">HR & Finance Dashboard</a>
            <div class="navbar-menu">
                <a href="master_data.php">Master Data Entry</a>
                  <a href="report_mro_cpr.php">Director Data Entry</a>
                <a href="verify_data.php" style="color: var(--accent);">Verify Data</a>
                <a href="data_history.php">History</a>
                <div class="user-info">
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="../logout.php" class="btn" style="padding: 0.5rem 1rem;">Logout</a>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <div class="month-navigation">
            <button onclick="changeMonth('prev')" class="btn">&larr; Previous Month</button>
            <h2>Data Verification - <?php echo date('F Y', strtotime($dataMonth)); ?></h2>
            <button onclick="changeMonth('next')" class="btn">Next Month &rarr;</button>
        </div>
        
        <?php if ($message): ?>
            <div class="success-banner">✓ <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-banner">⚠ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="verification-header">
            <h3>📋 Verification Dashboard</h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                    <div class="stat-label">Total Records</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: var(--warning);"><?php echo $stats['pending'] ?? 0; ?></div>
                    <div class="stat-label">Pending Verification</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: var(--success);"><?php echo $stats['verified'] ?? 0; ?></div>
                    <div class="stat-label">Verified</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: var(--danger);"><?php echo $stats['rejected'] ?? 0; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>
        </div>
        
        <div class="filter-section">
            <span style="color: var(--accent);">Filter by status:</span>
            <button class="filter-btn active" data-filter="all">All</button>
            <button class="filter-btn" data-filter="pending">Pending</button>
            <button class="filter-btn" data-filter="verified">Verified</button>
            <button class="filter-btn" data-filter="rejected">Rejected</button>
        </div>
        
        <div class="verification-table-wrapper">
            <table class="verification-table" id="verificationTable">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" id="selectAll">
                        </th>
                        <th>Department</th>
                        <th>Indicator</th>
                        <th>Actual</th>
                        <th>Target</th>
                        <th>%</th>
                        <th>Status</th>
                        <th>Verified By</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($record = $records->fetch_assoc()): 
                        $percentage = round($record['percentage_achievement'], 1);
                        $percentageClass = '';
                        if ($percentage >= 90) $percentageClass = 'percentage-high';
                        elseif ($percentage >= 70) $percentageClass = 'percentage-medium';
                        else $percentageClass = 'percentage-low';
                    ?>
                        <tr data-status="<?php echo $record['verification_status']; ?>" data-id="<?php echo $record['id']; ?>">
                            <td style="text-align: center;">
                                <?php if ($record['verification_status'] == 'pending'): ?>
                                    <input type="checkbox" class="record-checkbox" value="<?php echo $record['id']; ?>">
                                <?php endif; ?>
                            </td>
                            <td><span class="department-badge"><?php echo htmlspecialchars($record['department']); ?></span></td>
                            <td><?php echo htmlspecialchars($record['indicator_name']); ?></td>
                            <td><?php echo number_format($record['actual_value'], 2); ?></td>
                            <td><?php echo number_format($record['target_value'], 2); ?></td>
                            <td class="<?php echo $percentageClass; ?>"><?php echo $percentage; ?>%</td>
                            <td><?php echo getStatusBadge($record['verification_status']); ?></td>
                            <td>
                                <?php if ($record['verified_by']): ?>
                                    <?php echo htmlspecialchars($record['verified_by_name']); ?><br>
                                    <small><?php echo date('Y-m-d H:i', strtotime($record['verified_at'])); ?></small>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="verification-info">
                                Created: <?php echo date('Y-m-d', strtotime($record['created_at'])); ?><br>
                                By: <?php echo htmlspecialchars($record['created_by_name']); ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-view" onclick="viewDetails(<?php echo $record['id']; ?>)">View</button>
                                    <?php if ($record['verification_status'] == 'pending'): ?>
                                        <button class="btn btn-sm btn-verify" onclick="verifyRecord(<?php echo $record['id']; ?>, 'verify')">Verify</button>
                                        <button class="btn btn-sm btn-reject" onclick="verifyRecord(<?php echo $record['id']; ?>, 'reject')">Reject</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($stats['pending'] > 0): ?>
            <div class="batch-actions">
                <span style="color: var(--accent);">Batch Actions:</span>
                <select id="batchAction">
                    <option value="">Select Action</option>
                    <option value="verify">Verify Selected</option>
                    <option value="reject">Reject Selected</option>
                </select>
                <button class="btn" onclick="batchAction()">Apply</button>
                <span id="selectedCount" style="color: var(--light-bg);">0 selected</span>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal for Viewing Details -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>📄 Record Details</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div id="modalBody">
                <!-- Dynamic content will be loaded here -->
            </div>
        </div>
    </div>
    
    <!-- Modal for Verification -->
    <div id="verifyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="verifyModalTitle">Verify Record</h3>
                <span class="close" onclick="closeVerifyModal()">&times;</span>
            </div>
            <form id="verifyForm" action="update_verification.php" method="POST">
                <input type="hidden" name="record_id" id="verifyRecordId">
                <input type="hidden" name="action" id="verifyAction">
                <input type="hidden" name="month" value="<?php echo $currentMonth; ?>">
                
                <div class="detail-row">
                    <div class="detail-label">Verification Notes</div>
                    <textarea name="notes" id="verificationNotes" rows="4" placeholder="Enter verification notes or reason for rejection..."></textarea>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeVerifyModal()">Cancel</button>
                    <button type="submit" class="btn btn-verify">Confirm</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Filter functionality
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const filter = this.dataset.filter;
                const rows = document.querySelectorAll('#verificationTable tbody tr');
                
                rows.forEach(row => {
                    if (filter === 'all' || row.dataset.status === filter) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        });
        
        // Select All functionality
        const selectAllCheckbox = document.getElementById('selectAll');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.record-checkbox');
                checkboxes.forEach(cb => {
                    cb.checked = this.checked;
                });
                updateSelectedCount();
            });
        }
        
        // Update selected count
        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.record-checkbox:checked');
            const count = checkboxes.length;
            const selectedSpan = document.getElementById('selectedCount');
            if (selectedSpan) {
                selectedSpan.innerHTML = `${count} selected`;
            }
        }
        
        // Add event listeners to checkboxes
        document.querySelectorAll('.record-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSelectedCount);
        });
        
        // Batch action
        function batchAction() {
            const action = document.getElementById('batchAction').value;
            const selected = document.querySelectorAll('.record-checkbox:checked');
            
            if (selected.length === 0) {
                alert('Please select at least one record');
                return;
            }
            
            if (!action) {
                alert('Please select an action');
                return;
            }
            
            const recordIds = Array.from(selected).map(cb => cb.value);
            
            if (confirm(`Are you sure you want to ${action} ${selected.length} record(s)?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'update_verification.php';
                
                form.innerHTML = `
                    <input type="hidden" name="batch_ids" value='${JSON.stringify(recordIds)}'>
                    <input type="hidden" name="batch_action" value="${action}">
                    <input type="hidden" name="month" value="<?php echo $currentMonth; ?>">
                `;
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // View record details
        function viewDetails(recordId) {
            const modal = document.getElementById('detailsModal');
            const modalBody = document.getElementById('modalBody');
            
            fetch(`get_record_details.php?id=${recordId}&month=<?php echo $currentMonth; ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        modalBody.innerHTML = `
                            <div class="detail-row">
                                <div class="detail-label">Department</div>
                                <div class="detail-value"><strong>${data.record.department}</strong></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Indicator</div>
                                <div class="detail-value">${data.record.indicator_name}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Values</div>
                                <div class="detail-value">
                                    <strong>Actual:</strong> ${data.record.actual_value}<br>
                                    <strong>Target:</strong> ${data.record.target_value}<br>
                                    <strong>Percentage:</strong> ${data.record.percentage_achievement}%
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Remarks</div>
                                <div class="remarks-text">${data.record.remarks || 'No remarks'}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Created By</div>
                                <div class="detail-value">${data.record.created_by_name} on ${data.record.created_at}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Last Updated</div>
                                <div class="detail-value">${data.record.updated_by_name || 'N/A'} on ${data.record.updated_at || 'N/A'}</div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Verification Status</div>
                                <div class="detail-value">${data.record.verification_status}</div>
                            </div>
                            ${data.record.verified_by_name ? `
                            <div class="detail-row">
                                <div class="detail-label">Verified By</div>
                                <div class="detail-value">${data.record.verified_by_name}<br>On: ${data.record.verified_at}</div>
                            </div>
                            ` : ''}
                        `;
                        modal.style.display = 'block';
                    } else {
                        alert('Error loading record details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading record details');
                });
        }
        
        // Verify/Reject record
        function verifyRecord(recordId, action) {
            const modal = document.getElementById('verifyModal');
            const title = document.getElementById('verifyModalTitle');
            const verifyRecordId = document.getElementById('verifyRecordId');
            const verifyAction = document.getElementById('verifyAction');
            
            title.innerHTML = action === 'verify' ? '✓ Verify Record' : '✗ Reject Record';
            verifyRecordId.value = recordId;
            verifyAction.value = action;
            
            modal.style.display = 'block';
        }
        
        // Close modals
        function closeModal() {
            document.getElementById('detailsModal').style.display = 'none';
        }
        
        function closeVerifyModal() {
            document.getElementById('verifyModal').style.display = 'none';
            document.getElementById('verificationNotes').value = '';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('detailsModal');
            const verifyModal = document.getElementById('verifyModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
            if (event.target == verifyModal) {
                verifyModal.style.display = 'none';
            }
        }
        
        // Change month function
        function changeMonth(direction) {
            let currentUrl = new URL(window.location.href);
            let currentMonth = currentUrl.searchParams.get('month') || '<?php echo $currentMonth; ?>';
            let date = new Date(currentMonth + '-01');
            
            if (direction === 'prev') {
                date.setMonth(date.getMonth() - 1);
            } else {
                date.setMonth(date.getMonth() + 1);
            }
            
            let newMonth = date.toISOString().slice(0, 7);
            window.location.href = `verify_data.php?month=${newMonth}`;
        }
        
        // Initialize
        updateSelectedCount();
    </script>
</body>
</html>