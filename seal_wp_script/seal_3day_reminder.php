<?php
/**
 * SEAL / 3-Day Reminder Script (Self-Contained with Holiday Logic)
 * ------------------------------------------------------------
 *  - Sends reminder emails if 3 business days (excl. weekends & holidays)
 *    have passed since an ILL request was created.
 *  - Uses live Nager.Date API + Winter Break (Dec 24 – Jan 1).
 */

set_time_limit(1800);
$logfile = '/var/log/seal_illiad_cron.log';
error_log(date('c') . " - ===== Starting seal_3day_reminder.php =====\n", 3, $logfile);

// ----------------------------------------------------
//  Auto-Updating Holiday Function (Option 1 + Winter Break)
// ----------------------------------------------------
function getHolidaysAuto($country = 'US') {
    $currentYear = (int)date('Y');
    $month = (int)date('n');
    $day = (int)date('j');

    // If it's mid-December, also include next year
    $years = [$currentYear];
    if ($month === 12 && $day >= 15) {
        $years[] = $currentYear + 1;
    }

    $holidays = [];
    $logfile = '/var/log/seal_illiad_cron.log';

    foreach ($years as $year) {
        $cacheFile = "/var/www/seal_wp_script/holidays_{$year}.json";
        $url = "https://date.nager.at/api/v3/PublicHolidays/{$year}/{$country}";
        $data = [];

        // Cached JSON (valid ≤ 7 days)
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 604800) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if (!is_array($data)) $data = [];
        } else {
            $context = stream_context_create(['http' => ['timeout' => 10]]);
            $json = @file_get_contents($url, false, $context);
            if ($json === false) {
                error_log(date('c') . " - Holiday fetch failed for $year, using cache/fallback\n", 3, $logfile);
                $data = file_exists($cacheFile) ? json_decode(file_get_contents($cacheFile), true) : [];
            } else {
                file_put_contents($cacheFile, $json);
                $data = json_decode($json, true);
            }
        }

        // Extract YYYY-MM-DD
        foreach ($data as $holiday) {
            if (!empty($holiday['date'])) $holidays[] = $holiday['date'];
        }

        // Winter Break (Dec 24 → Jan 1)
        $winterStart = new DateTime("{$year}-12-24");
        $winterEnd   = new DateTime(($year + 1) . "-01-01");
        while ($winterStart <= $winterEnd) {
            $holidays[] = $winterStart->format('Y-m-d');
            $winterStart->modify('+1 day');
        }
    }

    $holidays = array_unique($holidays);
    sort($holidays);
    error_log(date('c') . " - Loaded " . count($holidays) . " holidays for years: " . implode(',', $years) . "\n", 3, $logfile);
    return $holidays;
}

// ----------------------------------------------------
//  Database connection
// ----------------------------------------------------
require '/var/www/seal_wp_script/seal_db.inc';
$db = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$db) {
    error_log(date('c') . " - DB connection failed: " . mysqli_connect_error() . "\n", 3, $logfile);
    die("DB connection failed\n");
}
$db->set_charset("utf8mb4");

// ----------------------------------------------------
//  Helper: Count working days excluding holidays/weekends
// ----------------------------------------------------
function getWorkingDays($startDate, $endDate, $holidays) {
    $start = strtotime($startDate);
    $end   = strtotime($endDate);
    $workingDays = 0;

    while ($start <= $end) {
        $dow = date('N', $start); // 1=Mon,7=Sun
        $dateStr = date('Y-m-d', $start);
        if ($dow < 6 && !in_array($dateStr, $holidays)) {
            $workingDays++;
        }
        $start = strtotime('+1 day', $start);
    }
    return $workingDays;
}

// ----------------------------------------------------
//  Load holiday list
// ----------------------------------------------------
$holidays = getHolidaysAuto();

// ----------------------------------------------------
//  Query open requests that need reminders
// ----------------------------------------------------
$sqlselect = "SELECT * FROM `$sealSTAT` WHERE `emailsent`='0' AND `fill`='3'";
$retval = mysqli_query($db, $sqlselect);
if (!$retval) {
    error_log(date('c') . " - SQL select failed: " . mysqli_error($db) . "\n", 3, $logfile);
    die("Query failed\n");
}

$count = mysqli_num_rows($retval);
echo "Found $count open requests\n";
error_log(date('c') . " - Found $count open requests\n", 3, $logfile);

