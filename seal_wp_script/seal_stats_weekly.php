<?php

/**
 * SEAL / Weekly Stats Email
 * ------------------------------------------------------------
 * Purpose:
 *   Email SEAL activity stats for the last 7 days to staff.
 *
 * Location: /var/www/seal_wp_script/seal_weekly_stats.php
 * Logs:     /var/log/seal_illiad_cron.log
 */

set_time_limit(300);
$logfile = '/var/log/seal_illiad_cron.log';
error_log(date('c') . " - ===== Starting seal_weekly_stats.php =====\n", 3, $logfile);

// ----------------------------------------------------
//  DB Connect
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
//  Date Window
// ----------------------------------------------------
$startdate = date('Y-m-d', strtotime('-7 days'));
$curdate   = date('Y-m-d');
// To use “from start of service”, uncomment next line:
// $startdate = '2016-07-01';

$startTs = $startdate . ' 00:00:00';
$endTs   = $curdate   . ' 23:59:59';

error_log(date('c') . " - Stats window: $startTs → $endTs\n", 3, $logfile);

// ----------------------------------------------------
//  Helper for COUNT(*) queries
// ----------------------------------------------------
function count_query(mysqli $db, string $sql): int
{
    $res = mysqli_query($db, $sql);
    if (!$res) {
        return -1;
    }
    $row = mysqli_fetch_assoc($res);
    return (int)($row['cnt'] ?? 0);
}

// ----------------------------------------------------
//  Counts (use COUNT(*) for performance)
// ----------------------------------------------------
$total_req = count_query($db, "SELECT COUNT(*) AS cnt FROM `$sealSTAT` WHERE `Timestamp` BETWEEN '$startTs' AND '$endTs'");
$filled    = count_query($db, "SELECT COUNT(*) AS cnt FROM `$sealSTAT` WHERE `Timestamp` BETWEEN '$startTs' AND '$endTs' AND `Fill`=1");
$notfill   = count_query($db, "SELECT COUNT(*) AS cnt FROM `$sealSTAT` WHERE `Timestamp` BETWEEN '$startTs' AND '$endTs' AND `Fill`=0");
$noanswer  = count_query($db, "SELECT COUNT(*) AS cnt FROM `$sealSTAT` WHERE `Timestamp` BETWEEN '$startTs' AND '$endTs' AND `Fill`=3");
$expired   = count_query($db, "SELECT COUNT(*) AS cnt FROM `$sealSTAT` WHERE `Timestamp` BETWEEN '$startTs' AND '$endTs' AND `Fill`=4");
$canceled  = count_query($db, "SELECT COUNT(*) AS cnt FROM `$sealSTAT` WHERE `Timestamp` BETWEEN '$startTs' AND '$endTs' AND `Fill`=6");

$libs_active        = count_query($db, "SELECT COUNT(*) AS cnt FROM `$sealLIB` WHERE `participant`=1 AND `suspend`=0");
$libs_suspended     = count_query($db, "SELECT COUNT(*) AS cnt FROM `$sealLIB` WHERE `participant`=1 AND `suspend`=1");
$libs_nonparticipant = count_query($db, "SELECT COUNT(*) AS cnt FROM `$sealLIB` WHERE `participant`=0 AND `suspend`=0");

// sanity log on any count error
$counts = [
  'total_req' => $total_req,'filled' => $filled,'notfill' => $notfill,'noanswer' => $noanswer,
  'expired' => $expired,'canceled' => $canceled,'libs_active' => $libs_active,
  'libs_suspended' => $libs_suspended,'libs_nonparticipant' => $libs_nonparticipant
];
foreach ($counts as $k => $v) {
    if ($v < 0) {
        error_log(date('c') . " - Count error for $k (query failed)\n", 3, $logfile);
    }
}

// ----------------------------------------------------
//  Derived metrics
// ----------------------------------------------------
$fill_rate   = ($total_req > 0) ? sprintf('%.1f%%', ($filled / $total_req) * 100) : '0.0%';
$expire_rate = ($total_req > 0) ? sprintf('%.1f%%', ($expired / $total_req) * 100) : '0.0%';

// ----------------------------------------------------
//  Build Email
// ----------------------------------------------------
$subject = "SEAL Stats — $startdate to $curdate";
$headers  = "From: SENYLRC SEAL <donotreply@senylrc.org>\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";

$messagedest = "
SEAL stats from <b>" . htmlspecialchars($startdate) . "</b> to <b>" . htmlspecialchars($curdate) . "</b><br><br>

<b>Requests</b><br>
&nbsp;&nbsp;&bull; Request Filled: {$filled}<br>
&nbsp;&nbsp;&bull; Request Not Filled: {$notfill}<br>
&nbsp;&nbsp;&bull; Request Expired: {$expired}<br>
&nbsp;&nbsp;&bull; Request Not Answered: {$noanswer}<br>
&nbsp;&nbsp;&bull; Request Canceled: {$canceled}<br>
&nbsp;&nbsp;&bull; <b>Total Requests:</b> {$total_req}<br>
&nbsp;&nbsp;&bull; Fill Rate: {$fill_rate}<br>
&nbsp;&nbsp;&bull; Expire Rate: {$expire_rate}<br>
<br>
<b>Libraries</b><br>
&nbsp;&nbsp;&bull; Active Libraries: {$libs_active}<br>
&nbsp;&nbsp;&bull; Active Libraries Suspended: {$libs_suspended}<br>
&nbsp;&nbsp;&bull; Non-Participating Libraries: {$libs_nonparticipant}<br>
<br>
<small>Window: {$startTs} to {$endTs}</small>
";

// ----------------------------------------------------
//  Send Email
// ----------------------------------------------------
$email_to = "ill@senylrc.org";
$sent = mail($email_to, $subject, $messagedest, $headers, "-f donotreply@senylrc.org");

if ($sent) {
    error_log(date('c') . " - Weekly stats email sent to $email_to\n", 3, $logfile);
} else {
    error_log(date('c') . " - FAILED to send weekly stats email to $email_to\n", 3, $logfile);
    // Optional: alert NOC on failure
    $noc_headers  = "From: SEAL <donotreply@senylrc.org>\r\n";
    $noc_headers .= "MIME-Version: 1.0\r\n";
    $noc_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    @mail("noc@senylrc.org", "SEAL Weekly Stats: Send Failure", "Failed to send stats to $email_to for $startdate to $curdate.", $noc_headers, "-f donotreply@senylrc.org");
}

mysqli_close($db);
error_log(date('c') . " - ===== Completed seal_weekly_stats.php =====\n", 3, $logfile);
echo "Weekly stats completed.\n";
