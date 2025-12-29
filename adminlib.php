<link rel="stylesheet" href="https://seal.senylrc.org/assets/jquery-ui.css">
<script src="https://seal.senylrc.org/assets/jquery.min.js"></script>
<script src="https://seal.senylrc.org/assets/jquery-ui.min.js"></script>


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
// WordPress Role Enforcement — Restrict to Administrator
// ==========================================================
require_once('/var/www/wpSEAL/wp-load.php');
$current_user = wp_get_current_user();
$user_roles = (array)$current_user->roles;

if (!in_array('administrator', $user_roles, true)) {
    die("<div style='padding:20px;color:red;font-weight:bold;'>
        Access Denied<br>You must have the <b>Administrator</b> role to access this page.
    </div>");
}


/**
 * adminlib.php  — SEAL Admin: Library Directory
 * - Modernized layout (matches your CSS)
 * - Persistent filters + sane first-load defaults
 * - Clean radio layouts for delivery/options
 * - Optional SQL debug panel
 */


session_id('YOUR_SESSION_ID');
session_start();

require '/var/www/seal_wp_script/seal_function.php';
require '/var/www/seal_wp_script/seal_db.inc';

// ---------- CONFIG ----------
$debug = false; // set true to show SQL + counts
// ----------------------------

// Connect DB
$db = mysqli_connect($dbhost, $dbuser, $dbpass);
mysqli_select_db($db, $dbname);

// Table names (usually in seal_function.php, but we’ll be defensive)
if (!isset($sealLIB) || !$sealLIB) {
    $sealLIB = 'sealLIB';
}

// Helpers (don’t rely on theme functions)
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function checked_val($v)
{
    return ($v === "yes" || $v === 1 || $v === "1") ? "checked" : "";
}
function selected_val($cur, $target)
{
    return ((string)$cur === (string)$target) ? "selected" : "";
}

// Read initial flags
$firstpass = (isset($_REQUEST['firstpass']) ? "no" : "yes");

// ----- Filter state -----
if ($firstpass === "no") {
    // Pull current filters from request
    $filter_library     = $_REQUEST['library']          ?? "";
    $filter_loc         = $_REQUEST['loc']              ?? "";
    $filter_alias       = $_REQUEST['filter_alias']     ?? "";
    $filter_illemail    = $_REQUEST['filter_illemail']  ?? "";
    $filter_numresults  = $_REQUEST['filter_numresults'] ?? "25";
    $filter_offset      = $_REQUEST['filter_offset']    ?? "0";

    // Checkbox-ish filters
    $filter_aliasblank  = $_REQUEST['filter_aliasblank']   ?? "";
    $filter_illemailblank = $_REQUEST['filter_illemailblank'] ?? "";
    $filter_illpart     = $_REQUEST['filter_illpart']     ?? "";
    $filter_suspend     = $_REQUEST['filter_suspend']     ?? "";
    $filter_all_libs    = $_REQUEST['filter_all_libs']    ?? "";
    $filter_system      = $_REQUEST['filter_system']      ?? "";
} else {
    // First load — show *all libraries* so the list never looks empty
    $filter_library = $_REQUEST['library'] ?? "";
    $filter_loc     = $_REQUEST['loc']     ?? "";
    $filter_offset  = 0;

    $filter_alias = $filter_aliasblank = $filter_illemail = $filter_illemailblank = $filter_suspend = $filter_system = "";
    $filter_numresults = "25";

    $filter_all_libs = "yes";
    $filter_illpart  = "";
}

// ----- Action parameters (edit/delete/add/mass suspend) -----
$pageaction = isset($_REQUEST['action']) ? $_REQUEST['action'] : '0';
$librecnumb = $_REQUEST['librecnumb'] ?? "";
$libname    = $_REQUEST['libname']     ?? "";
$libalias   = $_REQUEST['libalias']    ?? "";
$libemail   = $_REQUEST['libemail']    ?? "";
$libilliad  = $_REQUEST['libilliad']   ?? "";
$libilliadkey  = $_REQUEST['libilliadkey'] ?? "";
$libilliadurl  = $_REQUEST['libilliadurl'] ?? "";
$libilliaddate = $_REQUEST['libilliaddate'] ?? "";
$libemailalert = $_REQUEST['libemailalert'] ?? "";

$participant = $_REQUEST['participant'] ?? "";
$suspend     = $_REQUEST['suspend']     ?? "";
$enddate     = $_REQUEST['enddate']     ?? "";
$system      = $_REQUEST['system']      ?? "";
$phone       = $_REQUEST['phone']       ?? "";
$address1    = $_REQUEST['address1']    ?? "";
$address2    = $_REQUEST['address2']    ?? "";
$address3    = $_REQUEST['address3']    ?? "";
$oclc        = $_REQUEST['oclc']        ?? "";
$loc         = $_REQUEST['loc']         ?? "";

// Delivery + loaning options (keep stable even if not set)
$lbEmpire       = $_REQUEST['lbEmpire']       ?? "";
$lbsyscourier   = $_REQUEST['lbsyscourier']   ?? "";
$lbUSPS         = $_REQUEST['lbUSPS']         ?? "";
$lbCommCourier  = $_REQUEST['lbCommCourier']  ?? "";

$book       = $_REQUEST['book']       ?? "";
$journal    = $_REQUEST['journal']    ?? "";
$av         = $_REQUEST['av']         ?? "";
$reference  = $_REQUEST['reference']  ?? "";
$ebook      = $_REQUEST['ebook']      ?? "";
$ejournal   = $_REQUEST['ejournal']   ?? "";

// ---------- PAGE START ----------
echo "<div class='adminlib-wrapper'>";

// Header / quick links
echo "<div class='adminlib-header'>
        <h3>SEAL — Library Directory Admin</h3>
        <div class='actions'>
          <a class='btn-secondary' href='/adminlib'> Back to Library List</a>
          <a class='btn-secondary' href='" . h($_SERVER['REDIRECT_URL']) . "?action=1'>Add Library</a>
          <a class='btn-secondary' href='" . h($_SERVER['REDIRECT_URL']) . "?action=5'>Mass Suspend/Activate</a>";
if ((int)$pageaction === 0) {
    echo "<a class='btn-secondary' target='_blank' href='/export'>Export CSV</a>";
}
echo      "</div>
      </div>";

/**
 * ACTION 3 — DELETE
 */
if ((int)$pageaction === 3) {
    if (($_SERVER['REQUEST_METHOD'] === 'POST') || isset($_GET['page'])) {
        $librecnumb = mysqli_real_escape_string($db, $librecnumb);
        $sqldel = "DELETE FROM `$sealLIB` WHERE recnum='$librecnumb'";
        mysqli_query($db, $sqldel);
        echo "<div class='section-card status-message'>Library has been deleted.</div>";
        echo "<a class='btn-primary' href='" . h($_SERVER['REDIRECT_URL']) . "'>Return to main list</a>";
    } else {
        echo "<div class='section-card adminlib-panel'>";
        echo "<h4>Confirm Delete</h4>";
        echo "<form action='" . h($_SERVER['REDIRECT_URL']) . "?" . h($_SERVER['QUERY_STRING']) . "' method='post'>";
        echo "<input type='hidden' name='action' value='3'>";
        echo "<input type='hidden' name='librecnumb' value='" . h($librecnumb) . "'>";
        echo "<p>Are you sure you want to delete this library?</p>";
        echo "<button type='submit' class='btn-danger'>Confirm Delete</button> ";
        echo "<a class='btn-secondary' href='" . h($_SERVER['REDIRECT_URL']) . "'>Cancel</a>";
        echo "</form></div>";
    }
    echo "</div>";
    exit;
}

/**
 * ACTION 5 — MASS SUSPEND/ACTIVATE
 */
if ((int)$pageaction === 5) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $task   = $_POST['task']   ?? '';
        $system = $_POST['system'] ?? 'none';
        $enddate = $_POST['enddate'] ?? '';

        if ($system === 'none') {
            echo "<div class='section-card status-error'>Please select a library system.</div>";
        } else {
            $sflag = ($task === 'suspend') ? 1 : 0;

            if ($sflag === 1 && strlen($enddate) < 2) {
                $enddate = date('Y-m-d', strtotime('+7 day'));
            } else {
                $enddate = date('Y-m-d', strtotime(str_replace('-', '/', $enddate)));
            }

            $system_esc = mysqli_real_escape_string($db, $system);
            $end_esc = mysqli_real_escape_string($db, $enddate);

            $sql = "UPDATE `$sealLIB` SET suspend=$sflag, SuspendDateEnd='$end_esc' WHERE `system`='$system_esc'";
            mysqli_query($db, $sql);

            echo "<div class='section-card status-message'>";
            echo ($sflag ? "Suspended" : "Activated") . " lending for system <strong>" . h($system) . "</strong>";
            if ($sflag) {
                echo " until <strong>" . h($end_esc) . "</strong>";
            }
            echo ".</div>";
        }
    }

    echo "<div class='adminlib-panel section-card'>";
    echo "<h4>Mass Suspend/Activate</h4>";
    echo "<form action='" . h($_SERVER['REDIRECT_URL']) . "?action=5' method='post' class='LibProfile_Form'>";

    echo "<div class='form-grid'>";
    echo "<div class='form-group'>
            <label>Action</label>
            <label class='choice'><input type='radio' name='task' value='suspend'> Suspend lending</label>
            <label class='choice'><input type='radio' name='task' value='activate' checked> Activate lending</label>
          </div>";
    echo "<div class='form-group'>
            <label>Library System</label>
            <select name='system'>
              <option value='none'>Select a system</option>
              <option value='DU'>Dutchess BOCES</option>
              <option value='MH'>Mid-Hudson Library System</option>
              <option value='OU'>Orange Ulster BOCES</option>
              <option value='RC'>Ramapo Catskill Library System</option>
              <option value='RB'>Rockland BOCES</option>
              <option value='SE'>SENYLRC</option>
              <option value='SB'>Sullivan BOCES</option>
              <option value='UB'>Ulster BOCES</option>
            </select>
          </div>";


    echo '<div class="form-group">';
    echo '  <label for="datepicker">Suspension End Date</label>';
    echo '  <input id="datepicker" name="enddate" value="' . h($enddate ?: '') . '">';
    echo '  <div class="helper">If no date is picked, the system will default to seven (7) days.</div>';
    echo '</div>';


    echo "<div class='actions'><button class='btn-primary'>Submit</button></div>";
    echo "</form></div>";
    echo "</div>";
    exit;
}

