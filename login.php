<?php
session_start();
require_once 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $conn = getConnection();
    $stmt = $conn->prepare("SELECT id, username, full_name, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];

            // Update last login
            $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->bind_param("i", $user['id']);
            $updateStmt->execute();
            $updateStmt->close();

            // Redirect based on role
            if ($user['role'] === 'director') {
                header('Location: director/md_dashboard.php');
            } else {
                header('Location: admin/master_data.php');
            }
            exit();
        } else {
            $error = 'Invalid password';
        }
    } else {
        $error = 'User not found';
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - HR & Finance Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        /* Background pattern like Ethiopian Airlines page */
        .background-pattern {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 0;
        }

        .background-pattern svg {
            width: 100%;
            height: 100%;
            fill: #d4d4d4;
        }

        /* Login Container */
        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 400px;
            margin: 1.5rem;
        }

        /* Card Style */
        .login-card {
            background: #ffffff;
            border-radius: 1.4rem;
            border: 1px solid #e5e5e5;
            box-shadow: 0px 2px 3px 0px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .card-content {
            padding: 1.5rem;
        }

        /* Logo */
        .logo-container {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo {
            max-width: 180px;
            height: auto;
        }

        /* Title */
        .title {
            font-size: 1.25rem;
            font-weight: 600;
            text-align: center;
            color: #000;
            margin-bottom: 1.5rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #000;
        }

        .input-wrapper {
            position: relative;
        }

        .form-group input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            line-height: 1.5;
            color: #000;
            background-color: transparent;
            border: 1px solid #e5e5e5;
            border-radius: 0.375rem;
            transition: all 0.2s ease;
            font-family: inherit;
        }

        .form-group input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
        }

        .form-group input::placeholder {
            color: #9ca3af;
        }

        /* Password field with eye button */
        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            padding-right: 2.5rem;
        }

        .toggle-password {
            position: absolute;
            top: 0;
            right: 0;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 0.75rem;
            background: transparent;
            border: none;
            cursor: pointer;
            color: #6b7280;
            transition: color 0.2s;
        }

        .toggle-password:hover {
            color: #000;
        }

        .toggle-password svg {
            width: 1rem;
            height: 1rem;
        }

        /* Button */
        .btn-signin {
            width: 100%;
            background-color: #10b981;
            color: white;
            font-weight: 700;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: background-color 0.2s ease;
            font-family: inherit;
            margin-top: 0.5rem;
        }

        .btn-signin:hover {
            background-color: #059669;
        }

        .btn-signin:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.3);
        }

        /* Error Message */
        .error-message {
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            text-align: center;
            font-size: 0.875rem;
        }

        /* Reset Password Link */
        .reset-link {
            margin-top: 1rem;
            text-align: right;
        }

        .reset-link a {
            color: #10b981;
            font-size: 0.875rem;
            font-weight: 700;
            text-decoration: underline;
            text-underline-offset: 4px;
        }

        .reset-link a:hover {
            color: #059669;
        }

        /* Footer */
        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.75rem;
            color: #6b7280;
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            body {
                background: #0F172A;
            }

            .login-card {
                background: #ffffff;
                border-color: #ffffff;
            }

            .title {
                color: #000000;
            }

            .form-group label {
                color: #000000;
            }

            .form-group input {
                background-color: #3a3a3a;
                border-color: #4a4a4a;
                color: #e5e5e5;
            }

            .form-group input:focus {
                border-color: #10b981;
                box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.3);
            }

            .background-pattern svg {
                fill: #2d2d2d;
            }

            .login-footer {
                color: #ffffff;
            }

            .toggle-password {
                color: #9ca3af;
            }

            .toggle-password:hover {
                color: #000000;
            }
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-container {
                margin: 1rem;
            }

            .card-content {
                padding: 1.25rem;
            }
        }
    </style>
</head>

<body>
    <!-- Background pattern like Ethiopian Airlines page -->
    <div class="background-pattern">
        <svg width="100%" height="100%" viewBox="0 0 100 100" preserveAspectRatio="none">
            <defs>
                <pattern id="dotPattern" width="16" height="16" patternUnits="userSpaceOnUse">
                    <circle cx="1" cy="1" r="1" fill="currentColor" />
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#dotPattern)" opacity="0.3" />
        </svg>
    </div>

    <div class="login-container">
        <div class="login-card">
            <div class="card-content">
                <!-- Logo Section - You can replace with your actual logo -->
                <div class="logo-container">
                    <img src="assets/images/logo.png" alt="Online Data Icon"  height="75">

                </div>

                <!-- Title -->
                <h2 class="title">HR & Finance Dashboard</h2>

                <!-- Error Message -->
                <?php if ($error): ?>
                    <div class="error-message">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Login Form -->
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="password" name="password" required autocomplete="off">
                            <button type="button" class="toggle-password" onclick="togglePassword()">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <span class="sr-only">Show password</span>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-signin">
                        Sign in
                    </button>
                </form>

                <!-- Reset Password Link -->
                <div class="reset-link">
                    <a href="#">Reset Password</a>
                </div>
            </div>
        </div>

        <div class="login-footer">
            &copy; <?php echo date('Y'); ?> HR & Finance Dashboard
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Optional: Change the eye icon style
            const button = document.querySelector('.toggle-password');
            if (type === 'text') {
                button.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0z"></path><circle cx="12" cy="12" r="3"></circle><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-3.8 0-7.2-2.1-9-5.4a10.07 10.07 0 0 1 3.06-3.06"></path><path d="M7.06 7.06A10.07 10.07 0 0 1 12 4c3.8 0 7.2 2.1 9 5.4a10.07 10.07 0 0 1-3.06 3.06"></path><line x1="2" y1="2" x2="22" y2="22"></line></svg>`;
            } else {
                button.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0z"></path><circle cx="12" cy="12" r="3"></circle></svg>`;
            }
        }
    </script>
</body>

</html>