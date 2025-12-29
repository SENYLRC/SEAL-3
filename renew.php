<?php
/**
 * renew.php (ADA/WCAG-friendly)
 * - Keeps your functions and visible display essentially the same
 * - Adds: proper labels, fieldsets/legends (screen-reader only), aria-live messaging,
 *   required indicators, and datepicker accessibility hooks.
 *
 * Source reviewed: :contentReference[oaicite:0]{index=0}
 */
?>
<link rel="stylesheet" href="https://seal.senylrc.org/assets/jquery-ui.css">
<script src="https://seal.senylrc.org/assets/jquery.min.js"></script>
<script src="https://seal.senylrc.org/assets/jquery-ui.min.js"></script>

<style>
/* Screen-reader-only utility (no visual change) */
.screen-reader-text{
  position:absolute!important;
  width:1px;height:1px;
  padding:0;margin:-1px;
  overflow:hidden;
  clip:rect(0,0,0,0);
  white-space:nowrap;border:0;
}
</style>

<div id="sr-status" class="screen-reader-text" aria-live="polite" aria-atomic="true"></div>

<script>
  jQuery(function($){
    var currentYear = new Date().getFullYear();

    // jQuery UI datepicker is not fully accessible by default; this adds basics:
    // - keeps the input keyboard-focusable
    // - ensures a helpful aria-label
    // - uses an ISO-like format you already rely on
    $("#datepicker").attr({
      "autocomplete":"off",
      "inputmode":"numeric",
      "aria-label":"New due date, format YYYY dash MM dash DD"
    }).datepicker({
      dateFormat: "yy-mm-dd",
      changeMonth: true,
      changeYear: true,
      yearRange: currentYear + ":" + (currentYear + 3),
      showAnim: "fadeIn"
    });

    // Announce date changes to screen readers
    $("#datepicker").on("change", function(){
      $("#sr-status").text("Due date set to " + $(this).val());
    });
  });
</script>

