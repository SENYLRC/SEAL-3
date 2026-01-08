<?php
/**
 * SEAL / Data Anonymizer + Maintenance
 *
 * Purpose:
 *  1) Anonymize sensitive ILL request details older than 5 years (60 months).
 *  2) Auto-expire pending requests older than 18 months.
 *  3) For expired requests, clear Request Note (reqNOTE) after 30 days.
 *
 * Location: /var/www/seal_wp_script/seal_anonymizer.php
 */

set_time_limit(900); // 15-minute safety limit

$LOGFILE = '/var/log/seal_illiad_cron.log';

// === Connect to Database ===
require '/var/www/seal_wp_script/seal_db.inc';

$db = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$db) {
    error_log(date('c') . " - DB connection failed in seal_anonymizer.php: " . mysqli_connect_error() . "\n", 3, $LOGFILE);
    die("DB connection failed\n");
}
mysqli_set_charset($db, 'utf8mb4');

error_log(date('c') . " - ===== Starting seal_anonymizer.php =====\n", 3, $LOGFILE);

// === Compute cutoff dates ===
// 60 months (5 years) anonymization cutoff
$cut60 = new DateTime();
$cut60->sub(new DateInterval('P60M'));
$ydate60 = $cut60->format('Y-m-d');

// 18 months auto-expire cutoff
$cut18 = new DateTime();
$cut18->sub(new DateInterval('P18M'));
$ydate18 = $cut18->format('Y-m-d');

// 30 days clear reqNOTE cutoff
$cut30 = new DateTime();
$cut30->sub(new DateInterval('P30D'));
$ydate30 = $cut30->format('Y-m-d');

error_log(date('c') . " - Cutoff date (60 months) anonymization: $ydate60\n", 3, $LOGFILE);
error_log(date('c') . " - Cutoff date (18 months) auto-expire: $ydate18\n", 3, $LOGFILE);
error_log(date('c') . " - Cutoff date (30 days) expired reqNOTE cleanup: $ydate30\n", 3, $LOGFILE);

// ======================================================================
// STEP 1: Auto-expire pending requests older than 18 months
// Pending definition: Fill IS NULL OR Fill=3  (matches your LS page)
// Expired definition: emailsent=3 OR responderNOTE contains "expire"
// ======================================================================

$expireCountSql = "
  SELECT COUNT(*) AS total
  FROM `$sealSTAT` s
  WHERE s.`Timestamp` < '$ydate18'
    AND (s.`Fill` IS NULL OR s.`Fill` = 3)
    AND NOT (s.`emailsent` = 3 OR LOWER(COALESCE(s.`responderNOTE`, '')) LIKE '%expire%')
";
$resExpireCount = mysqli_query($db, $expireCountSql);
$rowExpireCount = $resExpireCount ? mysqli_fetch_assoc($resExpireCount) : ['total' => 0];
$toExpire = (int)($rowExpireCount['total'] ?? 0);

echo "Found $toExpire pending records older than $ydate18 to auto-expire\n";
error_log(date('c') . " - Found $toExpire pending records older than $ydate18 to auto-expire\n", 3, $LOGFILE);

$expireUpdateSql = "
  UPDATE `$sealSTAT` s
  SET
    s.`emailsent` = 3,
    s.`responderNOTE` = CONCAT(
        LEFT(COALESCE(s.`responderNOTE`, ''), 220),
        CASE WHEN COALESCE(s.`responderNOTE`, '') = '' THEN '' ELSE ' | ' END,
        'Expired automatically (18 months)'
    ),
    s.`fillNofillDate` = COALESCE(s.`fillNofillDate`, NOW())
  WHERE s.`Timestamp` < '$ydate18'
    AND (s.`Fill` IS NULL OR s.`Fill` = 3)
    AND NOT (s.`emailsent` = 3 OR LOWER(COALESCE(s.`responderNOTE`, '')) LIKE '%expire%')
";

if (!mysqli_query($db, $expireUpdateSql)) {
    $err = mysqli_error($db);
    echo "Auto-expire update failed: $err\n";
    error_log(date('c') . " - seal_anonymizer.php | Auto-expire failed: $err\n", 3, $LOGFILE);

    // Notify NOC if auto-expire fails
    $headers  = "From: SEAL <donotreply@senylrc.org>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $to       = "noc@senylrc.org";
    $subject  = "SEAL Auto-Expire Failure (18 months)";
    $message  = "SEAL Auto-Expire step failed on " . date('Y-m-d H:i:s') . "<br><br>
                 Error details:<br><pre>$err</pre>";
    @mail($to, $subject, $message, $headers, "-f donotreply@senylrc.org");
} else {
    $affected = mysqli_affected_rows($db);
    echo "Auto-expire complete — $affected records updated.\n";
    error_log(date('c') . " - seal_anonymizer.php | Auto-expire succeeded for $affected records\n", 3, $LOGFILE);
}

