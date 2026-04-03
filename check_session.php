<?php
session_start();
header('Content-Type: text/html');

echo '<h2>Session Debug Info</h2>';
echo '<pre>';
echo 'Session ID: ' . session_id() . '<br>';
echo 'Session Status: ' . session_status() . '<br>';
echo 'Session Save Path: ' . session_save_path() . '<br>';
echo 'Session Cookie Params: <br>';
print_r(session_get_cookie_params());
echo '<br><br>Session Data:<br>';
print_r($_SESSION);
echo '</pre>';

if (isset($_SESSION['user_id'])) {
    echo '<p style="color:green">✅ User is logged in: ' . $_SESSION['username'] . '</p>';
    echo '<a href="logout.php">Logout</a>';
} else {
    echo '<p style="color:red">❌ No user logged in</p>';
    echo '<a href="login.php">Login</a>';
}
?>