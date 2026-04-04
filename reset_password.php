<?php
require_once 'session_config.php';
require_once 'config/database.php';

// Check if user is logged in with temp password
if (!isset($_SESSION['temp_user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($newPassword) || empty($confirmPassword)) {
        $error = 'Please fill in all fields';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Password must be at least 6 characters long';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match';
    } else {
        $conn = getConnection();

        // Hash the new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update user password and clear temp password
        $updateStmt = $conn->prepare("UPDATE users SET password = ?, temp_password = NULL, temp_password_expiry = NULL WHERE id = ?");
        $updateStmt->bind_param("si", $hashedPassword, $_SESSION['temp_user_id']);

        if ($updateStmt->execute()) {
            // Clear temp session
            unset($_SESSION['temp_user_id']);
            session_regenerate_id(true);

            $success = "Password changed successfully! You will be redirected to login page.";
            header("refresh:3;url=index.php");
        } else {
            $error = "Failed to update password. Please try again.";
        }

        $updateStmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - HR & Finance Dashboard</title>
    <link rel="icon" type="image/x-icon" href="assets/images/ethiopian_logo.ico">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            background-color: #000;
            background-image: radial-gradient(rgba(255, 255, 255, 0.3) 1px, transparent 1px);
            background-size: 20px 20px;
        }

        body::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.2) 0%, rgba(0, 0, 0, 0.1) 100%);
            pointer-events: none;
        }

        .container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 450px;
            margin: 1.5rem;
        }

        .card {
            background: rgba(255, 255, 255);
            backdrop-filter: blur(10px);
            border-radius: 1.4rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0px 20px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .card-content {
            padding: 2rem;
        }

        .logo-container {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .title {
            font-size: 1.25rem;
            font-weight: 600;
            text-align: center;
            color: #111827;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            font-size: 0.8rem;
            text-align: center;
            color: #6b7280;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #374151;
        }

        .form-group input {
            width: 100%;
            padding: 0.625rem 0.875rem;
            font-size: 0.875rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .btn {
            width: 100%;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            font-weight: 600;
            padding: 0.625rem 1rem;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .error-message {
            background-color: #fee2e2;
            border-left: 4px solid #dc2626;
            color: #991b1b;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.25rem;
            font-size: 0.875rem;
        }

        .success-message {
            background-color: #d1fae5;
            border-left: 4px solid #10b981;
            color: #065f46;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.25rem;
            font-size: 0.875rem;
        }

        .password-requirements {
            font-size: 0.7rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }

        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.8);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card {
            animation: fadeInUp 0.5s ease-out;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="card">
            <div class="card-content">
                <div class="logo-container">
                    <img src="assets/images/logo.png" alt="Logo" height="75">
                </div>

                <h2 class="title">Create New Password</h2>
                <div class="subtitle">Please create a new password for your account</div>

                <?php if ($error): ?>
                    <div class="error-message">
                        ⚠️ <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="success-message">
                        ✓ <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required>
                        <div class="password-requirements">Minimum 6 characters</div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>

                    <button type="submit" class="btn">Change Password</button>
                </form>
            </div>
        </div>

        <div class="login-footer">
            &copy; <?php echo date('Y'); ?> Ethiopian Airlines. All rights reserved.
        </div>
    </div>
</body>

</html>