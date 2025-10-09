<?php
// This must be the very first line of the file — no blank lines above!
//export.php
ob_end_clean();
if (ob_get_length()) ob_end_clean();
header_remove();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="export.csv"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

session_start();

require '/var/www/seal_wp_script/seal_function.php';
require '/var/www/seal_wp_script/seal_db.inc';

// Table name fallback
if (!isset($sealLIB) || !$sealLIB) {
    $sealLIB = 'sealLIB';
}

// Connect DB
$db = mysqli_connect($dbhost, $dbuser, $dbpass);
if (!$db) {
    header('HTTP/1.1 500 Internal Server Error');
    echo "Database connection failed.";
    exit;
}
mysqli_select_db($db, $dbname);

// Build SQL safely (with proper quoting for `system`)
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
    echo "Invalid query: " . mysqli_error($db);
    exit;
}

// ✅ Force browser to treat it as a CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="export.csv"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// Disable any compression that might add junk
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', 1);
}
ini_set('zlib.output_compression', 0);

// Start clean output stream
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

fclose($fp);
mysqli_close($db);
exit;
?>