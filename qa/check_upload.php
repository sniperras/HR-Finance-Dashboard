<?php
// check_upload.php - Diagnostic script
header('Content-Type: text/plain');

echo "=== Upload Diagnostic ===\n\n";

// Check PHP settings
echo "PHP Version: " . PHP_VERSION . "\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "max_execution_time: " . ini_get('max_execution_time') . "\n\n";

// Check directories
$qaDir = __DIR__;
echo "QA Directory: $qaDir\n";
echo "QA Directory writable: " . (is_writable($qaDir) ? 'Yes' : 'No') . "\n";

$uploadsDir = __DIR__ . '/../uploads/';
echo "Uploads Directory: $uploadsDir\n";
if (!file_exists($uploadsDir)) {
    echo "Uploads directory does not exist. Creating...\n";
    mkdir($uploadsDir, 0777, true);
    echo "Created: " . (file_exists($uploadsDir) ? 'Yes' : 'No') . "\n";
}
echo "Uploads Directory writable: " . (is_writable($uploadsDir) ? 'Yes' : 'No') . "\n";

$logsDir = __DIR__ . '/../logs/';
echo "Logs Directory: $logsDir\n";
if (!file_exists($logsDir)) {
    echo "Logs directory does not exist. Creating...\n";
    mkdir($logsDir, 0777, true);
    echo "Created: " . (file_exists($logsDir) ? 'Yes' : 'No') . "\n";
}

// Check Composer autoloader
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
echo "\nComposer autoload: $vendorAutoload\n";
echo "Exists: " . (file_exists($vendorAutoload) ? 'Yes' : 'No') . "\n";

if (!file_exists($vendorAutoload)) {
    echo "\n*** PhpSpreadsheet not found! Run: composer require phpoffice/phpspreadsheet ***\n";
}

echo "\n=== End Diagnostic ===\n";
