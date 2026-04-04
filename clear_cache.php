<?php
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

require_once 'session_config.php';
require_once './includes/auth.php';
requireRole('it_admin');

$response = ['success' => true, 'message' => '', 'warnings' => ''];

try {
    $messages = [];
    $errors = [];

    // Clear opcache if enabled
    if (function_exists('opcache_reset')) {
        if (@opcache_reset()) {
            $messages[] = 'OPcache cleared successfully';
        } else {
            $errors[] = 'Failed to clear OPcache';
        }
    } else {
        $messages[] = 'OPcache not available on this server';
    }

    // Clear APC/APCu cache if enabled (with error suppression)
    if (function_exists('apc_clear_cache')) {
        @apc_clear_cache();
        @apc_clear_cache('user');
        $messages[] = 'APC cache cleared successfully';
    }

    // Clear APCu cache (alternative to APC)
    if (function_exists('apcu_clear_cache')) {
        @apcu_clear_cache();
        $messages[] = 'APCu cache cleared successfully';
    }

    // Clear session cache
    @session_cache_limiter('');

    // Clear temporary session files (optional - be careful)
    $tempDir = sys_get_temp_dir();
    $cleaned = 0;
    if (is_dir($tempDir) && ($handle = @opendir($tempDir))) {
        while (false !== ($entry = readdir($handle))) {
            if (strpos($entry, 'sess_') === 0) {
                // Don't delete current session
                if ($entry !== 'sess_' . session_id()) {
                    @unlink($tempDir . '/' . $entry);
                    $cleaned++;
                }
            }
        }
        @closedir($handle);
        if ($cleaned > 0) {
            $messages[] = "Cleaned $cleaned expired session files";
        }
    }

    if (empty($errors)) {
        $response['success'] = true;
        $response['message'] = implode(' | ', $messages);
    } else {
        $response['success'] = false;
        $response['message'] = implode(' | ', $errors);
        $response['warnings'] = implode(' | ', $messages);
    }
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
exit();