/**
 * ACTION 1 — ADD LIBRARY
 */
if ((int)$pageaction === 1) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $timestamp = date("Y-m-d H:i:s");

        // Escape & trim
        $libname   = trim(mysqli_real_escape_string($db, $libname));
        $libalias  = trim(mysqli_real_escape_string($db, $libalias));
        $libemail  = trim(mysqli_real_escape_string($db, $libemail));
        $address1  = trim(mysqli_real_escape_string($db, $address1));
        $address2  = trim(mysqli_real_escape_string($db, $address2));
        $address3  = trim(mysqli_real_escape_string($db, $address3));
        $phone     = trim(mysqli_real_escape_string($db, $phone));
        $oclc      = trim(mysqli_real_escape_string($db, $oclc));
        $loc       = trim(mysqli_real_escape_string($db, $loc));

        $participant = mysqli_real_escape_string($db, $participant);
        $suspend     = mysqli_real_escape_string($db, $suspend);
        $system      = mysqli_real_escape_string($db, $system);

        $book     = mysqli_real_escape_string($db, $book);
        $journal  = mysqli_real_escape_string($db, $journal);
        $av       = mysqli_real_escape_string($db, $av);
        $ebook    = mysqli_real_escape_string($db, $ebook);
        $ejournal = mysqli_real_escape_string($db, $ejournal);
        $reference = mysqli_real_escape_string($db, $reference);

        $insertsql = "
          INSERT INTO `$sealLIB`
            (`recnum`, `Name`, `ill_email`, `alias`, `participant`, `suspend`, `system`, `phone`,
             `address1`, `address2`, `address3`, `loc`, `oclc`,
             `book_loan`,`periodical_loan`,`av_loan`,`ebook_request`,`ejournal_request`,`theses_loan`,`ModifyDate`,
             `lbEmpire`,`lbsyscourier`,`lbUSPS`,`lbCommCourier`)
          VALUES
            (NULL,'$libname','$libemail','$libalias','$participant','$suspend','$system','$phone',
             '$address1','$address2','$address3','$loc','$oclc',
             '$book','$journal','$av','$ebook','$ejournal','$reference','$timestamp',
             '".h($lbEmpire)."','".h($lbsyscourier)."','".h($lbUSPS)."','".h($lbCommCourier)."')
        ";

        mysqli_query($db, $insertsql);

        echo "<div class='section-card status-message'>Library has been added.</div>";
        echo "<div class='actions'>
                <a class='btn-primary' href='" . h($_SERVER['REDIRECT_URL']) . "'>Return to main list</a>
                <a class='btn-secondary' href='" . h($_SERVER['REDIRECT_URL']) . "?action=1'>Add another library</a>
              </div>";
        echo "</div>";
        exit;
    }

    // Add form
    echo "<div class='adminlib-panel section-card'>";
    echo "<h4>Add Library</h4>";
    echo "<form action='" . h($_SERVER['REDIRECT_URL']) . "?action=1' method='post' class='LibProfile_Form'>";

    echo "<div class='form-grid'>";
    echo "<div class='form-group'><label>Library Name</label><input type='text' name='libname'></div>";
    echo "<div class='form-group'><label>Library Alias</label><input type='text' name='libalias'></div>";
    echo "<div class='form-group'><label>ILL Email</label><input type='text' name='libemail'></div>";
    echo "<div class='form-group'><label>Phone</label><input type='text' name='phone'></div>";
    echo "<div class='form-group'><label>ILL Dept</label><input type='text' name='address1'></div>";
    echo "<div class='form-group'><label>Street Address</label><input type='text' name='address2'></div>";
    echo "<div class='form-group'><label>City, State, ZIP</label><input type='text' name='address3'></div>";
    echo "<div class='form-group'><label>OCLC Symbol</label><input type='text' name='oclc'></div>";
    echo "<div class='form-group'><label>LOC Location (REQUIRED)</label><input type='text' name='loc'> <a target='_blank' href='https://www.loc.gov/marc/organizations/'>lookup</a></div>";
    echo "<div class='form-group'><label>ILL Participant</label><select name='participant'><option value='1'>Yes</option><option value='0'>No</option></select></div>";
    echo "<div class='form-group'><label>Suspend ILL</label><select name='suspend'><option value='0'>No</option><option value='1'>Yes</option></select></div>";
    echo "<div class='form-group'><label>Library System</label><select name='system'>
            <option value='DU'>Dutchess BOCES</option>
            <option value='MH'>Mid-Hudson Library System</option>
            <option value='OU'>Orange Ulster BOCES</option>
            <option value='RC'>Ramapo Catskill Library System</option>
            <option value='RB'>Rockland BOCES</option>
            <option value='SE'>SENYLRC</option>
            <option value='SB'>Sullivan BOCES</option>
            <option value='UB'>Ulster BOCES</option>
          </select></div>";
    echo "</div>";

    echo "<div class='section-divider'></div>";
    echo '<div class="section-card">';
    echo '  <h4>Delivery Options</h4>';
    echo '  <div class="form-section two-col">';

    echo '    <div class="form-group">';
    echo '      <label>Empire Library Delivery</label>';
    echo '      <div class="inline-options">';
    echo '        <label class="choice"><input type="radio" name="lbEmpire" value="Yes" ' . (($lbEmpire == "Yes") ? "checked" : "") . '> Yes</label>';
    echo '        <label class="choice"><input type="radio" name="lbEmpire" value="No" ' . (($lbEmpire == "No") ? "checked" : "") . '> No</label>';
    echo '      </div>';
    echo '    </div>';

    echo '    <div class="form-group">';
    echo '      <label>Public Library System Courier (MHLS or RCLS)</label>';
    echo '      <div class="inline-options">';
    echo '        <label class="choice"><input type="radio" name="lbsyscourier" value="Yes" ' . (($lbsyscourier == "Yes") ? "checked" : "") . '> Yes</label>';
    echo '        <label class="choice"><input type="radio" name="lbsyscourier" value="No" ' . (($lbsyscourier == "No") ? "checked" : "") . '> No</label>';
    echo '      </div>';
    echo '    </div>';

    echo '    <div class="form-group">';
    echo '      <label>US Mail</label>';
    echo '      <div class="inline-options">';
    echo '        <label class="choice"><input type="radio" name="lbUSPS" value="Yes" ' . (($lbUSPS == "Yes") ? "checked" : "") . '> Yes</label>';
    echo '        <label class="choice"><input type="radio" name="lbUSPS" value="No" ' . (($lbUSPS == "No") ? "checked" : "") . '> No</label>';
    echo '      </div>';
    echo '    </div>';

    echo '    <div class="form-group">';
    echo '      <label>Commercial Courier (UPS or FedEx)</label>';
    echo '      <div class="inline-options">';
    echo '        <label class="choice"><input type="radio" name="lbCommCourier" value="Yes" ' . (($lbCommCourier == "Yes") ? "checked" : "") . '> Yes</label>';
    echo '        <label class="choice"><input type="radio" name="lbCommCourier" value="No" ' . (($lbCommCourier == "No") ? "checked" : "") . '> No</label>';
    echo '      </div>';
    echo '    </div>';

    echo '  </div>';
    echo '</div>';

    echo '<div class="section-card">';
    echo '  <h4>Items Willing to Loan</h4>';
    echo '  <div class="form-section two-col">';

    echo '    <div class="form-group">';
    echo '      <label>Print Book</label>';
    echo '      <div class="inline-options">';
    echo '        <label class="choice"><input type="radio" name="book" value="Yes" ' . (($book == "Yes") ? "checked" : "") . '> Yes</label>';
    echo '        <label class="choice"><input type="radio" name="book" value="No" ' . (($book == "No") ? "checked" : "") . '> No</label>';
    echo '      </div>';
    echo '    </div>';

    echo '    <div class="form-group">';
    echo '      <label>Print Journal or Article</label>';
    echo '      <div class="inline-options">';
    echo '        <label class="choice"><input type="radio" name="journal" value="Yes" ' . (($journal == "Yes") ? "checked" : "") . '> Yes</label>';
    echo '        <label class="choice"><input type="radio" name="journal" value="No" ' . (($journal == "No") ? "checked" : "") . '> No</label>';
    echo '      </div>';
    echo '    </div>';

    echo '    <div class="form-group">';
    echo '      <label>Audio Video Materials</label>';
    echo '      <div class="inline-options">';
    echo '        <label class="choice"><input type="radio" name="av" value="Yes" ' . (($av == "Yes") ? "checked" : "") . '> Yes</label>';
    echo '        <label class="choice"><input type="radio" name="av" value="No" ' . (($av == "No") ? "checked" : "") . '> No</label>';
    echo '      </div>';
    echo '    </div>';

    echo '    <div class="form-group">';
    echo '      <label>Reference/Microfilm</label>';
    echo '      <div class="inline-options">';
    echo '        <label class="choice"><input type="radio" name="reference" value="Yes" ' . (($reference == "Yes") ? "checked" : "") . '> Yes</label>';
    echo '        <label class="choice"><input type="radio" name="reference" value="No" ' . (($reference == "No") ? "checked" : "") . '> No</label>';
    echo '      </div>';
    echo '    </div>';

    echo '    <div class="form-group">';
    echo '      <label>Electronic Book</label>';
    echo '      <div class="inline-options">';
    echo '        <label class="choice"><input type="radio" name="ebook" value="Yes" ' . (($ebook == "Yes") ? "checked" : "") . '> Yes</label>';
    echo '        <label class="choice"><input type="radio" name="ebook" value="No" ' . (($ebook == "No") ? "checked" : "") . '> No</label>';
    echo '      </div>';
    echo '    </div>';

    echo '    <div class="form-group">';
    echo '      <label>Electronic Journal</label>';
    echo '      <div class="inline-options">';
    echo '        <label class="choice"><input type="radio" name="ejournal" value="Yes" ' . (($ejournal == "Yes") ? "checked" : "") . '> Yes</label>';
    echo '        <label class="choice"><input type="radio" name="ejournal" value="No" ' . (($ejournal == "No") ? "checked" : "") . '> No</label>';
    echo '      </div>';
    echo '    </div>';

    echo '  </div>';
    echo '</div>';


    echo "<div class='actions'><button class='btn-primary'>Submit</button></div>";
    echo "</form></div>";
    echo "</div>";
    exit;
}

