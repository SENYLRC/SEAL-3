<?php
/**
 * respond.php — ADA/WCAG improvements, same functionality + same visible display
 *
 * Accessibility changes only (no layout redesign):
 * - Adds screen-reader utility class + aria-live region for announcements
 * - Adds role="alert" to access-denied messages
 * - Fixes invalid hidden input markup (had a stray quote)
 * - Adds proper <label for> associations (or screen-reader-only labels)
 * - Groups related fields with <fieldset>/<legend> (legend hidden, so display stays the same)
 * - Adds required/aria-required where appropriate
 * - Adds basic ARIA guidance for the date field / datepicker
 * - Ensures textareas have labels (screen-reader-only) and preserves visible headings
 *
 * Source reviewed: :contentReference[oaicite:0]{index=0}
 */
?>

<link rel="stylesheet" href="https://seal.senylrc.org/assets/jquery-ui.css">
<script src="https://seal.senylrc.org/assets/jquery.min.js"></script>
<script src="https://seal.senylrc.org/assets/jquery-ui.min.js"></script>

<style>
/* Screen-reader-only utility (no visible change) */
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
    const currentYear = new Date().getFullYear();

    // Add accessible hints to the input (does not change display)
    $("#datepicker")
      .attr({
        "autocomplete":"off",
        "inputmode":"numeric",
        "aria-label":"Due date, format YYYY dash MM dash DD",
        "aria-describedby":"duedate-help"
      })
      .datepicker({
        dateFormat: "yy-mm-dd",
        changeMonth: true,
        changeYear: true,
        showAnim: "fadeIn",
        yearRange: currentYear + ":" + (currentYear + 3),
        minDate: new Date(currentYear, 0, 1),
        maxDate: new Date(currentYear + 3, 11, 31)
      })
      .on("change", function(){
        const sr = document.getElementById("sr-status");
        if(sr){
          sr.textContent = "";
          setTimeout(()=>{ sr.textContent = "Due date set to " + this.value; }, 20);
        }
      });
  });
</script>

<?php
// Load WordPress if needed
if (!defined('ABSPATH')) {
    require_once('/var/www/wpSEAL/wp-load.php');
}

