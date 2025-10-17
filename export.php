<?php
// ==========================================================
// export.php — Secure CSV Export for Authorized Roles Only
// ==========================================================

// 🚫 Absolutely no blank lines before this block
if (ob_get_length()) ob_end_clean();
header_remove();

// Ensure we can use WordPress functions
require_once('/var/www/wpSEAL/wp-load.php');

// Restrict to logged-in users
if (!is_user_logged_in()) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/plain; charset=utf-8');
    echo "Access Denied: Login required.";
    exit;
}

// Get current user info
$current_user = wp_get_current_user();
$user_roles   = (array)$current_user->roles;

// ✅ Allowed roles: Administrator, Library Staff, Lib Systems Staff
$allowed_roles = ['administrator', 'libstaff', 'libsys'];

if (!array_intersect($allowed_roles, $user_roles)) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/plain; charset=utf-8');
    echo "Access Denied: You do not have permission to export data.";
    exit;
}

// ==========================================================
// Initialize environment and database connection
// ==========================================================
require '/var/www/seal_wp_script/seal_function.php';
require '/var/www/seal_wp_script/seal_db.inc';

// Connect securely
$db = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$db) {
    header('HTTP/1.1 500 Internal Server Error');
    echo "Database connection failed.";
    exit;
}
mysqli_set_charset($db, 'utf8mb4');

// ==========================================================
// Build Export Query
// ==========================================================
session_start();
$sessionQuery = $_SESSION['query2'] ?? null;

if ($sessionQuery && stripos($sessionQuery, 'select') === 0) {
    // Remove any LIMIT clause to export all results
    $allsqlresults = preg_replace('/\s+LIMIT\s+\d+(\s*,\s*\d+)?\s*;?$/i', '', $sessionQuery);
} else {
    // Default export of all libraries
    $allsqlresults = "
        SELECT
            recnum,
            Name,
            alias,
            ill_email,
            phone,
            address1,
            address2,
            address3,
            `system`,
            suspend,
            SuspendDateEnd
        FROM `$sealLIB`
        ORDER BY `Name` ASC
    ";
}

$result = mysqli_query($db, $allsqlresults);
if (!$result) {
    header('HTTP/1.1 500 Internal Server Error');
    echo "Invalid query: " . mysqli_error($db);
    exit;
}

// ==========================================================
// Send CSV headers and output cleanly
// ==========================================================
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="seal_export.csv"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// Disable compression & buffering to avoid corrupt output
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', 1);
}
ini_set('zlib.output_compression', 0);
ini_set('output_buffering', 0);
set_time_limit(0);

// Open output stream
$fp = fopen('php://output', 'w');

// Header row
fputcsv($fp, [
    'Library Name',
    'Alias',
    'ILL Email',
    'Phone',
    'Address 1',
    'Address 2',
    'Address 3',
    'System',
    'Suspended',
    'Suspend Until'
]);

// Rows
while ($row = mysqli_fetch_assoc($result)) {
    fputcsv($fp, [
        $row['Name'] ?? '',
        $row['alias'] ?? '',
        $row['ill_email'] ?? '',
        $row['phone'] ?? '',
        $row['address1'] ?? '',
        $row['address2'] ?? '',
        $row['address3'] ?? '',
        $row['system'] ?? '',
        $row['suspend'] ?? '',
        $row['SuspendDateEnd'] ?? ''
    ]);
}

// Clean up
fclose($fp);
mysqli_close($db);
exit;
?>