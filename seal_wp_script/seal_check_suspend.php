<?php
/**
 * SEAL / Suspension Auto-Release Script
 * ------------------------------------------------------------
 * Purpose:
 *   Automatically re-enables libraries whose suspension end date
 *   has passed, and logs daily check activity.
 *
 * Location: /var/www/seal_wp_script/seal_suspend_check.php
 * Logs:     /var/log/seal_illiad_cron.log
 */

set_time_limit(600);
$logfile = '/var/log/seal_illiad_cron.log';
error_log(date('c') . " - ===== Starting seal_suspend_check.php =====\n", 3, $logfile);

// ----------------------------------------------------
//  Connect to database
// ----------------------------------------------------
require '/var/www/seal_wp_script/seal_db.inc';
$db = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$db) {
    $err = mysqli_connect_error();
    error_log(date('c') . " - DB connection failed: $err\n", 3, $logfile);
    die("DB connection failed\n");
}
$db->set_charset("utf8mb4");

// ----------------------------------------------------
//  Query suspended participants
// ----------------------------------------------------
$today = date("Y-m-d");
$sqlselect = "
    SELECT `loc`, `ill_email`, `SuspendDateEnd`, `SuspendCheckDate`
    FROM `$sealLIB`
    WHERE `participant` = 1 AND `suspend` = 1
";
$result = mysqli_query($db, $sqlselect);
if (!$result) {
    $err = mysqli_error($db);
    error_log(date('c') . " - Query failed: $err\n", 3, $logfile);
    die("Query failed\n");
}

$count = mysqli_num_rows($result);
echo "Checking $count suspended libraries...\n";
error_log(date('c') . " - Checking $count suspended libraries\n", 3, $logfile);

// ----------------------------------------------------
//  Process each suspended library
// ----------------------------------------------------
while ($row = mysqli_fetch_assoc($result)) {
    $loc             = $row["loc"];
    $illemail        = $row["ill_email"];
    $suspendEndDate  = $row["SuspendDateEnd"];
    $suspendCheck    = $row["SuspendCheckDate"];

    $logprefix = "LOC $loc";

    // --- Expired suspension ---
    if (!empty($suspendEndDate) && $today > $suspendEndDate) {
        $updatesql = "
            UPDATE `$sealLIB`
            SET `suspend` = '0',
                `SuspendDateEnd` = NULL,
                `SuspendCheckDate` = '$today'
            WHERE `loc` = '$loc'
        ";
        if (mysqli_query($db, $updatesql)) {
            echo "Ended suspension for $loc\n";
            error_log(date('c') . " - $logprefix | Suspension ended\n", 3, $logfile);
        } else {
            $err = mysqli_error($db);
            error_log(date('c') . " - $logprefix | Update failed: $err\n", 3, $logfile);
            // Optional email alert on error
            $headers  = "From: SEAL <donotreply@senylrc.org>\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $subject  = "SEAL Suspension Update Failed — $loc";
            $message  = "Database update failed for $loc:<br><pre>$err</pre>";
            mail("noc@senylrc.org", $subject, $message, $headers, "-f donotreply@senylrc.org");
        }
    }
    // --- Still suspended ---
    else {
        $updatesql = "
            UPDATE `$sealLIB`
            SET `SuspendCheckDate` = '$today'
            WHERE `loc` = '$loc'
        ";
        if (mysqli_query($db, $updatesql)) {
            echo "Checked (still suspended): $loc\n";
            error_log(date('c') . " - $logprefix | Still suspended, check date updated\n", 3, $logfile);
        } else {
            $err = mysqli_error($db);
            error_log(date('c') . " - $logprefix | Failed to update check date: $err\n", 3, $logfile);
        }
    }
}

mysqli_close($db);
error_log(date('c') . " - ===== Completed seal_suspend_check.php =====\n", 3, $logfile);
echo "Suspension check completed.\n";
?>
