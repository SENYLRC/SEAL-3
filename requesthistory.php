<?php
// requesthistory.php###
// Goal: keep functions + visible display the same, but improve ADA/WCAG accessibility.
// Source reviewed: :contentReference[oaicite:0]{index=0}

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

require '/var/www/seal_wp_script/seal_function.php';

// -------------------------------
// Handle filters
// -------------------------------
if (isset($_GET['loc'])) {
    $loc = $field_loc_location_code;
    $filter_yes = "yes";
    $filter_no = "yes";
    $filter_noans = "yes";
    $filter_expire = "";
    $filter_cancel = "";
    $filter_recevied = "";
    $filter_return = "";
    $filter_checkin = "";
    $filter_days = "365";
    $filter_destination = "";
    $filter_illnum = "";
} elseif (isset($_REQUEST['loc'])) {
    $loc = $field_loc_location_code;
    $filter_illnum = $_REQUEST['filter_illnum'] ?? "";
    if ($filter_illnum != "") {
        $filter_yes = $filter_no = $filter_noans = $filter_expire = $filter_cancel =
        $filter_recevied = $filter_return = $filter_checkin = "yes";
        $filter_days = "all";
        $filter_destination = "";
    } else {
        $filter_yes = $_REQUEST['filter_yes'] ?? "";
        $filter_no = $_REQUEST['filter_no'] ?? "";
        $filter_noans = $_REQUEST['filter_noans'] ?? "";
        $filter_expire = $_REQUEST['filter_expire'] ?? "";
        $filter_cancel = $_REQUEST['filter_cancel'] ?? "";
        $filter_recevied = $_REQUEST['filter_recevied'] ?? "";
        $filter_return = $_REQUEST['filter_return'] ?? "";
        $filter_checkin = $_REQUEST['filter_checkin'] ?? "";
        $filter_days = $_REQUEST['filter_days'] ?? "";
        $filter_destination = $_REQUEST['filter_destination'] ?? "";
        $filter_illnum = $_REQUEST['filter_illnum'] ?? "";
    }
} else {
    $loc = $field_loc_location_code;
    $filter_yes = "yes";
    $filter_no = "yes";
    $filter_noans = "yes";
    $filter_expire = "";
    $filter_cancel = "";
    $filter_recevied = "";
    $filter_return = "";
    $filter_checkin = "";
    $filter_days = "365";
    $filter_destination = "";
    $filter_illnum = "";
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
$has_multi = (count($all_locs) > 1);

if (!$has_multi) {
    // only one LOC available
    $filter_loc = $all_locs[0] ?? strtoupper($primary_loc);
} else {
    if ($filter_loc === '') $filter_loc = 'all';
    // validate
    if ($filter_loc !== 'all' && !in_array(strtoupper($filter_loc), $all_locs, true)) {
        $filter_loc = 'all';
    }
}

/**
 * ADA helpers
 * - Screen-reader-only text class (no visible change)
 * - Use aria-live region for JS alerts (still uses alert() but also announces to SR)
 */
?>
<style>
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
// Filter Form (keep visible layout the same)
// -------------------------------
$form_action = $_SERVER['REDIRECT_URL'] ?? '';
echo "<form action='".esc_attr($form_action)."' method='post' aria-labelledby='rh_title'>";

// Hidden loc is fine; add id so label associations elsewhere don't conflict
echo "<input type='hidden' id='rh_loc' name='loc' value='".esc_attr($loc)."'>";

echo "<h3 id='rh_title'>Borrowing Requests Submitted By Your Library</h3>";
echo "<h3>Limit Results</h3>";

// Show library selector only if user has more than one LOC
if ($has_multi) {
    echo "<p><b>Library:</b></p>";
    echo "<div style='display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-bottom:12px;'>";
    // Give the select an id + label text that screen readers can use; keep same visible display.
    echo "<label for='filter_loc'><span class='screen-reader-text'>Library</span>";
    echo "<select id='filter_loc' name='filter_loc' style='min-width:240px;'>";
    echo "<option value='all' " . selected('all', $filter_loc, false) . ">All My Libraries</option>";
    foreach ($all_locs as $code) {
        echo "<option value='" . esc_attr($code) . "' " . selected($code, strtoupper($filter_loc), false) . ">" . esc_html($code) . "</option>";
    }
    echo "</select></label>";
    echo "</div>";
} else {
    // keep scope stable even without dropdown
    echo "<input type='hidden' name='filter_loc' value='" . esc_attr($filter_loc) . "'>";
}

// Group checkboxes with a fieldset/legend for ADA (no visual change)
echo "<fieldset style='border:0;padding:0;margin:0;'>";
echo "<legend class='screen-reader-text'>By Fill Status</legend>";

echo "<p><b>By Fill Status:</b></p>";
echo "<div style='display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:6px;max-width:720px;align-items:center;'>";

echo "<label><input type='checkbox' name='filter_yes' value='yes' " . checked($filter_yes, 'yes', false) . "> Yes</label>";
echo "<label><input type='checkbox' name='filter_no' value='yes' " . checked($filter_no, 'yes', false) . "> No</label>";
echo "<label><input type='checkbox' name='filter_noans' value='yes' " . checked($filter_noans, 'yes', false) . "> No Answer</label>";
echo "<label><input type='checkbox' name='filter_expire' value='yes' " . checked($filter_expire, 'yes', false) . "> Expired</label>";
echo "<label><input type='checkbox' name='filter_cancel' value='yes' " . checked($filter_cancel, 'yes', false) . "> Canceled</label>";
echo "<label><input type='checkbox' name='filter_recevied' value='yes' " . checked($filter_recevied, 'yes', false) . "> Receive</label>";
echo "<label><input type='checkbox' name='filter_return' value='yes' " . checked($filter_return, 'yes', false) . "> Return</label>";
echo "<label><input type='checkbox' name='filter_checkin' value='yes' " . checked($filter_checkin, 'yes', false) . "> Check In</label>";
echo "<label><input type='checkbox' name='filter_renew' value='yes' " . checked($filter_renew ?? '', 'yes', false) . "> Renew Pending</label>";

echo "</div>";
echo "</fieldset>";

echo "<div style='display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-top:12px;'>";

// Add ids and 'for' bindings; keep visible display the same
echo "<label for='filter_days'><b>Time Frame&nbsp;</b>
        <select id='filter_days' name='filter_days'>
          <option value='365' " . selected('365', $filter_days, false) . ">365 days</option>
          <option value='90' "  . selected('90', $filter_days, false) . ">90 days</option>
          <option value='60' "  . selected('60', $filter_days, false) . ">60 days</option>
          <option value='30' "  . selected('30', $filter_days, false) . ">30 days</option>
          <option value='all' " . selected('all', $filter_days, false) . ">all days</option>
        </select></label>";

echo "<label for='filter_illnum'><b>&nbsp;ILL #&nbsp;</b>
        <input id='filter_illnum' name='filter_illnum' type='text' value='".esc_attr($filter_illnum)."' style='min-width:160px;'></label>";

echo "<label for='filter_destination'><b>&nbsp;Lender Destination&nbsp;</b>
        <input id='filter_destination' name='filter_destination' type='text' value='".esc_attr($filter_destination)."' style='min-width:220px;'></label>";

echo "</div><br>";

// IMPORTANT ADA FIX: Don't put <a> inside <button>. Keep same appearance via inline styles.
// Reset link remains a link, submit remains a real button/input.
echo "<a class='btn-primary' style='display:inline-block;background:#6c757d;color:#fff;padding:6px 10px;border-radius:4px;text-decoration:none;' href='".esc_url($form_action)."?clear=yes'>Reset Filters</a> <b>OR</b> ";
echo "<input type='submit' value='Update Results'>";
echo "</form>";

// -------------------------------
// DB Query
// -------------------------------
require '/var/www/seal_wp_script/seal_db.inc';
$db = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

// Build WHERE clause for Requester LOC based on filter_loc
if ($has_multi && $filter_loc === 'all') {
    $esc_locs = [];
    foreach ($all_locs as $c) {
        $esc_locs[] = "'" . mysqli_real_escape_string($db, $c) . "'";
    }
    $where_loc = "`Requester LOC` IN (" . implode(',', $esc_locs) . ")";
} else {
    $chosen = strtoupper($filter_loc ?: $primary_loc);
    $chosen = mysqli_real_escape_string($db, $chosen);
    $where_loc = "`Requester LOC` = '$chosen'";
}

$SQLBASE = "SELECT *, DATE_FORMAT(`Timestamp`, '%Y/%m/%d') AS ts_fmt FROM `$sealSTAT` WHERE $where_loc";
$SQLEND  = " ORDER BY `Timestamp` DESC";
$SQL_DAYS = ($filter_days == "all") ? "" : " AND (DATE(`Timestamp`) BETWEEN NOW() - INTERVAL ".intval($filter_days)." DAY AND NOW())";
$SQLILL  = (strlen($filter_illnum) > 2) ? " AND `illNUB` LIKE '%".mysqli_real_escape_string($db, $filter_illnum)."%' " : "";

$conds = [];
if ($filter_yes == "yes")     $conds[] = "`fill`=1";
if ($filter_no == "yes")      $conds[] = "`fill`=0";
if ($filter_noans == "yes")   $conds[] = "`fill`=3";
if ($filter_expire == "yes")  $conds[] = "`fill`=4";
if ($filter_cancel == "yes")  $conds[] = "`fill`=6";
if ($filter_checkin == "yes") $conds[] = "`checkinAccount` IS NOT NULL";
if ($filter_recevied == "yes")$conds[] = "`receiveAccount` IS NOT NULL AND `returnAccount` IS NULL";
if ($filter_return == "yes")  $conds[] = "`returnAccount` IS NOT NULL AND `checkinAccount` IS NULL";

$SQLMIDDLE = count($conds) ? implode(" OR ", $conds) : "`fill`=''";
$GETLISTSQL = $SQLBASE.$SQL_DAYS.$SQLILL." AND (".$SQLMIDDLE.") ".$SQLEND;

$GETLIST = mysqli_query($db, $GETLISTSQL);
$GETLISTCOUNTwhole = $GETLIST ? mysqli_num_rows($GETLIST) : 0;

// -------------------------------
// Results
// -------------------------------
if (!$GETLIST) {
    die('Error: '.mysqli_error($db));
} elseif ($GETLISTCOUNTwhole == 0) {
    echo "<p role='status' aria-live='polite'>No results found.</p>";
} else {
    ?>
    <hr>
    <h4 id="bulk_action_heading">Perform Bulk Action</h4>

    <!-- ADA: label is explicit; keep display the same -->
    <label for="bulkaction" class="screen-reader-text">Bulk action</label>
    <select id="bulkaction" required aria-required="true" aria-labelledby="bulk_action_heading">
        <option value="">--Select Action--</option>
        <option value="1">Cancel Requests</option>
        <option value="3">Receive Items</option>
        <option value="2">Renew Requests</option>
        <option value="4">Return Items</option>
    </select>

    <button id="bulkSubmit" type="button">Submit</button>
    <br><br>

    <?php echo "<p role='status' aria-live='polite'>" . intval($GETLISTCOUNTwhole) . " results</p>"; ?>

    <!-- ADA: table caption + headers + scope + checkbox labels -->
    <table class="responsive-table">
      <caption class="screen-reader-text">Borrowing request history results</caption>
      <thead>
        <tr>
          <th scope="col">
            <label for="check_all" class="screen-reader-text">Select all requests</label>
            <input type="checkbox" id="check_all">
          </th>
          <th scope="col">ILL #</th>
          <th scope="col">Title/Author</th>
          <th scope="col">Type/Need By</th>
          <th scope="col">Lender/Contact</th>
          <th scope="col">Due Date/Shipping</th>
          <th scope="col">Timestamp/Status/ILLiad ID</th>
          <th scope="col">Action</th>
        </tr>
      </thead>
      <tbody>
    <?php
    $rowtype = 1;
    while ($row = mysqli_fetch_assoc($GETLIST)) {
        $illNUB = $row["illNUB"];
        $rowclass = ($rowtype & 1) ? "group-odd" : "group-even";

        // Destination display logic (unchanged)
        $dest = $row['Destination'];
        $destemail = '';

        if (strlen($dest) > 0) {
            $GETLISTSQLDEST = "SELECT `Name`, `ill_email` FROM `$sealLIB` WHERE loc = '" . mysqli_real_escape_string($db, $dest) . "' LIMIT 1";
            $resultdest = mysqli_query($db, $GETLISTSQLDEST);

            if ($resultdest && mysqli_num_rows($resultdest) > 0) {
                $rowdest = mysqli_fetch_assoc($resultdest);
                $dest = $rowdest['Name'];
                $destemail = $rowdest['ill_email'];
            }
        } else {
            $dest = "Error — No Library Selected";
        }

        // ADA: per-row checkbox needs a label; keep display the same (screen-reader-only label)
        $row_cb_id = 'cb_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$illNUB);

        echo "<tr class='".esc_attr($rowclass)."'>
              <td>
                <label for='".esc_attr($row_cb_id)."' class='screen-reader-text'>Select request ".esc_html($illNUB)."</label>
                <input type='checkbox' id='".esc_attr($row_cb_id)."' class='check_item' value='".esc_attr($illNUB)."'>
              </td>
              <td>".esc_html($illNUB)."</td>
              <td>".esc_html($row["Title"])."<br><i>".esc_html($row["Author"])."</i></td>
              <td>".esc_html($row["Itype"])."<br>".esc_html($row["needbydate"])."</td>";

        // Output Lender / Contact
        echo "<td>" . esc_html($dest);
        if (!empty($destemail)) {
            echo "<br><a href='mailto:" . esc_attr($destemail) . "'>" . esc_html($destemail) . "</a>";
        }
        echo "</td>";

        echo "<td>".esc_html($row["DueDate"])."<br>".esc_html(shipmtotxt($row["shipMethod"]))."</td>
              <td>".esc_html(date("Y-m-d", strtotime($row["Timestamp"])))."<br>"
                . itemstatus($row["Fill"], $row["receiveAccount"], $row["returnAccount"], $row["returnDate"], $row["receiveDate"], $row["checkinAccount"], $row["checkinTimeStamp"], $row["fillNofillDate"])
                ."<br>".esc_html($row["IlliadTransID"])."</td>
              <td>";

        // Row-level actions preserved; ensure buttons have type submit and clear text
        if ($row["Fill"] == 3) {
            echo "<form method='post' action='/status'>
                    <input type='hidden' name='a' value='6'>
                    <input type='hidden' name='num' value='".esc_attr($illNUB)."'>
                    <button type='submit'>Cancel Request</button>
                  </form>";
        } elseif ($row["Fill"] == 1 && strlen($row["receiveAccount"]) < 2) {
            echo "<form method='post' action='/status'>
                    <input type='hidden' name='a' value='1'>
                    <input type='hidden' name='num' value='".esc_attr($illNUB)."'>
                    <button type='submit'>Receive Item</button>
                  </form>";
        } elseif ($row["Fill"] == 1 && strlen($row["receiveAccount"]) > 1 && strlen($row["returnAccount"]) < 1) {
            echo "<form method='post' action='/renew'>
                    <input type='hidden' name='a' value='3'>
                    <input type='hidden' name='num' value='".esc_attr($illNUB)."'>
                    <button type='submit'>Request Renewal</button>
                  </form><hr>
                  <form method='post' action='/status'>
                    <input type='hidden' name='a' value='2'>
                    <input type='hidden' name='num' value='".esc_attr($illNUB)."'>
                    <button type='submit'>Return Item</button>
                  </form>";
        }
        echo "</td></tr>";

        // ----------------------------------------------
        // Display notes as separate rows beneath the item
        // ----------------------------------------------
        $reqnote      = trim($row["reqnote"] ?? '');
        $patronnote   = trim($row["patronnote"] ?? '');
        $lendnote     = trim($row["responderNOTE"] ?? '');
        $returnnote   = trim($row["returnNote"] ?? '');
        $returnmethod = trim($row["returnmethod"] ?? '');
        $renewNote    = trim($row["renewNote"] ?? '');
        $renewNoteLender = trim($row["renewNoteLender"] ?? '');
        $nofillreason = trim((string)($row["reasonNotFilled"] ?? ''));

        $nofill_map = [
  '20' => 'In Use',
  '21' => 'Lost',
  '22' => 'Non-Circulating',
  '23' => 'Not on shelf',
  '24' => 'Poor condition',
  '25' => 'Too New',
];

$reasontxt = '';
if ($nofillreason !== '') {
  $key = preg_replace('/\D+/', '', $nofillreason); // keep digits only
  if (isset($nofill_map[$key])) {
    $reasontxt = $nofill_map[$key];
  } elseif ($key !== '') {
    $reasontxt = 'Other (' . $key . ')';
  }
}


        // combine and format
        $displaynotes = '';
        if (strlen($reqnote) > 2)    $displaynotes .= "<b>Requester Note:</b> " . esc_html($reqnote) . "<br>";
        if (strlen($patronnote) > 2) $displaynotes .= "<b>Patron Note:</b> " . esc_html($patronnote) . "<br>";
        if (strlen($lendnote) > 2)   $displaynotes .= "<b>Lender Note:</b> " . esc_html($lendnote) . "<br>";
        if ($reasontxt !== '' && (int)$row['Fill'] !== 1) {
  $displaynotes .= "<b>Reason Not Filled:</b> " . esc_html($reasontxt) . "<br>";
}


        // IMPORTANT ADA FIX: your colspans were "9" even though table has 8 columns.
        // Keep the visible layout the same but use correct colspan="6" after the first two empty cells.
        // (2 empty tds + 6-column span = 8 total)
        if (!empty($displaynotes)) {
            echo "<tr class='".esc_attr($rowclass)."'>
                    <td></td><td></td>
                    <td colspan='6' style='background:#f9f9f9;padding:6px;border-left:3px solid #ccc;'>$displaynotes</td>
                  </tr>";
        }

        // Return Notes
        if ((strlen($returnnote) > 2) || (strlen($returnmethod) > 2)) {
            $displayreturnnotes = '';
            if (strlen($returnnote) > 2)   $displayreturnnotes .= "<b>Return Note:</b> " . esc_html($returnnote) . "<br>";
            if (strlen($returnmethod) > 2) $displayreturnnotes .= "<b>Return Method:</b> " . esc_html($returnmethod);
            echo "<tr class='".esc_attr($rowclass)."'>
                    <td></td><td></td>
                    <td colspan='6' style='background:#eef8ff;padding:6px;border-left:3px solid #6ba4d9;'>$displayreturnnotes</td>
                  </tr>";
        }

        // Renewal Notes
        if ((strlen($renewNote) > 2) || (strlen($renewNoteLender) > 2)) {
            $displayrenewnotes = '';
            if (strlen($renewNote) > 2)       $displayrenewnotes .= "<b>Renewal Note (Borrower):</b> " . esc_html($renewNote) . "<br>";
            if (strlen($renewNoteLender) > 2) $displayrenewnotes .= "<b>Renewal Note (Lender):</b> " . esc_html($renewNoteLender);
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
    sr.textContent = ""; // reset
    setTimeout(()=>{ sr.textContent = msg; }, 20);
  }

  // check all toggle
  const checkAll = document.getElementById("check_all");
  if (checkAll) {
    checkAll.addEventListener("click",function(e){
      document.querySelectorAll(".check_item").forEach(cb=>cb.checked=e.target.checked);
      announce(e.target.checked ? "All requests selected." : "All requests unselected.");
    });
  }

  // bulk submit
  const bulkBtn = document.getElementById("bulkSubmit");
  if (bulkBtn) {
    bulkBtn.addEventListener("click",function(){
      const action = document.getElementById("bulkaction") ? document.getElementById("bulkaction").value : "";
      const selected = Array.from(document.querySelectorAll(".check_item:checked")).map(cb=>cb.value);

      if(!action){
        announce("Please select an action.");
        alert("Please select an action.");
        return;
      }
      if(selected.length===0){
        announce("Please select at least one request.");
        alert("Please select at least one request.");
        return;
      }
      if(!confirm("Confirm bulk update?")){
        announce("Bulk update canceled.");
        return;
      }

      announce("Submitting bulk update.");
      const params = new URLSearchParams();
      params.append("bulkaction", action);
      selected.forEach(v=>params.append("check_list[]", v));

      fetch("/bulkaction",{
        method:"POST",
        headers:{"Content-Type":"application/x-www-form-urlencoded"},
        body:params
      })
        .then(r=>r.text())
        .then(()=>{
          announce("Bulk update complete. Reloading page.");
          alert("Bulk update complete.");
          location.reload();
        })
        .catch(err=>{
          announce("Bulk update error.");
          alert("Error: " + err);
        });
    });
  }
})();
</script>