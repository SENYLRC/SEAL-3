<link rel="stylesheet" href="https://sealbeta.senylrc.org/assets/jquery-ui.css">
<script src="https://sealbeta.senylrc.org/assets/jquery.min.js"></script>
<script src="https://sealbeta.senylrc.org/assets/jquery-ui.min.js"></script>

<!-- Load the separated CSS file; adjust path if needed -->
<link rel="stylesheet" href="/assets/css/libprofile.css">

<script>
  jQuery(function($){
    $("#datepicker").datepicker({
      dateFormat: "yy-mm-dd",
      changeMonth: true,
      changeYear: true,
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
// libprofile.php###
require '/var/www/seal_wp_script/seal_function.php';
// Get loc from user profile
$loc = $field_loc_location_code;

// Connect to database
require '/var/www/seal_wp_script/seal_db.inc';
$db = mysqli_connect($dbhost, $dbuser, $dbpass);
mysqli_select_db($db, $dbname);

// Helper to safely echo attribute values
function h($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $timestamp    = date("Y-m-d H:i:s");
    $libname      = $_REQUEST["libname"] ?? '';
    $libemail     = $_REQUEST["libemail"] ?? '';
    $phone        = $_REQUEST["phone"] ?? '';
    $address1     = $_REQUEST["address1"] ?? '';
    $address2     = $_REQUEST["address2"] ?? '';
    $address3     = $_REQUEST["address3"] ?? '';
    $oclc         = $_REQUEST["oclc"] ?? '';
    $suspend      = $_REQUEST["suspend"] ?? '0';
    $book         = $_REQUEST["book"] ?? 'No';
    $av           = $_REQUEST["av"] ?? 'No';
    $journal      = $_REQUEST["journal"] ?? 'No';
    $ebook        = $_REQUEST["ebook"] ?? 'No';
    $ejournal     = $_REQUEST["ejournal"] ?? 'No';
    $reference    = $_REQUEST["reference"] ?? 'No';
    $enddated     = $_REQUEST["enddate"] ?? '';
    $lbsyscourier = $_REQUEST["lbsyscourier"] ?? 'No';
    $lbUSPS       = $_REQUEST["lbUSPS"] ?? 'No';
    $lbEmpire     = $_REQUEST["lbEmpire"] ?? 'No';
    $lbCommCourier= $_REQUEST["lbCommCourier"] ?? 'No';
    $lastmodemail = $email; // from seal_function.php context

    // Sanitize for DB
    $libname       = mysqli_real_escape_string($db, $libname);
    $libemail      = mysqli_real_escape_string($db, trim($libemail));
    $phone         = mysqli_real_escape_string($db, $phone);
    $address1      = mysqli_real_escape_string($db, $address1);
    $address2      = mysqli_real_escape_string($db, $address2);
    $address3      = mysqli_real_escape_string($db, $address3);
    $oclc          = mysqli_real_escape_string($db, trim($oclc));
    $suspend       = mysqli_real_escape_string($db, $suspend);
    $book          = mysqli_real_escape_string($db, $book);
    $journal       = mysqli_real_escape_string($db, $journal);
    $av            = mysqli_real_escape_string($db, $av);
    $ebook         = mysqli_real_escape_string($db, $ebook);
    $ejournal      = mysqli_real_escape_string($db, $ejournal);
    $reference     = mysqli_real_escape_string($db, $reference);
    $lbsyscourier  = mysqli_real_escape_string($db, $lbsyscourier);
    $lbUSPS        = mysqli_real_escape_string($db, $lbUSPS);
    $lbEmpire      = mysqli_real_escape_string($db, $lbEmpire);
    $lbCommCourier = mysqli_real_escape_string($db, $lbCommCourier);

    // Suspension date default: +7 days if suspending with no date
    if (($suspend == '1') && (strlen($enddated) < 2)) {
        $enddated = date('Y-m-d', strtotime("+7 day"));
    } else {
        $enddated = date('Y-m-d', strtotime(str_replace('-', '/', $enddated)));
    }

    $sqlupdate = "
        UPDATE `$sealLIB`
           SET Name = '$libname',
               `ill_email` = '$libemail',
               suspend = $suspend,
               phone = '$phone',
               address1 = '$address1',
               address2 = '$address2',
               address3 = '$address3',
               oclc = '$oclc',
               book_loan = '$book',
               periodical_loan = '$journal',
               av_loan = '$av',
               ebook_request = '$ebook',
               ejournal_request = '$ejournal',
               theses_loan = '$reference',
               SuspendDateEnd = '$enddated',
               ModifyDate = '$timestamp',
               ModEmail = '$lastmodemail',
               lbsyscourier = '$lbsyscourier',
               lbUSPS = '$lbUSPS',
               lbEmpire = '$lbEmpire',
               lbCommCourier = '$lbCommCourier'
         WHERE `loc` = '".mysqli_real_escape_string($db, $loc)."'";

    $result = mysqli_query($db, $sqlupdate);

    echo "<div class='LibProfile_Form'><div class='section-card notice-success'><strong>Library has been edited.</strong></div>";
    echo "<a href='/libprofile'>Return to Library Profile</a></div>";

} else {
    // Load current values
    $GETLISTSQL = "SELECT * FROM `$sealLIB` WHERE `loc` = '".mysqli_real_escape_string($db, $loc)."' LIMIT 1";
    $GETLIST    = mysqli_query($db, $GETLISTSQL);

    // Initialize vars
    $libname = $libalias = $libemail = $oclc = $phone = $address1 = $address2 = $address3 = '';
    $libparticipant = $libsuspend = $system = $book = $reference = $av = $ebook = $ejournal = $journal = '';
    $enddate = $timestamp = $lastmodemail = $libilliad = $libilliaddate = $libemailalert = '';
    $lbsyscourier = $lbUSPS = $lbEmpire = $lbCommCourier = '';

    if ($GETLIST && ($row = mysqli_fetch_assoc($GETLIST))) {
        $libname        = $row["Name"];
        $libalias       = $row["alias"];
        $libemail       = $row["ill_email"];
        $oclc           = $row["oclc"];
        $loc            = $row["loc"];
        $phone          = $row["phone"];
        $address1       = $row["address1"];
        $address2       = $row["address2"];
        $address3       = $row["address3"];
        $libparticipant = $row["participant"];
        $libsuspend     = $row["suspend"];
        $system         = $row["system"];
        $book           = $row["book_loan"];
        $reference      = $row["theses_loan"];
        $av             = $row["av_loan"];
        $ebook          = $row["ebook_request"];
        $ejournal       = $row["ejournal_request"];
        $journal        = $row["periodical_loan"];
        $enddate        = $row["SuspendDateEnd"];
        $timestamp      = $row["ModifyDate"];
        $lastmodemail   = $row["ModEmail"];
        $libilliad      = $row["Illiad"];
        $libilliaddate  = $row["IlliadDATE"];
        $libemailalert  = $row["LibEmailAlert"];
        $lbsyscourier   = $row["lbsyscourier"];
        $lbUSPS         = $row["lbUSPS"];
        $lbEmpire       = $row["lbEmpire"];
        $lbCommCourier  = $row["lbCommCourier"];
    }

    if ($loc != 'null') {
?>
<div class="LibProfile_Form">

  <form action="/libprofile?<?php echo h($_SERVER['QUERY_STRING'] ?? ''); ?>" method="post">
    <input type="hidden" name="loc" value="<?php echo h($loc); ?>">

    <!-- Library Details -->
    <div class="section-card">
      <h4>Library Details</h4>
      <div class="form-section">

        <div class="form-group">
          <label for="libname">Library Name</label>
          <input id="libname" type="text" name="libname" maxlength="255" value="<?php echo h($libname); ?>">
        </div>

        <div class="form-group">
          <label>Library Alias</label>
          <div class="pill"><?php echo h($libalias ?: '—'); ?></div>
        </div>

        <div class="form-group">
          <label for="libemail">Library ILL Email</label>
          <input id="libemail" type="text" name="libemail" maxlength="255" value="<?php echo h($libemail); ?>">
        </div>

        <div class="form-group">
          <label for="phone">Library Phone</label>
          <input id="phone" type="text" name="phone" maxlength="255" value="<?php echo h($phone); ?>">
        </div>

        <div class="form-group">
          <label for="address1">Library Address Dept</label>
          <input id="address1" type="text" name="address1" maxlength="255" value="<?php echo h($address1); ?>">
        </div>

        <div class="form-group">
          <label for="address2">Library Address Street</label>
          <input id="address2" type="text" name="address2" maxlength="255" value="<?php echo h($address2); ?>">
        </div>

        <div class="form-group">
          <label for="address3">Library Address City, State and Zip</label>
          <input id="address3" type="text" name="address3" maxlength="255" value="<?php echo h($address3); ?>">
        </div>

        <div class="form-group">
          <label for="oclc">OCLC Symbol</label>
          <input id="oclc" type="text" name="oclc" maxlength="255" value="<?php echo h($oclc); ?>">
        </div>

        <div class="form-group">
          <label>LOC Location</label>
          <div class="pill"><?php echo h($loc); ?></div>
        </div>

        <div class="form-group">
          <label>Lib Email Alert</label>
          <div class="pill"><?php echo ($libemailalert=='1' ? 'Yes' : 'No'); ?></div>
        </div>

 <?php if ($libilliad=='1') { ?>
  <div class="form-group">
    <label>Lib ILLiad API</label>
    <div class="pill">Yes</div>
  </div>

  <div class="form-group">
    <label>ILLiad Due Date Days</label>
    <div class="pill"><?php echo h($libilliaddate ?: '—'); ?></div>
  </div>
<?php } ?>

        <div class="form-group">
          <label>Library System</label>
          <div class="pill">
            <?php
              $systemMap = [
                'DU'=>'Dutchess BOCES','MH'=>'Mid-Hudson Library System','OU'=>'Orange Ulster BOCES',
                'RC'=>'Ramapo Catskill Library System','RB'=>'Rockland BOCES','SE'=>'SENYLRC','SB'=>'Sullivan BOCES'
              ];
              echo h($systemMap[$system] ?? $system);
            ?>
          </div>
        </div>

      </div>
    </div>

    <!-- Suspension -->
    <div class="section-card">
      <h4>Suspension</h4>
      <div class="form-section">

        <div class="form-group">
          <label for="suspend">Suspend Your Library’s lending status?</label>
          <select id="suspend" name="suspend">
            <option value="0" <?php if ($libsuspend=="0") echo "selected='selected'"; ?>>No</option>
            <option value="1" <?php if ($libsuspend=="1") echo "selected='selected'"; ?>>Yes</option>
          </select>
          <div class="helper">
            Setting this to <strong>YES</strong> will <strong>prevent</strong> your library from getting ILL requests.<br>
            Setting this to <strong>NO</strong> will <strong>allow</strong> your library to receive ILL requests.
          </div>
        </div>

        <div class="form-group">
          <label for="datepicker">Suspension End Date</label>
          <input id="datepicker" name="enddate" value="<?php echo h($enddate ?: ''); ?>">
          <div class="helper">If no date is picked, the system will default to seven (7) days.</div>
        </div>

        <div class="form-group">
          <?php if ($libsuspend=="1") { ?>
            <div class="pill"><?php echo h($libname); ?> will not receive requests until <strong><?php echo h($enddate); ?></strong></div>
          <?php } else { ?>
            <div class="pill"><?php echo h($libname); ?> is currently receiving requests.</div>
          <?php } ?>
        </div>

      </div>
    </div>

    <!-- Delivery Options -->
    <div class="section-card">
      <h4>Delivery Options</h4>
      <div class="form-section">

        <div class="form-group">
          <label>Empire Library Delivery</label>
          <div class="inline-options">
            <label class="choice"><input type="radio" name="lbEmpire" value="Yes" <?php if ($lbEmpire=="Yes") echo "checked"; ?>> Yes</label>
            <label class="choice"><input type="radio" name="lbEmpire" value="No"  <?php if ($lbEmpire=="No")  echo "checked"; ?>> No</label>
          </div>
        </div>

        <div class="form-group">
          <label>Public Library System Courier (MHLS or RCLS)</label>
          <div class="inline-options">
            <label class="choice"><input type="radio" name="lbsyscourier" value="Yes" <?php if ($lbsyscourier=="Yes") echo "checked"; ?>> Yes</label>
            <label class="choice"><input type="radio" name="lbsyscourier" value="No"  <?php if ($lbsyscourier=="No")  echo "checked"; ?>> No</label>
          </div>
        </div>

        <div class="form-group">
          <label>US Mail</label>
          <div class="inline-options">
            <label class="choice"><input type="radio" name="lbUSPS" value="Yes" <?php if ($lbUSPS=="Yes") echo "checked"; ?>> Yes</label>
            <label class="choice"><input type="radio" name="lbUSPS" value="No"  <?php if ($lbUSPS=="No")  echo "checked"; ?>> No</label>
          </div>
        </div>

        <div class="form-group">
          <label>Commercial Courier (UPS or FedEx)</label>
          <div class="inline-options">
            <label class="choice"><input type="radio" name="lbCommCourier" value="Yes" <?php if ($lbCommCourier=="Yes") echo "checked"; ?>> Yes</label>
            <label class="choice"><input type="radio" name="lbCommCourier" value="No"  <?php if ($lbCommCourier=="No")  echo "checked"; ?>> No</label>
          </div>
        </div>

      </div>
    </div>

    <!-- Items Willing to Loan -->
    <div class="section-card">
      <h4>Items Willing to Loan</h4>
      <div class="form-section">

        <div class="form-group">
          <label>Print Book</label>
          <div class="inline-options">
            <label class="choice"><input type="radio" name="book" value="Yes" <?php if ($book=="Yes") echo "checked"; ?>> Yes</label>
            <label class="choice"><input type="radio" name="book" value="No"  <?php if ($book=="No")  echo "checked"; ?>> No</label>
          </div>
        </div>

        <div class="form-group">
          <label>Print Journal or Article</label>
          <div class="inline-options">
            <label class="choice"><input type="radio" name="journal" value="Yes" <?php if ($journal=="Yes") echo "checked"; ?>> Yes</label>
            <label class="choice"><input type="radio" name="journal" value="No"  <?php if ($journal=="No")  echo "checked"; ?>> No</label>
          </div>
        </div>

        <div class="form-group">
          <label>Audio Video Materials</label>
          <div class="inline-options">
            <label class="choice"><input type="radio" name="av" value="Yes" <?php if ($av=="Yes") echo "checked"; ?>> Yes</label>
            <label class="choice"><input type="radio" name="av" value="No"  <?php if ($av=="No")  echo "checked"; ?>> No</label>
          </div>
        </div>

        <div class="form-group">
          <label>Reference/Microfilm</label>
          <div class="inline-options">
            <label class="choice"><input type="radio" name="reference" value="Yes" <?php if ($reference=="Yes") echo "checked"; ?>> Yes</label>
            <label class="choice"><input type="radio" name="reference" value="No"  <?php if ($reference=="No")  echo "checked"; ?>> No</label>
          </div>
        </div>

        <div class="form-group">
          <label>Electronic Book</label>
          <div class="inline-options">
            <label class="choice"><input type="radio" name="ebook" value="Yes" <?php if ($ebook=="Yes") echo "checked"; ?>> Yes</label>
            <label class="choice"><input type="radio" name="ebook" value="No"  <?php if ($ebook=="No")  echo "checked"; ?>> No</label>
          </div>
        </div>

        <div class="form-group">
          <label>Electronic Journal</label>
          <div class="inline-options">
            <label class="choice"><input type="radio" name="ejournal" value="Yes" <?php if ($ejournal=="Yes") echo "checked"; ?>> Yes</label>
            <label class="choice"><input type="radio" name="ejournal" value="No"  <?php if ($ejournal=="No")  echo "checked"; ?>> No</label>
          </div>
        </div>

      </div>
    </div>

    <div class="actions">
      <strong>Please click on Submit to save your profile.</strong><br><br>
      <input class="btn-primary" type="submit" value="Submit">
    </div>
  </form>

  <br><br>
  Last Modified: <?php echo h($timestamp); ?> by <?php echo h($lastmodemail); ?>

</div>
<?php
    } // end if $loc != 'null'
} // end GET
?>