// ----------------------------------------------------
//  Process each request
// ----------------------------------------------------
while ($row = mysqli_fetch_assoc($retval)) {
    $timestamp   = $row["Timestamp"];
    $destination = trim($row["Destination"]);
    $illnum      = trim($row["illNUB"]);
    $title       = $row["Title"];
    $author      = $row["Author"];
    $itype       = $row["Itype"];
    $pubdate     = $row["pubdate"];
    $isbn        = $row["reqisbn"];
    $issn        = $row["reqissn"];
    $itemcall    = $row["Call Number"];
    $itemavail   = $row["Available"];
    $article     = $row["article"];
    $inst        = $row["Requester lib"];
    $address     = $row["saddress"];
    $caddress    = $row["caddress"];
    $needbydate  = $row["needbydate"];
    $reqnote     = $row["reqnote"];
    $fname       = $row["Requester person"];
    $email       = $row["requesterEMAIL"];
    $wphone      = $row["requesterPhone"];

    $reqdate     = substr($timestamp, 0, 10);
    $today       = date("Y-m-d");
    $calenddate  = date("Y-m-d", strtotime("$reqdate +3 day"));
    $workdays    = getWorkingDays($reqdate, $calenddate, $holidays);

    if ($workdays < 3) {
        $diff = 3 - $workdays;
        $calenddate = date("Y-m-d", strtotime("$calenddate +$diff day"));
    }

    $logprefix = "ILL#$illnum ($destination)";
    error_log(date('c') . " - Checking $logprefix | req=$reqdate calc=$calenddate workdays=$workdays\n", 3, $logfile);

    if ($today <= $calenddate) continue; // not yet due

    // --- Get destination ILL email ---
    $destemail = '';
    $resDest = mysqli_query($db, "SELECT `ill_email` FROM `$sealLIB` WHERE `loc` LIKE '$destination' LIMIT 1");
    if ($resDest && mysqli_num_rows($resDest) > 0) {
        $rowDest = mysqli_fetch_assoc($resDest);
        $destemail = trim($rowDest["ill_email"]);
    }
    $destemailarray = explode(';', $destemail);
    $email_to = implode(',', array_map('trim', $destemailarray));

    // --- Compose emails ---
    $subject = "REMINDER: ILL Request from $inst — ILL# $illnum";
    $headers  = "From: SEAL <donotreply@senylrc.org>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    $messagedest = "
        <p>An ILL request (<b>$illnum</b>) from <b>$inst</b> is awaiting your response:</p>
        <p><b>Title:</b> $title<br>
        <b>Author:</b> $author<br>
        <b>Type:</b> $itype<br>
        <b>Publication:</b> $pubdate<br>
        <b>Call Number:</b> $itemcall<br>
        $isbn $issn<br>$article</p>
        <p><b>Ship To:</b><br>$inst<br>$address<br>$caddress<br>
        Need by: $needbydate<br>$reqnote</p>
        <p><a href='https://seal.senylrc.org/respond?num=$illnum&a=1'>Yes, we can fill</a> |
           <a href='https://seal.senylrc.org/respond?num=$illnum&a=0'>No, unable to fill</a></p>
        <hr>
        <p>This is an automated message from the SEAL ILL System.<br>
        Please contact $email for more information.</p>";

    $messagereq = "
        <p>Your ILL request (<b>$illnum</b>) for <b>$title</b> is still awaiting response from <b>$destination</b>.</p>
        <p>This is an automated 3-day reminder from the SEAL ILL System.<br>
        For questions, contact <b>$email_to</b>.</p>";

    // --- Send emails ---
    $destSent = mail($email_to, $subject, $messagedest, $headers, "-f donotreply@senylrc.org");
    $reqSent  = mail($email, $subject, $messagereq, $headers, "-f donotreply@senylrc.org");

    if ($destSent || $reqSent) {
        $sqlupdate = "UPDATE `$sealSTAT`
                      SET `emailsent`='2', `responderNOTE`='REMINDER MSG Sent'
                      WHERE `illNUB`='$illnum'";
        if (mysqli_query($db, $sqlupdate)) {
            error_log(date('c') . " - $logprefix | Reminder sent successfully\n", 3, $logfile);
        } else {
            $err = mysqli_error($db);
            error_log(date('c') . " - $logprefix | Reminder sent but DB update failed: $err\n", 3, $logfile);
        }
    } else {
        error_log(date('c') . " - $logprefix | Reminder email failed (to: $email_to, $email)\n", 3, $logfile);
    }
}

mysqli_close($db);
error_log(date('c') . " - ===== Completed seal_3day_reminder.php =====\n", 3, $logfile);
echo "3-day reminder processing complete.\n";
?>
