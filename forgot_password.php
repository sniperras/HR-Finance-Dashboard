<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once 'session_config.php';
require_once 'config/database.php';

// Load PHPMailer
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';
require_once 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Handle POST request with redirect to prevent resubmission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginInput = trim($_POST['login_input'] ?? '');
    $error = '';
    $success = '';

    if (empty($loginInput)) {
        $error = 'Please enter your username or OID';
        $_SESSION['fp_error'] = $error;
    } else {
        $conn = getConnection();

        // Check if login input is numeric (OID) or text (username)
        $isOID = is_numeric($loginInput);

        if ($isOID) {
            $stmt = $conn->prepare("SELECT id, username, oid, full_name, email FROM users WHERE oid = ?");
            $stmt->bind_param("s", $loginInput);
        } else {
            $stmt = $conn->prepare("SELECT id, username, oid, full_name, email FROM users WHERE username = ?");
            $stmt->bind_param("s", $loginInput);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            // Check if user has email
            if (empty($user['email'])) {
                $error = 'No email address associated with this account. Please contact administrator.';
                $_SESSION['fp_error'] = $error;
            } else {
                // Generate temporary password (16 characters)
                $tempPassword = bin2hex(random_bytes(8));
                $hashedTempPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
                $expiryTime = date('Y-m-d H:i:s', strtotime('+1 day'));

                // Store temporary password in users table
                $updateStmt = $conn->prepare("UPDATE users SET temp_password = ?, temp_password_expiry = ? WHERE id = ?");
                $updateStmt->bind_param("ssi", $hashedTempPassword, $expiryTime, $user['id']);
                $updateStmt->execute();
                $updateStmt->close();

                // Email content
                $to = $user['email'];
                $subject = "Password Reset Request - HR & Finance Dashboard";

                // Email body with proper UTF-8 encoding for emoji
                $message = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px; }
                        .header { background: #4CAF50; color: white; padding: 10px; text-align: center; border-radius: 5px 5px 0 0; }
                        .content { padding: 20px; }
                        .password-box { background: #f4f4f4; padding: 15px; text-align: center; font-size: 20px; font-family: monospace; letter-spacing: 2px; border-radius: 5px; margin: 20px 0; }
                        .footer { font-size: 12px; text-align: center; color: #777; margin-top: 20px; padding-top: 10px; border-top: 1px solid #ddd; }
                        .warning { color: #ff9800; font-weight: bold; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>Password Reset Request</h2>
                        </div>
                        <div class='content'>
                            <p>Dear <strong>" . htmlspecialchars($user['full_name']) . "</strong>,</p>
                            <p>We received a request to reset your password for your HR & Finance Dashboard account.</p>
                            <p>Your temporary password is:</p>
                            <div class='password-box'>
                                <strong>" . $tempPassword . "</strong>
                            </div>
                            <p class='warning'>⚠️ Important Security Information:</p>
                            <ul>
                                <li>This temporary password is valid for <strong>24 hours only</strong>.</li>
                                <li>After logging in with this temporary password, you will be required to change your password.</li>
                                <li>If you did not request this password reset, please ignore this email.</li>
                                <li>For security reasons, do not share this password with anyone.</li>
                            </ul>
                            <p>To reset your password:</p>
                            <ol>
                                <li>Go to the login page: <a href='https://mrodashboard.infinityfree.me/index.php'>https://mrodashboard.infinityfree.me/index.php</a></li>
                                <li>Enter your username/OID and this temporary password</li>
                                <li>You will be redirected to change your password</li>
                                <li>Create a new strong password</li>
                            </ol>
                            <p>If you remember your password, you can continue using it. This temporary password will expire automatically.</p>
                            <p>Best regards,<br>HR & Finance Dashboard Team</p>
                        </div>
                        <div class='footer'>
                            <p>This is an automated message, please do not reply to this email.</p>
                            <p>&copy; " . date('Y') . " HR & Finance Dashboard. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>
                ";

                // Send email using PHPMailer
                $mail = new PHPMailer(true);

                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'nathanaelbizuneh@gmail.com';
                    $mail->Password   = 'raaqanjfgikzqciz';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    $mail->setFrom('nathanaelbizuneh@gmail.com', 'HR & Finance Dashboard');
                    $mail->addAddress($to, $user['full_name']);
                    $mail->addReplyTo('nathanaelbizuneh@gmail.com', 'HR & Finance Dashboard');

                    $mail->isHTML(true);
                    $mail->CharSet = 'UTF-8';
                    $mail->Subject = $subject;
                    $mail->Body    = $message;
                    $mail->AltBody = strip_tags($message);

                    $mail->send();
                    $_SESSION['fp_success'] = "A temporary password has been sent to your email address: " . htmlspecialchars($user['email']) . ". The password is valid for 24 hours.<br><br>
                    <span style='color: #ff9800; font-weight: bold;'>⚠️ SECURITY NOTICE:</span> Please ensure the email is sent from <strong>nathanaelbizuneh@gmail.com</strong>. If you receive a password reset email from any other email address, DO NOT click any links inside and report it immediately to the IT Team.";
                } catch (Exception $e) {
                    $_SESSION['fp_error'] = "Failed to send email. Mailer Error: " . $mail->ErrorInfo;
                }
            }
        } else {
            $_SESSION['fp_error'] = 'No account found with the provided username or OID.';
        }

        $stmt->close();
        $conn->close();
    }

    // Redirect to prevent form resubmission
    header('Location: forgot_password.php');
    exit();
}

// Get session messages after redirect
$error = isset($_SESSION['fp_error']) ? $_SESSION['fp_error'] : '';
$success = isset($_SESSION['fp_success']) ? $_SESSION['fp_success'] : '';
unset($_SESSION['fp_error']);
unset($_SESSION['fp_success']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - HR & Finance Dashboard</title>
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
            word-wrap: break-word;
        }

        .success-message {
            background-color: #d1fae5;
            border-left: 4px solid #10b981;
            color: #065f46;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.25rem;
            font-size: 0.875rem;
            line-height: 1.5;
        }

        .success-message strong {
            font-weight: bold;
        }

        .success-message span {
            display: inline-block;
            margin-top: 0.5rem;
        }

        .back-link {
            margin-top: 1rem;
            text-align: center;
        }

        .back-link a {
            color: #10b981;
            text-decoration: none;
            font-size: 0.875rem;
        }

        .back-link a:hover {
            text-decoration: underline;
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

                <h2 class="title">Forgot Password?</h2>
                <div class="subtitle">Enter your username or OID to receive a temporary password</div>

                <?php if ($error): ?>
                    <div class="error-message">
                        ⚠️ <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="success-message">
                        ✓ <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="login_input">Username or OID</label>
                        <input type="text" id="login_input" name="login_input" required placeholder="Enter your username or OID">
                    </div>

                    <button type="submit" class="btn">Send Reset Password</button>
                </form>

                <div class="back-link">
                    <a href="index.php">← Back to Login</a>
                </div>
            </div>
        </div>

        <div class="login-footer">
            &copy; <?php echo date('Y'); ?> Ethiopian Airlines. All rights reserved.
        </div>
    </div>
</body>

</html>