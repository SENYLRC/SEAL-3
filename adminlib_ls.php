<?php
// adminlib_ls.php — styled like the Library Profile page, with working datepickers
// Library System is read-only and always pulled from the signed-in user's profile.

// --- Session (for your export feature) ---
session_id('YOUR_SESSION_ID');
session_start();

// --- Includes ---
require '/var/www/seal_wp_script/seal_function.php';
require '/var/www/seal_wp_script/seal_db.inc';

// $sealLIB and $field_home_library_system are expected from your includes

// --- Helpers (fallbacks if not in full WP context) ---
if (!function_exists('selected')) {
    function selected($value, $current) { return ((string)$value === (string)$current) ? 'selected="selected"' : ''; }
}
if (!function_exists('checked')) {
    // Accepts truthy strings like "yes" or "1"
    function checked($value, $current = 'yes') {
        $v = is_bool($value) ? ($value ? 'yes' : 'no') : (string)$value;
        return ($v === (string)$current) ? 'checked="checked"' : '';
    }
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// --- DB ---
$db = mysqli_connect($dbhost, $dbuser, $dbpass);
mysqli_select_db($db, $dbname);

// --- Request vars (safe defaults) ---
$firstpass = (isset($_REQUEST['firstpass']) ? "no" : "yes");

$filter_library = isset($_REQUEST['library']) ? $_REQUEST['library'] : "";
$filter_loc     = isset($_REQUEST['loc']) ? $_REQUEST['loc'] : "";
$filter_alias   = isset($_REQUEST['filter_alias']) ? $_REQUEST['filter_alias'] : "";
$filter_illemail= isset($_REQUEST['filter_illemail']) ? $_REQUEST['filter_illemail'] : "";
$filter_numresults = isset($_REQUEST['filter_numresults']) ? $_REQUEST['filter_numresults'] : "25";
$filter_offset  = isset($_REQUEST['filter_offset']) ? $_REQUEST['filter_offset'] : "0";

if ($firstpass == "no") {
    if (($filter_library != "") || ($filter_loc != "") || ($filter_alias != "") || ($filter_illemail != "")) {
        $filter_aliasblank   = "";
        $filter_illemailblank= "";
        $filter_illpart      = isset($_REQUEST['filter_illpart']) ? $_REQUEST['filter_illpart'] : "";
        $filter_suspend      = isset($_REQUEST['filter_suspend']) ? $_REQUEST['filter_suspend'] : "";
        $filter_system       = $field_home_library_system;
    } else {
        $filter_illemailblank= isset($_REQUEST['filter_illemailblank']) ? $_REQUEST['filter_illemailblank'] : "";
        $filter_illpart      = isset($_REQUEST['filter_illpart']) ? $_REQUEST['filter_illpart'] : "";
        $filter_suspend      = isset($_REQUEST['filter_suspend']) ? $_REQUEST['filter_suspend'] : "";
        $filter_system       = $field_home_library_system;
        $filter_aliasblank   = isset($_REQUEST['filter_aliasblank']) ? $_REQUEST['filter_aliasblank'] : "";
    }
} else {
    $filter_offset     = "0";
    $filter_alias      = "";
    $filter_aliasblank = "";
    $filter_illemail   = "";
    $filter_illemailblank = "";
    $filter_suspend    = "";
    $filter_numresults = "25";
    $filter_illpart    = (($filter_library != "") || ($filter_loc != "")) ? "" : "yes";
}

// --- Actions ---
$pageaction = isset($_REQUEST['action']) ? $_REQUEST['action'] : '0';
$librecnumb = isset($_REQUEST['librecnumb']) ? $_REQUEST['librecnumb'] : null;

// Common inputs used across actions
$libname       = isset($_REQUEST['libname']) ? $_REQUEST['libname'] : "";
$libalias      = isset($_REQUEST['libalias']) ? $_REQUEST['libalias'] : "";
$libemail      = isset($_REQUEST['libemail']) ? $_REQUEST['libemail'] : "";
$libilliad     = isset($_REQUEST['libilliad']) ? $_REQUEST['libilliad'] : "";
$libilliadkey  = isset($_REQUEST['libilliadkey']) ? $_REQUEST['libilliadkey'] : "";
$libilliadurl  = isset($_REQUEST['libilliadurl']) ? $_REQUEST['libilliadurl'] : "";
$libemailalert = isset($_REQUEST['libemailalert']) ? $_REQUEST['libemailalert'] : "";
$participant   = isset($_REQUEST['participant']) ? $_REQUEST['participant'] : "";
$suspend       = isset($_REQUEST['suspend']) ? $_REQUEST['suspend'] : "";
$enddate       = isset($_REQUEST['enddate']) ? $_REQUEST['enddate'] : "";
$system        = $field_home_library_system; // authoritative source
$phone         = isset($_REQUEST['phone']) ? $_REQUEST['phone'] : "";
$address1      = isset($_REQUEST['address1']) ? $_REQUEST['address1'] : "";
$address2      = isset($_REQUEST['address2']) ? $_REQUEST['address2'] : "";
$address3      = isset($_REQUEST['address3']) ? $_REQUEST['address3'] : "";
$oclc          = isset($_REQUEST['oclc']) ? $_REQUEST['oclc'] : "";
$loc           = isset($_REQUEST['loc']) ? $_REQUEST['loc'] : "";
$book          = isset($_REQUEST['book']) ? $_REQUEST['book'] : "";
$journal       = isset($_REQUEST['journal']) ? $_REQUEST['journal'] : "";
$ebook         = isset($_REQUEST['ebook']) ? $_REQUEST['ebook'] : "";
$ejournal      = isset($_REQUEST['ejournal']) ? $_REQUEST['ejournal'] : "";
$reference     = isset($_REQUEST['reference']) ? $_REQUEST['reference'] : "";

// --- UI shell + assets (match library profile page vibe) ---
?>
<style>
.seal-shell {font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, "Helvetica Neue", Arial, sans-serif; color:#111; }
.seal-wrap {max-width: 1100px; margin: 0 auto; padding: 18px;}
.seal-card {background:#fff; border:1px solid #e5e7eb; border-radius:14px; box-shadow: 0 1px 2px rgba(0,0,0,.04); padding:18px; margin-bottom:16px;}
.seal-title {font-size: 22px; font-weight:700; margin: 0 0 8px;}
.seal-sub {font-size:14px; color:#555; margin-bottom: 12px;}
.seal-row {display:flex; gap:12px; flex-wrap:wrap; align-items:center;}
.seal-row .input {padding:8px 10px; border:1px solid #d1d5db; border-radius:8px; min-width: 220px;}
.seal-btn {display:inline-block; padding:9px 14px; border-radius:10px; border:1px solid #0f766e; background:#0d9488; color:#fff; text-decoration:none; font-weight:600; cursor:pointer;}
.seal-btn.secondary { background:#fff; color:#0f766e; }
.seal-btn + .seal-btn { margin-left:8px;}
.seal-muted {color:#555;}
.seal-table {width:100%; border-collapse: collapse;}
.seal-table th, .seal-table td { padding:10px 8px; border-bottom:1px solid #eef2f7; text-align:left; }
.seal-table th { font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#6b7280; background:#fafafa;}
.seal-pills {display:flex; gap:12px; flex-wrap:wrap;}
.seal-pill {display:flex; gap:6px; align-items:center;}
.seal-pill input[type="checkbox"] { transform: scale(1.2); }
.seal-hr { border:0; border-top:1px solid #e5e7eb; margin:16px 0;}
.seal-note { font-size:13px; color:#6b7280;}
@media (max-width: 720px) {
  .seal-row .input { min-width: 100%; }
  .seal-table th:nth-child(6), .seal-table td:nth-child(6) { display:none; } /* hide OCLC on mobile */
}
</style>
<script>
// Ensure jQuery UI Datepicker exists; if not, load it + CSS, then init.
(function(){
  function addCss(href){ var l=document.createElement('link'); l.rel='stylesheet'; l.href=href; document.head.appendChild(l); }
  function addJs(src, cb){ var s=document.createElement('script'); s.src=src; s.onload=cb; document.head.appendChild(s); }

  function initPickers(){
    if (!window.jQuery) return;
    jQuery(function($){
      var init = function(){
        if ($.fn.datepicker) {
          $('#datepicker, #suspend_enddate').datepicker({ dateFormat: 'mm/dd/yy' });
        }
      };
      if ($.ui && $.ui.datepicker) { init(); }
      else {
        addCss('https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css');
        addJs('https://code.jquery.com/ui/1.13.2/jquery-ui.min.js', init);
      }
    });
  }

  if (window.jQuery) { initPickers(); }
  else {
    addJs('https://code.jquery.com/jquery-3.7.1.min.js', initPickers);
  }
})();
</script>

<div class="seal-shell"><div class="seal-wrap">
<?php
// ======================= ACTION 3: Delete =======================
if ($pageaction == '3') {
    if (($_SERVER['REQUEST_METHOD'] == 'POST') || (isset($_GET['page']))) {
        $librecnumb = mysqli_real_escape_string($db, $librecnumb);
        $sqldel = "DELETE FROM `$sealLIB` WHERE recnum='$librecnumb'";
        mysqli_query($db, $sqldel);
        echo '<div class="seal-card"><div class="seal-title">Library deleted</div><a class="seal-btn" href="'.h($_SERVER['REDIRECT_URL']).'">Return to main list</a></div>';
    } else {
        echo '<div class="seal-card">';
        echo '<div class="seal-title">Confirm delete</div>';
        echo '<form method="post" action="'.h($_SERVER['REDIRECT_URL']).'?'.h($_SERVER['QUERY_STRING']).'">';
        echo '<input type="hidden" name="action" value="3">';
        echo '<input type="hidden" name="librecnumb" value="'.h($librecnumb).'">';
        echo '<button class="seal-btn">Confirm</button> <a class="seal-btn secondary" href="'.h($_SERVER['REDIRECT_URL']).'">Cancel</a>';
        echo '</form></div>';
    }

// =================== ACTION 5: Mass suspend/activate ===================
} elseif ($pageaction == '5') {
    echo '<div class="seal-card">';
    echo '<div class="seal-title">Mass suspend / activate lending</div>';
    echo '<div class="seal-sub">Select an action for your system.</div>';
    echo '<form method="post" action="/status-confirmation">';
    echo '<input type="hidden" name="system" value="'.h($field_home_library_system).'">';
    echo '<div class="seal-row">';
    echo '<label class="seal-pill"><input type="radio" name="task" value="suspend"> <span>Suspend lending</span></label>';
    echo '<label class="seal-pill"><input type="radio" name="task" value="activate" checked> <span>Activate lending</span></label>';
    echo '</div><div class="seal-hr"></div>';
    echo '<div class="seal-row seal-muted"><b>Library System:</b> '.h($field_home_library_system).'</div>';
    echo '<div class="seal-row"><label><b>Suspension End Date:</b> <input class="input" id="suspend_enddate" name="enddate" type="text" placeholder="MM/DD/YYYY"></label></div>';
    echo '<div style="margin-top:10px;"><button class="seal-btn" type="submit">Submit</button></div>';
    echo '</form></div>';

// ========================== ACTION 1: Add ==========================
} elseif ($pageaction == '1') {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $timestamp = date("Y-m-d H:i:s");

        // Force system from user profile (ignore any request value)
        $system = $field_home_library_system;

        // escape & trim
        foreach (['libname','libalias','libemail','address1','address2','address3','phone','loc','book','journal','av','ebook','ejournal','reference','oclc','participant','suspend'] as $k) {
            $$k = mysqli_real_escape_string($db, trim($$k));
        }

        $insertsql  = "
        INSERT INTO `$sealLIB` (`recnum`, `Name`, `ill_email`, `alias`, `participant`, `suspend`, `system`, `phone`, `address1`, `address2`, `address3`,  `loc`, `oclc`, `book_loan`,`periodical_loan`,`av_loan`, `ebook_request`, `ejournal_request`,`theses_loan`,`ModifyDate`)
        VALUES (NULL,'$libname','$libemail','$libalias','$participant','$suspend','$system','$phone','$address1','$address2','$address3','$loc','$oclc','$book','$journal','$av','$ebook','$ejournal','$reference','$timestamp')";
        mysqli_query($db, $insertsql);

        echo '<div class="seal-card"><div class="seal-title">Library added</div>
              <a class="seal-btn" href="'.h($_SERVER['REDIRECT_URL']).'">Return to main list</a>
              <a class="seal-btn secondary" href="'.h($_SERVER['REDIRECT_URL']).'?action=1">Add another</a></div>';
    } else {
        echo '<div class="seal-card"><div class="seal-title">Add Library</div>';
        echo '<form method="post" action="'.h($_SERVER['REDIRECT_URL']).'?'.h($_SERVER['QUERY_STRING']).'">';
        ?>
        <div class="seal-row">
            <label><b>Library Name</b><br><input class="input" type="text" name="libname" maxlength="255" size="60"></label>
            <label><b>Library Alias</b><br><input class="input" type="text" name="libalias" maxlength="255" size="60"></label>
            <label><b>Library ILL Email</b><br><input class="input" type="text" name="libemail" maxlength="255" size="60"></label>
        </div>
        <div class="seal-row">
            <label><b>Library Phone</b><br><input class="input" type="text" name="phone" maxlength="255" size="60"></label>
            <label><b>ILL Dept</b><br><input class="input" type="text" name="address1" maxlength="255" size="60"></label>
            <label><b>Street Address</b><br><input class="input" type="text" name="address2" maxlength="255" size="60"></label>
        </div>
        <div class="seal-row">
            <label><b>City State Zip</b><br><input class="input" type="text" name="address3" maxlength="255" size="60"></label>
            <label><b>OCLC Symbol</b><br><input class="input" type="text" name="oclc" maxlength="255" size="60"></label>
            <label><b>LOC Location</b><br>
              <input class="input" type="text" name="loc" maxlength="255" size="60">
              <div class="seal-note"><a target="_blank" href="https://www.loc.gov/marc/organizations/">(Required for ILL)</a></div>
            </label>
        </div>
        <div class="seal-row">
            <label><b>Library System</b><br>
              <input class="input" type="text" value="<?php echo h($field_home_library_system); ?>" disabled>
              <input type="hidden" name="system" value="<?php echo h($field_home_library_system); ?>">
            </label>
            <label><b>Library ILL participant</b><br>
                <select class="input" name="participant">
                    <option value="1">Yes</option><option value="0">No</option>
                </select>
            </label>
            <label><b>Suspend ILL</b><br>
                <select class="input" name="suspend">
                    <option value="0">No</option><option value="1">Yes</option>
                </select>
            </label>
        </div>
        <div class="seal-hr"></div>
        <div class="seal-title" style="font-size:18px;">Items willing to loan in SEAL</div>
        <div class="seal-row">
            <label><b>Print Book</b><br>
                <label class="seal-pill"><input type="radio" name="book" value="Yes"> Yes</label>
                <label class="seal-pill"><input type="radio" name="book" value="No"> No</label>
            </label>
            <label><b>Print Journal or Article</b><br>
                <label class="seal-pill"><input type="radio" name="journal" value="Yes"> Yes</label>
                <label class="seal-pill"><input type="radio" name="journal" value="No"> No</label>
            </label>
            <label><b>Audio Video Materials</b><br>
                <label class="seal-pill"><input type="radio" name="av" value="Yes"> Yes</label>
                <label class="seal-pill"><input type="radio" name="av" value="No"> No</label>
            </label>
        </div>
        <div class="seal-row">
            <label><b>Reference/Microfilm</b><br>
                <label class="seal-pill"><input type="radio" name="reference" value="Yes"> Yes</label>
                <label class="seal-pill"><input type="radio" name="reference" value="No"> No</label>
            </label>
            <label><b>Electronic Book</b><br>
                <label class="seal-pill"><input type="radio" name="ebook" value="Yes"> Yes</label>
                <label class="seal-pill"><input type="radio" name="ebook" value="No"> No</label>
            </label>
            <label><b>Electronic Journal</b><br>
                <label class="seal-pill"><input type="radio" name="ejournal" value="Yes"> Yes</label>
                <label class="seal-pill"><input type="radio" name="ejournal" value="No"> No</label>
            </label>
        </div>
        <div style="margin-top:12px;"><button class="seal-btn" type="submit">Submit</button></div>
        <?php
        echo '</form></div>';
    }

// ========================== ACTION 2: Edit ==========================
} elseif ($pageaction == '2') {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $timestamp = date("Y-m-d H:i:s");

        // Force system from user profile (ignore any request value)
        $system = $field_home_library_system;

        foreach (['libname','libalias','libemail','address1','address2','address3','phone','book','journal','av','ebook','ejournal','reference','oclc','loc','libilliadurl','participant','suspend','libilliad','libilliadkey','libemailalert'] as $k) {
            $$k = mysqli_real_escape_string($db, trim($$k));
        }

        // End date default if suspending without date
        if ((int)$suspend === 1 && strlen($enddate) < 2) {
            $enddate = date('Y-m-d', strtotime("+7 day"));
        } else {
            $enddate = date('Y-m-d', strtotime(str_replace('-', '/', $enddate)));
        }

        $email = isset($_REQUEST['email']) ? mysqli_real_escape_string($db, $_REQUEST['email']) : '';

        $sqlupdate = "
        UPDATE `$sealLIB`
           SET Name = '$libname',
               alias='$libalias',
               `ill_email` ='$libemail',
               participant=$participant,
               suspend=$suspend,
               SuspendDateEnd='$enddate',
               `system`='$system',
               phone='$phone',
               address1='$address1',
               address2='$address2',
               address3='$address3',
               oclc='$oclc',
               loc='$loc',
               book_loan='$book',
               periodical_loan='$journal',
               av_loan='$av',
               ebook_request='$ebook',
               ejournal_request='$ejournal',
               theses_loan='$reference',
               ModifyDate='$timestamp',
               Illiad='$libilliad',
               IlliadURL='$libilliadurl',
               APIkey='$libilliadkey',
               ModEmail ='$email',
               LibEmailAlert='$libemailalert'
         WHERE `recnum` = '".mysqli_real_escape_string($db, $librecnumb)."' ";

        mysqli_query($db, $sqlupdate);
        echo '<div class="seal-card"><div class="seal-title">Library updated</div><a class="seal-btn" href="'.h($_SERVER['REDIRECT_URL']).'">Return to main list</a></div>';

    } else {
        $GETEDITLISTSQL="SELECT * FROM `$sealLIB` WHERE `recnum` ='".mysqli_real_escape_string($db, $librecnumb)."'";
        $GETLIST = mysqli_query($db, $GETEDITLISTSQL);
        $row = mysqli_fetch_assoc($GETLIST);

        // hydrate vars
        $libname       = $row["Name"];
        $libalias      = $row["alias"];
        $libemail      = $row["ill_email"];
        $phone         = $row["phone"];
        $libparticipant= $row["participant"];
        $libemailalert = $row["LibEmailAlert"];
        $libilliad     = $row["Illiad"];
        $libilliadkey  = $row["APIkey"];
        $oclc          = $row["oclc"];
        $loc           = $row["loc"];
        $lastmodemail  = $row["ModEmail"];
        $libilliadurl  = $row["IlliadURL"];
        $libsuspend    = $row["suspend"];
        $system_current= $row["system"]; // display only; not editable
        $address1      = $row["address1"];
        $address2      = $row["address2"];
        $address3      = $row["address3"];
        $book          = $row["book_loan"];
        $reference     = $row["theses_loan"];
        $av            = $row["av_loan"];
        $ebook         = $row["ebook_request"];
        $ejournal      = $row["ejournal_request"];
        $journal       = $row["periodical_loan"];
        $timestamp     = $row["ModifyDate"];
        $enddateshow   = $row["SuspendDateEnd"];

        echo '<div class="seal-card"><div class="seal-title">Edit Library</div>';
        echo '<form method="post" action="/adminlib_ls?'.h($_SERVER['QUERY_STRING']).'">';
        echo '<input type="hidden" name="return_qs" value="'.h($_SERVER['QUERY_STRING'] ?? ($_SESSION['query2_qs'] ?? '')).'">';
        ?>
        <div class="seal-row">
            <label><b>Library Name</b><br><input class="input" type="text" name="libname" value="<?php echo h($libname)?>"></label>
            <label><b>Library Alias</b><br><input class="input" type="text" name="libalias" value="<?php echo h($libalias)?>"></label>
            <label><b>Library ILL Email</b><br><input class="input" type="text" name="libemail" value="<?php echo h($libemail)?>"></label>
        </div>
        <div class="seal-row">
            <label><b>Library Phone</b><br><input class="input" type="text" name="phone" value="<?php echo h($phone)?>"></label>
            <label><b>ILL Dept</b><br><input class="input" type="text" name="address1" value="<?php echo h($address1)?>"></label>
            <label><b>Street Address</b><br><input class="input" type="text" name="address2" value="<?php echo h($address2)?>"></label>
        </div>
        <div class="seal-row">
            <label><b>City State Zip</b><br><input class="input" type="text" name="address3" value="<?php echo h($address3)?>"></label>
            <label><b>OCLC Symbol</b><br><input class="input" type="text" name="oclc" value="<?php echo h($oclc)?>"></label>
            <label><b>LOC Location</b><br>
              <input class="input" type="text" name="loc" value="<?php echo h($loc)?>">
              <div class="seal-note"><a target="_blank" href="https://www.loc.gov/marc/organizations/">(Required for ILL)</a></div>
            </label>
        </div>
        <div class="seal-row">
            <label><b>Library System</b><br>
              <input class="input" type="text" value="<?php echo h($field_home_library_system); ?>" disabled>
              <input type="hidden" name="system" value="<?php echo h($field_home_library_system); ?>">
              <div class="seal-note">Currently stored value for this record: <?php echo h($system_current ?: '(none)'); ?></div>
            </label>
            <label><b>Library Email Alert</b><br>
                <select class="input" name="libemailalert">
                    <option value="1" <?php echo selected("1", $libemailalert); ?>>Yes</option>
                    <option value="0" <?php echo selected("0", $libemailalert); ?>>No</option>
                </select>
            </label>
            <label><b>Library ILLiad</b><br>
                <select class="input" name="libilliad">
                    <option value="1" <?php echo selected("1", $libilliad); ?>>Yes</option>
                    <option value="0" <?php echo selected("0", $libilliad); ?>>No</option>
                </select>
            </label>
        </div>
        <div class="seal-row">
            <label><b>ILLiad URL</b><br><input class="input" type="text" name="libilliadurl" value="<?php echo h($libilliadurl)?>"></label>
            <label><b>ILLiad API key</b><br><input class="input" type="text" name="libilliadkey" value="<?php echo h($libilliadkey)?>"></label>
            <label><b>Library ILL participant</b><br>
                <select class="input" name="participant">
                    <option value="1" <?php echo selected("1", $libparticipant); ?>>Yes</option>
                    <option value="0" <?php echo selected("0", $libparticipant); ?>>No</option>
                </select>
            </label>
        </div>
        <div class="seal-row">
            <label><b>Suspend ILL</b><br>
                <select class="input" name="suspend">
                    <option value="0" <?php echo selected("0", $libsuspend); ?>>No</option>
                    <option value="1" <?php echo selected("1", $libsuspend); ?>>Yes</option>
                </select>
                <div class="seal-note" style="margin-top:6px;"><b>Suspension End Date:</b> <input class="input" id="datepicker" name="enddate" type="text" placeholder="MM/DD/YYYY"></div>
                <div class="seal-note" style="margin-top:6px;">
                    <?php if ($libsuspend=="1") { echo 'This Library has <strong>suspension enabled</strong> until '.h($enddateshow); }
                          else { echo 'This Library has suspension disabled'; } ?>
                </div>
            </label>
        </div>

        <div class="seal-hr"></div>
        <div class="seal-title" style="font-size:18px;">Items willing to loan in SEAL</div>
        <div class="seal-row">
            <label><b>Print Book</b><br>
                <label class="seal-pill"><input type="radio" name="book" value="Yes" <?php echo ($book=="Yes"?'checked':''); ?>> Yes</label>
                <label class="seal-pill"><input type="radio" name="book" value="No"  <?php echo ($book=="No"?'checked':''); ?>> No</label>
            </label>
            <label><b>Print Journal or Article</b><br>
                <label class="seal-pill"><input type="radio" name="journal" value="Yes" <?php echo ($journal=="Yes"?'checked':''); ?>> Yes</label>
                <label class="seal-pill"><input type="radio" name="journal" value="No"  <?php echo ($journal=="No"?'checked':''); ?>> No</label>
            </label>
            <label><b>Audio Video Materials</b><br>
                <label class="seal-pill"><input type="radio" name="av" value="Yes" <?php echo ($av=="Yes"?'checked':''); ?>> Yes</label>
                <label class="seal-pill"><input type="radio" name="av" value="No"  <?php echo ($av=="No"?'checked':''); ?>> No</label>
            </label>
        </div>
        <div class="seal-row">
            <label><b>Theses</b><br>
                <label class="seal-pill"><input type="radio" name="reference" value="Yes" <?php echo ($reference=="Yes"?'checked':''); ?>> Yes</label>
                <label class="seal-pill"><input type="radio" name="reference" value="No"  <?php echo ($reference=="No"?'checked':''); ?>> No</label>
            </label>
            <label><b>Electronic Book</b><br>
                <label class="seal-pill"><input type="radio" name="ebook" value="Yes" <?php echo ($ebook=="Yes"?'checked':''); ?>> Yes</label>
                <label class="seal-pill"><input type="radio" name="ebook" value="No"  <?php echo ($ebook=="No"?'checked':''); ?>> No</label>
            </label>
            <label><b>Electronic Journal</b><br>
                <label class="seal-pill"><input type="radio" name="ejournal" value="Yes" <?php echo ($ejournal=="Yes"?'checked':''); ?>> Yes</label>
                <label class="seal-pill"><input type="radio" name="ejournal" value="No"  <?php echo ($ejournal=="No"?'checked':''); ?>> No</label>
            </label>
        </div>

        <div style="margin-top:12px;"><button class="seal-btn" type="submit">Submit</button></div>
        <div class="seal-note" style="margin-top:10px;">Last Modified: <?php echo h($timestamp); ?> by <?php echo h($lastmodemail); ?></div>
        <?php
        echo '</form></div>';
    }

// ========================= DEFAULT LIST =========================
} else {
    // Build filters -> SQL
    $SQLBASE="SELECT * FROM `$sealLIB` WHERE ";
    $SQLEND =" ORDER BY `Name` ASC ";
    if ($filter_numresults != "all") {
        $sqllimiter = max(0, (int)$filter_numresults * (int)$filter_offset);
        $SQLLIMIT = " LIMIT " . $sqllimiter . ", " . (int)$filter_numresults;
    } else {
        $SQLLIMIT = "";
    }

    $SQLMIDDLE  = ($filter_illpart == "yes") ? "`participant` = 1 " : "`participant` = 0 ";
    $SQLMIDDLE .= ($filter_aliasblank == "yes")    ? "AND `alias` = '' "             : " ";
    $SQLMIDDLE .= ($filter_illemailblank == "yes") ? "AND `ill_email` = '' "         : " ";
    $SQLMIDDLE .= (strlen($filter_loc) >= 1)       ? "AND `loc` LIKE '%".mysqli_real_escape_string($db,$filter_loc)."%' " : "";
    $SQLMIDDLE .= (strlen($filter_illemail) >= 1)  ? "AND `ill_email` LIKE '%".mysqli_real_escape_string($db,$filter_illemail)."%' " : "";
    $SQLMIDDLE .= (strlen($filter_alias) >= 1)     ? "AND `alias` LIKE '%".mysqli_real_escape_string($db,$filter_alias)."%' " : "";
    $SQLMIDDLE .= ($filter_suspend == "yes")       ? "AND `suspend` = 1 "            : " AND `suspend` = 0 ";
    $SQLMIDDLE .= (strlen($filter_library) >= 2)   ? "AND `Name` LIKE '%".mysqli_real_escape_string($db,$filter_library)."%' " : "";

    // Always constrain to the signed-in user's system
    if (strlen($field_home_library_system) >= 1) {
        $SQLMIDDLE .= "AND `system` LIKE '%".mysqli_real_escape_string($db,$field_home_library_system)."%' ";
    } else {
        // Fallback set (if no profile system for some reason)
        $SQLMIDDLE .= "AND (`system` LIKE '%CRB%' OR `system` LIKE '%Q3S%' OR `system` LIKE '%HFM%' OR `system` LIKE '%WSWHE%') ";
    }

    $GETFULLSQL = $SQLBASE . $SQLMIDDLE . $SQLEND;
    $GETLISTSQL = $GETFULLSQL . $SQLLIMIT;

    $GETLIST   = mysqli_query($db, $GETLISTSQL);
    $GETCOUNT  = mysqli_query($db, $GETFULLSQL);
    $GETLISTCOUNTwhole = mysqli_num_rows($GETCOUNT);

    /* Persist export SQL + current filters */
    $_SESSION['query2']    = $GETFULLSQL;
    $_SESSION['query2_qs'] = $_SERVER['QUERY_STRING'] ?? '';

    // Pagination calc
    $per_page = ($filter_numresults == 'all') ? $GETLISTCOUNTwhole : max(1, (int)$filter_numresults);
    $resultpages = ($per_page > 0) ? (int)ceil($GETLISTCOUNTwhole / $per_page) : 1;
    $display_page = (int)$filter_offset + 1;

    // FILTER CARD
    echo '<div class="seal-card">';
    echo '<div class="seal-title">Libraries</div>';
    echo '<div class="seal-sub">Filter and manage library records. Export uses your current filter.</div>';
    echo '<form method="post" action="'.h($_SERVER['REDIRECT_URL']).'">';
    echo '<input type="hidden" name="firstpass" value="no">';

    echo '<div class="seal-title" style="font-size:16px; margin-top:6px;">Display Filters</div>';
    echo '<div class="seal-pills">';
    echo '<label class="seal-pill"><input type="checkbox" name="filter_aliasblank" value="yes" '.checked($filter_aliasblank).'> <span>Missing alias</span></label>';
    echo '<label class="seal-pill"><input type="checkbox" name="filter_illemailblank" value="yes" '.checked($filter_illemailblank).'> <span>Missing ILL Email</span></label>';
    echo '<label class="seal-pill"><input type="checkbox" name="filter_illpart" value="yes" '.checked($filter_illpart).'> <span>ILL Participant</span></label>';
    echo '<label class="seal-pill"><input type="checkbox" name="filter_suspend" value="yes" '.checked($filter_suspend).'> <span>ILL Suspended</span></label>';
    echo '</div>';

    echo '<div class="seal-hr"></div>';
    echo '<div class="seal-title" style="font-size:16px;">Search by</div>';
    echo '<div class="seal-row">';
    echo '<label><b>Library Name</b><br><input class="input" name="library" type="text" value="'.h($filter_library).'"></label>';
    echo '<label><b>Library Alias</b><br><input class="input" name="filter_alias" type="text" value="'.h($filter_alias).'"></label>';
    echo '<label><b>ILL Code</b><br><input class="input" name="loc" type="text" value="'.h($filter_loc).'"></label>';
    echo '<label><b>ILL Email</b><br><input class="input" name="filter_illemail" type="text" value="'.h($filter_illemail).'"></label>';
    echo '</div>';

    echo '<div class="seal-row" style="margin-top:10px;">';
    echo '<label><b>Results per page</b><br><select class="input" name="filter_numresults">';
    foreach (['25','50','100','all'] as $opt) {
        echo '<option '.selected($opt, $filter_numresults).' value="'.h($opt).'">'.h($opt).'</option>';
    }
    echo '</select></label>';

    if ($filter_numresults != "all") {
        echo '<label><b>Page</b><br><select class="input" name="filter_offset">';
        for ($x = 1; $x <= max(1,$resultpages); $x++) {
            $localoffset = $x - 1;
            echo '<option '.selected($localoffset, $filter_offset).' value="'.$localoffset.'">'.$x.'</option>';
        }
        echo '</select> <span class="seal-muted">of '.h($resultpages).'</span></label>';
    }
    echo '<div class="seal-row" style="margin-left:auto;">';
    echo '<a class="seal-btn secondary" href="adminlib">Clear</a>';
    echo ' <button class="seal-btn" type="submit">Update</button>';
    echo '</div>';

    echo '</div>'; // row
    echo '<div class="seal-note" style="margin-top:8px;">Total Results: '.h($GETLISTCOUNTwhole).'</div>';
    echo '</form>';
    echo '</div>'; // card

    // ACTION LINKS
    echo '<div class="seal-card">';
    echo '<div class="seal-row">';
    echo '<a class="seal-btn" href="'.h($_SERVER['REDIRECT_URL']).'?action=1">Add a Library</a>';
    echo '<a class="seal-btn secondary" href="'.h($_SERVER['REDIRECT_URL']).'?action=5">Mass suspend/activate</a>';
    echo '<a class="seal-btn secondary" target="_blank" href="/export_ls">Export to CSV</a>';
    echo '</div></div>';

    // RESULTS TABLE
    echo '<div class="seal-card">';
    echo '<table class="seal-table">';
    echo '<tr><th>Library</th><th>Alias</th><th>Participant</th><th>Suspend</th><th>System</th><th>OCLC</th><th>LOC</th><th>Action</th></tr>';
    while ($row = mysqli_fetch_assoc($GETLIST)) {
        $librecnumb = $row["recnum"];
        $libname    = $row["Name"];
        $libalias   = $row["alias"];
        $libparticipant = $row["participant"] == "1" ? "Yes" : "No";
        $libsuspend = $row["suspend"] == "1" ? "Yes" : "No";
        $system     = $row["system"];
        $oclc       = $row["oclc"];
        $loc        = $row["loc"];

        echo '<tr>';
        echo '<td>'.h($libname).'</td>';
        echo '<td>'.h($libalias).'</td>';
        echo '<td>'.h($libparticipant).'</td>';
        echo '<td>'.h($libsuspend).'</td>';
        echo '<td>'.h($system).'</td>';
        echo '<td>'.h($oclc).'</td>';
        echo '<td>'.h($loc).'</td>';
        $curr_qs = $_SERVER['QUERY_STRING'] ?? '';
        echo '<td><a class="seal-btn secondary" href="'.h($_SERVER['REDIRECT_URL']).'?action=2&librecnumb='.h($librecnumb).'&'.h($curr_qs).'">Edit</a> ';
        echo '<a class="seal-btn secondary" href="'.h($_SERVER['REDIRECT_URL']).'?action=3&librecnumb='.h($librecnumb).'&'.h($curr_qs).'">Delete</a></td>';

        echo '</tr>';
    }
    echo '</table>';
    // store full query for export
    $_SESSION['query2'] = $GETFULLSQL;
    echo '</div>';
}
?>
</div></div>