<link rel="stylesheet" href="https://seal.senylrc.org/assets/jquery-ui.css">
<script src="https://seal.senylrc.org/assets/jquery.min.js"></script>
<script src="https://seal.senylrc.org/assets/jquery-ui.min.js"></script>

<script>
  jQuery(function($){
    var currentYear = new Date().getFullYear();

    $("#datepicker").datepicker({
      dateFormat: "yy-mm-dd",
      changeMonth: true,
      changeYear: true,
      yearRange: currentYear + ":" + (currentYear + 3), // ← limit to current year through +3 years
      showAnim: "fadeIn"
    });
  });
</script>

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
if (!array_intersect(['administrator', 'libstaff'], $user_roles)) {
    die("<div style='padding:20px;color:red;font-weight:bold;'>
        Access Denied<br>You must have the <b>Administrator</b> or <b>Library Staff</b> role to access this page.
    </div>");
}
// renew.php
require '/var/www/seal_wp_script/seal_function.php';

// Collect inputs
$reqnumb         = $_POST["num"]         ?? ($_REQUEST["num"] ?? '');
$renewNote       = $_POST["renewNote"]   ?? ($_REQUEST["renewNote"] ?? '');
$duedate         = $_POST["duedate"]     ?? ($_REQUEST["duedate"] ?? '');
$renewNoteLender = $_POST["renewNoteLender"] ?? ($_REQUEST["renewNoteLender"] ?? '');
$renanswer       = $_POST["a"]           ?? ($_REQUEST["a"] ?? '');

// Timestamps
$timestamp = date("Y-m-d H:i:s");
$todaydate = date("Y-m-d");

// Connect to database
require '/var/www/seal_wp_script/seal_db.inc';
$db = mysqli_connect($dbhost, $dbuser, $dbpass);
mysqli_select_db($db, $dbname);

// Escape
$reqnumb         = mysqli_real_escape_string($db, $reqnumb);
$renewNote       = mysqli_real_escape_string($db, $renewNote);
$renewNoteLender = mysqli_real_escape_string($db, $renewNoteLender);
$duedate         = mysqli_real_escape_string($db, $duedate);
$renanswer       = mysqli_real_escape_string($db, $renanswer);
$wholename       = mysqli_real_escape_string($db, $wholename ?? '');

// ----------------- Actions -----------------

// 1 = approve renew
if ($renanswer === '1') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $duedate !== '') {
        $sql = "UPDATE `$sealSTAT`
                SET `renewNoteLender` = '$renewNoteLender',
                    `renewAnswer` = '1',
                    `renewTimeStamp` = '$timestamp',
                    `renewAccountLender` = '$wholename',
                    `DueDate` = '$duedate'
                WHERE `illNUB` = '$reqnumb'";
        if (mysqli_query($db, $sql)) {
            echo "Renewal request has been approved. <a href='/lender-history'>Back to lender history</a>";
            // Email borrower
            $res = mysqli_query($db, "SELECT Title,RequesterEMAIL FROM `$sealSTAT` WHERE `illNUB` = '$reqnumb' LIMIT 1");
            if ($res && ($value = mysqli_fetch_object($res))) {
                $reqemail = $value->RequesterEMAIL;
                $title    = $value->Title;
                $messagedest = "Your renewal request for ILL# $reqnumb ($title) has been approved with a due date of $duedate.<br>";
                if ($renewNoteLender) $messagedest .= "Lender Note: $renewNoteLender<br>";
                $subject = "SEAL Renewal Approved: ILL# $reqnumb";
                $headers = "From: Southeastern SEAL <donotreply@senylrc.org>\r\n".
                           "MIME-Version: 1.0\r\n".
                           "Content-Type: text/html; charset=UTF-8\r\n";
                mail($reqemail, $subject, $messagedest, $headers, "-f donotreply@senylrc.org");
            }
        } else {
            echo "Error: unable to approve renewal.";
        }
    } else {
        ?>
        <form method="post" action="/renew">
          <input type="hidden" name="a" value="1">
          <input type="hidden" name="num" value="<?php echo htmlspecialchars($reqnumb); ?>">
          New Due Date: <input id="datepicker" name="duedate" required><br>
          Lender Notes:<br>
          <textarea name="renewNoteLender" rows="6" cols="40" maxlength="255"></textarea><br>
          <button type="submit">Submit</button>
        </form>
        <?php
    }

