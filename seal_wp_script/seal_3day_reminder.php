<?php

/**
 * SEAL / 3-Day Reminder Script (Business Day Logic)
 * ------------------------------------------------------------
 *  - Sends reminder emails if 3 business days (excl. weekends & holidays)
 *    have passed since an ILL request was created.
 *  - Uses live Nager.Date API + Winter Break (Dec 24 – Jan 1).
 */

set_time_limit(1800);
date_default_timezone_set('America/New_York');

$logfile = '/var/log/seal_illiad_cron.log';
error_log(date('c') . " - ===== Starting seal_3day_reminder.php =====\n", 3, $logfile);

// ----------------------------------------------------
//  Auto-Updating Holiday Function (Nager.Date + Winter Break)
// ----------------------------------------------------
function getHolidaysAuto($country = 'US')
{
    $currentYear = (int)date('Y');
    $month = (int)date('n');

    // Always include previous + current year
    $years = [$currentYear - 1, $currentYear];

    // If December, include next year as well
    if ($month === 12) {
        $years[] = $currentYear + 1;
    }

    $years = array_values(array_unique($years));
    sort($years);


    $holidays = [];
    $logfile = '/var/log/seal_illiad_cron.log';

    foreach ($years as $year) {
        $cacheFile = "/var/www/seal_wp_script/holidays_{$year}.json";
        $url = "https://date.nager.at/api/v3/PublicHolidays/{$year}/{$country}";
        $data = [];

        // Cached JSON (valid ≤ 7 days)
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 604800) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if (!is_array($data)) {
                $data = [];
            }
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
            if (!empty($holiday['date'])) {
                $holidays[] = $holiday['date'];
            }
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
//  Helper functions for business days
// ----------------------------------------------------
function isBusinessDay(string $ymd, array $holidays): bool
{
    $dow = (int)date('N', strtotime($ymd)); // 1=Mon .. 7=Sun
    return $dow < 6 && !in_array($ymd, $holidays, true);
}

/**
 * Add exactly $days business days to $startDate, skipping weekends/holidays
 */
function addBusinessDays(string $startDate, int $days, array $holidays, bool $includeStart = false): string
{
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
                break; // stop when we’ve reached exactly $days business days
            }
        }
        $date->modify('+1 day');
    }

    return $date->format('Y-m-d');
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

    $reqdate = substr($timestamp, 0, 10);
    $today   = date("Y-m-d");
    $dueDate = addBusinessDays($reqdate, 3, $holidays, false); // Start counting next business day

    $logprefix = "ILL#$illnum ($destination)";
    error_log(date('c') . " - Checking $logprefix | req=$reqdate due=$dueDate (3 business days)\n", 3, $logfile);

    if ($today <= $dueDate) {
        continue; // Not yet due
    }

    // ------------------------------------------------
    // If Destination is missing, alert NOC and skip
    // ------------------------------------------------
    if ($destination === '' || $destination === null) {
        $noc_subject = "SEAL ALERT: Missing Destination for ILL# $illnum";
        $noc_message = "
            <p><b>Missing destination code detected in 3-Day Reminder script.</b></p>
            <p><b>ILL#:</b> $illnum<br>
            <b>Requester Library:</b> $inst<br>
            <b>Title:</b> $title<br>
            <b>Requester Email:</b> $email<br></p>

            <p>This request cannot be sent to a lender because the Destination field is empty or NULL.</p>
        ";

        $noc_headers  = "From: SEAL <donotreply@senylrc.org>\r\n";
        $noc_headers .= "MIME-Version: 1.0\r\n";
        $noc_headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        mail("noc@senylrc.org", $noc_subject, $noc_message, $noc_headers, "-f donotreply@senylrc.org");

        error_log(
            date('c') . " - $logprefix | Destination is EMPTY — sent NOC alert instead of reminder\n",
            3,
            $logfile
        );

        // Mark as handled so we don't keep retrying
        mysqli_query(
            $db,
            "UPDATE `$sealSTAT`
             SET `emailsent`='2',
                 `responderNOTE`='REMINDER FAILED — NO DESTINATION. NOC ALERTED.'
             WHERE `illNUB`='$illnum'"
        );

        continue; // Next row
    }

    // ------------------------------------------------
    // Get destination ILL email
    // ------------------------------------------------
    $destemail = '';
    $resDest = mysqli_query($db, "SELECT `ill_email` FROM `$sealLIB` WHERE `loc` = '$destination' LIMIT 1");

    if (!$resDest) {
        // SQL error – alert NOC and skip
        $sqlErr = mysqli_error($db);
        $noc_subject = "SEAL ALERT: SQL error looking up Destination $destination (ILL# $illnum)";
        $noc_message = "
            <p><b>3-Day Reminder could not be sent due to an SQL error.</b></p>
            <p><b>ILL#:</b> $illnum<br>
            <b>Destination Code:</b> $destination<br>
            <b>Requester Library:</b> $inst<br>
            <b>Title:</b> $title<br>
            <b>Requester Email:</b> $email<br></p>

            <p>MySQL error: " . htmlspecialchars($sqlErr) . "</p>
        ";

        $noc_headers  = "From: SEAL <donotreply@senylrc.org>\r\n";
        $noc_headers .= "MIME-Version: 1.0\r\n";
        $noc_headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        mail("noc@senylrc.org", $noc_subject, $noc_message, $noc_headers, "-f donotreply@senylrc.org");

        error_log(
            date('c') . " - $logprefix | SQL error on dest lookup: $sqlErr\n",
            3,
            $logfile
        );

        mysqli_query(
            $db,
            "UPDATE `$sealSTAT`
             SET `emailsent`='2',
                 `responderNOTE`='REMINDER FAILED — DEST LOOKUP SQL ERROR. NOC ALERTED.'
             WHERE `illNUB`='$illnum'"
        );

        continue;
    }

    if (mysqli_num_rows($resDest) > 0) {
        $rowDest   = mysqli_fetch_assoc($resDest);
        $destemail = trim($rowDest['ill_email'] ?? '');
    }

    // Normalize + split multiple addresses on ';'
    $destemailarray = array_filter(
        array_map('trim', explode(';', $destemail)),
        fn ($v) => $v !== ''
    );

    if (empty($destemailarray)) {
        // No usable email – alert NOC and skip
        $noc_subject = "SEAL ALERT: No ILL email found for Destination $destination (ILL# $illnum)";
        $noc_message = "
            <p><b>3-Day Reminder could not be sent — no ILL email found for destination.</b></p>
            <p><b>ILL#:</b> $illnum<br>
            <b>Destination Code:</b> $destination<br>
            <b>Requester Library:</b> $inst<br>
            <b>Title:</b> $title<br>
            <b>Requester Email:</b> $email<br></p>

            <p>Please check the SEAL libraries table and add an ILL email for this destination code.</p>
        ";

        $noc_headers  = "From: SEAL <donotreply@senylrc.org>\r\n";
        $noc_headers .= "MIME-Version: 1.0\r\n";
        $noc_headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        mail("noc@senylrc.org", $noc_subject, $noc_message, $noc_headers, "-f donotreply@senylrc.org");

        error_log(
            date('c') . " - $logprefix | No dest email for $destination — NOC alerted\n",
            3,
            $logfile
        );

        mysqli_query(
            $db,
            "UPDATE `$sealSTAT`
             SET `emailsent`='2',
                 `responderNOTE`='REMINDER FAILED — NO DEST EMAIL. NOC ALERTED.'
             WHERE `illNUB`='$illnum'"
        );

        continue;
    }

    $email_to = implode(',', $destemailarray);

    // ------------------------------------------------
    // Compose emails
    // ------------------------------------------------
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

    // ------------------------------------------------
    // Send emails
    // ------------------------------------------------
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
