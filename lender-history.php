<?php
/**
 * lender-history.php (ADA/WCAG improvements, same functionality + same visible display)
 *
 * What changed (accessibility only):
 * - Added screen-reader utility class (no visual change)
 * - Added aria-live region for JS messages (still uses alert/confirm for sighted users)
 * - Fixed invalid markup: removed <a> inside <button> (kept same look via inline styles)
 * - Added proper labels/ids for selects/inputs (or screen-reader-only labels)
 * - Grouped related checkboxes with a <fieldset>/<legend> (legend hidden)
 * - Added table caption + proper <th scope="col">
 * - Added labels for row checkboxes and “select all” checkbox
 * - Fixed incorrect colspan values (was 8 on an 8-col table after 2 empties; should be 6)
 *
 * Source reviewed: :contentReference[oaicite:0]{index=0}
 */

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

// lender-history.php — Lending history page
require '/var/www/seal_wp_script/seal_function.php';

// -------------------------------
// Handle filters
// -------------------------------
if (isset($_GET['loc'])) {
    $loc                 = $field_loc_location_code;
    $filter_yes          = "yes";
    $filter_no           = "yes";
    $filter_noans        = "yes";
    $filter_expire       = "";
    $filter_cancel       = "";
    $filter_recevied     = "";
    $filter_return       = "";
    $filter_checkin      = "";
    $filter_renew        = "";
    $filter_days         = "365";
    $filter_destination  = "";
    $filter_illnum       = "";
} elseif (isset($_REQUEST['loc'])) {
    $loc = $field_loc_location_code;
    $filter_illnum = ($_REQUEST['filter_illnum'] ?? "");

    if ($filter_illnum != "") {
        $filter_yes          = "yes";
        $filter_no           = "yes";
        $filter_noans        = "yes";
        $filter_expire       = "yes";
        $filter_cancel       = "yes";
        $filter_recevied     = "yes";
        $filter_return       = "yes";
        $filter_checkin      = "yes";
        $filter_renew        = "yes";
        $filter_days         = "all";
        $filter_destination  = "";
    } else {
        $filter_yes         = ($_REQUEST['filter_yes'] ?? "");
        $filter_no          = ($_REQUEST['filter_no'] ?? "");
        $filter_noans       = ($_REQUEST['filter_noans'] ?? "");
        $filter_expire      = ($_REQUEST['filter_expire'] ?? "");
        $filter_cancel      = ($_REQUEST['filter_cancel'] ?? "");
        $filter_recevied    = ($_REQUEST['filter_recevied'] ?? "");
        $filter_return      = ($_REQUEST['filter_return'] ?? "");
        $filter_checkin     = ($_REQUEST['filter_checkin'] ?? "");
        $filter_renew       = ($_REQUEST['filter_renew'] ?? "");
        $filter_days        = ($_REQUEST['filter_days'] ?? "");
        $filter_destination = ($_REQUEST['filter_destination'] ?? "");
        $filter_illnum      = ($_REQUEST['filter_illnum'] ?? "");
    }
} else {
    $loc                 = $field_loc_location_code;
    $filter_yes          = "yes";
    $filter_no           = "yes";
    $filter_noans        = "yes";
    $filter_expire       = "yes";
    $filter_cancel       = "yes";
    $filter_recevied     = "";
    $filter_return       = "";
    $filter_checkin      = "";
    $filter_renew        = "";
    $filter_days         = "365";
    $filter_destination  = "";
    $filter_illnum       = "";
}

// -------------------------------
// Library scope (primary + extra LOCs)
// -------------------------------
$primary_loc = trim($field_loc_location_code);

// extra LOCs from user meta: "NHIGS,NWATTJ"
$extra_locs_raw = get_user_meta($current_user->ID, 'seal_extra_locs', true);
$extra_locs_raw = is_string($extra_locs_raw) ? trim($extra_locs_raw) : '';

$extra_locs = [];
if ($extra_locs_raw !== '') {
    foreach (explode(',', $extra_locs_raw) as $c) {
        $c = strtoupper(trim($c));
        if ($c !== '') $extra_locs[] = $c;
    }
}

// normalize list
$all_locs = array_values(array_unique(array_filter(array_merge([strtoupper($primary_loc)], $extra_locs))));

// chosen library scope from form
$filter_loc = $_REQUEST['filter_loc'] ?? '';   // '', 'all', or LOC
$has_multi  = (count($all_locs) > 1);