// 2 = reject renew
} elseif ($renanswer === '2') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sql = "UPDATE `$sealSTAT`
                SET `renewNoteLender` = '$renewNoteLender',
                    `renewAnswer` = '2',
                    `renewTimeStamp` = '$timestamp',
                    `renewAccountLender` = '$wholename'
                WHERE `illNUB` = '$reqnumb'";
        if (mysqli_query($db, $sql)) {
            echo "Renewal request has been denied. <a href='/lender-history'>Back to lender history</a>";
            // Email borrower
            $res = mysqli_query($db, "SELECT Title,RequesterEMAIL FROM `$sealSTAT` WHERE `illNUB` = '$reqnumb' LIMIT 1");
            if ($res && ($value = mysqli_fetch_object($res))) {
                $reqemail = $value->RequesterEMAIL;
                $title    = $value->Title;
                $messagedest = "Your renewal request for ILL# $reqnumb ($title) has been denied. Please return by the original due date.<br>";
                if ($renewNoteLender) $messagedest .= "Lender Note: $renewNoteLender<br>";
                $subject = "SEAL Renewal Denied: ILL# $reqnumb";
                $headers = "From: Southeastern SEAL <donotreply@senylrc.org>\r\n".
                           "MIME-Version: 1.0\r\n".
                           "Content-Type: text/html; charset=UTF-8\r\n";
                mail($reqemail, $subject, $messagedest, $headers, "-f donotreply@senylrc.org");
            }
        } else {
            echo "Error: unable to deny renewal.";
        }
    } else {
        ?>
        <form method="post" action="/renew">
          <input type="hidden" name="a" value="2">
          <input type="hidden" name="num" value="<?php echo htmlspecialchars($reqnumb); ?>">
          Comments:<br>
          <textarea name="renewNoteLender" rows="6" cols="40" maxlength="255"></textarea><br>
          <button type="submit">Submit</button>
        </form>
        <?php
    }

// 3 = borrower requests renewal
} elseif ($renanswer === '3') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sql = "UPDATE `$sealSTAT`
                SET `renewTimeStamp` = '$timestamp',
                    `renewAccountRequester` = '$wholename',
                    `renewNote` = '$renewNote'
                WHERE `illNUB` = '$reqnumb'";
        if (mysqli_query($db, $sql)) {
            echo "Renewal request for ILL $reqnumb has been submitted. <a href='/requesthistory'>Back to request history</a>";
            // Email lender
            $res = mysqli_query($db, "SELECT Title,Destination,RequesterEMAIL FROM `$sealSTAT` WHERE `illNUB` = '$reqnumb' LIMIT 1");
            if ($res && ($value = mysqli_fetch_object($res))) {
                $lenderid = $value->Destination;
                $title    = $value->Title;
                $reqemail = $value->RequesterEMAIL;
                $res2 = mysqli_query($db, "SELECT ill_email FROM `$sealLIB` WHERE loc = '$lenderid' LIMIT 1");
                if ($res2 && ($rowdest = mysqli_fetch_assoc($res2))) {
                    $destemail = $rowdest["ill_email"];
                    $destemail_to = implode(',', explode(';', $destemail));
                    $messagedest = "$field_your_institution has requested a renewal for ILL# $reqnumb<br>Title: $title<br><br>";
                    $messagedest .= "Please respond in SEAL: Approve or Deny this renewal.<br>";
                    $subject = "SEAL Renewal Request: ILL# $reqnumb";
                    $headers = "From: Southeastern SEAL <donotreply@senylrc.org>\r\n".
                               "MIME-Version: 1.0\r\n".
                               "Content-Type: text/html; charset=UTF-8\r\n";
                    mail($destemail_to, $subject, $messagedest, $headers, "-f donotreply@senylrc.org");
                }
            }
        } else {
            echo "Error: unable to create renewal request.";
        }
    } else {
        ?>
        <h2>Renew ILL: <?php echo htmlspecialchars($reqnumb); ?></h2>
        <form method="post" action="/renew">
          <input type="hidden" name="a" value="3">
          <input type="hidden" name="num" value="<?php echo htmlspecialchars($reqnumb); ?>">
          Reason:<br>
          <textarea name="renewNote" rows="6" cols="40" maxlength="255"></textarea><br>
          <button type="submit">Submit</button>
        </form>
        <?php
    }

// 4 = lender edits due date
} elseif ($renanswer === '4') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sql = "UPDATE `$sealSTAT`
                SET `renewTimeStamp` = '$timestamp',
                    `renewAccountLender` = '$wholename',
                    `DueDate` = '$duedate'
                WHERE `illNUB` = '$reqnumb'";
        if (mysqli_query($db, $sql)) {
            echo "Due date updated. <a href='/lender-history'>Back to lender history</a>";
        } else {
            echo "Error: unable to update due date.";
        }
    } else {
        $res = mysqli_query($db, "SELECT DueDate FROM `$sealSTAT` WHERE `illNUB` = '$reqnumb' LIMIT 1");
        $current_due = '';
        if ($res && ($value = mysqli_fetch_object($res))) {
            $current_due = $value->DueDate;
        }
        ?>
        <h2>Edit Due Date for ILL# <?php echo htmlspecialchars($reqnumb); ?><br>Current: <?php echo htmlspecialchars($current_due); ?></h2>
        <form method="post" action="/renew">
          <input type="hidden" name="a" value="4">
          <input type="hidden" name="num" value="<?php echo htmlspecialchars($reqnumb); ?>">
          New Due Date: <input id="datepicker" name="duedate" required><br>
          <button type="submit">Submit</button>
        </form>
        <?php
    }

} else {
    echo "Invalid renewal request.";
}

mysqli_close($db);
?>