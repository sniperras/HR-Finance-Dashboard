<?php
session_start();
echo '<pre>';
echo 'Session Data:<br>';
print_r($_SESSION);
echo '</pre>';

if (isset($_SESSION['user_id'])) {
    echo 'User is logged in as: ' . $_SESSION['username'] . '<br>';
    echo 'Role: ' . $_SESSION['user_role'] . '<br>';
} else {
    echo 'No user logged in.<br>';
}
?>