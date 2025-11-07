<?php
/**
 * SEAL / Data Anonymizer
 * Purpose: Anonymize sensitive ILL request details older than 5 years (60 months).
 * Location: /var/www/seal_wp_script/seal_anonymizer.php
 */

set_time_limit(900); // 15-minute safety limit

// === Connect to Database ===
require '/var/www/seal_wp_script/seal_db.inc';
$db = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$db) {
    error_log(date('c') . " - DB connection failed in seal_anonymizer.php: " . mysqli_connect_error() . "\n", 3, '/var/log/seal_illiad_cron.log');
    die("DB connection failed\n");
}
$db->set_charset("utf8mb4");

error_log(date('c') . " - ===== Starting seal_anonymizer.php =====\n", 3, '/var/log/seal_illiad_cron.log');

// === Compute cutoff date (60 months ago) ===
$currentDate = new DateTime();
$currentDate->sub(new DateInterval('P60M')); // subtract 60 months
$ydate = $currentDate->format('Y-m-d');
error_log(date('c') . " - Cutoff date for anonymization: $ydate\n", 3, '/var/log/seal_illiad_cron.log');

// === Preview query ===
$testsqlselect = "SELECT COUNT(*) AS total FROM `$sealSTAT` WHERE `Timestamp` < '$ydate'";
$resCount = mysqli_query($db, $testsqlselect);
$countRow = $resCount ? mysqli_fetch_assoc($resCount) : ['total' => 0];
$totalToAnonymize = (int)$countRow['total'];
echo "Found $totalToAnonymize records older than $ydate to anonymize\n";
error_log(date('c') . " - Found $totalToAnonymize records older than $ydate to anonymize\n", 3, '/var/log/seal_illiad_cron.log');

// === Perform anonymization ===
$sqlupdate = "
    UPDATE `$sealSTAT`
    SET
        `Title`        = '',
        `Author`       = '',
        `pubdate`      = '',
        `reqisbn`      = '',
        `reqissn`      = '',
        `itype`        = '',
        `Call Number`  = '',
        `article`      = '',
        `needbydate`   = '',
        `patronnote`   = '',
        `DueDate`      = '',
        `reqNOTE`      = ''
    WHERE `Timestamp` < '$ydate'
";

if (mysqli_query($db, $sqlupdate)) {
    $affected = mysqli_affected_rows($db);
    echo "Anonymization complete — $affected records updated.\n";
    error_log(date('c') . " - seal_anonymizer.php | Anonymization succeeded for $affected records\n", 3, '/var/log/seal_illiad_cron.log');
} else {
    $err = mysqli_error($db);
    echo "Update failed: $err\n";
    error_log(date('c') . " - seal_anonymizer.php | Update failed: $err\n", 3, '/var/log/seal_illiad_cron.log');

    // Notify NOC if DB update fails
    $headers  = "From: SEAL <donotreply@senylrc.org>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $to       = "noc@senylrc.org";
    $subject  = "SEAL Anonymizer Failure";
    $message  = "SEAL Anonymizer failed to update database on " . date('Y-m-d H:i:s') . "<br><br>
                 Error details:<br><pre>$err</pre>";
    mail($to, $subject, $message, $headers, "-f donotreply@senylrc.org");
}

mysqli_close($db);
error_log(date('c') . " - ===== Completed seal_anonymizer.php =====\n", 3, '/var/log/seal_illiad_cron.log');
root@MainWeb2025:/var/www/seal_wp_script# 
