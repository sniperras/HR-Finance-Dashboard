<?php
// session_config.php - Include this at the VERY TOP of every page BEFORE any output
// This file must be included before ANY HTML output

// Suppress session warnings (for shared hosting permission issues)
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

// Set session cookie parameters BEFORE session_start()
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => 0,           // Session cookie (expires when browser closes)
    'path' => '/',             // Available for entire domain
    'domain' => '',            // Current domain only
    'secure' => false,         // Set to false for HTTP (InfinityFree uses HTTP by default)
    'httponly' => true,        // Prevents JavaScript access
    'samesite' => 'Lax'        // Allows same-site navigation
]);

// Start session if not already started, suppress warnings
if (session_status() === PHP_SESSION_NONE) {
    @session_start(); // @ suppresses the permission warning
}

// Regenerate session ID periodically for security
if (!isset($_SESSION['CREATED'])) {
    $_SESSION['CREATED'] = time();
} elseif (time() - $_SESSION['CREATED'] > 1800) {
    @session_regenerate_id(true); // @ suppresses warning
    $_SESSION['CREATED'] = time();
}

// Restore error reporting
error_reporting(E_ALL);