/**
 * ACTION 2 — EDIT LIBRARY
 */
if ((int)$pageaction === 2) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $timestamp = date("Y-m-d H:i:s");

        // Escape/trim
        $libname   = trim(mysqli_real_escape_string($db, $libname));
        $libalias  = trim(mysqli_real_escape_string($db, $libalias));
        $libemail  = trim(mysqli_real_escape_string($db, $libemail));
        $address1  = trim(mysqli_real_escape_string($db, $address1));
        $address2  = trim(mysqli_real_escape_string($db, $address2));
        $address3  = trim(mysqli_real_escape_string($db, $address3));
        $phone     = trim(mysqli_real_escape_string($db, $phone));
        $oclc      = trim(mysqli_real_escape_string($db, $oclc));
        $loc       = trim(mysqli_real_escape_string($db, $loc));

        $book     = mysqli_real_escape_string($db, $book);
        $journal  = mysqli_real_escape_string($db, $journal);
        $av       = mysqli_real_escape_string($db, $av);
        $ebook    = mysqli_real_escape_string($db, $ebook);
        $ejournal = mysqli_real_escape_string($db, $ejournal);
        $reference = mysqli_real_escape_string($db, $reference);

        $libilliadurl  = mysqli_real_escape_string($db, $libilliadurl);
        $libilliaddate = mysqli_real_escape_string($db, $libilliaddate);
        $libilliad     = mysqli_real_escape_string($db, $libilliad);
        $libilliadkey  = mysqli_real_escape_string($db, $libilliadkey);
        $libemailalert = mysqli_real_escape_string($db, $libemailalert);

        $participant = mysqli_real_escape_string($db, $participant);
        $suspend     = mysqli_real_escape_string($db, $suspend);
        $system      = mysqli_real_escape_string($db, $system);

        $lbsyscourier  = $_REQUEST["lbsyscourier"] ?? "";
        $lbUSPS        = $_REQUEST["lbUSPS"] ?? "";
        $lbEmpire      = $_REQUEST["lbEmpire"] ?? "";
        $lbCommCourier = $_REQUEST["lbCommCourier"] ?? "";
        // If suspended with no end date, default +7 days
        if (($suspend == 1) && (strlen($enddate) < 2)) {
            $enddate = date('Y-m-d', strtotime('+7 day'));
        } else {
            $enddate = date('Y-m-d', strtotime(str_replace('-', '/', $enddate)));
        }

        // If participant is set to 0, also suspend
        if ($participant === '0' && $suspend === '0') {
            // keep suspend = 0 if both are no
            $suspend = '0';
        } elseif ($participant === '0') {
            // force suspend if not participant
            $suspend = '1';
        }


        $librecnumb_esc = mysqli_real_escape_string($db, $librecnumb);

        $sqlupdate = "
          UPDATE `$sealLIB` SET
            Name = '$libname',
            alias = '$libalias',
            `ill_email` = '$libemail',
            participant = $participant,
            suspend = $suspend,
            SuspendDateEnd = '$enddate',
            `system` = '$system',
            phone = '$phone',
            address1 = '$address1',
            address2 = '$address2',
            address3 = '$address3',
            oclc = '$oclc',
            loc = '$loc',
            book_loan = '$book',
            periodical_loan = '$journal',
            av_loan = '$av',
            ebook_request = '$ebook',
            ejournal_request = '$ejournal',
            theses_loan = '$reference',
            ModifyDate = '$timestamp',
            Illiad = '$libilliad',
            IlliadURL = '$libilliadurl',
            IlliadDATE = '$libilliaddate',
            APIkey = '$libilliadkey',
            LibEmailAlert = '$libemailalert',
            lbEmpire = '".h($lbEmpire)."',
            lbsyscourier = '".h($lbsyscourier)."',
            lbUSPS = '".h($lbUSPS)."',
            lbCommCourier = '".h($lbCommCourier)."'
          WHERE `recnum` = '$librecnumb_esc'
        ";

        mysqli_query($db, $sqlupdate);

        echo "<div class='section-card status-message'>Library has been edited.</div>";
        echo "<a class='btn-primary' href='" . h($_SERVER['REDIRECT_URL']) . "'>Return to main list</a>";
        echo "</div>";
        exit;
    }

    // Load existing record
    $librecnumb_esc = mysqli_real_escape_string($db, $librecnumb);
    $GETEDITLISTSQL = "SELECT * FROM `$sealLIB` WHERE `recnum` ='$librecnumb_esc'";
    $GETLIST = mysqli_query($db, $GETEDITLISTSQL);
    $row = mysqli_fetch_assoc($GETLIST);

    // Fill local vars
    $libname = $row["Name"];
    $libalias = $row["alias"];
    $libemail = $row["ill_email"];
    $phone = $row["phone"];
    $libparticipant = $row["participant"];
    $libemailalert = $row["LibEmailAlert"];
    $libilliad = $row["Illiad"];
    $libilliadkey = $row["APIkey"];
    $oclc = $row["oclc"];
    $loc = $row["loc"];
    $libilliadurl = $row["IlliadURL"];
    $libilliaddate = $row["IlliadDATE"];
    $libsuspend = $row["suspend"];
    $system = $row["system"];
    $address1 = $row["address1"];
    $address2 = $row["address2"];
    $address3 = $row["address3"];
    $book = $row["book_loan"];
    $reference = $row["theses_loan"];
    $av = $row["av_loan"];
    $ebook = $row["ebook_request"];
    $ejournal = $row["ejournal_request"];
    $journal = $row["periodical_loan"];
    $timestamp = $row["ModifyDate"];
    $lbsyscourier = $row["lbsyscourier"];
    $lbUSPS = $row["lbUSPS"];
    $lbEmpire = $row["lbEmpire"];
    $lbCommCourier = $row["lbCommCourier"];
    $enddateshow = $row["SuspendDateEnd"];

    // Edit form
    echo "<div class='adminlib-panel section-card'>";
    echo "<h4>Edit Library</h4>";
    echo "<form action='" . h($_SERVER['REDIRECT_URL']) . "?action=2' method='post' class='LibProfile_Form'>";
    echo "<input type='hidden' name='librecnumb' value='" . h($librecnumb) . "'>";

    echo "<div class='form-grid'>";
    echo "<div class='form-group'><label>Library Name</label><input type='text' name='libname' value='" . h($libname) . "'></div>";
    echo "<div class='form-group'><label>Library Alias</label><input type='text' name='libalias' value='" . h($libalias) . "'></div>";
    echo "<div class='form-group'><label>ILL Email</label><input type='text' name='libemail' value='" . h($libemail) . "'></div>";
    echo "<div class='form-group'><label>Phone</label><input type='text' name='phone' value='" . h($phone) . "'></div>";
    echo "<div class='form-group'><label>ILL Dept</label><input type='text' name='address1' value='" . h($address1) . "'></div>";
    echo "<div class='form-group'><label>Street Address</label><input type='text' name='address2' value='" . h($address2) . "'></div>";
    echo "<div class='form-group'><label>City, State, ZIP</label><input type='text' name='address3' value='" . h($address3) . "'></div>";
    echo "<div class='form-group'><label>OCLC Symbol</label><input type='text' name='oclc' value='" . h($oclc) . "'></div>";
    echo "<div class='form-group'><label>LOC Location (REQUIRED)</label><input type='text' name='loc' value='" . h($loc) . "'> <a target='_blank' href='https://www.loc.gov/marc/organizations/'>lookup</a></div>";

    echo "<div class='form-group'><label>Library Email Alert</label>
            <select name='libemailalert'>
              <option value='1' " . selected_val($libemailalert, "1") . ">Yes</option>
              <option value='0' " . selected_val($libemailalert, "0") . ">No</option>
            </select>
          </div>";

    echo "<div class='form-group'><label>Library ILLiad</label>
            <select name='libilliad'>
              <option value='1' " . selected_val($libilliad, "1") . ">Yes</option>
              <option value='0' " . selected_val($libilliad, "0") . ">No</option>
            </select>
          </div>";

    echo "<div class='form-group'><label>ILLiad URL</label><input type='text' name='libilliadurl' value='" . h($libilliadurl) . "'></div>";
    echo "<div class='form-group'><label>ILLiad Due Date Days</label><input type='text' name='libilliaddate' value='" . h($libilliaddate) . "'></div>";
    echo "<div class='form-group'><label>ILLiad API Key</label><input type='text' name='libilliadkey' value='" . h($libilliadkey) . "'></div>";

    echo "<div class='form-group'><label>ILL Participant</label>
            <select name='participant'>
              <option value='1' " . selected_val($libparticipant, "1") . ">Yes</option>
              <option value='0' " . selected_val($libparticipant, "0") . ">No</option>
            </select>
          </div>";

    echo "<div class='form-group'><label>Suspend ILL</label>
            <select name='suspend'>
              <option value='0' " . selected_val($libsuspend, "0") . ">No</option>
              <option value='1' " . selected_val($libsuspend, "1") . ">Yes</option>
            </select>
          </div>";

    echo '<div class="form-group">';
    echo '  <label for="datepicker">Suspension End Date</label>';
    echo '  <input id="datepicker" name="enddate" value="' . h($enddate ?: '') . '">';
    echo '  <div class="helper">If no date is picked, the system will default to seven (7) days.</div>';
    echo '</div>';


    echo "<div class='form-group'><label>Library System</label>
            <select name='system'>
              <option value='DU' " . selected_val($system, 'DU') . ">Dutchess BOCES</option>
              <option value='MH' " . selected_val($system, 'MH') . ">Mid-Hudson Library System</option>
              <option value='OU' " . selected_val($system, 'OU') . ">Orange Ulster BOCES</option>
              <option value='RC' " . selected_val($system, 'RC') . ">Ramapo Catskill Library System</option>
              <option value='RB' " . selected_val($system, 'RB') . ">Rockland BOCES</option>
              <option value='SE' " . selected_val($system, 'SE') . ">SENYLRC</option>
              <option value='SB' " . selected_val($system, 'SB') . ">Sullivan BOCES</option>
              <option value='UB' " . selected_val($system, 'UB') . ">Ulster BOCES</option>
            </select>
          </div>";
    echo "</div>";

    echo "<div class='section-divider'></div>";
    echo '<div class="section-card">';
    echo '  <h4>Delivery Options</h4>';
    echo '  <div class="form-section two-col">';

    echo '    <div class="form-group">';
    echo '      <label>Empire Library Delivery</label>';
    echo '      <div class="inline-options">';
    echo '        <label class="choice"><input type="radio" name="lbEmpire" value="Yes" ' . (($lbEmpire == "Yes") ? "checked" : "") . '> Yes</label>';
    echo '        <label class="choice"><input type="radio" name="lbEmpire" value="No" ' . (($lbEmpire == "No") ? "checked" : "") . '> No</label>';
    echo '      </div>';
    echo '    </div>';

    echo '    <div class="form-group">';
    echo '      <label>Public Library System Courier (MHLS or RCLS)</label>';
    echo '      <div class="inline-options">';
    echo '        <label class="choice"><input type="radio" name="lbsyscourier" value="Yes" ' . (($lbsyscourier == "Yes") ? "checked" : "") . '> Yes</label>';
    echo '        <label class="choice"><input type="radio" name="lbsyscourier" value="No" ' . (($lbsyscourier == "No") ? "checked" : "") . '> No</label>';
    echo '      </div>';
    echo '    </div>';

    echo '    <div class="form-group">';
    echo '      <label>US Mail</label>';
    echo '      <div class="inline-options">';
    echo '        <label class="choice"><input type="radio" name="lbUSPS" value="Yes" ' . (($lbUSPS == "Yes") ? "checked" : "") . '> Yes</label>';
    echo '        <label class="choice"><input type="radio" name="lbUSPS" value="No" ' . (($lbUSPS == "No") ? "checked" : "") . '> No</label>';
    echo '      </div>';
    echo '    </div>';

    echo '    <div class="form-group">';
    echo '      <label>Commercial Courier (UPS or FedEx)</label>';
    echo '      <div class="inline-options">';
    echo '        <label class="choice"><input type="radio" name="lbCommCourier" value="Yes" ' . (($lbCommCourier == "Yes") ? "checked" : "") . '> Yes</label>';
    echo '        <label class="choice"><input type="radio" name="lbCommCourier" value="No" ' . (($lbCommCourier == "No") ? "checked" : "") . '> No</label>';
    echo '      </div>';
    echo '    </div>';

    echo '  </div>';
    echo '</div>';

    echo '<div class="section-card">';
    echo '  <h4>Items Willing to Loan</h4>';
    echo '  <div class="form-section two-col">';

    echo '    <div class="form-group">';
    echo '      <label>Print Book</label>';
    echo '      <div class="inline-options">';
    echo '        <label class="choice"><input type="radio" name="book" value="Yes" ' . (($book == "Yes") ? "checked" : "") . '> Yes</label>';
    echo '        <label class="choice"><input type="radio" name="book" value="No" ' . (($book == "No") ? "checked" : "") . '> No</label>';
    echo '      </div>';
    echo '    </div>';

    echo '    <div class="form-group">';
    echo '      <label>Print Journal or Article</label>';
    echo '      <div class="inline-options">';
    echo '        <label class="choice"><input type="radio" name="journal" value="Yes" ' . (($journal == "Yes") ? "checked" : "") . '> Yes</label>';
    echo '        <label class="choice"><input type="radio" name="journal" value="No" ' . (($journal == "No") ? "checked" : "") . '> No</label>';
    echo '      </div>';
    echo '    </div>';

    echo '    <div class="form-group">';
    echo '      <label>Audio Video Materials</label>';
    echo '      <div class="inline-options">';
    echo '        <label class="choice"><input type="radio" name="av" value="Yes" ' . (($av == "Yes") ? "checked" : "") . '> Yes</label>';
    echo '        <label class="choice"><input type="radio" name="av" value="No" ' . (($av == "No") ? "checked" : "") . '> No</label>';
    echo '      </div>';
    echo '    </div>';

    echo '    <div class="form-group">';
    echo '      <label>Reference/Microfilm</label>';
    echo '      <div class="inline-options">';
    echo '        <label class="choice"><input type="radio" name="reference" value="Yes" ' . (($reference == "Yes") ? "checked" : "") . '> Yes</label>';
    echo '        <label class="choice"><input type="radio" name="reference" value="No" ' . (($reference == "No") ? "checked" : "") . '> No</label>';
    echo '      </div>';
    echo '    </div>';

    echo '    <div class="form-group">';
    echo '      <label>Electronic Book</label>';
    echo '      <div class="inline-options">';
    echo '        <label class="choice"><input type="radio" name="ebook" value="Yes" ' . (($ebook == "Yes") ? "checked" : "") . '> Yes</label>';
    echo '        <label class="choice"><input type="radio" name="ebook" value="No" ' . (($ebook == "No") ? "checked" : "") . '> No</label>';
    echo '      </div>';
    echo '    </div>';

    echo '    <div class="form-group">';
    echo '      <label>Electronic Journal</label>';
    echo '      <div class="inline-options">';
    echo '        <label class="choice"><input type="radio" name="ejournal" value="Yes" ' . (($ejournal == "Yes") ? "checked" : "") . '> Yes</label>';
    echo '        <label class="choice"><input type="radio" name="ejournal" value="No" ' . (($ejournal == "No") ? "checked" : "") . '> No</label>';
    echo '      </div>';
    echo '    </div>';

    echo '  </div>';
    echo '</div>';


    if ($libsuspend == "1") {
        echo "<p class='pill'>This library is <strong>suspended</strong> until " . h($enddateshow) . ".</p>";
    } else {
        echo "<p class='pill'>This library has suspension disabled.</p>";
    }

    echo "<div class='actions'><button class='btn-primary'>Submit</button></div>";
    echo "</form><br>Last Modified: " . h($timestamp) . "</div>";
    echo "</div>";
    exit;
}

