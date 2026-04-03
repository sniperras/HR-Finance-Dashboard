<?php
session_start();
require_once __DIR__ . '/config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldPassword = $_POST['old_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validate inputs
    if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
        $response['message'] = 'All fields are required';
        echo json_encode($response);
        exit();
    }

    if ($newPassword !== $confirmPassword) {
        $response['message'] = 'New passwords do not match';
        echo json_encode($response);
        exit();
    }

    if (strlen($newPassword) < 6) {
        $response['message'] = 'Password must be at least 6 characters';
        echo json_encode($response);
        exit();
    }

    $conn = getConnection();
    $userId = $_SESSION['user_id'];

    // Get current password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    // Verify old password
    if (!password_verify($oldPassword, $user['password'])) {
        $response['message'] = 'Current password is incorrect';
        echo json_encode($response);
        $conn->close();
        exit();
    }

    // Hash new password
    $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update password
    $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $updateStmt->bind_param("si", $newHashedPassword, $userId);

    if ($updateStmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Password changed successfully! You will be logged out now.';

        // Destroy session to force logout
        session_destroy();
    } else {
        $response['message'] = 'Failed to change password. Please try again.';
    }

    $updateStmt->close();
    $conn->close();

    echo json_encode($response);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: transparent;
            font-family: 'Segoe UI', 'Inter', system-ui, sans-serif;
        }

        .password-container {
            background: var(--medium-bg, #1E293B);
            border-radius: 16px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid var(--border-light, #334155);
        }

        .password-header {
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid var(--border-light, #334155);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .password-header h3 {
            color: var(--accent, #38BDF8);
            font-size: 1.1rem;
            margin: 0;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary, #94A3B8);
            transition: color 0.2s;
        }

        .close-btn:hover {
            color: #EF4444;
        }

        .password-body {
            padding: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.2rem;
        }

        .form-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--accent, #38BDF8);
            margin-bottom: 0.4rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border-radius: 8px;
            border: 1px solid var(--border-light, #334155);
            background: var(--dark-bg, #0F172A);
            color: var(--text-primary, #F1F5F9);
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--accent, #38BDF8);
            box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2);
        }

        .password-requirements {
            font-size: 0.65rem;
            color: var(--text-secondary, #94A3B8);
            margin-top: 0.3rem;
        }

        .error-message {
            background: rgba(239, 68, 68, 0.15);
            border-left: 3px solid #EF4444;
            padding: 0.6rem;
            border-radius: 6px;
            font-size: 0.75rem;
            margin-bottom: 1rem;
            color: #EF4444;
        }

        .success-message {
            background: rgba(16, 185, 129, 0.15);
            border-left: 3px solid #10B981;
            padding: 0.6rem;
            border-radius: 6px;
            font-size: 0.75rem;
            margin-bottom: 1rem;
            color: #10B981;
        }

        .button-group {
            display: flex;
            gap: 0.8rem;
            margin-top: 1rem;
        }

        .btn {
            flex: 1;
            padding: 0.6rem;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--accent, #38BDF8);
            color: var(--dark-bg, #0F172A);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(56, 189, 248, 0.3);
        }

        .btn-secondary {
            background: var(--dark-bg, #0F172A);
            color: var(--text-primary, #F1F5F9);
            border: 1px solid var(--border-light, #334155);
        }

        .btn-secondary:hover {
            background: var(--border-light, #334155);
        }

        .spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.6s linear infinite;
            margin-right: 6px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Light Theme Support */
        body.light-theme .password-container {
            background: white;
            border-color: #E2E8F0;
        }

        body.light-theme .form-group input {
            background: #F8FAFC;
            border-color: #E2E8F0;
            color: #0F172A;
        }

        body.light-theme .btn-secondary {
            background: #F1F5F9;
            color: #0F172A;
            border-color: #E2E8F0;
        }

        /* Countdown timer */
        .countdown {
            text-align: center;
            margin-top: 1rem;
            font-size: 0.75rem;
            color: var(--text-secondary, #94A3B8);
        }
    </style>
</head>

<body>
    <div class="password-container">
        <div class="password-header">
            <h3>🔐 Change Password</h3>
            <button class="close-btn" onclick="parent.closePasswordPopup()">&times;</button>
        </div>
        <div class="password-body">
            <div id="messageContainer"></div>

            <form id="passwordForm">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" id="old_password" name="old_password" required autocomplete="off">
                </div>

                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" id="new_password" name="new_password" required autocomplete="off">
                    <div class="password-requirements">Minimum 6 characters</div>
                </div>

                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required autocomplete="off">
                </div>

                <div class="button-group">
                    <button type="button" class="btn btn-secondary" onclick="parent.closePasswordPopup()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Change Password</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Get theme from parent page
        if (window.parent && window.parent.document.body.classList.contains('light-theme')) {
            document.body.classList.add('light-theme');
        }

        document.getElementById('passwordForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const oldPassword = document.getElementById('old_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const submitBtn = document.getElementById('submitBtn');
            const messageContainer = document.getElementById('messageContainer');

            messageContainer.innerHTML = '';

            if (!oldPassword || !newPassword || !confirmPassword) {
                messageContainer.innerHTML = '<div class="error-message">⚠️ All fields are required</div>';
                return;
            }

            if (newPassword !== confirmPassword) {
                messageContainer.innerHTML = '<div class="error-message">⚠️ New passwords do not match</div>';
                return;
            }

            if (newPassword.length < 6) {
                messageContainer.innerHTML = '<div class="error-message">⚠️ Password must be at least 6 characters</div>';
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner"></span> Changing...';

            try {
                const formData = new URLSearchParams();
                formData.append('old_password', oldPassword);
                formData.append('new_password', newPassword);
                formData.append('confirm_password', confirmPassword);

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: formData.toString()
                });

                const data = await response.json();

                if (data.success) {
                    messageContainer.innerHTML = '<div class="success-message">✅ ' + data.message + '</div>';
                    document.getElementById('passwordForm').reset();

                    // Disable the form inputs
                    document.getElementById('old_password').disabled = true;
                    document.getElementById('new_password').disabled = true;
                    document.getElementById('confirm_password').disabled = true;
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = 'Redirecting...';

                    // Add countdown timer
                    let countdown = 0;
                    const countdownDiv = document.createElement('div');
                    countdownDiv.className = 'countdown';
                    countdownDiv.innerHTML = `Redirecting to login in ${countdown} seconds...`;
                    messageContainer.appendChild(countdownDiv);

                    const timer = setInterval(() => {
                        countdown--;
                        if (countdown > 0) {
                            countdownDiv.innerHTML = `Redirecting to login in ${countdown} seconds...`;
                        } else {
                            clearInterval(timer);
                            // Force redirect to logout page
                            window.parent.location.href = 'logout.php';
                            // Force reload after redirect
                            setTimeout(() => {
                                window.parent.location.reload(true); // reload from server
                            }, 500); // wait half a second so redirect happens first
                        }
                    }, 1000);

                    // Also close the modal after 1 second
                    setTimeout(() => {
                        if (window.parent && window.parent.closePasswordPopup) {
                            window.parent.closePasswordPopup();
                        }
                    }, 1000);

                } else {
                    messageContainer.innerHTML = '<div class="error-message">⚠️ ' + data.message + '</div>';
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Change Password';
                }
            } catch (error) {
                messageContainer.innerHTML = '<div class="error-message">⚠️ An error occurred. Please try again.</div>';
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Change Password';
            }
        });
    </script>
</body>

</html>