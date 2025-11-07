<?php
/**
 * SEAL / ILLiad Due Date Checker
 * Purpose: For shipped items, poll ILLiad for updated DueDate; update SEAL DB and notify requester if changed.
 * Location: /var/www/seal_wp_script/seal_check_illiad_duedate.php
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

error_log(date('c') . " - ===== Starting seal_check_illiad_duedate.php =====\n", 3, '/var/log/seal_illiad_cron.log');

// === Fetch requests currently marked as Shipped ===
$sqlselect = "
    SELECT *
    FROM `$sealSTAT`
    WHERE `IlliadStatus` LIKE '%Shipped%'
      AND `IlliadTransID` <> ''
      AND `Title` <> ''
";
$retval = mysqli_query($db, $sqlselect);
if (!$retval) {
    error_log(date('c') . " - Query failed: " . mysqli_error($db) . "\n", 3, '/var/log/seal_illiad_cron.log');
    exit;
}
$GETLISTCOUNT = mysqli_num_rows($retval);
echo "Found $GETLISTCOUNT shipped requests to verify due date\n";
error_log(date('c') . " - Found $GETLISTCOUNT shipped requests\n", 3, '/var/log/seal_illiad_cron.log');

while ($row = mysqli_fetch_assoc($retval)) {
    $Illiadid        = trim((string)$row["IlliadTransID"]);
    $sqlidnumb       = (int)$row["index"];
    $reqnumb         = trim((string)$row['illNUB']);
    $destloc         = trim((string)$row['Destination']);
    $title           = trim((string)$row['Title']);
    $origDueDate     = trim((string)$row['DueDate']);
    $requesterEMAIL  = trim((string)$row['requesterEMAIL']);

    if ($Illiadid === '') continue;

    // --- Destination library info ---
    $destloc_safe = mysqli_real_escape_string($db, $destloc);
    $GETLISTSQLDEST = "
        SELECT `APIkey`, `IlliadURL`, `Name`, `ill_email`
        FROM `$sealLIB`
        WHERE `loc` LIKE '$destloc_safe'
        LIMIT 1
    ";
    $resultdest = mysqli_query($db, $GETLISTSQLDEST);
    if (!$resultdest || mysqli_num_rows($resultdest) === 0) {
        error_log(date('c') . " - Missing destination record for loc=$destloc (index $sqlidnumb)\n", 3, '/var/log/seal_illiad_cron.log');
        continue;
    }

    $rowdest     = mysqli_fetch_assoc($resultdest);
    $destlibname = $rowdest["Name"]     ?? '';
    $apikey      = $rowdest["APIkey"]   ?? '';
    $illiadURL   = $rowdest["IlliadURL"]?? '';
    $destemail   = $rowdest["ill_email"]?? '';

    // --- Build and execute ILLiad API request ---
    $url = rtrim($illiadURL, '/') . '/' . rawurlencode($Illiadid);
    $cmd = "curl -s -H 'ApiKey: " . escapeshellcmd($apikey) . "' '" . escapeshellcmd($url) . "'";
    $output = shell_exec($cmd);
    $data = json_decode($output, true);

    if (!is_array($data)) {
        error_log(date('c') . " - Invalid JSON for ILLiadID $Illiadid | URL=$url | Output=" . substr((string)$output, 0, 200) . "\n", 3, '/var/log/seal_illiad_cron.log');
        continue;
    }

    $status        = $data['TransactionStatus']     ?? '';
    $reasonCancel  = $data['ReasonForCancellation'] ?? '';
    $dueDateApi    = $data['DueDate']               ?? '';
    $dueDate       = $dueDateApi ? (strstr($dueDateApi, 'T', true) ?: $dueDateApi) : '';

    $logprefix = "ILL#$reqnumb ($destlibname / index $sqlidnumb)";
    error_log(date('c') . " - Checking $logprefix | Status: $status | DueDate: $dueDate\n", 3, '/var/log/seal_illiad_cron.log');

    // === Handle Cancelled ===
    if ((stripos($status, 'cancelled') !== false) && !empty($reasonCancel)) {
        $reasontxt = 'Not specified'; $nofillreason = "0";
        if (stripos($reasonCancel, 'In use') !== false)        { $reasontxt='In Use'; $nofillreason="20"; }
        elseif (stripos($reasonCancel, 'Lost') !== false)      { $reasontxt='Lost'; $nofillreason="21"; }
        elseif (stripos($reasonCancel, 'non') !== false)       { $reasontxt='Non-Circulating'; $nofillreason="22"; }
        elseif (stripos($reasonCancel, 'Not on shelf') !== false){ $reasontxt='Not on shelf'; $nofillreason="23"; }
        elseif (stripos($reasonCancel, 'Poor condition') !== false){ $reasontxt='Poor condition'; $nofillreason="24"; }

        $status_sql = mysqli_real_escape_string($db, $status);
        $sqlupdate  = "UPDATE `$sealSTAT` SET `reasonNotFilled`='$nofillreason', `Fill`='0', `IlliadStatus`='$status_sql' WHERE `index`=$sqlidnumb";
        if (!mysqli_query($db, $sqlupdate)) {
            $err = mysqli_error($db);
            error_log(date('c') . " - $logprefix | Cancel update failed: $err\n", 3, '/var/log/seal_illiad_cron.log');
        } else {
            error_log(date('c') . " - $logprefix | Marked cancelled ($reasontxt)\n", 3, '/var/log/seal_illiad_cron.log');
        }
        continue;
    }

    // === Handle Shipped (due date changes) ===
    $is_shipped = stripos($status, 'shipped') !== false;
    if ($is_shipped) {
        $origNorm = preg_replace('/\s+/', '', strtolower($origDueDate));
        if ($origNorm === 'none') $origNorm = '';
        $newNorm = preg_replace('/\s+/', '', strtolower($dueDate));

        if ($newNorm !== $origNorm && strlen($newNorm) >= 4) {
            $due_sql = mysqli_real_escape_string($db, $dueDate);
            $status_sql = mysqli_real_escape_string($db, $status);
            $sqlupdate = "UPDATE `$sealSTAT` SET `DueDate`='$due_sql', `IlliadStatus`='$status_sql' WHERE `index`=$sqlidnumb";
            if (mysqli_query($db, $sqlupdate)) {
                error_log(date('c') . " - $logprefix | Due date updated to $dueDate\n", 3, '/var/log/seal_illiad_cron.log');

                // Email borrower on due date change
                if (filter_var($requesterEMAIL, FILTER_VALIDATE_EMAIL)) {
                    $headers  = "From: SEAL <donotreply@senylrc.org>\r\n";
                    $headers .= "MIME-Version: 1.0\r\n";
                    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                    $subject  = "Request $reqnumb has a new due date";
                    $message  = "Your ILL request <b>$reqnumb</b> for <b>" . htmlspecialchars($title) . "</b> from <b>" . htmlspecialchars($destlibname) . "</b> now has a new due date: <b>$dueDate</b>.<br><br>This is an automated message 
from the SEAL ILL System.";
                    mail($requesterEMAIL, $subject, $message, $headers, "-f donotreply@senylrc.org");
                    error_log(date('c') . " - $logprefix | Email sent to requester ($requesterEMAIL)\n", 3, '/var/log/seal_illiad_cron.log');
                }
            } else {
                $err = mysqli_error($db);
                error_log(date('c') . " - $logprefix | DB update failed: $err\n", 3, '/var/log/seal_illiad_cron.log');
            }
        } else {
            error_log(date('c') . " - $logprefix | No due date change ($origDueDate)\n", 3, '/var/log/seal_illiad_cron.log');
        }
    }
}

// === Auto-close very old shipped requests (7 months) ===
$sqlselect_old = "
    SELECT `index`
    FROM `$sealSTAT`
    WHERE `IlliadStatus` LIKE '%Shipped%'
      AND `TimeStamp` < DATE_SUB(NOW(), INTERVAL 7 MONTH)
";
$retval_old = mysqli_query($db, $sqlselect_old);
if ($retval_old) {
    while ($row = mysqli_fetch_assoc($retval_old)) {
        $sqlidnumb = (int)$row['index'];
        $sqlupdate = "
            UPDATE `$sealSTAT`
            SET `IlliadStatus` = 'Assumed Complete after 7 months'
            WHERE `index` = $sqlidnumb
        ";
        if (mysqli_query($db, $sqlupdate)) {
            error_log(date('c') . " - Index $sqlidnumb | Auto-closed (7-month rule)\n", 3, '/var/log/seal_illiad_cron.log');
        } else {
            error_log(date('c') . " - Index $sqlidnumb | Auto-close failed: " . mysqli_error($db) . "\n", 3, '/var/log/seal_illiad_cron.log');
        }
    }
}

mysqli_close($db);
error_log(date('c') . " - ===== Completed seal_check_illiad_duedate.php =====\n", 3, '/var/log/seal_illiad_cron.log');
echo "Due date check complete.\n";