// =======================
// MAIN LISTING PAGE
// =======================

// Sanitize for LIKE
$loc_like          = mysqli_real_escape_string($db, $filter_loc);
$illemail_like     = mysqli_real_escape_string($db, $filter_illemail);
$alias_like        = mysqli_real_escape_string($db, $filter_alias);
$library_like      = mysqli_real_escape_string($db, $filter_library);
$system_like       = mysqli_real_escape_string($db, $filter_system);

// Build SQL
$SQLBASE = "SELECT * FROM `$sealLIB` WHERE ";
$SQLEND  = " ORDER BY `Name` ASC ";

if ($filter_numresults != "all") {
    $sqllimiter = ((int)$filter_numresults) * ((int)$filter_offset);
    $SQLLIMIT   = " LIMIT " . $sqllimiter . ", " . (int)$filter_numresults;
} else {
    $SQLLIMIT = "";
}

if ($filter_all_libs == "yes") {
    $SQLMIDDLE = "1=1 ";
} else {
    $SQLMIDDLE  = ($filter_illpart == "yes") ? "`participant` = 1 " : "`participant` = 0 ";
    $SQLMIDDLE .= ($filter_suspend == "yes") ? "AND `suspend` = 1 " : "AND `suspend` = 0 ";
}
$SQLMIDDLE .= ($filter_aliasblank == "yes") ? "AND alias = '' " : " ";
$SQLMIDDLE .= ($filter_illemailblank == "yes") ? "AND `ill_email` = '' " : " ";
$SQLMIDDLE .= (strlen($filter_loc) >= 1) ? "AND `loc` LIKE '%$loc_like%' " : "";
$SQLMIDDLE .= (strlen($filter_illemail) >= 1) ? "AND `ill_email` LIKE '%$illemail_like%' " : "";
$SQLMIDDLE .= (strlen($filter_alias) >= 1) ? "AND `Alias` LIKE '%$alias_like%' " : "";
$SQLMIDDLE .= (strlen($filter_library) >= 2) ? "AND `Name` LIKE '%$library_like%' " : "";
$SQLMIDDLE .= (strlen($filter_system) >= 1) ? "AND `System` LIKE '%$system_like%' " : "";

