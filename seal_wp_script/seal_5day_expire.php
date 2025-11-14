<?php
/**
 * SEAL / 5-Day Expiration Script (Business Day Logic)
 * ------------------------------------------------------------
 * Purpose:
 *   Automatically expire ILL requests that remain unfilled
 *   after 5 business days (excluding weekends & holidays).
 *
 * Location: /var/www/seal_wp_script/seal_5day_expire.php
 * Logs:     /var/log/seal_illiad_cron.log
 */

set_time_limit(1800);
date_default_timezone_set('America/New_York');

$logfile = '/var/log/seal_illiad_cron.log';
error_log(date('c') . " - ===== Starting seal_5day_expire.php =====\n", 3, $logfile);

// ----------------------------------------------------
//  Auto-Updating Holiday Function (Nager.Date + Winter Break)
// ----------------------------------------------------
function getHolidaysAuto($country = 'US') {
    $currentYear = (int)date('Y');
    $month = (int)date('n');
    $years = [$currentYear];
    if ($month === 12) $years[] = $currentYear + 1;

    $holidays = [];
    $logfile = '/var/log/seal_illiad_cron.log';

    foreach ($years as $year) {
        $cacheFile = "/var/www/seal_wp_script/holidays_{$year}.json";
        $url = "https://date.nager.at/api/v3/PublicHolidays/{$year}/{$country}";
        $data = [];

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 604800) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if (!is_array($data)) $data = [];
        } else {
            $context = stream_context_create(['http' => ['timeout' => 10]]);
            $json = @file_get_contents($url, false, $context);
            if ($json === false) {
                error_log(date('c') . " - Holiday fetch failed for $year\n", 3, $logfile);
                $data = file_exists($cacheFile) ? json_decode(file_get_contents($cacheFile), true) : [];
            } else {
                file_put_contents($cacheFile, $json);
                $data = json_decode($json, true);
            }
        }

        foreach ($data as $holiday)
            if (!empty($holiday['date'])) $holidays[] = $holiday['date'];

        // Winter Break (Dec 24 → Jan 1)
        $d1 = new DateTime("$year-12-24");
        $d2 = new DateTime(($year + 1) . "-01-01");
        while ($d1 <= $d2) {
            $holidays[] = $d1->format('Y-m-d');
            $d1->modify('+1 day');
        }
    }

    $holidays = array_unique($holidays);
    sort($holidays);
    return $holidays;
}

// ----------------------------------------------------
//  Business-day helpers (same as 3-day reminder)
// ----------------------------------------------------
function isBusinessDay(string $ymd, array $holidays): bool {
    $dow = (int)date('N', strtotime($ymd)); // 1=Mon .. 7=Sun
    return $dow < 6 && !in_array($ymd, $holidays, true);
}

/**
 * Return date exactly $days business days after $startDate.
 */
function addBusinessDays(string $startDate, int $days, array $holidays, bool $includeStart = false): string {
    $tz   = new DateTimeZone('America/New_York');
    $date = new DateTime($startDate, $tz);

    if (!$includeStart) {
        $date->modify('+1 day');
    }

    $added = 0;
    while ($added < $days) {
        $dstr = $date->format('Y-m-d');
        if (isBusinessDay($dstr, $holidays)) {
            $added++;
            if ($added === $days) {
                break;
            }
        }
        $date->modify('+1 day');
    }

    return $date->format('Y-m-d');
}

// ----------------------------------------------------
//  Connect to database
// ----------------------------------------------------
require '/var/www/seal_wp_script/seal_db.inc';
$db = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$db) {
    error_log(date('c') . " - DB connection failed: " . mysqli_connect_error() . "\n", 3, $logfile);
    die("DB connection failed\n");
}
$db->set_charset("utf8mb4");

// ----------------------------------------------------
//  Load holidays
// ----------------------------------------------------
$holidays = getHolidaysAuto();

// ----------------------------------------------------
//  Select requests that are still unfilled
// ----------------------------------------------------
$sqlselect = "SELECT * FROM `$sealSTAT` WHERE `fill`='3' AND `emailsent` IN ('0','2')";
$res = mysqli_query($db, $sqlselect);
if (!$res) {
    error_log(date('c') . " - Query failed: " . mysqli_error($db) . "\n", 3, $logfile);
    die("Query failed\n");
}

$count = mysqli_num_rows($res);
echo "Found $count open requests\n";
error_log(date('c') . " - Found $count open requests for expiration check\n", 3, $logfile);

// ----------------------------------------------------
//  Process each record
// ----------------------------------------------------
while ($row = mysqli_fetch_assoc($res)) {
    $timestamp   = $row["Timestamp"];
    $illnum      = trim($row["illNUB"]);
    $title       = $row["Title"];
    $requester   = $row["Requester lib"];
    $email       = trim($row["requesterEMAIL"]);
    $destination = trim($row["Destination"]);

    $reqdate = substr($timestamp, 0, 10);
    $today   = date("Y-m-d");

    $expireDate = addBusinessDays($reqdate, 5, $holidays, false);
    $logprefix  = "ILL#$illnum ($destination)";
    error_log(date('c') . " - Checking $logprefix | req=$reqdate expire=$expireDate (5 business days)\n", 3, $logfile);

    if ($today <= $expireDate) continue; // not yet expired

    error_log(date('c') . " - $logprefix | Expiring now after 5 business days\n", 3, $logfile);

    // ----------------------------------------------------
    //  Mark as expired
    // ----------------------------------------------------
    $sqlupdate = "
        UPDATE `$sealSTAT`
        SET `Fill` = '4',
            `emailsent` = '3',
            `responderNOTE` = 'EXPIRE MSG Sent'
        WHERE `illNUB` = '$illnum'
    ";

    if (mysqli_query($db, $sqlupdate)) {
        error_log(date('c') . " - $logprefix | Marked expired (Fill=4, emailsent=3)\n", 3, $logfile);
    } else {
        $err = mysqli_error($db);
        error_log(date('c') . " - $logprefix | DB update failed: $err\n", 3, $logfile);
        continue;
    }

    // ----------------------------------------------------
    //  Email notifications
    // ----------------------------------------------------
    $subject = "ILL Request Expired — ILL# $illnum";
    $headers  = "From: SEAL <donotreply@senylrc.org>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    $message = "
        <p>Your SEAL ILL request (<b>$illnum</b>) for <b>$title</b> has expired after
        five business days without a response from <b>$destination</b>.</p>
        <p>You may wish to submit a new request to a different library.</p>
        <hr>
        <p>This is an automated message from the SEAL ILL System.</p>";

    // Notify requester
    mail($email, $subject, $message, $headers, "-f donotreply@senylrc.org");

    // Notify NOC
    $nocMessage = "$logprefix expired after 5 business days.\n$title\nRequester: $requester <$email>";
    mail("noc@senylrc.org", "SEAL ILL Expired $illnum", $nocMessage, $headers, "-f donotreply@senylrc.org");
}

mysqli_close($db);
error_log(date('c') . " - ===== Completed seal_5day_expire.php =====\n", 3, $logfile);
echo "5-day expiration processing complete.\n";
?>
