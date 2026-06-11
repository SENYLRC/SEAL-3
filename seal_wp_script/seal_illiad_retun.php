<?php
/**
 * SEAL / ILLiad Return-Finished Updater
 * Purpose: Poll ILLiad API and mark requests finished/returned in SEAL.
 * Location: /var/www/seal_wp_script/seal_illiad_return.php
 */

set_time_limit(1800); // 30-minute max runtime

// === DB connect ===
require '/var/www/seal_wp_script/seal_db.inc';
$db = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$db) {
    error_log(date('c') . " - DB connection failed: " . mysqli_connect_error() . "\n", 3, '/var/log/seal_illiad_cron.log');
    die("DB connection failed\n");
}
$db->set_charset("utf8mb4");

error_log(date('c') . " - ===== Starting seal_illiad_return.php =====\n", 3, '/var/log/seal_illiad_cron.log');

// === Select eligible requests ===
$sqlselect = "
SELECT *
FROM `$sealSTAT`
WHERE (
        (`IlliadStatus` LIKE '%Awaiting%'
         OR `IlliadStatus` LIKE '%Review%'
         OR `IlliadStatus` LIKE '%Shipped%'
         OR `IlliadStatus` LIKE '%Switch%')
        AND `IlliadStatus` NOT LIKE '%Cancelled by ILL Staff%'
        AND `Title` <> ''
        AND `IlliadTransID` <> ''
    )
  AND `TimeStamp` >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH);
";

$retval = mysqli_query($db, $sqlselect);
if (!$retval) {
    error_log(date('c') . " - Query failed: " . mysqli_error($db) . "\n", 3, '/var/log/seal_illiad_cron.log');
    exit;
}
$count = mysqli_num_rows($retval);
echo "Found $count requests to check for return/finished\n";
error_log(date('c') . " - Found $count requests to process for return/finished\n", 3, '/var/log/seal_illiad_cron.log');

while ($row = mysqli_fetch_assoc($retval)) {
    $Illiadid        = trim((string)$row["IlliadTransID"]);
    $sqlidnumb       = (int)$row["index"];
    $reqnumb         = trim((string)$row['illNUB']);
    $destloc         = trim((string)$row['Destination']);
    $title           = trim((string)$row['Title']);
    $requesterEMAIL  = trim((string)$row['requesterEMAIL']);

    if ($Illiadid === '') continue;

    // === Destination info ===
    $destloc_safe = mysqli_real_escape_string($db, $destloc);
    $sqlDest = "
        SELECT `APIkey`, `IlliadURL`, `Name`, `ill_email`
        FROM `$sealLIB`
        WHERE `loc` LIKE '$destloc_safe'
        LIMIT 1
    ";
    $resDest = mysqli_query($db, $sqlDest);
    if (!$resDest || mysqli_num_rows($resDest) === 0) {
        error_log(date('c') . " - Missing destination record for loc=$destloc (index $sqlidnumb)\n", 3, '/var/log/seal_illiad_cron.log');
        continue;
    }
    $rowdest     = mysqli_fetch_assoc($resDest);
    $destlibname = $rowdest['Name'] ?? '';
    $apikey      = $rowdest['APIkey'] ?? '';
    $illiadURL   = $rowdest['IlliadURL'] ?? '';

    // === Build and call ILLiad API ===
    $url = rtrim($illiadURL, '/') . '/' . rawurlencode($Illiadid);
    $cmd = "curl -s -H 'ApiKey: " . escapeshellcmd($apikey) . "' '" . escapeshellcmd($url) . "'";
    $output = shell_exec($cmd);
    $data = json_decode($output, true);

    if (!is_array($data)) {
        error_log(date('c') . " - Invalid JSON from ILLiad for $Illiadid | URL=$url | Output=" . substr((string)$output, 0, 200) . "\n", 3, '/var/log/seal_illiad_cron.log');
        continue;
    }

    $status  = $data['TransactionStatus'] ?? '';
    $dueDate = $data['DueDate'] ?? '';
    if ($dueDate) $dueDate = strstr($dueDate, 'T', true);
    $logprefix = "ILL#$reqnumb ($destlibname / index $sqlidnumb)";
    error_log(date('c') . " - Checking $logprefix | Status: $status | DueDate: $dueDate\n", 3, '/var/log/seal_illiad_cron.log');

    // === Detect finished/returned states ===
    $status_lower = strtolower($status);
    $is_finished  =
        strpos($status_lower, 'request finished') !== false ||
        strpos($status_lower, 'checked in') !== false ||
        strpos($status_lower, 'returned') !== false;

    if ($is_finished) {
        $status_sql = mysqli_real_escape_string($db, $status);
        $sqlupdate  = "
            UPDATE `$sealSTAT`
            SET
                `checkinAccount` = 'ILLiad',
                `returnAccount`  = 'ILLiad',
                `IlliadStatus`   = '$status_sql'
            WHERE `index` = $sqlidnumb
        ";

        if (mysqli_query($db, $sqlupdate)) {
            error_log(date('c') . " - $logprefix | Marked as finished/returned ($status)\n", 3, '/var/log/seal_illiad_cron.log');
        } else {
            $err = mysqli_error($db);
            error_log(date('c') . " - $logprefix | DB update failed: $err\n", 3, '/var/log/seal_illiad_cron.log');
            // Notify NOC on DB failure
            $headers  = "From: SEAL <donotreply@senylrc.org>\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $to       = "noc@senylrc.org";
            $subject  = "SEAL Database Update Failure";
            $message  = "SEAL could not update ILLiad status for:<br>
                         ILL#: <b>$reqnumb</b><br>
                         Index: <b>$sqlidnumb</b><br>
                         Error: <pre>$err</pre>";
            mail($to, $subject, $message, $headers, "-f donotreply@senylrc.org");
        }
        continue;
    }

    // Not finished yet — just log
    error_log(date('c') . " - $logprefix | Still active (status=$status)\n", 3, '/var/log/seal_illiad_cron.log');
}

// === Close connection ===
mysqli_close($db);
error_log(date('c') . " - ===== Completed seal_illiad_return.php =====\n", 3, '/var/log/seal_illiad_cron.log');
echo "Return/finished processing complete.\n";