$GETFULLSQL = $SQLBASE . $SQLMIDDLE . $SQLEND;
$GETLISTSQL = $GETFULLSQL . $SQLLIMIT;

// Debug panel
if ($debug) {
    echo "<pre style='background:#f8f9fa;border:1px solid #ccc;padding:10px;margin:10px 0;'>";
    echo "<b>DEBUG MODE</b>\nSQL:\n" . h($GETLISTSQL) . "\n";
    $testQ = mysqli_query($db, $GETFULLSQL);
    if ($testQ) {
        echo "Total rows found: " . mysqli_num_rows($testQ) . "\n";
    } else {
        echo "Query error: " . mysqli_error($db) . "\n";
    }
    echo "</pre>";
}

// Fetch
$GETLIST = mysqli_query($db, $GETLISTSQL);
$GETCOUNT = mysqli_query($db, $GETFULLSQL);
$GETLISTCOUNTwhole = $GETCOUNT ? mysqli_num_rows($GETCOUNT) : 0;
//for export
$_SESSION['query2'] = $GETFULLSQL; // place this after $GETFULLSQL is defined
// ---- Filter bar ----
echo "<form action='" . h($_SERVER['REDIRECT_URL']) . "' method='post' class='filter-bar'>";
echo "<input type='hidden' name='firstpass' value='no'>";