// Restrict to Logged-In Administrator or Library Staff
if (!is_user_logged_in()) {
    die("<div style='padding:20px;color:red;font-weight:bold;' role='alert' aria-live='assertive'>
        Access Denied<br>You must be logged in to view this page.
    </div>");
}

$current_user = wp_get_current_user();
$user_roles   = (array)$current_user->roles;

if (!array_intersect(['administrator', 'libstaff'], $user_roles)) {
    die("<div style='padding:20px;color:red;font-weight:bold;' role='alert' aria-live='assertive'>
        Access Denied<br>You must have the <b>Administrator</b> or <b>Library Staff</b> role to access this page.
    </div>");
}

$user_id = $current_user->ID;

if (get_current_user_id() !== (int)$user_id && !current_user_can('edit_user', $user_id)) {
    wp_die('You do not have permission to edit this profile.');
}

// respond.php — Lender response page

// --- Config / Setup ---
$illsystemhost = $_SERVER["SERVER_NAME"];

// Connect to database
require '/var/www/seal_wp_script/seal_db.inc';
require '/var/www/seal_wp_script/seal_function.php'; // ensure $sealSTAT, $sealLIB are available
$db = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

// Get values from POST/REQUEST safely
$reqnumb    = $_POST["num"]        ?? ($_REQUEST["num"] ?? '');
$reqanswer  = $_POST["fill"]       ?? ($_REQUEST["fill"] ?? '');
$FromLender = $_POST["FromLender"] ?? ($_REQUEST["FromLender"] ?? '');

// Escape values AFTER db connection
$reqnumb   = mysqli_real_escape_string($db, $reqnumb);
$reqanswer = mysqli_real_escape_string($db, $reqanswer);

function esc_out($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $FromLender != 1) {

    $respnote     = $_REQUEST["respondnote"]  ?? '';
    $resfill      = $_REQUEST["fill"]         ?? '';
    $duedate      = $_REQUEST["duedate"]      ?? '';
    $nofillreason = $_REQUEST["nofillreason"] ?? '';
    $shipmethod   = $_REQUEST["shipmethod"]   ?? '';

    // Escape values for security
    $respnote = mysqli_real_escape_string($db, $respnote);
    $resfill  = mysqli_real_escape_string($db, $resfill);
    $todaydate = date("Y-m-d");

    $sqlupdate = "UPDATE `$sealSTAT`
                  SET `emailsent` = '1',
                      `Fill` = '$resfill',
                      `responderNOTE` = '$respnote',
                      `shipMethod` = '$shipmethod',
                      `DueDate` = '$duedate',
                      `ReasonNotFilled` = '$nofillreason',
                      `fillNofillDate` = '$todaydate'
                  WHERE `illNUB` = '$reqnumb'";

    if (mysqli_query($db, $sqlupdate)) {

        echo "<div role='status' aria-live='polite'>Thank you. Your response has been recorded to the request.</div>";
        echo "<a href='/lender-history'>Click here to view your lender history</a><br>";

        // Setup the note data to be in email
        $respnote = stripslashes($respnote);
        if (strlen($respnote) > 0) {
            $respnote = "The lending library has noted the following <br> $respnote";
        }

        $sqlselect = "SELECT responderNOTE, requesterEMAIL, Title, Destination
                      FROM `$sealSTAT`
                      WHERE illNUB='$reqnumb'
                      LIMIT 1";
        $result = mysqli_query($db, $sqlselect);
        $row = mysqli_fetch_array($result);

        $title         = $row['Title'];
        $requesterEMAIL= $row['requesterEMAIL'];
        $destlib       = $row['Destination'];

        // Get the Destination Name
        $GETLISTSQLDEST="SELECT `Name`, `ill_email` FROM `$sealLIB` WHERE loc like '$destlib' LIMIT 1";
        $resultdest=mysqli_query($db, $GETLISTSQLDEST);
        while ($rowdest = mysqli_fetch_assoc($resultdest)) {
            $destlib   = $rowdest["Name"];
            $destemail = $rowdest["ill_email"];
        }

        // In case multiple, break it down to comma for php mail
        $destemailarray = explode(';', $destemail ?? '');
        $destemail_to = implode(',', array_map('trim', $destemailarray));

        $headers = "From: Southeastern SEAL <donotreply@senylrc.org>\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";

        // sending filled email
        if ($resfill == '1') {

            $shiptxt = '';
            if($shipmethod=="usps")   { $shiptxt='US Mail'; }
            if($shipmethod=="mhls")   { $shiptxt='Mid-Hudson Courier'; }
            if($shipmethod=="rcls")   { $shiptxt='RCLS Courier'; }
            if($shipmethod=="empire") { $shiptxt='Empire Delivery'; }
            if($shipmethod=="ups")    { $shiptxt='UPS'; }
            if($shipmethod=="fedex")  { $shiptxt='FedEx'; }
            if($shipmethod=="other")  { $shiptxt='Other'; }

            $message = "Your ILL request $reqnumb for $title will be filled by $destlib <br>" .
                       "Due Date: $duedate<br><br>" .
                       "Shipped via: $shiptxt<br><br>$respnote " .
                       "<br><br>Please email <b>".$destemail_to."</b> for future communications regarding this request ";

            $to = $requesterEMAIL;
            $subject = "ILL Request Filled ILL# $reqnumb";

            $message = preg_replace('/(?<!\r)\n/', "\r\n", $message);
            $headers = preg_replace('/(?<!\r)\n/', "\r\n", $headers);
            mail($to, $subject, $message, $headers, "-f donotreply@senylrc.org");

        } else {

            $reasontxt = '';
            if ($nofillreason=='20') { $reasontxt='In Use'; }
            if ($nofillreason=='21') { $reasontxt='Lost'; }
            if ($nofillreason=='22') { $reasontxt='Non-Circulating'; }
            if ($nofillreason=='23') { $reasontxt='Not on shelf'; }
            if ($nofillreason=='24') { $reasontxt='Poor condition'; }
            if ($nofillreason=='25') { $reasontxt='Too New'; }

            $message = "Your ILL request $reqnumb for $title can not be filled by $destlib.<br>" .
                       "Reason request can not be filled: $reasontxt" .
                       "<br><br>$respnote<br><br> <a href='http://".$illsystemhost."'>Would you like to try a different library</a>?";

            $to = $requesterEMAIL;
            $subject = "ILL Request Not Filled ILL# $reqnumb";

            $message = preg_replace('/(?<!\r)\n/', "\r\n", $message);
            $headers = preg_replace('/(?<!\r)\n/', "\r\n", $headers);
            mail($to, $subject, $message, $headers, "-f donotreply@senylrc.org");
        }

    } else {
        echo "<div role='alert' aria-live='assertive'>Unable to record answer for ILL request " . esc_out($reqnumb) . ". Please call SENYLRC to report this error.</div>";
    }

} else {

    // The Request will be filled
    if ($reqanswer == '1') {

        echo "<div role='status' aria-live='polite'>Please click the submit button to confirm you will fill the request. Thank you.</div>";
        echo "<h6>Recommend shipping methods</h6>";

        // Determine borrower/lender shipping compatibility (unchanged logic)
        $LibInLISTSQL="SELECT `Requester LOC`, `Destination` FROM `$sealSTAT` WHERE `illNUB` = '$reqnumb'";
        $LibGETLIST = mysqli_query($db, $LibInLISTSQL);
        $Librow = mysqli_fetch_assoc($LibGETLIST);

        $reqlib = $Librow["Requester LOC"];
        $destlib = strtolower($Librow["Destination"]);

        $getlenderoptions="SELECT lbsyscourier,lbUSPS,lbEmpire,lbCommCourier FROM `$sealLIB` WHERE `loc` ='$destlib'";
        $GETLISTlendOPT = mysqli_query($db, $getlenderoptions);
        $rowlendopt = mysqli_fetch_assoc($GETLISTlendOPT);

        $lbsyscourier1 = $rowlendopt["lbsyscourier"];
        $lbUSPS1       = $rowlendopt["lbUSPS"];
        $lbEmpire1     = $rowlendopt["lbEmpire"];
        $lbCommCourier1= $rowlendopt["lbCommCourier"];

        $getborrowptions="SELECT lbsyscourier,lbUSPS,lbEmpire,lbCommCourier FROM `$sealLIB` WHERE `loc` ='$reqlib'";
        $GETLISTborrowOPT = mysqli_query($db, $getborrowptions);
        $rowborrowdopt = mysqli_fetch_assoc($GETLISTborrowOPT);

        $lbsyscourier2  = $rowborrowdopt["lbsyscourier"];
        $lbUSPS2        = $rowborrowdopt["lbUSPS"];
        $lbEmpire2      = $rowborrowdopt["lbEmpire"];
        $lbCommCourier2 = $rowborrowdopt["lbCommCourier"];

        $illdelmes='0';
        if (($lbEmpire1 === "Yes") && ($lbEmpire2 === "Yes")) {
            echo "<p class='green-text'>OK to ship via Empire Library Delivery</p>";
            $illdelmes++;
        }
        if (($lbsyscourier1 === "Yes") && ($lbsyscourier2 === "Yes")) {
            echo "<p class='green-text'>OK to ship via Public Library System Courier</p>";
            $illdelmes++;
        }
        if (($lbUSPS1 === "Yes") && ($lbUSPS2 === "Yes")) {
            echo "<p class='green-text'>OK to ship via US Mail</p>";
            $illdelmes++;
        }
        if (($lbCommCourier1 === "Yes") && ($lbCommCourier2 === "Yes")) {
            echo "<p class='green-text'>OK to ship via Commercial Courier like FedEx or UPS</p>";
            $illdelmes++;
        }
        if ($illdelmes < 1) {
            echo "<p class='red-text'>Contact the borrowing library to discuss an appropriate delivery method. More delivery information can also be found on the <a target='_blank' href='https://libguides.senylrc.org/SEAL/Delivery'>SEAL libguide</a></p>";
        }
        ?>

        <br><br><h4>Please note the delivery method, tracking info, special handling, etc</h4>

        <form action="/respond" method="post" aria-labelledby="fill-form-title">
          <h2 id="fill-form-title" class="screen-reader-text">Confirm you will fill this request</h2>

          <!-- hidden values (fixed invalid markup) -->
          <input type="hidden" name="num" value="<?php echo esc_out($reqnumb); ?>">
          <input type="hidden" name="fill" value="1">
          <input type="hidden" name="nofillreason" value="0">

          <fieldset style="border:0;padding:0;margin:0;">
            <legend class="screen-reader-text">Fill details</legend>

            <label for="respondnote_fill" class="screen-reader-text">Delivery note</label>
            <textarea id="respondnote_fill" name="respondnote" rows="4" cols="50" aria-describedby="note-help"></textarea>
            <div id="note-help" class="screen-reader-text">Optional. Add tracking info, special handling, or other delivery notes.</div>
            <br>

            <label for="datepicker">Due Date <span aria-hidden="true">*</span></label><br>
            <input id="datepicker" name="duedate" required aria-required="true" placeholder="YYYY-MM-DD">
            <div id="duedate-help" class="screen-reader-text">
              Required. Enter in the format YYYY dash MM dash DD. A date picker is available.
            </div>
            <br>

            <label for="shipmethod">Ship Method <span aria-hidden="true">*</span></label><br>
            <select id="shipmethod" name="shipmethod" required aria-required="true">
              <option value=""></option>
              <option value="usps">US Mail</option>
              <option value="mhls">Mid-Hudson Courier</option>
              <option value="rcls">RCLS Courier</option>
              <option value="empire">Empire Delivery</option>
              <option value="ups">UPS</option>
              <option value="fedex">FedEx</option>
              <option value="other">Other</option>
            </select>
            <br><br>

            <input type="submit" value="Submit">
          </fieldset>
        </form>

        <?php
    } else {
        // The request will not be filled
        echo "<div role='status' aria-live='polite'>Please click the submit button to confirm you can not fill the request.</div>";
        ?>

        <br><br><h4>Would you like to add a note about the decline?<br></h4>

        <form action="/respond" method="post" aria-labelledby="nofill-form-title">
          <h2 id="nofill-form-title" class="screen-reader-text">Confirm you cannot fill this request</h2>

          <input type="hidden" name="num" value="<?php echo esc_out($reqnumb); ?>">
          <input type="hidden" name="fill" value="0">

          <fieldset style="border:0;padding:0;margin:0;">
            <legend class="screen-reader-text">Decline details</legend>

            <label for="nofillreason">Reason <span aria-hidden="true">*</span></label><br>
            <select id="nofillreason" name="nofillreason" required aria-required="true">
              <option value="0">Reason</option>
              <option value="20">In Use</option>
              <option value="21">Lost</option>
              <option value="22">Non-Circulating Format</option>
              <option value="23">Not on shelf</option>
              <option value="24">Poor condition</option>
              <option value="25">Too New</option>
            </select>
            <br><br>

            <label for="respondnote_nofill" class="screen-reader-text">Decline note</label>
            <textarea id="respondnote_nofill" name="respondnote" rows="4" cols="50" aria-describedby="decline-help"></textarea>
            <div id="decline-help" class="screen-reader-text">Optional. Provide additional context about why the request cannot be filled.</div>
            <br>

            <input type="submit" value="Submit">
          </fieldset>
        </form>

        <?php
    }
}

mysqli_close($db);
?>