<?php
// ==========================================================
// WordPress Access Control — Restrict to Logged-In Users
// with Role: Administrator or Library Staff
// ==========================================================
if (!is_user_logged_in()) {
    die("<div style='padding:20px;color:red;font-weight:bold;'>
        Access Denied<br>You must be logged in to view this page.
    </div>");
}

$current_user = wp_get_current_user();
$user_roles   = (array)$current_user->roles;

// Only allow Administrator or Library Staff roles
if (!array_intersect(['administrator', 'library_staff'], $user_roles)) {
    die("<div style='padding:20px;color:red;font-weight:bold;'>
        Access Denied<br>You must have the <b>Administrator</b> or <b>Library Staff</b> role to access this page.
    </div>");
}
// status.php

require '/var/www/seal_wp_script/seal_function.php';

// Get values from POST/REQUEST
$reqnumb      = $_POST["num"]        ?? ($_REQUEST["num"] ?? '');
$recanswer    = $_POST["a"]          ?? ($_REQUEST["a"] ?? '');
$returnnote   = $_POST["returnnote"] ?? ($_REQUEST["returnnote"] ?? '');
$itemreturn   = $_POST["itemreturn"] ?? ($_REQUEST["itemreturn"] ?? '');
$returnmethod = $_POST["shipmethod"] ?? ($_REQUEST["shipmethod"] ?? '');
$respondnote  = $_POST["respondnote"] ?? ($_REQUEST["respondnote"] ?? '');
$resfill      = $_POST["fill"]        ?? ($_REQUEST["fill"] ?? '');

// Connect to database
require '/var/www/seal_wp_script/seal_db.inc';
$db = mysqli_connect($dbhost, $dbuser, $dbpass);
mysqli_select_db($db, $dbname);

// Escape values
$reqnumb      = mysqli_real_escape_string($db, $reqnumb);
$recanswer    = mysqli_real_escape_string($db, $recanswer);
$returnnote   = mysqli_real_escape_string($db, $returnnote);
$itemreturn   = mysqli_real_escape_string($db, $itemreturn);
$returnmethod = mysqli_real_escape_string($db, $returnmethod);
$respondnote  = mysqli_real_escape_string($db, $respondnote);
$resfill      = mysqli_real_escape_string($db, $resfill);
$wholename    = mysqli_real_escape_string($db, $wholename ?? '');

// Timestamp
$timestamp = date("Y-m-d H:i:s");
$todaydate = date("Y-m-d");

// -------------------- ACTIONS --------------------

// 1 = received
if ($recanswer === '1') {
    $sql = "UPDATE `$sealSTAT`
            SET `receiveTimeStamp` = '$timestamp',
                `receiveAccount` = '$wholename',
                `receiveDate` = '$todaydate'
            WHERE `illNUB` = '$reqnumb'";
    if (mysqli_query($db, $sql)) {
        echo "ILL $reqnumb has been received; <a href='/requesthistory'>Back to request history</a>";
    } else {
        echo "Error: unable to mark item received.";
    }

    // 2 = returning
} elseif ($recanswer === '2') {
    if ($returnmethod === '') {
        ?>
        <form action="/status" method="post">
            <input type="hidden" name="a" value="2">
            <input type="hidden" name="num" value="<?php echo htmlspecialchars($reqnumb); ?>">
            <label>How are you returning the item:
                <select name="shipmethod" required>
                    <option value=""></option>
                    <option value="lc">Library Courier</option>
                    <option value="usps">US Mail</option>
                    <option value="upsfx">UPS/FedEx</option>
                    <option value="empire">Empire Library Delivery</option>
                    <option value="other">Other</option>
                </select>
            </label><br><br>
            <label>Return Notes <input type="text" size="100" name="returnnote"></label><br>
            <button type="submit">Submit</button>
        </form>
        <?php
    } else {
        $sql = "UPDATE `$sealSTAT`
                SET `returnTimeStamp` = '$timestamp',
                    `returnMethod` = '$returnmethod',
                    `returnNote` = '$returnnote',
                    `returnAccount` = '$wholename',
                    `returnDate` = '$todaydate',
                    `patronnote` = ''
                WHERE `illNUB` = '$reqnumb'";
        if (mysqli_query($db, $sql)) {
            echo "ILL $reqnumb has been marked as returned; <a href='/requesthistory'>Back to request history</a>";
        } else {
            echo "Error: unable to mark return.";
        }
    }

    // 3 = lender received item / check-in
} elseif ($recanswer === '3') {
    if ($itemreturn === '') {
        ?>
        <form action="/status" method="post">
            <input type="hidden" name="a" value="3">
            <input type="hidden" name="num" value="<?php echo htmlspecialchars($reqnumb); ?>">
            <p>Do you wish to mark the item as returned?</p>
            <label><input type="radio" name="itemreturn" value="1" checked> Yes</label><br>
            <label><input type="radio" name="itemreturn" value="0"> No</label><br>
            <button type="submit">Submit</button>
        </form>
        <?php
    } else {
        if ($itemreturn === '1') {
            $sql = "UPDATE `$sealSTAT`
                    SET `checkinTimeStamp` = '$timestamp',
                        `checkinAccount` = '$wholename'
                    WHERE `illNUB` = '$reqnumb'";
            if (mysqli_query($db, $sql)) {
                echo "ILL $reqnumb has been checked in; <a href='/lender-history'>Back to lending history</a>";
            } else {
                echo "Error: unable to check in.";
            }
        } else {
            echo "At your request, the item has <strong>NOT</strong> been marked as returned. <a href='/lender-history'>Back to lending history</a>";
        }
    }

    // 6 = cancel request
} elseif ($recanswer === '6') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // get title and requester email before clearing
        $sqlselect = "SELECT requesterEMAIL, Title, Destination FROM `$sealSTAT` WHERE illNUB='$reqnumb' LIMIT 1";
        $result = mysqli_query($db, $sqlselect);
        $row = mysqli_fetch_assoc($result);

        $sqlupdate = "UPDATE `$sealSTAT` 
                      SET `Fill` = '6', 
                          `Title` = '', `Author` = '', `pubdate` = '', `reqisbn` = '', `reqissn` = '', 
                          `itype` = '', `Call Number` = '', `article` = '', `needbydate` = '', 
                          `patronnote` = '', `DueDate` = '', `emailsent` = '1',
                          `responderNOTE` = '$respondnote', `IlliadStatus` = ''
                      WHERE `illNUB` = '$reqnumb'";

        if (mysqli_query($db, $sqlupdate)) {
            echo "ILL $reqnumb has been canceled.<br><a href='/requesthistory'>Back to request history</a>";

            $respnote = stripslashes($respondnote);
            if (strlen($respnote) > 0) {
                $respnote = "The requesting library has noted the following:<br>$respnote";
            }

            $title          = $row["Title"];
            $requesterEMAIL = $row["requesterEMAIL"];
            $destlib        = $row["Destination"];

            // Get destination library info
            $GETLISTSQLDEST = "SELECT `Name`, `ill_email` FROM `$sealLIB` WHERE loc LIKE '$destlib' LIMIT 1";
            $resultdest = mysqli_query($db, $GETLISTSQLDEST);
            $rowdest    = mysqli_fetch_assoc($resultdest);
            $destemail  = $rowdest["ill_email"] ?? '';
            $destemailarray = explode(';', $destemail);
            $destemail_to   = implode(',', $destemailarray);

            $headers  = "From: Southeastern SEAL <donotreply@senylrc.org>\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";

            $subject = "ILL Request Canceled ILL# $reqnumb";
            $message = "ILL request $reqnumb for $title has been canceled.<br><br>$respnote";

            // Normalize newlines
            $message = preg_replace('/(?<!\r)\n/', "\r\n", $message);
            $headers = preg_replace('/(?<!\r)\n/', "\r\n", $headers);

            // Send mail to requester and lending library
            mail($requesterEMAIL, $subject, $message, $headers, "-f donotreply@senylrc.org");
            mail($destemail_to, $subject, $message, $headers, "-f donotreply@senylrc.org");
        } else {
            echo "Error: unable to cancel request $reqnumb.";
        }
    } else {
        // Show cancellation confirmation form
        ?>
        <p>Please confirm you want to cancel this request.</p>
        <form action="/status" method="post">
            <input type="hidden" name="a" value="6">
            <input type="hidden" name="num" value="<?php echo htmlspecialchars($reqnumb); ?>">
            <input type="hidden" name="fill" value="6">
            <label>Notes about the cancellation:<br>
                <textarea name="respondnote" rows="4" cols="50"></textarea>
            </label><br>
            <button type="submit">Submit</button>
        </form>
        <?php
    }

} else {
    echo "Invalid request. Please go through <a href='/user'>your profile</a>.";
}

mysqli_close($db);
?>