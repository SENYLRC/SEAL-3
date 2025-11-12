<?php
// ===============================================================
// adminlib_ls.php — Library list and inline editor for system staff
// ===============================================================

session_name('seal_admin_session');
if (session_status() === PHP_SESSION_NONE) session_start();

require '/var/www/seal_wp_script/seal_function.php';
require '/var/www/seal_wp_script/seal_db.inc';
require_once('/var/www/wpSEAL/wp-load.php');

$current_user = wp_get_current_user();
$user_roles = (array)$current_user->roles;

if (!in_array('administrator', $user_roles, true) && !in_array('libsys', $user_roles, true)) {
    die("<div style='padding:20px;color:red;font-weight:bold;'>Access Denied<br>You must have the <b>Lib Systems Staff</b> role to access this page.</div>");
}

$field_home_library_system = get_user_meta($current_user->ID, 'home_system', true);
if (empty($field_home_library_system)) {
    die("<div style='padding:20px;color:red;font-weight:bold;'>Your account does not have a Home Library System assigned.</div>");
}

if (!function_exists('selected')) {
    function selected($value, $current) { return ((string)$value === (string)$current) ? 'selected="selected"' : ''; }
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$db = mysqli_connect($dbhost, $dbuser, $dbpass);
mysqli_select_db($db, $dbname);
$db->set_charset('utf8mb4');

$filter_library = $_REQUEST['library'] ?? "";
$filter_loc     = $_REQUEST['loc'] ?? "";
$filter_alias   = $_REQUEST['filter_alias'] ?? "";
$filter_illemail= $_REQUEST['filter_illemail'] ?? "";
$pageaction     = $_REQUEST['action'] ?? '0';
$librecnumb     = $_REQUEST['librecnumb'] ?? null;
$current_page   = strtok($_SERVER['REQUEST_URI'], '?');
?>
<style>
.seal-shell {font-family: system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial,sans-serif;color:#111;}
.seal-wrap {max-width:1100px;margin:0 auto;padding:18px;}
.seal-card {background:#fff;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 1px 2px rgba(0,0,0,.05);padding:18px;margin-bottom:16px;}
.seal-title {font-size:22px;font-weight:700;margin:0 0 8px;}
.seal-sub {font-size:14px;color:#555;margin-bottom:12px;}
.seal-row {display:flex;gap:12px;flex-wrap:wrap;align-items:center;}
.seal-row .input {padding:8px 10px;border:1px solid #d1d5db;border-radius:8px;min-width:220px;}
.seal-btn {display:inline-block;padding:6px 12px;border-radius:8px;border:1px solid #0f766e;background:#0d9488;color:#fff;text-decoration:none;font-weight:600;cursor:pointer;}
.seal-btn.secondary {background:#fff;color:#0f766e;}
.seal-btn.secondary:hover {background:#0f766e;color:#fff;}
.seal-table {width:100%;border-collapse:collapse;}
.seal-table th, .seal-table td {padding:10px 8px;border-bottom:1px solid #eef2f7;text-align:left;vertical-align:middle;}
.seal-table th {font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#6b7280;background:#fafafa;}
.action-buttons {display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-start;}
.status-yes {color:#166534;font-weight:600;}
.status-no {color:#b91c1c;font-weight:600;}
@media (max-width:720px){
  .seal-row .input{min-width:100%;}
  .action-buttons{flex-direction:column;align-items:flex-start;}
}
</style>

<div class="seal-shell"><div class="seal-wrap">
<?php
// ========================================
// DELETE
// ========================================
if ($pageaction == '3') {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $librecnumb = mysqli_real_escape_string($db, $librecnumb);
        mysqli_query($db, "DELETE FROM `$sealLIB` WHERE recnum='$librecnumb'");
        echo '<div class="seal-card"><div class="seal-title">Library deleted</div>
              <a class="seal-btn" href="'.h($current_page).'">Return to list</a></div>';
    } else {
        echo '<div class="seal-card"><div class="seal-title">Confirm delete</div>
              <form method="post" action="'.h($current_page).'?'.h($_SERVER['QUERY_STRING']).'">
              <input type="hidden" name="action" value="3">
              <input type="hidden" name="librecnumb" value="'.h($librecnumb).'">
              <button class="seal-btn">Confirm</button>
              <a class="seal-btn secondary" href="'.h($current_page).'">Cancel</a>
              </form></div>';
    }

// ========================================
// EDIT LIBRARY (inline editor version of libprofile.php)
// ========================================
} elseif ($pageaction == '2') {
    $librecnumb = mysqli_real_escape_string($db, $librecnumb);

    // === Save Edits (POST) ===
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recnum'])) {
        $recnum = mysqli_real_escape_string($db, $_POST['recnum']);
        $libname = mysqli_real_escape_string($db, $_POST['libname']);
        $libemail = mysqli_real_escape_string($db, $_POST['libemail']);
        $phone = mysqli_real_escape_string($db, $_POST['phone']);
        $address1 = mysqli_real_escape_string($db, $_POST['address1']);
        $address2 = mysqli_real_escape_string($db, $_POST['address2']);
        $address3 = mysqli_real_escape_string($db, $_POST['address3']);
        $oclc = mysqli_real_escape_string($db, $_POST['oclc']);
        $suspend = mysqli_real_escape_string($db, $_POST['suspend']);
        $enddate = mysqli_real_escape_string($db, $_POST['enddate']);
        $book = mysqli_real_escape_string($db, $_POST['book'] ?? '');
        $journal = mysqli_real_escape_string($db, $_POST['journal'] ?? '');
        $av = mysqli_real_escape_string($db, $_POST['av'] ?? '');
        $reference = mysqli_real_escape_string($db, $_POST['reference'] ?? '');
        $ebook = mysqli_real_escape_string($db, $_POST['ebook'] ?? '');
        $ejournal = mysqli_real_escape_string($db, $_POST['ejournal'] ?? '');
        $lbEmpire = mysqli_real_escape_string($db, $_POST['lbEmpire'] ?? '');
        $lbsyscourier = mysqli_real_escape_string($db, $_POST['lbsyscourier'] ?? '');
        $lbUSPS = mysqli_real_escape_string($db, $_POST['lbUSPS'] ?? '');
        $lbCommCourier = mysqli_real_escape_string($db, $_POST['lbCommCourier'] ?? '');
        $emailuser = mysqli_real_escape_string($db, $current_user->user_email);
        $today = date('Y-m-d H:i:s');

        if (empty($enddate)) $enddate = date('Y-m-d', strtotime('+7 days'));

        $UPDATE_SQL = "
            UPDATE `$sealLIB`
            SET
                Name = '$libname',
                ill_email = '$libemail',
                phone = '$phone',
                address1 = '$address1',
                address2 = '$address2',
                address3 = '$address3',
                oclc = '$oclc',
                suspend = '$suspend',
                SuspendDateEnd = '$enddate',
                book_loan = '$book',
                periodical_loan = '$journal',
                av_loan = '$av',
                theses_loan = '$reference',
                ebook_request = '$ebook',
                ejournal_request = '$ejournal',
                lbEmpire = '$lbEmpire',
                lbsyscourier = '$lbsyscourier',
                lbUSPS = '$lbUSPS',
                lbCommCourier = '$lbCommCourier',
                ModEmail = '$emailuser',
                ModifyDate = '$today'
            WHERE recnum = '$recnum'
            LIMIT 1
        ";

        if (mysqli_query($db, $UPDATE_SQL)) {
            header("Location: $current_page?updated=1");
            exit;
        } else {
            echo '<div class="seal-card"><div class="seal-title">Update Failed</div>
                  <div class="seal-sub">'.h(mysqli_error($db)).'</div>
                  <a class="seal-btn" href="'.h($current_page).'">Return to list</a></div>';
            exit;
        }
    }

    // === Load current record ===
    $GETLISTSQL = "SELECT * FROM `$sealLIB` WHERE `recnum` = '$librecnumb' LIMIT 1";
    $GETLIST = mysqli_query($db, $GETLISTSQL);
    if (!$GETLIST || mysqli_num_rows($GETLIST) == 0) {
        echo '<div class="seal-card"><div class="seal-title">Error</div>
              <div class="seal-sub">Library not found.</div>
              <a class="seal-btn" href="'.h($current_page).'">Return to list</a></div>';
        exit;
    }

  $row = mysqli_fetch_assoc($GETLIST);

// Explicit assignments for clarity (fixes missing values)
$Name            = $row["Name"];
$ill_email       = $row["ill_email"];
$phone           = $row["phone"];
$address1        = $row["address1"];
$address2        = $row["address2"];
$address3        = $row["address3"];
$oclc            = $row["oclc"];
$suspend         = $row["suspend"];
$SuspendDateEnd  = $row["SuspendDateEnd"];

// Explicit assignments — matching actual column names
$book_loan        = $row["book_loan"]        ?? '';
$periodical_loan  = $row["periodical_loan"]  ?? '';
$av_loan          = $row["av_loan"]          ?? '';
$theses_loan      = $row["theses_loan"]      ?? '';
$ebook_request    = $row["ebook_request"]    ?? '';
$ejournal_request = $row["ejournal_request"] ?? '';


$lbEmpire       = $row["lbEmpire"];
$lbsyscourier   = $row["lbsyscourier"];
$lbUSPS         = $row["lbUSPS"];
$lbCommCourier  = $row["lbCommCourier"];


    if (empty($SuspendDateEnd) || $SuspendDateEnd == '0000-00-00' || $SuspendDateEnd == '1970-01-01') {
        $SuspendDateEnd = date('Y-m-d', strtotime('+7 days'));
    }

    // === Edit Form ===
?>
<div class="LibProfile_Form">
  <form action="<?php echo h($current_page); ?>?action=2&librecnumb=<?php echo h($librecnumb); ?>" method="post">
    <input type="hidden" name="recnum" value="<?php echo h($librecnumb); ?>">
    <div class="section-card">
      <h4>Library Details</h4>
      <div class="form-section">
        <div class="form-group"><label>Library Name</label>
          <input type="text" name="libname" value="<?php echo h($Name); ?>">
        </div>
        <div class="form-group"><label>ILL Email</label>
          <input type="text" name="libemail" value="<?php echo h($ill_email); ?>">
        </div>
        <div class="form-group"><label>Phone</label>
          <input type="text" name="phone" value="<?php echo h($phone); ?>">
        </div>
        <div class="form-group"><label>Address Dept</label>
          <input type="text" name="address1" value="<?php echo h($address1); ?>">
        </div>
        <div class="form-group"><label>Address Street</label>
          <input type="text" name="address2" value="<?php echo h($address2); ?>">
        </div>
        <div class="form-group"><label>Address City/State/Zip</label>
          <input type="text" name="address3" value="<?php echo h($address3); ?>">
        </div>
        <div class="form-group"><label>OCLC</label>
          <input type="text" name="oclc" value="<?php echo h($oclc); ?>">
        </div>
      </div>
    </div>

    <div class="section-card">
      <h4>Suspension</h4>
      <div class="form-section">
        <div class="form-group"><label>Suspend Library?</label>
          <select name="suspend">
            <option value="0" <?php if ($suspend=="0") echo "selected"; ?>>No</option>
            <option value="1" <?php if ($suspend=="1") echo "selected"; ?>>Yes</option>
          </select>
        </div>
        <div class="form-group"><label>Suspension End Date</label>
          <input type="text" name="enddate" value="<?php echo h($SuspendDateEnd); ?>">
        </div>
      </div>
    </div>

    <!-- Delivery & Lending -->
<div class="section-card">
  <h4>Delivery & Lending</h4>
  <div class="form-section two-col">

    <div class="form-group">
      <label>Empire Library Delivery</label>
      <div class="inline-options">
        <label class="choice">
          <input type="radio" name="lbEmpire" value="Yes" <?php if ($lbEmpire=="Yes") echo "checked"; ?>> Yes
        </label>
        <label class="choice">
          <input type="radio" name="lbEmpire" value="No" <?php if ($lbEmpire=="No") echo "checked"; ?>> No
        </label>
      </div>
    </div>

    <div class="form-group">
      <label>System Courier (MHLS or RCLS)</label>
      <div class="inline-options">
        <label class="choice">
          <input type="radio" name="lbsyscourier" value="Yes" <?php if ($lbsyscourier=="Yes") echo "checked"; ?>> Yes
        </label>
        <label class="choice">
          <input type="radio" name="lbsyscourier" value="No" <?php if ($lbsyscourier=="No") echo "checked"; ?>> No
        </label>
      </div>
    </div>

    <div class="form-group">
      <label>US Mail</label>
      <div class="inline-options">
        <label class="choice">
          <input type="radio" name="lbUSPS" value="Yes" <?php if ($lbUSPS=="Yes") echo "checked"; ?>> Yes
        </label>
        <label class="choice">
          <input type="radio" name="lbUSPS" value="No" <?php if ($lbUSPS=="No") echo "checked"; ?>> No
        </label>
      </div>
    </div>

    <div class="form-group">
      <label>Commercial Courier (UPS or FedEx)</label>
      <div class="inline-options">
        <label class="choice">
          <input type="radio" name="lbCommCourier" value="Yes" <?php if ($lbCommCourier=="Yes") echo "checked"; ?>> Yes
        </label>
        <label class="choice">
          <input type="radio" name="lbCommCourier" value="No" <?php if ($lbCommCourier=="No") echo "checked"; ?>> No
        </label>
      </div>
    </div>

  </div>
</div>

<!-- Items Willing to Loan -->
 <?php
echo '<pre style="background:#eef;border:1px solid #99f;padding:10px;margin-bottom:15px;">';
echo "DEBUG – ITEM VALUES LOADED FROM DB\n";
printf("book_loan: [%s]\n", $book_loan ?? '(missing)');
printf("periodical_loan: [%s]\n", $periodical_loan ?? '(missing)');
printf("av_loan: [%s]\n", $av_loan ?? '(missing)');
printf("theses_loan: [%s]\n", $theses_loan ?? '(missing)');
printf("ebook_request: [%s]\n", $ebook_request ?? '(missing)');
printf("ejournal_request: [%s]\n", $ejournal_request ?? '(missing)');
echo "</pre>";
?>

<div class="section-card">
  <h4>Items Willing to Loan</h4>
  <div class="form-section two-col">

    <div class="form-group">
      <label>Print Books</label>
      <div class="inline-options">
<label class="choice">
  <input type="radio" name="book" value="Yes" <?php if (in_array(strtolower(trim($book_loan)), ['yes', '1', 'y'], true)) echo 'checked'; ?>> Yes
</label>
<label class="choice">
  <input type="radio" name="book" value="No" <?php if (in_array(strtolower(trim($book_loan)), ['no', '0', 'n'], true)) echo 'checked'; ?>> No
</label>

      </div>
    </div>

    <div class="form-group">
      <label>Print Journals / Articles</label>
      <div class="inline-options">
   <label class="choice">
  <input type="radio" name="journal" value="Yes" <?php if (in_array(strtolower(trim($periodical_loan)), ['yes', '1', 'y'], true)) echo 'checked'; ?>> Yes
</label>
<label class="choice">
  <input type="radio" name="journal" value="No" <?php if (in_array(strtolower(trim($periodical_loan)), ['no', '0', 'n'], true)) echo 'checked'; ?>> No
</label>

      </div>
    </div>

    <div class="form-group">
      <label>Audio / Video Materials</label>
      <div class="inline-options">
<label class="choice">
  <input type="radio" name="av" value="Yes" <?php if (in_array(strtolower(trim($av_loan)), ['yes', '1', 'y'], true)) echo 'checked'; ?>> Yes
</label>
<label class="choice">
  <input type="radio" name="av" value="No" <?php if (in_array(strtolower(trim($av_loan)), ['no', '0', 'n'], true)) echo 'checked'; ?>> No
</label>

      </div>
    </div>

    <div class="form-group">
      <label>Reference / Microfilm</label>
      <div class="inline-options">
<label class="choice">
  <input type="radio" name="reference" value="Yes" <?php if (in_array(strtolower(trim($theses_loan)), ['yes', '1', 'y'], true)) echo 'checked'; ?>> Yes
</label>
<label class="choice">
  <input type="radio" name="reference" value="No" <?php if (in_array(strtolower(trim($theses_loan)), ['no', '0', 'n'], true)) echo 'checked'; ?>> No
</label>

      </div>
    </div>

    <div class="form-group">
      <label>Electronic Books</label>
      <div class="inline-options">
 <label class="choice">
  <input type="radio" name="ebook" value="Yes" <?php if (in_array(strtolower(trim($ebook_request)), ['yes', '1', 'y'], true)) echo 'checked'; ?>> Yes
</label>
<label class="choice">
  <input type="radio" name="ebook" value="No" <?php if (in_array(strtolower(trim($ebook_request)), ['no', '0', 'n'], true)) echo 'checked'; ?>> No
</label>
      </div>
    </div>

    <div class="form-group">
      <label>Electronic Journals</label>
      <div class="inline-options">
<label class="choice">
  <input type="radio" name="ejournal" value="Yes" <?php if (in_array(strtolower(trim($ejournal_request)), ['yes', '1', 'y'], true)) echo 'checked'; ?>> Yes
</label>
<label class="choice">
  <input type="radio" name="ejournal" value="No" <?php if (in_array(strtolower(trim($ejournal_request)), ['no', '0', 'n'], true)) echo 'checked'; ?>> No
</label>
      </div>
    </div>

  </div>
</div>




    <div class="actions" style="margin-top:20px;">
      <input class="seal-btn" type="submit" value="Save Changes">
      <a class="seal-btn secondary" href="<?php echo h($current_page); ?>">Cancel</a>
    </div>
  </form>
</div>
<?php
// ========================================
// MASS SUSPEND
// ========================================
} elseif ($pageaction == '5') {
    echo '<div class="seal-card"><div class="seal-title">Mass Suspend / Activate</div>
          <div class="seal-sub">Applies to all libraries in '.h($field_home_library_system).'</div>
          <form method="post" action="/status-confirmation">
          <input type="hidden" name="system" value="'.h($field_home_library_system).'">
          <div class="seal-row">
          <label><input type="radio" name="task" value="suspend"> Suspend</label>
          <label><input type="radio" name="task" value="activate" checked> Activate</label>
          </div>
          <div class="seal-row"><label><b>Suspension End Date:</b> 
          <input class="input" id="suspend_enddate" name="enddate" type="text" placeholder="MM/DD/YYYY"></label></div>
          <div style="margin-top:10px;"><button class="seal-btn" type="submit">Submit</button></div></form></div>';

// ========================================
// DEFAULT LIST VIEW
// ========================================
} else {
    $SQL = "SELECT * FROM `$sealLIB` WHERE `system` LIKE '%" . mysqli_real_escape_string($db, $field_home_library_system) . "%'";
    if ($filter_library)  $SQL .= " AND `Name` LIKE '%" . mysqli_real_escape_string($db, $filter_library) . "%'";
    if ($filter_alias)    $SQL .= " AND `alias` LIKE '%" . mysqli_real_escape_string($db, $filter_alias) . "%'";
    if ($filter_loc)      $SQL .= " AND `loc` LIKE '%" . mysqli_real_escape_string($db, $filter_loc) . "%'";
    if ($filter_illemail) $SQL .= " AND `ill_email` LIKE '%" . mysqli_real_escape_string($db, $filter_illemail) . "%'";
    $SQL .= " ORDER BY `Name` ASC";
    $GETLIST = mysqli_query($db, $SQL);
    $count = $GETLIST ? mysqli_num_rows($GETLIST) : 0;

    if (isset($_GET['updated'])) {
        echo '<div class="seal-card" style="border-left:5px solid #16a34a;"><div class="seal-title">✅ Library Updated</div><div class="seal-sub">Your changes were saved successfully.</div></div>';
    }

    echo '<div class="seal-card">';
    echo '<div class="seal-title">Libraries in '.h($field_home_library_system).' System</div>';
    echo '<div class="seal-sub">Showing all libraries within your system.</div>';
    echo '<form method="post" action="'.h($current_page).'">';
    echo '<div class="seal-row">';
    echo '<label><b>Library Name</b><br><input class="input" name="library" value="'.h($filter_library).'"></label>';
    echo '<label><b>Alias</b><br><input class="input" name="filter_alias" value="'.h($filter_alias).'"></label>';
    echo '<label><b>LOC Code</b><br><input class="input" name="loc" value="'.h($filter_loc).'"></label>';
    echo '<label><b>Email</b><br><input class="input" name="filter_illemail" value="'.h($filter_illemail).'"></label>';
    echo '</div><div class="seal-row"><button class="seal-btn" type="submit">Search</button></div></form></div>';

    echo '<div class="seal-card"><div class="seal-row">';
    echo '<a class="seal-btn secondary" href="'.h($current_page).'?action=5">Mass Suspend/Activate</a>';
    echo '</div></div>';

    echo '<div class="seal-card">';
    echo '<table class="seal-table">';
    echo '<tr><th>Library</th><th>Alias</th><th>Participant</th><th>Suspend</th><th>System</th><th>OCLC</th><th>LOC</th><th>Actions</th></tr>';
    if ($count == 0) {
        echo '<tr><td colspan="8">No libraries found in this system.</td></tr>';
    } else {
        while ($row = mysqli_fetch_assoc($GETLIST)) {
            $librecnumb = $row["recnum"];
            $partClass = ($row["participant"] ? "status-yes" : "status-no");
            $partText  = ($row["participant"] ? "Yes" : "No");
            $suspendClass = ($row["suspend"] ? "status-no" : "status-yes");
            $suspendText  = ($row["suspend"] ? "Yes" : "No");



            echo '<tr>';
            echo '<td>'.h($row["Name"]).'</td>';
            echo '<td>'.h($row["alias"]).'</td>';
            echo '<td class="'.$partClass.'">'.$partText.'</td>';
            echo '<td class="'.$suspendClass.'">'.$suspendText.'</td>';
            echo '<td>'.h($row["system"]).'</td>';
            echo '<td>'.h($row["oclc"]).'</td>';
            echo '<td>'.h($row["loc"]).'</td>';
            echo '<td><div class="action-buttons">
                    <a class="seal-btn secondary" href="'.h($current_page).'?action=2&librecnumb='.h($librecnumb).'">Edit</a>
                    <a class="seal-btn secondary" href="'.h($current_page).'?action=3&librecnumb='.h($librecnumb).'">Delete</a>
                  </div></td>';
            echo '</tr>';
        }
    }
    echo '</table></div>';
}
?>
</div></div>