echo "<div class='filter-grid'>";
echo "<div class='form-group'><label><input type='checkbox' name='filter_aliasblank' value='yes' " . checked_val($filter_aliasblank) . "> Missing alias</label></div>";
echo "<div class='form-group'><label><input type='checkbox' name='filter_illemailblank' value='yes' " . checked_val($filter_illemailblank) . "> Missing ILL Email</label></div>";
echo "<div class='form-group'><label><input type='checkbox' name='filter_illpart' value='yes' " . checked_val($filter_illpart) . "> ILL Participant</label></div>";
echo "<div class='form-group'><label><input type='checkbox' name='filter_suspend' value='yes' " . checked_val($filter_suspend) . "> ILL Suspended</label></div>";
echo "<div class='form-group'><label><input type='checkbox' name='filter_all_libs' value='yes' " . checked_val($filter_all_libs) . "> All Libraries</label></div>";

echo "<div class='form-group'>
        <label>Library System</label>
        <select name='filter_system'>
          <option value='' " . selected_val($filter_system, '') . ">All</option>
          <option value='DU' " . selected_val($filter_system, 'DU') . ">Dutchess BOCES</option>
          <option value='MH' " . selected_val($filter_system, 'MH') . ">Mid-Hudson</option>
          <option value='OU' " . selected_val($filter_system, 'OU') . ">Orange Ulster BOCES</option>
          <option value='RC' " . selected_val($filter_system, 'RC') . ">Ramapo Catskill</option>
          <option value='RB' " . selected_val($filter_system, 'RB') . ">Rockland BOCES</option>
          <option value='SE' " . selected_val($filter_system, 'SE') . ">SENYLRC</option>
          <option value='SB' " . selected_val($filter_system, 'SB') . ">Sullivan BOCES</option>
          <option value='UB' " . selected_val($filter_system, 'UB') . ">Ulster BOCES</option>
        </select>
      </div>";