if (!$has_multi) {
    $filter_loc = $all_locs[0] ?? strtoupper($primary_loc);
} else {
    if ($filter_loc === '') $filter_loc = 'all';
    if ($filter_loc !== 'all' && !in_array(strtoupper($filter_loc), $all_locs, true)) {
        $filter_loc = 'all';
    }
}

// Small output helpers
function esc_out($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>

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

<?php
// -------------------------------
// DB Query
// -------------------------------
require '/var/www/seal_wp_script/seal_db.inc';
$db = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
mysqli_set_charset($db, 'utf8mb4');

// -------------------------------
// Filter Form (same visible layout)
// -------------------------------
echo "<form action='/lender-history' method='post' aria-labelledby='lh_title'>";
echo "<input type='hidden' name='loc' value='" . esc_out($loc) . "'>";

echo "<h3 id='lh_title'>Lending Requests Received By Your Library</h3>";
echo "<h3>Limit Results</h3>";
$loc_name_map = [];

if (!empty($all_locs) && $db) {
    $esc = [];
    foreach ($all_locs as $c) {
        $esc[] = "'" . mysqli_real_escape_string($db, $c) . "'";
    }

    $sql_names = "SELECT `loc`, `Name` FROM `$sealLIB` WHERE `loc` IN (" . implode(',', $esc) . ")";
    $res_names = mysqli_query($db, $sql_names);

    if ($res_names) {
        while ($rr = mysqli_fetch_assoc($res_names)) {
            $k = strtoupper(trim((string)$rr['loc']));
            $v = trim((string)$rr['Name']);
            if ($k !== '' && $v !== '') {
                $loc_name_map[$k] = $v;
            }
        }
    }
}


// Show library selector only if user has more than one LOC
if ($has_multi) {
    echo "<p><b>Library:</b></p>";
    echo "<div style='display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-bottom:12px;'>";

    // Add id + accessible label; keep the same visible dropdown
    echo "<label for='filter_loc'><span class='screen-reader-text'>Library</span>";
    echo "<select id='filter_loc' name='filter_loc' style='min-width:240px;'>";
    echo "<option value='all' " . selected('all', $filter_loc, false) . ">All My Libraries</option>";
    foreach ($all_locs as $code) {
       $code_u = strtoupper($code);
$label  = $loc_name_map[$code_u] ?? $code_u; // fallback if name missing

echo "<option value='" . esc_attr($code_u) . "' " . selected($code_u, strtoupper($filter_loc), false) . ">" .
     esc_html($label . " (" . $code_u . ")") .
     "</option>";

    }
    echo "</select></label>";

    echo "</div>";
} else {
    echo "<input type='hidden' name='filter_loc' value='" . esc_attr($filter_loc) . "'>";
}

// Group checkboxes semantically (no visible change)
echo "<fieldset style='border:0;padding:0;margin:0;'>";
echo "<legend class='screen-reader-text'>Filter by fill status</legend>";

echo "<p><b>By Fill Status:</b></p>";
echo "<div style='display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:6px;max-width:720px;align-items:center;'>";
echo "<label><input type='checkbox' name='filter_yes' value='yes' " . checked($filter_yes, 'yes', false) . "> Yes</label>";
echo "<label><input type='checkbox' name='filter_no' value='yes' " . checked($filter_no, 'yes', false) . "> No</label>";
echo "<label><input type='checkbox' name='filter_noans' value='yes' " . checked($filter_noans, 'yes', false) . "> No Answer</label>";
echo "<label><input type='checkbox' name='filter_expire' value='yes' " . checked($filter_expire, 'yes', false) . "> Expired</label>";
echo "<label><input type='checkbox' name='filter_cancel' value='yes' " . checked($filter_cancel, 'yes', false) . "> Canceled</label>";
echo "<label><input type='checkbox' name='filter_recevied' value='yes' " . checked($filter_recevied, 'yes', false) . "> Received</label>";
echo "<label><input type='checkbox' name='filter_return' value='yes' " . checked($filter_return, 'yes', false) . "> Return</label>";
echo "<label><input type='checkbox' name='filter_checkin' value='yes' " . checked($filter_checkin, 'yes', false) . "> Check In</label>";
echo "<label><input type='checkbox' name='filter_renew' value='yes' " . checked($filter_renew ?? '', 'yes', false) . "> Renew Pending</label>";
echo "</div>";
echo "</fieldset>";

// Add ids for form fields (keep same visual layout)
echo "<div style='display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-top:12px;'>";

echo "<label for='filter_days'><b>Time Frame&nbsp;</b>
        <select id='filter_days' name='filter_days'>
          <option value='365' " . selected('365', $filter_days, false) . ">365 days</option>
          <option value='90' "  . selected('90', $filter_days, false) . ">90 days</option>
          <option value='60' "  . selected('60', $filter_days, false) . ">60 days</option>
          <option value='30' "  . selected('30', $filter_days, false) . ">30 days</option>
          <option value='all' " . selected('all', $filter_days, false) . ">all days</option>
        </select></label>";

echo "<label for='filter_illnum'><b>&nbsp;ILL #&nbsp;</b>
        <input id='filter_illnum' name='filter_illnum' type='text' value='" . esc_out($filter_illnum ?? '') . "' style='min-width:160px;'></label>";

echo "<label for='filter_destination'><b>&nbsp;Lender Destination&nbsp;</b>
        <input id='filter_destination' name='filter_destination' type='text' value='" . esc_out($filter_destination ?? '') . "' style='min-width:220px;'></label>";

echo "</div><br>";

// ADA FIX: remove <a> inside <button>. Keep same look.
$reset_url = ($_SERVER['REDIRECT_URL'] ?? '/lender-history') . "?clear=yes";
echo "<a class='btn-primary' style='display:inline-block;background:#6c757d;color:#fff;padding:6px 10px;border-radius:4px;text-decoration:none;' href='" . esc_url($reset_url) . "'>Reset Filters</a> <b>OR</b> ";
echo "<input type='submit' value='Update Results'>";
echo "</form>";


// Build lender-scope WHERE clause based on filter_loc
if ($has_multi && $filter_loc === 'all') {
    $esc_locs = [];
    foreach ($all_locs as $c) {
        $esc_locs[] = "'" . mysqli_real_escape_string($db, $c) . "'";
    }
    $where_loc = "`Destination` IN (" . implode(',', $esc_locs) . ")";
} else {
    $chosen = strtoupper($filter_loc ?: $primary_loc);
    $chosen = mysqli_real_escape_string($db, $chosen);
    $where_loc = "`Destination` = '$chosen'";
}

// Build query
$SQLBASE = "SELECT *, DATE_FORMAT(`Timestamp`, '%Y/%m/%d') AS `Timestamp_fmt`
            FROM `$sealSTAT`
            WHERE $where_loc";

$SQLEND   = " ORDER BY `Timestamp` DESC";
$SQL_DAYS = ($filter_days === "all") ? "" : " AND (DATE(`Timestamp`) BETWEEN NOW() - INTERVAL " . intval($filter_days) . " DAY AND NOW())";
$SQLILL   = (strlen($filter_illnum ?? '') > 2) ? " AND `illNUB` LIKE '%" . mysqli_real_escape_string($db, $filter_illnum) . "%'" : "";

// Destination filter (Borrower search by name)
$SQL_DESTINATION = "";
if (strlen($filter_destination ?? '') > 2) {
    $SQL_Dest_Search = "SELECT `loc` FROM `$sealLIB` WHERE `Name` LIKE '%" . mysqli_real_escape_string($db, $filter_destination) . "%'";
    $PossibleDests = mysqli_query($db, $SQL_Dest_Search);
    $destClauses = [];
    while ($rowdest = mysqli_fetch_assoc($PossibleDests)) {
        $destClauses[] = "`Requester LOC` = '" . mysqli_real_escape_string($db, $rowdest["loc"]) . "'";
    }
    if (count($destClauses) > 0) {
        $SQL_DESTINATION = " AND (" . implode(" OR ", $destClauses) . ")";
    }
}

// Status conditions
$conds = [];
if ($filter_yes === "yes")    $conds[] = "`Fill` = 1";
if ($filter_no === "yes")     $conds[] = "`Fill` = 0";
if ($filter_noans === "yes")  $conds[] = "`Fill` = 3";
if ($filter_expire === "yes") $conds[] = "`Fill` = 4";
if ($filter_cancel === "yes") $conds[] = "`Fill` = 6";
if ($filter_checkin === "yes") $conds[] = "`checkinAccount` IS NOT NULL";
if ($filter_recevied === "yes") $conds[] = "`receiveAccount` IS NOT NULL AND `returnAccount` IS NULL";
if ($filter_return === "yes") $conds[] = "`returnAccount` IS NOT NULL AND `checkinAccount` IS NULL";
if ($filter_renew === "yes")  $conds[] = "`renewAnswer` > 1";

$SQLMIDDLE = count($conds) ? implode(' OR ', $conds) : "`Fill` = ''";
$GETLISTSQL = $SQLBASE . $SQL_DESTINATION . $SQL_DAYS . $SQLILL . " AND (" . $SQLMIDDLE . ")" . $SQLEND;

$GETLIST = mysqli_query($db, $GETLISTSQL);
$GETLISTCOUNTwhole = $GETLIST ? mysqli_num_rows($GETLIST) : 0;

// -------------------------------
// Results
// -------------------------------
if (!$GETLIST) {
    die('Error: ' . mysqli_error($db));
} elseif ($GETLISTCOUNTwhole == 0) {
    echo "<p role='status' aria-live='polite'>No results found.</p>";
} else {
?>
    <hr>
    <h4 id="bulk_action_heading">Perform Bulk Action</h4>

    <!-- Accessible label (hidden) -->
    <label for="bulkaction" class="screen-reader-text">Select bulk action</label>
    <select id="bulkaction" required aria-required="true" aria-labelledby="bulk_action_heading">
        <option value="">--Select Action--</option>
        <option value="5">Request Not Filled</option>
        <option value="6">Check Item Back In</option>
    </select>

    <button id="bulkSubmit" type="button">Submit</button>
    <br><br>

    <?php echo "<p role='status' aria-live='polite'>" . intval($GETLISTCOUNTwhole) . " results</p>"; ?>

    <table class="responsive-table">
        <caption class="screen-reader-text">Lending request history results</caption>
        <thead>
          <tr>
            <th scope="col">
              <label for="check_all" class="screen-reader-text">Select all requests</label>
              <input type="checkbox" id="check_all">
            </th>
            <th scope="col">ILL #</th>
            <th scope="col">Title/Author</th>
            <th scope="col">Type/Need By</th>
            <th scope="col">Borrower/Contact</th>
            <th scope="col">Due Date/Shipping</th>
            <th scope="col">Timestamp/Status/ILLiad ID</th>
            <th scope="col">Action</th>
          </tr>
        </thead>
        <tbody>
<?php
    $rowtype = 1;
    while ($row = mysqli_fetch_assoc($GETLIST)) {
        $illNUB   = $row["illNUB"];
        $fill     = $row["Fill"];
        $rowclass = ($rowtype & 1) ? "group-odd" : "group-even";

        // Row checkbox needs a label for SR (no visible change)
        $row_cb_id = 'cb_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$illNUB);

        echo "<tr class='".esc_attr($rowclass)."'>
                <td>
                  <label for='".esc_attr($row_cb_id)."' class='screen-reader-text'>Select request ".esc_html($illNUB)."</label>
                  <input type='checkbox' id='".esc_attr($row_cb_id)."' class='check_item' value='" . esc_out($illNUB) . "'>
                </td>
                <td>" . esc_html($illNUB) . "</td>
                <td>" . esc_html($row["Title"]) . "<br><i>" . esc_html($row["Author"]) . "</i></td>
                <td>" . esc_html($row["Itype"]) . "<br>" . esc_html($row["needbydate"]) . "</td>
                <td>" . esc_html($row["Requester person"]) . "<br><a href='mailto:" . esc_attr($row["requesterEMAIL"]) . "?Subject=NOTE%20Request%20ILL%23%20" . rawurlencode($illNUB) . "' target='_blank'>" . esc_html($row["Requester lib"]) . "</a></td>
                <td>" . esc_html($row["DueDate"]) . "<br>" . esc_html(shipmtotxt($row["shipMethod"])) . "</td>
                <td>" . esc_html(date("Y-m-d", strtotime($row["Timestamp"]))) . "<br>" .
                    itemstatus(
                        $fill,
                        $row["receiveAccount"],
                        $row["returnAccount"],
                        $row["returnDate"],
                        $row["receiveDate"],
                        $row["checkinAccount"],
                        $row["checkinTimeStamp"],
                        $row["fillNofillDate"]
                    ) .
                    "<br>" . esc_html($row["IlliadTransID"]) . "</td>
                <td>";

        // ==== Actions (unchanged) ====
        $receive  = $row["receiveAccount"];
        $return   = $row["returnAccount"];
        $renewReq = $row["renewAccountRequester"];
        $checkin  = $row["checkinAccount"];
        $daysdiff = (time() - strtotime($row["Timestamp"])) / (60 * 60 * 24);

        if ($fill == 0) {
            echo "&nbsp;";
        } elseif (($fill == 3) || (strlen($receive) < 1 && $daysdiff < 30 && $fill != 6)) {
            echo "<form method='post' action='/respond'>
                        <input type='hidden' name='FromLender' value='1'>
                        <input type='hidden' name='num' value='" . esc_out($illNUB) . "'>
                        <input type='hidden' name='fill' value='1'>
                        <button type='submit'>Yes, Will Fill</button>
                      </form>
                      <hr>
                      <form method='post' action='/respond'>
                        <input type='hidden' name='FromLender' value='1'>
                        <input type='hidden' name='num' value='" . esc_out($illNUB) . "'>
                        <input type='hidden' name='fill' value='0'>
                        <button type='submit'>No, Can&#39;t Fill</button>
                      </form>";
        } elseif ((strlen($return) < 2) && ($fill == 1) && (strlen($renewReq) > 1) && (strlen($checkin) < 2)) {
            echo "<form method='post' action='/renew'>
                        <input type='hidden' name='num' value='" . esc_out($illNUB) . "'>
                        <input type='hidden' name='a' value='1'>
                        <button type='submit'>Approve Renewal</button>
                      </form>
                      <form method='post' action='/renew'>
                        <input type='hidden' name='num' value='" . esc_out($illNUB) . "'>
                        <input type='hidden' name='a' value='2'>
                        <button type='submit'>Deny Renewal</button>
                      </form>
                      <hr>
                      <form method='post' action='/status'>
                        <input type='hidden' name='num' value='" . esc_out($illNUB) . "'>
                        <input type='hidden' name='a' value='3'>
                        <button type='submit'>Check Item Back In</button>
                      </form>";
        } elseif (($daysdiff > 14) && (strlen($checkin) < 2) && ($fill != 4) && ($fill != 6)) {
            echo "<form method='post' action='/status'>
                        <input type='hidden' name='num' value='" . esc_out($illNUB) . "'>
                        <input type='hidden' name='a' value='3'>
                        <button type='submit'>Check Item Back In</button>
                      </form>";
        } elseif ((strlen($return) < 2) && (strlen($renewReq) < 1) && (strlen($receive) > 1) && (strlen($checkin) < 2)) {
            echo "<form method='post' action='/renew'>
                        <input type='hidden' name='num' value='" . esc_out($illNUB) . "'>
                        <input type='hidden' name='a' value='4'>
                        <button type='submit'>Edit Due Date</button>
                      </form>";
            if ($daysdiff > 14) {
                echo "<form method='post' action='/status'>
                            <input type='hidden' name='num' value='" . esc_out($illNUB) . "'>
                            <input type='hidden' name='a' value='3'>
                            <button type='submit'>Check Item Back In</button>
                          </form>";
            }
        } elseif ((strlen($checkin) < 2) && (strlen($receive) > 1)) {
            echo "<form method='post' action='/status'>
                        <input type='hidden' name='num' value='" . esc_out($illNUB) . "'>
                        <input type='hidden' name='a' value='3'>
                        <button type='submit'>Check Item Back In</button>
                      </form>";
        } else {
            echo "&nbsp;";
        }

        echo "</td></tr>";

        // ----------------------------------------------
        // Notes rows (fix colspan)
        // Table has 8 columns total.
        // We output 2 empty <td>, so colspan must be 6 (2 + 6 = 8).
        // ----------------------------------------------
        $reqnote          = trim($row["reqnote"] ?? '');
        $lendnote         = trim($row["responderNOTE"] ?? '');
        $renewNote        = trim($row["renewNote"] ?? '');
        $renewNoteLender  = trim($row["renewNoteLender"] ?? '');

        $displaynotes = '';
        if (strlen($reqnote) > 2) {
            $displaynotes .= "<b>Requester Note:</b> " . esc_out($reqnote) . "<br>";
        }
        if (strlen($lendnote) > 2) {
            $displaynotes .= "<b>Lender Note:</b> " . esc_out($lendnote) . "<br>";
        }
        if (!empty($displaynotes)) {
            echo "<tr class='".esc_attr($rowclass)."'>
                    <td></td><td></td>
                    <td colspan='6' style='background:#f9f9f9;padding:6px;border-left:3px solid #ccc;'>$displaynotes</td>
                  </tr>";
        }

        // Return Notes (as you had them)
        $returnnote    = trim($row["returnNote"] ?? '');
        $returnmethod  = trim($row["returnMethod"] ?? '');
        $returnDate    = trim($row["returnDate"] ?? '');
        $returnAccount = trim($row["returnAccount"] ?? '');

        if (!empty($returnDate) && strtotime($returnDate) < strtotime('1980-01-01')) {
            $returnDate = '';
        }

        if ($returnnote || $returnmethod || $returnDate || $returnAccount) {
            $displayreturnnotes = '';
            if (strlen($returnnote) > 2)   $displayreturnnotes .= "<b>Return Note:</b> " . nl2br(esc_out($returnnote)) . "<br>";
            if (strlen($returnmethod) > 2) $displayreturnnotes .= "<b>Return Method:</b> " . esc_out($returnmethod) . "<br>";
            if (strlen($returnDate) > 2)   $displayreturnnotes .= "<b>Returned On:</b> " . esc_out($returnDate) . "<br>";
            if (strlen($returnAccount) > 2) $displayreturnnotes .= "<b>Checked In By:</b> " . esc_out($returnAccount);

            echo "<tr class='".esc_attr($rowclass)."'>
                    <td></td><td></td>
                    <td colspan='6' style='background:#eef8ff;padding:6px;border-left:3px solid #6ba4d9;'>$displayreturnnotes</td>
                  </tr>";
        }

        if ((strlen($renewNote) > 2) || (strlen($renewNoteLender) > 2)) {
            $displayrenewnotes = '';
            if (strlen($renewNote) > 2) {
                $displayrenewnotes .= "<b>Renewal Note (Borrower):</b> " . esc_out($renewNote) . "<br>";
            }
            if (strlen($renewNoteLender) > 2) {
                $displayrenewnotes .= "<b>Renewal Note (Lender):</b> " . esc_out($renewNoteLender);
            }
            echo "<tr class='".esc_attr($rowclass)."'>
                    <td></td><td></td>
                    <td colspan='6' style='background:#f3fff1;padding:6px;border-left:3px solid #76c67a;'>$displayrenewnotes</td>
                  </tr>";
        }

        $rowtype++;
    }
    echo "</tbody></table>";
}
?>

<script>
(function(){
  const sr = document.getElementById("sr-status");
  function announce(msg){
    if(!sr) return;
    sr.textContent = "";
    setTimeout(()=>{ sr.textContent = msg; }, 20);
  }

  // "check all" toggle
  const checkAll = document.getElementById("check_all");
  if (checkAll) {
    checkAll.addEventListener("click", function(e) {
      document.querySelectorAll(".check_item").forEach(cb => cb.checked = e.target.checked);
      announce(e.target.checked ? "All requests selected." : "All requests unselected.");
    });
  }

  // Handle bulk submit via JS only (existing behavior, but with SR announcements)
  const bulkBtn = document.getElementById("bulkSubmit");
  if (bulkBtn) {
    bulkBtn.addEventListener("click", function() {
      const actionEl = document.getElementById("bulkaction");
      const action = actionEl ? actionEl.value : "";
      const selected = Array.from(document.querySelectorAll(".check_item:checked"))
                            .map(cb => cb.value);

      if (!action) {
        announce("Please select an action.");
        alert("Please select an action.");
        return;
      }
      if (selected.length === 0) {
        announce("Please select at least one request.");
        alert("Please select at least one request.");
        return;
      }
      if (!confirm("Confirm, you want to continue with bulk update.")) {
        announce("Bulk update canceled.");
        return;
      }

      const params = new URLSearchParams();
      params.append("bulkaction", action);
      selected.forEach(val => params.append("check_list[]", val));

      announce("Submitting bulk update.");
      fetch("/bulkaction", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: params
      })
      .then(r => r.text())
      .then(() => {
        announce("Bulk update complete. Reloading page.");
        alert("Bulk update complete.");
        location.reload();
      })
      .catch(err => {
        announce("Error during bulk update.");
        alert("Error: " + err);
      });
    });
  }
})();
</script>