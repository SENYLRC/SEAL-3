<?php

/**
 * SEAL / ILLiad Auto-Status Updater with Status Change Logging
 * Location: /var/www/seal_wp_script/seal_check_illiad.php
 * Purpose: Poll ILLiad API for recent SEAL requests, update statuses, and log when they change.
 */

set_time_limit(1800); // 30-minute max runtime

// === DB connect ===
require '/var/www/seal_wp_script/seal_db.inc';
$db = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$db) {
    error_log(date('c') . " - DB connection failed: " . mysqli_connect_error() . "\n", 3, '/var/log/seal_illiad_cron.log');
    die("DB connection failed\n");
}

// ensure full UTF-8 support for SEAL and ILLiad data
$db->set_charset("utf8mb4");

// === Fetch requests awaiting status ===
$sqlselect = "
SELECT * 
FROM `$sealSTAT`
WHERE (
        (`IlliadStatus` LIKE '%Awaiting%' 
        OR `IlliadStatus` LIKE '%Review%' 
        OR `IlliadStatus` LIKE '%Switch%'
        OR `IlliadStatus` LIKE '%Searching%')
        AND `IlliadStatus` NOT LIKE '%Cancelled by ILL Staff%'
        AND `Title` != ''
    )
AND `TimeStamp` >= DATE_SUB(CURDATE(), INTERVAL 10 MONTH);
";

echo $sqlselect . "\n";
$retval = mysqli_query($db, $sqlselect);
if (!$retval) {
    error_log(date('c') . " - Query failed: " . mysqli_error($db) . "\n", 3, '/var/log/seal_illiad_cron.log');
    exit;
}
$GETLISTCOUNT = mysqli_num_rows($retval);
echo "Found $GETLISTCOUNT requests to process\n";

