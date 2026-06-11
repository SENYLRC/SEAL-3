<?php
/**
 * SEAL / Due Date Reminder
 * ------------------------------------------------------------
 * Purpose:
 *   Sends an email reminder 5 days before an ILL item’s due date.
 *   (Works for items received but not yet returned/checked in.)
 *
 * Location: /var/www/seal_wp_script/seal_due_date.php
 * Logs:     /var/log/seal_illiad_cron.log
 */

set_time_limit(900);
$logfile = '/var/log/seal_illiad_cron.log';
error_log(date('c') . " - ===== Starting seal_due_date.php =====\n", 3, $logfile);

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
//  Prepare date range (5 days from today)
// ----------------------------------------------------
$today = date("Y-m-d");
$fivedays = date('m/d/Y', strtotime('+5 days')); // Matches stored format (MM/DD/YYYY)
error_log(date('c') . " - Checking for due dates on $fivedays\n", 3, $logfile);

// ----------------------------------------------------
//  Query items due in 5 days
// ----------------------------------------------------
$sqlselect = "
    SELECT *
    FROM `$sealSTAT`
    WHERE `DueDate` = '$fivedays'
      AND `receiveAccount` IS NOT NULL
      AND `returnAccount` IS NULL
      AND `checkinAccount` IS NULL
    ORDER BY `index` ASC
";
$retval = mysqli_query($db, $sqlselect);
if (!$retval) {
    error_log(date('c') . " - SQL query failed: " . mysqli_error($db) . "\n", 3, $logfile);
    die("Query failed\n");
}

$count = mysqli_num_rows($retval);
echo "Found $count items with due date $fivedays\n";
error_log(date('c') . " - Found $count items with due date $fivedays\n", 3, $logfile);

// ----------------------------------------------------
//  Process and send reminders
// ----------------------------------------------------
while ($row = mysqli_fetch_assoc($retval)) {
    $illnum     = $row["illNUB"];
    $title      = $row["Title"];
    $author     = $row["Author"];
    $itype      = $row["Itype"];
    $pubdate    = $row["pubdate"];
    $isbn       = $row["reqisbn"];
    $issn       = $row["reqissn"];
    $article    = $row["article"];
    $duedate    = $row["DueDate"];
    $email      = trim($row["requesterEMAIL"]);

    // --- clean missing values ---
    if (empty($title))   $title = '(no title)';
    if (empty($author))  $author = '(unknown author)';
    if (empty($itype))   $itype = '';
    if (empty($pubdate)) $pubdate = '';
    if (empty($isbn))    $isbn = '';
    if (empty($issn))    $issn = '';
    if (empty($article)) $article = '';

    // --- Compose message ---
    $subject = "SEAL Approaching Due Date — ILL# $illnum";
    $headers  = "From: SEAL <donotreply@senylrc.org>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    $message = "
        <p><b>ILL# $illnum</b> has a due date approaching: <b>$duedate</b></p>
        <p>
        Title: $title<br>
        Author: $author<br>
        Item Type: $itype<br>
        Publication Date: $pubdate<br>
        $isbn<br>$issn<br>$article
        </p>
        <hr style='width:200px;text-align:left;margin-left:0'><br>
        <p>This is an automated message from the SEAL ILL System. 
        Responses to this email will be sent back to staff at Southeastern 
        New York Library Resources Council.</p>";

    // --- Send email ---
    $sent = mail($email, $subject, $message, $headers, "-f donotreply@senylrc.org");
    if ($sent) {
        error_log(date('c') . " - ILL#$illnum | Due date reminder sent to $email ($duedate)\n", 3, $logfile);
    } else {
        error_log(date('c') . " - ILL#$illnum | FAILED to send due date reminder to $email\n", 3, $logfile);
    }
}

mysqli_close($db);
error_log(date('c') . " - ===== Completed seal_due_date.php =====\n", 3, $logfile);
echo "Due date reminder processing complete.\n";
?>
