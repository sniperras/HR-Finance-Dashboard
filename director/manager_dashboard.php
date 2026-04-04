<?php
require_once '../session_config.php';
require_once '../includes/auth.php';
requireRole('manager');

// Get user info
$fullName = $_SESSION['full_name'] ?? 'Manager';
$username = $_SESSION['username'] ?? '';
$userRole = $_SESSION['user_role'] ?? 'manager';

// Get user's department/section from database
$userDept = '';
$conn = getConnection();
$stmt = $conn->prepare("SELECT section FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $userDept = $row['section'];
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../assets/images/ethiopian_logo.ico">
    <title>Manager Dashboard - Under Development</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', 'Inter', system-ui, sans-serif;
            background: var(--dark-bg, #0F172A);
            color: var(--text-primary, #F1F5F9);
            min-height: 100vh;
        }

        .navbar {
            background: var(--medium-bg, #1E293B);
            padding: 0.8rem 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            border-bottom: 1px solid var(--border-light, #334155);
        }

        .navbar-container {
            max-width: 100%;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .navbar-brand {
            font-size: 1rem;
            font-weight: bold;
            color: var(--accent, #38BDF8);
            text-decoration: none;
        }

        .navbar-menu {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .user-info {
            display: flex;
            gap: 0.8rem;
            align-items: center;
        }

        .user-name {
            color: var(--accent, #38BDF8);
            font-weight: bold;
            font-size: 0.8rem;
        }

        .dept-badge {
            background: var(--accent, #38BDF8);
            color: var(--dark-bg, #0F172A);
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: bold;
        }

        .btn {
            background: var(--accent, #38BDF8);
            color: var(--dark-bg, #0F172A);
            border: none;
            padding: 0.3rem 0.8rem;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.7rem;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn:hover {
            transform: translateY(-1px);
            opacity: 0.9;
        }

        .container {
            max-width: 800px;
            margin: 3rem auto;
            padding: 0 1rem;
        }

        .under-development-card {
            background: var(--card-bg, #1E293B);
            border-radius: 20px;
            padding: 3rem 2rem;
            text-align: center;
            border: 1px solid var(--border-light, #334155);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .construction-icon {
            font-size: 5rem;
            margin-bottom: 1rem;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        h1 {
            color: var(--accent, #38BDF8);
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: var(--text-secondary, #94A3B8);
            margin-bottom: 1.5rem;
            font-size: 1rem;
        }

        .message-box {
            background: rgba(56, 189, 248, 0.1);
            border-left: 4px solid var(--accent, #38BDF8);
            padding: 1rem;
            border-radius: 8px;
            margin: 1.5rem 0;
            text-align: left;
        }

        .message-box p {
            margin: 0.5rem 0;
            font-size: 0.9rem;
        }

        .director-link {
            display: inline-block;
            background: var(--accent, #38BDF8);
            color: var(--dark-bg, #0F172A);
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            margin-top: 1rem;
            transition: all 0.3s;
        }

        .director-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(56, 189, 248, 0.3);
        }

        .info-row {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-light, #334155);
        }

        .info-item {
            text-align: center;
        }

        .info-label {
            font-size: 0.7rem;
            color: var(--text-secondary, #94A3B8);
        }

        .info-value {
            font-size: 0.9rem;
            font-weight: bold;
            color: var(--accent, #38BDF8);
        }

        .theme-toggle {
            background: transparent;
            border: 1px solid var(--accent, #38BDF8);
            color: var(--accent, #38BDF8);
            padding: 0.3rem 0.8rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.7rem;
        }

        .theme-toggle:hover {
            background: var(--accent, #38BDF8);
            color: var(--dark-bg, #0F172A);
        }

        /* Light Theme */
        body.light-theme {
            --dark-bg: #F8FAFC;
            --medium-bg: #FFFFFF;
            --accent: #0284C7;
            --card-bg: #FFFFFF;
            --border-light: #E2E8F0;
            --text-primary: #0F172A;
            --text-secondary: #475569;
        }

        body.light-theme .navbar {
            background: white;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
        }

        @media (max-width: 600px) {
            .under-development-card {
                padding: 2rem 1rem;
            }

            h1 {
                font-size: 1.3rem;
            }

            .info-row {
                flex-direction: column;
                gap: 0.8rem;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="manager_dashboard.php" class="navbar-brand">HR & Finance Dashboard</a>
            <div class="navbar-menu">
                <div class="user-info">
                    <button id="themeToggle" class="theme-toggle">☀️ Light</button>
                    <span class="user-name"><?php echo htmlspecialchars($fullName); ?></span>
                    <span class="dept-badge"><?php echo htmlspecialchars($userDept ?: 'Manager'); ?></span>
                    <a href="../logout.php" class="btn">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="under-development-card">
            <div class="construction-icon">
                🚧
            </div>
            <h1>Under Development</h1>
            <div class="subtitle">Manager Dashboard is coming soon!</div>

            <div class="message-box">
                <p>📢 <strong>Dear <?php echo htmlspecialchars($fullName); ?>,</strong></p>
                <p>Your dedicated Manager Dashboard is currently under development.</p>

                <p>⏳ <strong>Estimated completion:</strong> Coming soon</p>
            </div>

            <div class="info-row">
                <div class="info-item">
                    <div class="info-label">Your Role</div>
                    <div class="info-value">Manager</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Department</div>
                    <div class="info-value"><?php echo htmlspecialchars($userDept ?: 'Not Assigned'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Status</div>
                    <div class="info-value">In Development</div>
                </div>
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

        document.addEventListener('DOMContentLoaded', function() {
            new ThemeManager();
        });

        // Keep session alive by sending heartbeat every 5 minutes
        function keepSessionAlive() {
            fetch('/HRandMDDash/keep_alive.php', {
                method: 'GET',
                cache: 'no-cache'
            }).catch(error => console.log('Session keep-alive failed:', error));
        }

        // Send heartbeat every 5 minutes
        setInterval(keepSessionAlive, 5 * 60 * 1000);
    </script>
</body>

</html>