while ($row = mysqli_fetch_assoc($retval)) {
    // --- Extract data ---
    $Illiadid         = $row["IlliadTransID"];
    $sqlidnumb        = (int)$row["index"];
    $reqnumb          = $row['illNUB'];
    $destlib          = $row['Destination'];
    $title            = $row['Title'];
    $eformFILL        = $row['Fill'];
    $requesterEMAIL   = trim($row['requesterEMAIL']);
    $oldStatus        = trim($row['IlliadStatus']);

    // --- Look up destination library info ---
    $destlib_safe = mysqli_real_escape_string($db, $destlib);
    $GETLISTSQLDEST = "SELECT `APIkey`, `IlliadURL`, `Name`, `ill_email` 
                       FROM `$sealLIB` 
                       WHERE loc LIKE '$destlib_safe' LIMIT 1";
    $resultdest = mysqli_query($db, $GETLISTSQLDEST);

    $destemail = $apikey = $illiadURL = $destlibname = '';
    if ($resultdest && mysqli_num_rows($resultdest) > 0) {
        $rowdest = mysqli_fetch_assoc($resultdest);
        $destlibname = $rowdest["Name"];
        $destemail   = $rowdest["ill_email"];
        $apikey      = $rowdest["APIkey"];
        $illiadURL   = $rowdest["IlliadURL"];
    } else {
        error_log(date('c') . " - Missing destination record for $destlib\n", 3, '/var/log/seal_illiad_cron.log');
        continue;
    }

    // --- Fetch ILLiad JSON ---
    $url = rtrim($illiadURL, '/') . '/' . $Illiadid;
    $cmd = "curl -s -H 'ApiKey: $apikey' '$url'";
    $output = shell_exec($cmd);

    // --- Decode response ---
    $output_decoded = json_decode($output, true);
    if (!is_array($output_decoded)) {
        error_log(date('c') . " - Invalid JSON from ILLiad for $Illiadid\n", 3, '/var/log/seal_illiad_cron.log');
        continue;
    }

    $illiadtxnub   = $output_decoded['TransactionNumber'] ?? '';
    $status        = $output_decoded['TransactionStatus'] ?? '';
    $articleURL    = $output_decoded['ArticleExchangeUrl'] ?? '';
    $articlePASS   = $output_decoded['ArticleExchangePassword'] ?? '';
    $reasonCancel  = $output_decoded['ReasonForCancellation'] ?? '';
    $dueDate       = $output_decoded['DueDate'] ?? '';
    if ($dueDate) {
        $dueDate = strstr($dueDate, 'T', true);
    }

    echo "Processing ILL# $reqnumb | Status: $status | Dest: $destlibname\n";

    // === NEW: Log only if status changed ===
    if ($status !== $oldStatus && $status !== '') {
        $logMsg = sprintf(
            "%s - ILL# %s | Status changed: '%s' → '%s' | Dest: %s\n",
            date('c'),
            $reqnumb,
            $oldStatus,
            $status,
            $destlibname
        );
        error_log($logMsg, 3, '/var/log/seal_illiad_cron.log');

        // Update DB with the new ILLiadStatus immediately
        $updateStatus = "UPDATE `$sealSTAT` SET `IlliadStatus` = '" . mysqli_real_escape_string($db, $status) . "' WHERE `index` = $sqlidnumb";
        mysqli_query($db, $updateStatus);
    }

    // === Email setup ===
    $headers  = "From: SEAL <donotreply@senylrc.org>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $destemailarray = explode(';', $destemail);
    $destemail_to = implode(',', $destemailarray);

    // === CASE 1: Filled and shipped ===
    if (!empty($dueDate) && strpos($status, 'Shipped') !== false) {
        $sqlupdate2 = "
            UPDATE `$sealSTAT`
            SET `shipMethod`='', 
                `DueDate` = '$dueDate',
                `Fill` = '1',
                 `IlliadStatus` = '$status'
            WHERE `index` = $sqlidnumb
        ";
        if (mysqli_query($db, $sqlupdate2)) {
            $message = "Your ILL request <b>$reqnumb</b> for <b>$title</b> will be filled by <b>$destlibname</b>.<br>
                        Due Date: $dueDate<br><br>Shipped via: empire<br><br>
                        Please email <b>$destemail_to</b> for future communications regarding this request.";
            $subject = "ILL Request Filled ILL# $reqnumb";
            mail($requesterEMAIL, $subject, $message, $headers, "-f donotreply@senylrc.org");
        } else {
            error_log(date('c') . " - DB update failed for shipped item $reqnumb: " . mysqli_error($db) . "\n", 3, '/var/log/seal_illiad_cron.log');
        }
    }

    // === CASE 2: Filled via OCLC Article Exchange ===
    elseif ($eformFILL == '3' && strlen($articleURL) > 5) {
        $sqlupdate2 = "
            UPDATE `$sealSTAT`
            SET `shipMethod`='OCLC Article Exchange',
                `DueDate` = 'None',
                `Fill` = '1',
                `IlliadStatus` = 'Request Finished'
            WHERE `index` = $sqlidnumb
        ";
        if (mysqli_query($db, $sqlupdate2)) {
            $message = "Your ILL request <b>$reqnumb</b> for <b>$title</b> will be filled by <b>$destlibname</b>.<br><br>
                        Shipped via: OCLC Article Exchange<br><br>
                        Access at: <a href='$articleURL'>$articleURL</a><br>
                        Password: $articlePASS<br><br>
                        Please email <b>$destemail_to</b> for future communications regarding this request.";
            $subject = "ILL Request Filled ILL# $reqnumb";
            mail($requesterEMAIL, $subject, $message, $headers, "-f donotreply@senylrc.org");
        } else {
            error_log(date('c') . " - DB update failed for OCLC item $reqnumb: " . mysqli_error($db) . "\n", 3, '/var/log/seal_illiad_cron.log');
        }
    }

    // === CASE 3: Cancelled ===
    elseif (strpos($status, 'Cancelled') !== false && !empty($reasonCancel)) {
        $reasontxt = 'Not specified';
        $nofillreason = "0";

        if (stripos($reasonCancel, 'In use') !== false) {
            $reasontxt = 'In Use';
            $nofillreason = "20";
        } elseif (stripos($reasonCancel, 'Lost') !== false) {
            $reasontxt = 'Lost';
            $nofillreason = "21";
 	} elseif (stripos($reasonCancel, 'Other') !== false) {
            $reasontxt = 'Other';
            $nofillreason = "0";
        } elseif (stripos($reasonCancel, 'non') !== false) {
            $reasontxt = 'Non-Circulating';
            $nofillreason = "22";
        } elseif (stripos($reasonCancel, 'Not on shelf') !== false) {
            $reasontxt = 'Not on shelf';
            $nofillreason = "23";
        } elseif (stripos($reasonCancel, 'Poor condition') !== false) {
            $reasontxt = 'Poor condition';
            $nofillreason = "24";
        } elseif (stripos($reasonCancel, 'Too New') !== false) {
            $reasontxt = 'Too New';
            $nofillreason = "25";
        } elseif (stripos($reasonCancel, 'Not owned') !== false) {
            $reasontxt = 'Not owned';
            $nofillreason = "26";
        } elseif (stripos($reasonCancel, 'Archive/special collections') !== false) {
            $reasontxt = 'Archive/special collections';
            $nofillreason = "22";
        } elseif (stripos($reasonCancel, 'Checked Out') !== false) {
            $reasontxt = 'Checked Out';
            $nofillreason = "20";
        } elseif (stripos($reasonCancel, 'Not found as cited') !== false) {
            $reasontxt = 'Not found as cited';
            $nofillreason = "27";
        }


        

        $sqlupdate2 = "
            UPDATE `$sealSTAT`
            SET `reasonNotFilled` = '$nofillreason',
                `Fill` = '0',
                 `IlliadStatus` = '$status'
            WHERE `index` = $sqlidnumb
        ";
        if (mysqli_query($db, $sqlupdate2)) {
            $message = "Your ILL request <b>$reqnumb</b> for <b>$title</b> cannot be filled by <b>$destlibname</b>.<br>
                        Reason: $reasontxt<br><br>
                        <a href='https://seal.senylrc.org'>Would you like to try a different library?</a>";
            $subject = "ILL Request Not Filled ILL# $reqnumb";
            mail($requesterEMAIL, $subject, $message, $headers, "-f donotreply@senylrc.org");
        } else {
            error_log(date('c') . " - DB update failed for cancelled item $reqnumb: " . mysqli_error($db) . "\n", 3, '/var/log/seal_illiad_cron.log');

        }

        // === CASE 4: Still pending ===
    } else {
        echo "ILL# $reqnumb not yet filled or cancelled.\n";
    }
}

mysqli_close($db);
echo "Processing complete.\n";
