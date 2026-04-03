<?php
session_start();
header('Content-Type: application/json');

if (isset($_SESSION['user_id'])) {
    $_SESSION['LAST_ACTIVITY'] = time();
    echo json_encode(['status' => 'active']);
} else {
    echo json_encode(['status' => 'inactive']);
}
?>