echo "<div class='form-group'><label>Library Name</label><input name='library' type='text' value='" . h($filter_library) . "'></div>";
echo "<div class='form-group'><label>Library Alias</label><input name='filter_alias' type='text' value='" . h($filter_alias) . "'></div>";
echo "<div class='form-group'><label>ILL Code (LOC)</label><input name='loc' type='text' value='" . h($filter_loc) . "'></div>";
echo "<div class='form-group'><label>ILL Email</label><input name='filter_illemail' type='text' value='" . h($filter_illemail) . "'></div>";

echo "<div class='form-group'><label>Results per page</label>
        <select name='filter_numresults'>
          <option value='25' " . selected_val($filter_numresults, '25') . ">25</option>
          <option value='50' " . selected_val($filter_numresults, '50') . ">50</option>
          <option value='100' " . selected_val($filter_numresults, '100') . ">100</option>
          <option value='all' " . selected_val($filter_numresults, 'all') . ">All</option>
        </select>
      </div>";

if ($filter_numresults != "all") {
    $resultpages = max(1, ceil($GETLISTCOUNTwhole / max(1, (int)$filter_numresults)));
    echo "<div class='form-group'><label>Page</label><select name='filter_offset'>";
    for ($x = 1; $x <= $resultpages; $x++) {
        $localoffset = $x - 1;
        echo "<option value='" . $localoffset . "' " . selected_val($filter_offset, $localoffset) . ">$x</option>";
    }
    echo "</select></div>";
}
echo "</div>"; // grid