// ======================================================================
// STEP 2: Clear Request Note (reqNOTE) for expired requests after 30 days
// Expired definition: emailsent=3 OR responderNOTE contains "expire"
// Age check: prefer fillNofillDate; fall back to Timestamp
// ======================================================================

$clearReqNoteCountSql = "
  SELECT COUNT(*) AS total
  FROM `$sealSTAT` s
  WHERE (s.`emailsent` = 3 OR LOWER(COALESCE(s.`responderNOTE`, '')) LIKE '%expire%')
    AND COALESCE(DATE(s.`fillNofillDate`), DATE(s.`Timestamp`)) < '$ydate30'
    AND COALESCE(s.`reqNOTE`, '') <> ''
";
$resClearCount = mysqli_query($db, $clearReqNoteCountSql);
$rowClearCount = $resClearCount ? mysqli_fetch_assoc($resClearCount) : ['total' => 0];
$toClear = (int)($rowClearCount['total'] ?? 0);

echo "Found $toClear expired records older than $ydate30 to clear Request Note (reqNOTE)\n";
error_log(date('c') . " - Found $toClear expired records older than $ydate30 to clear reqNOTE\n", 3, $LOGFILE);

$clearReqNoteSql = "
  UPDATE `$sealSTAT` s
  SET s.`reqNOTE` = ''
  WHERE (s.`emailsent` = 3 OR LOWER(COALESCE(s.`responderNOTE`, '')) LIKE '%expire%')
    AND COALESCE(DATE(s.`fillNofillDate`), DATE(s.`Timestamp`)) < '$ydate30'
    AND COALESCE(s.`reqNOTE`, '') <> ''
";

if (!mysqli_query($db, $clearReqNoteSql)) {
    $err = mysqli_error($db);
    echo "Clear reqNOTE update failed: $err\n";
    error_log(date('c') . " - seal_anonymizer.php | Clear reqNOTE failed: $err\n", 3, $LOGFILE);

    // Notify NOC if cleanup fails
    $headers  = "From: SEAL <donotreply@senylrc.org>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $to       = "noc@senylrc.org";
    $subject  = "SEAL Expired Note Cleanup Failure (30 days)";
    $message  = "SEAL expired reqNOTE cleanup failed on " . date('Y-m-d H:i:s') . "<br><br>
                 Error details:<br><pre>$err</pre>";
    @mail($to, $subject, $message, $headers, "-f donotreply@senylrc.org");
} else {
    $affected = mysqli_affected_rows($db);
    echo "Expired reqNOTE cleanup complete — $affected records updated.\n";
    error_log(date('c') . " - seal_anonymizer.php | Clear reqNOTE succeeded for $affected records\n", 3, $LOGFILE);
}

// ======================================================================
// STEP 3: Anonymize sensitive request details older than 60 months (5 years)
// ======================================================================

// Preview query
$testsqlselect = "SELECT COUNT(*) AS total FROM `$sealSTAT` WHERE `Timestamp` < '$ydate60'";
$resCount = mysqli_query($db, $testsqlselect);
$countRow = $resCount ? mysqli_fetch_assoc($resCount) : ['total' => 0];
$totalToAnonymize = (int)$countRow['total'];

echo "Found $totalToAnonymize records older than $ydate60 to anonymize\n";
error_log(date('c') . " - Found $totalToAnonymize records older than $ydate60 to anonymize\n", 3, $LOGFILE);

// Perform anonymization
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
    WHERE `Timestamp` < '$ydate60'
";

if (mysqli_query($db, $sqlupdate)) {
    $affected = mysqli_affected_rows($db);
    echo "Anonymization complete — $affected records updated.\n";
    error_log(date('c') . " - seal_anonymizer.php | Anonymization succeeded for $affected records\n", 3, $LOGFILE);
} else {
    $err = mysqli_error($db);
    echo "Anonymization update failed: $err\n";
    error_log(date('c') . " - seal_anonymizer.php | Anonymization update failed: $err\n", 3, $LOGFILE);

    // Notify NOC if anonymization fails
    $headers  = "From: SEAL <donotreply@senylrc.org>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $to       = "noc@senylrc.org";
    $subject  = "SEAL Anonymizer Failure";
    $message  = "SEAL Anonymizer failed to update database on " . date('Y-m-d H:i:s') . "<br><br>
                 Error details:<br><pre>$err</pre>";
    @mail($to, $subject, $message, $headers, "-f donotreply@senylrc.org");
}

mysqli_close($db);
error_log(date('c') . " - ===== Completed seal_anonymizer.php =====\n", 3, $LOGFILE);
