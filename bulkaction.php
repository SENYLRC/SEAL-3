<?php
// bulkaction.php — Process lender bulk actions

// Action codes
// 1 = Cancel (sends email)
// 2 = Renew (sends email)
// 3 = Mark Received
// 4 = Mark Returned
// 5 = Mark Not Filled (sends email)
// 6 = Check Item Back In

$illsystemhost = $_SERVER["SERVER_NAME"];

// Connect to database
require '/var/www/seal_wp_script/seal_db.inc';
require '/var/www/seal_wp_script/seal_function.php';

$db = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$db) {
    die("<div style='color:red;'>Database connection failed: " . mysqli_connect_error() . "</div>");
}

$timestamp = date("Y-m-d H:i:s");
$todaydate = date("Y-m-d");

// Store messages for confirmation page
$messages = [];

// Make sure action + selections exist
if (!empty($_POST['bulkaction']) && !empty($_POST['check_list']) && is_array($_POST['check_list'])) {
    $action = (int) $_POST['bulkaction'];

    foreach ($_POST['check_list'] as $id) {
        $id = mysqli_real_escape_string($db, trim($id));

        // Get title + requester info
        $sqlselect = "SELECT requesterEMAIL, Title, Destination 
                      FROM `$sealSTAT` 
                      WHERE illNUB = '$id' 
                      LIMIT 1";
        $result = mysqli_query($db, $sqlselect);
        if (!$result || mysqli_num_rows($result) === 0) {
            $messages[] = "<div class='error'>ILL #$id not found in database</div>";
            continue;
        }
        $row = mysqli_fetch_assoc($result);
        $title          = $row['Title'];
        $requesterEMAIL = $row['requesterEMAIL'];
        $destlib        = $row['Destination'];

        // Get destination library details
        $destemail_to = '';
        $GETLISTSQLDEST = "SELECT Name, ill_email 
                           FROM `$sealLIB` 
                           WHERE loc = '" . mysqli_real_escape_string($db, $destlib) . "' 
                           LIMIT 1";
        $resultdest = mysqli_query($db, $GETLISTSQLDEST);
        if ($rowdest = mysqli_fetch_assoc($resultdest)) {
            $destlib   = $rowdest["Name"];
            $destemail = $rowdest["ill_email"];
            $destemailarray = explode(';', $destemail);
            $destemail_to   = implode(',', $destemailarray);
        }

        // Setup headers for any emails
        $headers = "From: Southeastern SEAL <donotreply@senylrc.org>\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
        $headers = preg_replace('/(?<!\r)\n/', "\r\n", $headers);

        // Process actions
        switch ($action) {
            case 1: // Cancel
                $sqlupdate = "UPDATE `$sealSTAT` SET Fill = '6' WHERE illNUB = '$id'";
                if (mysqli_query($db, $sqlupdate)) {
                    $messages[] = "<div class='success'>ILL #$id ($title) has been <strong>canceled</strong></div>";
                    $subject = "ILL Request Canceled ILL# $id";
                    $message = "ILL request $id for $title has been canceled.";
                    mail($destemail_to, $subject, $message, $headers, "-f donotreply@senylrc.org");
                }
                break;

            case 2: // Renew
                $sqlupdate = "UPDATE `$sealSTAT` 
                              SET renewTimeStamp = '$timestamp', 
                                  renewAccountRequester = '" . mysqli_real_escape_string($db, $wholename ?? '') . "' 
                              WHERE illNUB = '$id'";
                if (mysqli_query($db, $sqlupdate)) {
                    $messages[] = "<div class='success'>Renewal requested for ILL #$id ($title)</div>";
                    $subject = "SEAL Renew Request: from " . ($field_your_institution ?? 'Unknown Institution') . " ILL# $id";
                    $messagedest = ($field_your_institution ?? 'A library') . " has requested a renewal for ILL# $id<br>Title: $title<br><br>
                        How do you wish to answer the renewal?  
                        <a href='http://$illsystemhost/renew?num=$id&fill=1'>Approve</a> &nbsp;&nbsp;&nbsp;&nbsp;
                        <a href='http://$illsystemhost/renew?num=$id&fill=0'>Deny</a><br><hr>
                        This is an automated message from SEAL.";
                    mail($destemail_to, $subject, $messagedest, $headers, "-f donotreply@senylrc.org");
                }
                break;

            case 3: // Received
                $sqlupdate = "UPDATE `$sealSTAT` 
                              SET receiveTimeStamp = '$timestamp', 
                                  receiveAccount = '" . mysqli_real_escape_string($db, $wholename ?? '') . "', 
                                  receiveDate = '$todaydate' 
                              WHERE illNUB = '$id'";
                if (mysqli_query($db, $sqlupdate)) {
                    $messages[] = "<div class='success'>ILL #$id ($title) has been <strong>marked received</strong></div>";
                }
                break;

            case 4: // Return
                $sqlupdate = "UPDATE `$sealSTAT` 
                              SET returnTimeStamp = '$timestamp',
                                  returnMethod = '" . mysqli_real_escape_string($db, $returnmethod ?? '') . "',
                                  returnNote = '" . mysqli_real_escape_string($db, $returnnote ?? '') . "',
                                  returnAccount = '" . mysqli_real_escape_string($db, $wholename ?? '') . "', 
                                  returnDate = '$todaydate' 
                              WHERE illNUB = '$id'";
                if (mysqli_query($db, $sqlupdate)) {
                    $messages[] = "<div class='success'>ILL #$id ($title) has been <strong>marked returned</strong></div>";
                }
                break;

            case 5: // Not filled
                $sqlupdate = "UPDATE `$sealSTAT` SET emailsent = '1', Fill = '0' WHERE illNUB = '$id'";
                if (mysqli_query($db, $sqlupdate)) {
                    $messages[] = "<div class='warning'>ILL #$id ($title) has been <strong>marked not filled</strong></div>";
                    $subject = "ILL Request Not Filled ILL# $id";
                    $message = "Your ILL request $id for $title cannot be filled by $destlib.<br>
                                <a href='http://$illsystemhost'>Would you like to try a different library?</a>";
                    mail($requesterEMAIL, $subject, $message, $headers, "-f donotreply@senylrc.org");
                }
                break;

            case 6: // Check item back in
                $sqlupdate = "UPDATE `$sealSTAT` 
                              SET checkinTimeStamp = '$timestamp', 
                                  checkinAccount = '" . mysqli_real_escape_string($db, $wholename ?? '') . "' 
                              WHERE illNUB = '$id'";
                if (mysqli_query($db, $sqlupdate)) {
                    $messages[] = "<div class='success'>ILL #$id ($title) has been <strong>checked in</strong></div>";
                }
                break;

            default:
                $messages[] = "<div class='error'>Unknown action ($action) for ILL #$id</div>";
        }
    } // end foreach
} else {
    $messages[] = "<div class='error'>No requests were selected for bulk action.</div>";
}

mysqli_close($db);
?>
<!DOCTYPE html>
<html>
<head>
  <title>SEAL Bulk Action Results</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 30px; }
    .success { background: #dff0d8; color: #3c763d; padding: 10px; margin-bottom: 8px; border: 1px solid #d6e9c6; border-radius: 4px; }
    .warning { background: #fcf8e3; color: #8a6d3b; padding: 10px; margin-bottom: 8px; border: 1px solid #faebcc; border-radius: 4px; }
    .error   { background: #f2dede; color: #a94442; padding: 10px; margin-bottom: 8px; border: 1px solid #ebccd1; border-radius: 4px; }
    a.button { display:inline-block; margin-top:15px; padding:8px 15px; background:#337ab7; color:#fff; text-decoration:none; border-radius:4px; }
  </style>
</head>
<body>
  <h2>Bulk Action Results</h2>
  <?php foreach ($messages as $msg) { echo $msg; } ?>
  <p><a href="/lender-history" class="button">Back to Lender History</a></p>
</body>
</html>