echo "<div class='filter-actions'><button class='btn-primary'>Update</button> <a class='btn-secondary' href='adminlib'>Clear</a></div>";
echo "</form>";

// ---- Results meta ----
echo "<div class='pagination-info'>Total Results: " . (int)$GETLISTCOUNTwhole . "</div>";

// ---- Results table ----
echo "<table class='rh-table'>";
echo "<thead><tr>
        <th>Library</th>
        <th>Alias</th>
        <th>Participant</th>
        <th>Suspended</th>
        <th>System</th>
        <th>OCLC</th>
        <th>LOC</th>
        <th>Action</th>
      </tr></thead><tbody>";

while ($row = mysqli_fetch_assoc($GETLIST)) {
    $librecnumb_r = $row["recnum"];
    $libname_r    = $row["Name"];
    $libalias_r   = $row["alias"];
    $libparticipant_r = $row["participant"] == "1" ? "Yes" : "No";
    $libsuspend_r = $row["suspend"] == "1" ? "Yes" : "No";
    $system_r     = $row["system"];
    $oclc_r       = $row["oclc"];
    $loc_r        = $row["loc"];

    echo "<tr>
            <td>" . h($libname_r) . "</td>
            <td>" . h($libalias_r) . "</td>
            <td>" . h($libparticipant_r) . "</td>
            <td>" . h($libsuspend_r) . "</td>
            <td>" . h($system_r) . "</td>
            <td>" . h($oclc_r) . "</td>
            <td>" . h($loc_r) . "</td>
            <td class='table-actions'>
              <a class='edit' href='" . h($_SERVER['REDIRECT_URL']) . "?action=2&librecnumb=" . h($librecnumb_r) . "'>Edit</a>
              <a class='delete' href='" . h($_SERVER['REDIRECT_URL']) . "?action=3&librecnumb=" . h($librecnumb_r) . "'>Delete</a>
            </td>
          </tr>";
}
echo "</tbody></table>";

echo "</div>"; // wrapper end