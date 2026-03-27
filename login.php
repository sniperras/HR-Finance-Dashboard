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
    <link rel="stylesheet" href="css/style.css">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--dark-bg) 0%, var(--medium-bg) 100%);
        }
        
        .login-box {
            background: var(--medium-bg);
            padding: 2rem;
            border-radius: 15px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .login-box h2 {
            color: var(--accent);
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .error-message {
            background: var(--danger);
            color: white;
            padding: 0.75rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--light-bg);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h2>HR & Finance Dashboard</h2>
            <h3 style="text-align: center; margin-bottom: 1rem;">Login</h3>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn" style="width: 100%;">Login</button>
            </form>
            
           
        </div>
    </div>
</body>
</html>