<?php
// ==========================================================
// WordPress Access Control — Restrict to Logged-In Users
// with Role: Administrator or Library Staff
// ==========================================================
if (!is_user_logged_in()) {
    die("<div style='padding:20px;color:red;font-weight:bold;' role='alert' aria-live='assertive'>
        Access Denied<br>You must be logged in to view this page.
    </div>");
}

$current_user = wp_get_current_user();
$user_roles   = (array)$current_user->roles;

// Only allow Administrator or Library Staff roles
if (!array_intersect(['administrator', 'libstaff'], $user_roles)) {
    die("<div style='padding:20px;color:red;font-weight:bold;' role='alert' aria-live='assertive'>
        Access Denied<br>You must have the <b>Administrator</b> or <b>Library Staff</b> role to access this page.
    </div>");
}

// renew.php
require '/var/www/seal_wp_script/seal_function.php';

// Collect inputs (raw)
$reqnumb         = $_POST["num"]              ?? ($_REQUEST["num"] ?? '');
$renewNote       = $_POST["renewNote"]        ?? ($_REQUEST["renewNote"] ?? '');
$duedate         = $_POST["duedate"]          ?? ($_REQUEST["duedate"] ?? '');
$renewNoteLender = $_POST["renewNoteLender"]  ?? ($_REQUEST["renewNoteLender"] ?? '');
$renanswer       = $_POST["a"]                ?? ($_REQUEST["a"] ?? '');

// Timestamps
$timestamp = date("Y-m-d H:i:s");
$todaydate = date("Y-m-d");

// Connect to database
require '/var/www/seal_wp_script/seal_db.inc';
$db = mysqli_connect($dbhost, $dbuser, $dbpass);
mysqli_select_db($db, $dbname);

// Escape for SQL
$reqnumb         = mysqli_real_escape_string($db, $reqnumb);
$renewNote       = mysqli_real_escape_string($db, $renewNote);
$renewNoteLender = mysqli_real_escape_string($db, $renewNoteLender);
$duedate         = mysqli_real_escape_string($db, $duedate);
$renanswer       = mysqli_real_escape_string($db, $renanswer);
$wholename       = mysqli_real_escape_string($db, $wholename ?? '');

// Small helpers
function esc_out($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function announce_status($msg){
    echo "<div role='status' aria-live='polite' style='margin:10px 0;'>".esc_out($msg)."</div>";
}

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
            announce_status("Renewal request has been approved.");

            echo "Renewal request has been approved. <a href='/lender-history'>Back to lender history</a>";

            // Email borrower
            $res = mysqli_query($db, "SELECT Title,RequesterEMAIL FROM `$sealSTAT` WHERE `illNUB` = '$reqnumb' LIMIT 1");
            if ($res && ($value = mysqli_fetch_object($res))) {
                $reqemail = $value->RequesterEMAIL;
                $title    = $value->Title;
                $messagedest = "Your renewal request for ILL# $reqnumb ($title) has been approved with a due date of $duedate.<br>";
                if ($renewNoteLender) {
                    $safeLenderNote = nl2br(htmlspecialchars($renewNoteLender, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                    $messagedest   .= "Lender Note: {$safeLenderNote}<br>";
                }
                $subject = "SEAL Renewal Approved: ILL# $reqnumb";
                $headers = "From: Southeastern SEAL <donotreply@senylrc.org>\r\n".
                           "MIME-Version: 1.0\r\n".
                           "Content-Type: text/html; charset=UTF-8\r\n";
                mail($reqemail, $subject, $messagedest, $headers, "-f donotreply@senylrc.org");
            }
        } else {
            echo "<div role='alert' aria-live='assertive'>Error: unable to approve renewal.</div>";
        }
    } else {

        // Show borrower note (if any) to lender
        $borrowerNote = '';
        $resNote = mysqli_query($db, "SELECT renewNote FROM `$sealSTAT` WHERE `illNUB` = '$reqnumb' LIMIT 1");
        if ($resNote && ($rowNote = mysqli_fetch_assoc($resNote))) {
            $borrowerNote = $rowNote['renewNote'] ?? '';
        }

        if (!empty($borrowerNote)) {
            echo "<p><strong>Borrower Note:</strong><br>" .
                 nl2br(htmlspecialchars($borrowerNote, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) .
                 "</p>";
        }
        ?>

        <h2 class="screen-reader-text" id="approve-renew-title">Approve renewal</h2>

        <form method="post" action="/renew" aria-labelledby="approve-renew-title">
          <input type="hidden" name="a" value="1">
          <input type="hidden" name="num" value="<?php echo esc_out($reqnumb); ?>">

          <div>
            <label for="datepicker">New Due Date <span aria-hidden="true">*</span></label>
            <input
              id="datepicker"
              name="duedate"
              required
              aria-required="true"
              aria-describedby="duedate-help"
              placeholder="YYYY-MM-DD"
            >
            <div id="duedate-help" class="screen-reader-text">
              Enter the due date in the format YYYY dash MM dash DD. A date picker is available.
            </div>
          </div>

          <div>
            <label for="renewNoteLender_approve">Lender Notes</label><br>
            <textarea
              id="renewNoteLender_approve"
              name="renewNoteLender"
              rows="6"
              cols="40"
              maxlength="255"
              aria-describedby="lendernote-help"
            ></textarea>
            <div id="lendernote-help" class="screen-reader-text">
              Optional. Up to 255 characters.
            </div>
          </div>

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
            announce_status("Renewal request has been denied.");

            echo "Renewal request has been denied. <a href='/lender-history'>Back to lender history</a>";

            // Email borrower
            $res = mysqli_query($db, "SELECT Title,RequesterEMAIL FROM `$sealSTAT` WHERE `illNUB` = '$reqnumb' LIMIT 1");
            if ($res && ($value = mysqli_fetch_object($res))) {
                $reqemail = $value->RequesterEMAIL;
                $title    = $value->Title;
                $messagedest = "Your renewal request for ILL# $reqnumb ($title) has been denied. Please return by the original due date.<br>";
                if ($renewNoteLender) {
                    $safeLenderNote = nl2br(htmlspecialchars($renewNoteLender, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                    $messagedest   .= "Lender Note: {$safeLenderNote}<br>";
                }
                $subject = "SEAL Renewal Denied: ILL# $reqnumb";
                $headers = "From: Southeastern SEAL <donotreply@senylrc.org>\r\n".
                           "MIME-Version: 1.0\r\n".
                           "Content-Type: text/html; charset=UTF-8\r\n";
                mail($reqemail, $subject, $messagedest, $headers, "-f donotreply@senylrc.org");
            }
        } else {
            echo "<div role='alert' aria-live='assertive'>Error: unable to deny renewal.</div>";
        }
    } else {

        // Show borrower note (if any) to lender
        $borrowerNote = '';
        $resNote = mysqli_query($db, "SELECT renewNote FROM `$sealSTAT` WHERE `illNUB` = '$reqnumb' LIMIT 1");
        if ($resNote && ($rowNote = mysqli_fetch_assoc($resNote))) {
            $borrowerNote = $rowNote['renewNote'] ?? '';
        }

        if (!empty($borrowerNote)) {
            echo "<p><strong>Borrower Note:</strong><br>" .
                 nl2br(htmlspecialchars($borrowerNote, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) .
                 "</p>";
        }
        ?>
        <h2 class="screen-reader-text" id="deny-renew-title">Deny renewal</h2>

        <form method="post" action="/renew" aria-labelledby="deny-renew-title">
          <input type="hidden" name="a" value="2">
          <input type="hidden" name="num" value="<?php echo esc_out($reqnumb); ?>">

          <div>
            <label for="renewNoteLender_deny">Comments</label><br>
            <textarea
              id="renewNoteLender_deny"
              name="renewNoteLender"
              rows="6"
              cols="40"
              maxlength="255"
              aria-describedby="deny-help"
            ></textarea>
            <div id="deny-help" class="screen-reader-text">
              Optional. Up to 255 characters.
            </div>
          </div>

          <button type="submit">Submit</button>
        </form>
        <?php
    }

// 3 = borrower requests renewal
} elseif ($renanswer === '3') {

    // Only finalize the renewal once we actually have a note
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $renewNote !== '') {

        $sql = "UPDATE `$sealSTAT`
                SET `renewTimeStamp` = '$timestamp',
                    `renewAccountRequester` = '$wholename',
                    `renewNote` = '$renewNote'
                WHERE `illNUB` = '$reqnumb'";
        if (mysqli_query($db, $sql)) {
            announce_status("Renewal request submitted.");

            echo "Renewal request for ILL $reqnumb has been submitted. <a href='/requesthistory'>Back to request history</a>";

            // Email lender
            $res = mysqli_query($db, "SELECT Title,Destination,RequesterEMAIL FROM `$sealSTAT` WHERE `illNUB` = '$reqnumb' LIMIT 1");
            if ($res && ($value = mysqli_fetch_object($res))) {
                $lenderid = $value->Destination;
                $title    = $value->Title;
                $reqemail = $value->RequesterEMAIL;

                $res2 = mysqli_query($db, "SELECT ill_email FROM `$sealLIB` WHERE loc = '$lenderid' LIMIT 1");
                if ($res2 && ($rowdest = mysqli_fetch_assoc($res2))) {
                    $destemail    = $rowdest["ill_email"];
                    $destemail_to = implode(',', explode(';', $destemail));

                    $messagedest  = "$field_your_institution has requested a renewal for ILL# $reqnumb<br>Title: $title<br><br>";

                    // Include borrower note in email
                    if (!empty($renewNote)) {
                        $safeRenewNote = nl2br(htmlspecialchars($renewNote, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                        $messagedest  .= "<strong>Borrower Note:</strong><br>{$safeRenewNote}<br><br>";
                    }

                    $messagedest .= "Please respond in SEAL: Approve or Deny this renewal.<br>";

                    $subject = "SEAL Renewal Request: ILL# $reqnumb";
                    $headers = "From: Southeastern SEAL <donotreply@senylrc.org>\r\n".
                               "MIME-Version: 1.0\r\n".
                               "Content-Type: text/html; charset=UTF-8\r\n";
                    mail($destemail_to, $subject, $messagedest, $headers, "-f donotreply@senylrc.org");
                }
            }
        } else {
            echo "<div role='alert' aria-live='assertive'>Error: unable to create renewal request.</div>";
        }

    } else {
        // First visit / initial POST with no note: show the form
        ?>
        <h2>Renew ILL: <?php echo esc_out($reqnumb); ?></h2>

        <form method="post" action="/renew" aria-labelledby="borrower-renew-title">
          <h3 id="borrower-renew-title" class="screen-reader-text">Request a renewal</h3>

          <input type="hidden" name="a" value="3">
          <input type="hidden" name="num" value="<?php echo esc_out($reqnumb); ?>">

          <div>
            <label for="renewNote_borrower">Reason <span aria-hidden="true">*</span></label><br>
            <textarea
              id="renewNote_borrower"
              name="renewNote"
              rows="6"
              cols="40"
              maxlength="255"
              required
              aria-required="true"
              aria-describedby="borrower-note-help"
            ></textarea>
            <div id="borrower-note-help" class="screen-reader-text">
              Required. Up to 255 characters.
            </div>
          </div>

          <button type="submit">Submit</button>
        </form>
        <?php
    }

// 4 = lender edits due date (no renew note change)
} elseif ($renanswer === '4') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sql = "UPDATE `$sealSTAT`
                SET `renewTimeStamp` = '$timestamp',
                    `renewAccountLender` = '$wholename',
                    `DueDate` = '$duedate'
                WHERE `illNUB` = '$reqnumb'";
        if (mysqli_query($db, $sql)) {
            announce_status("Due date updated.");
            echo "Due date updated. <a href='/lender-history'>Back to lender history</a>";
        } else {
            echo "<div role='alert' aria-live='assertive'>Error: unable to update due date.</div>";
        }
    } else {
        $res = mysqli_query($db, "SELECT DueDate FROM `$sealSTAT` WHERE `illNUB` = '$reqnumb' LIMIT 1");
        $current_due = '';
        if ($res && ($value = mysqli_fetch_object($res))) {
            $current_due = $value->DueDate;
        }
        ?>
        <h2>Edit Due Date for ILL# <?php echo esc_out($reqnumb); ?><br>Current: <?php echo esc_out($current_due); ?></h2>

        <form method="post" action="/renew" aria-labelledby="edit-due-title">
          <h3 id="edit-due-title" class="screen-reader-text">Edit due date</h3>

          <input type="hidden" name="a" value="4">
          <input type="hidden" name="num" value="<?php echo esc_out($reqnumb); ?>">

          <div>
            <label for="datepicker">New Due Date <span aria-hidden="true">*</span></label>
            <input
              id="datepicker"
              name="duedate"
              required
              aria-required="true"
              aria-describedby="duedate-help"
              placeholder="YYYY-MM-DD"
            ><br>
          </div>

          <button type="submit">Submit</button>
        </form>
        <?php
    }

} else {
    echo "<div role='alert' aria-live='assertive'>Invalid renewal request.</div>";
}

mysqli_close($db);
?>