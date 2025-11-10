<?php
// ==========================================================
// export_csv.php — Forced File Download for SEAL
// ==========================================================

// --- Kill any existing output buffers ---
while (ob_get_level()) {
    ob_end_clean();
}

// --- Disable compression ---
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', 1);
}
ini_set('zlib.output_compression', 0);
ini_set('output_buffering', 0);
header_remove();

// --- Start session early (if needed) ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==========================================================
// Minimal WordPress boot (for auth only)
// ==========================================================
define('SHORTINIT', true);
require_once('/var/www/wpSEAL/wp-load.php');
wp_cookie_constants();
require_once(ABSPATH . WPINC . '/pluggable.php');

// ==========================================================
// Access Control
// ==========================================================
if (!is_user_logged_in()) {
    header('HTTP/1.1 403 Forbidden');
    die("Access Denied: Login required.");
}

$current_user  = wp_get_current_user();
$user_roles    = (array)$current_user->roles;
$allowed_roles = ['administrator', 'libstaff', 'libsys'];

if (!array_intersect($allowed_roles, $user_roles)) {
    header('HTTP/1.1 403 Forbidden');
    die("Access Denied: You do not have permission to export data.");
}

// ==========================================================
// Connect to Database
// ==========================================================
require '/var/www/seal_wp_script/seal_function.php';
require '/var/www/seal_wp_script/seal_db.inc';

$db = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$db) {
    header('HTTP/1.1 500 Internal Server Error');
    die("Database connection failed.");
}
mysqli_set_charset($db, 'utf8mb4');

// ==========================================================
// Build Query
// ==========================================================
$sessionQuery = $_SESSION['query2'] ?? null;
if ($sessionQuery && stripos($sessionQuery, 'select') === 0) {
    $allsqlresults = preg_replace('/\s+LIMIT\s+\d+(\s*,\s*\d+)?\s*;?$/i', '', $sessionQuery);
} else {
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
    die("Invalid query: " . mysqli_error($db));
}

// ==========================================================
// Prepare download headers — absolutely first output
// ==========================================================
$filename = "seal_export_" . date('Y-m-d_H-i-s') . ".csv";
header("Content-Description: File Transfer");
header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Content-Transfer-Encoding: binary");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Expires: 0");
header("Pragma: public");
flush(); // Force headers out immediately

// ==========================================================
// Stream CSV directly
// ==========================================================
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

// Data rows
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

fclose($fp);
mysqli_close($db);
exit;
?>
