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
            if ($user['role'] === 'hr') {
                header('Location: admin/master_data.php');
            } elseif ($user['role'] === 'manager') {
                header('Location: director/manager_dashboard.php');
            } elseif ($user['role'] === 'director') {
                // Check if it's a Managing Director (admin director)
                if ($username === 'director_admin') {
                    header('Location: director/md_dashboard.php');
                } else {
                    header('Location: director/director_dashboard.php');
                }
            } elseif ($user['role'] === 'md') {
                header('Location: director/md_dashboard.php');
            } else {
                header('Location: index.php');
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

        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 400px;
            margin: 1.5rem;
        }

        .login-card {
            background: rgba(255, 255, 255);
            backdrop-filter: blur(10px);
            border-radius: 1.4rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0px 20px 40px rgba(0, 0, 0, 0.2), 0px 4px 12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0px 25px 50px rgba(0, 0, 0, 0.25);
        }

        .card-content {
            padding: 2rem;
        }

        .logo-container {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .logo {
            max-width: 180px;
            height: auto;
        }

        .title {
            font-size: 1.25rem;
            font-weight: 600;
            text-align: center;
            color: #111827;
            margin-bottom: 1.5rem;
            letter-spacing: -0.01em;
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

        .input-wrapper {
            position: relative;
        }

        .form-group input {
            width: 100%;
            padding: 0.625rem 0.875rem;
            font-size: 0.875rem;
            line-height: 1.5;
            color: #111827;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
            font-family: inherit;
        }

        .form-group input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
            background-color: #ffffff;
        }

        .form-group input::placeholder {
            color: #9ca3af;
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            padding-right: 2.75rem;
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 0;
            transform: translateY(-50%);
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 0.75rem;
            background: transparent;
            border: none;
            cursor: pointer;
            color: #9ca3af;
            transition: color 0.2s;
        }

        .toggle-password:hover {
            color: #10b981;
        }

        .toggle-password svg {
            width: 1.125rem;
            height: 1.125rem;
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border-width: 0;
        }

        .btn-signin {
            width: 100%;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            font-weight: 600;
            padding: 0.625rem 1rem;
            font-size: 0.875rem;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: inherit;
            margin-top: 0.5rem;
            position: relative;
            overflow: hidden;
        }

        .btn-signin::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn-signin:hover::before {
            left: 100%;
        }

        .btn-signin:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-signin:active {
            transform: translateY(0);
        }

        .btn-signin:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.3);
        }

        .error-message {
            background-color: #fee2e2;
            border-left: 4px solid #dc2626;
            color: #991b1b;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.25rem;
            text-align: left;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .reset-link {
            margin-top: 1rem;
            text-align: center;
        }

        .reset-link a {
            color: #10b981;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: color 0.2s;
        }

        .reset-link a:hover {
            color: #059669;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.8);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 480px) {
            .login-container {
                margin: 1rem;
            }
            .card-content {
                padding: 1.5rem;
            }
            .title {
                font-size: 1.125rem;
            }
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

        .login-card {
            animation: fadeInUp 0.5s ease-out;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="card-content">
                <div class="logo-container">
                    <img src="assets/images/logo.png" alt="Online Data Icon" height="75">
                </div>

                <h2 class="title">Welcome to HR & Finance Dashboard</h2>

                <?php if ($error): ?>
                    <div class="error-message">
                        ⚠️ <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required autocomplete="off" placeholder="Enter your username">
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="password" name="password" required autocomplete="off" placeholder="Enter your password">
                            <button type="button" class="toggle-password" onclick="togglePassword()" aria-label="Show password">
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

                <div class="reset-link">
                    <a href="#">Forgot password?</a>
                </div>
            </div>
        </div>

        <div class="login-footer">
            &copy; <?php echo date('Y'); ?> HR & Finance Dashboard. All rights reserved.
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            const button = document.querySelector('.toggle-password');
            if (type === 'text') {
                button.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0z"></path><circle cx="12" cy="12" r="3"></circle><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-3.8 0-7.2-2.1-9-5.4a10.07 10.07 0 0 1 3.06-3.06"></path><path d="M7.06 7.06A10.07 10.07 0 0 1 12 4c3.8 0 7.2 2.1 9 5.4a10.07 10.07 0 0 1-3.06 3.06"></path><line x1="2" y1="2" x2="22" y2="22"></line></svg>`;
                button.setAttribute('aria-label', 'Hide password');
            } else {
                button.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0z"></path><circle cx="12" cy="12" r="3"></circle></svg>`;
                button.setAttribute('aria-label', 'Show password');
            }
        }

        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const form = document.querySelector('form');
                if (form) {
                    form.submit();
                }
            }
        });
    </script>
</body>

</html>