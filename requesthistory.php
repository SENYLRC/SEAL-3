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
// requesthistory.php###

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
// Filter Form
// -------------------------------
echo "<form action='".$_SERVER['REDIRECT_URL']."' method='post'>";
echo "<input type='hidden' name='loc' value='$loc'>";
echo "<h3>Borrowing Requests Submitted By Your Library</h3>";
echo "<h3>Limit Results</h3>";

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

echo "<div style='display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-top:12px;'>";
echo "<label><b>Time Frame&nbsp;</b>
        <select name='filter_days'>
          <option value='365' " . selected('365', $filter_days, false) . ">365 days</option>
          <option value='90' "  . selected('90', $filter_days, false) . ">90 days</option>
          <option value='60' "  . selected('60', $filter_days, false) . ">60 days</option>
          <option value='30' "  . selected('30', $filter_days, false) . ">30 days</option>
          <option value='all' " . selected('all', $filter_days, false) . ">all days</option>
        </select></label>";
echo "<label><b>&nbsp;ILL #&nbsp;</b>
        <input name='filter_illnum' type='text' value='".htmlspecialchars($filter_illnum, ENT_QUOTES)."' style='min-width:160px;'></label>";
echo "<label><b>&nbsp;Lender Destination&nbsp;</b>
        <input name='filter_destination' type='text' value='".htmlspecialchars($filter_destination, ENT_QUOTES)."' style='min-width:220px;'></label>";
echo "</div><br>";

echo "<button><a style='color:#fff;' href='".$_SERVER['REDIRECT_URL']."?clear=yes'>Reset Filters</a></button> <b>OR</b> ";
echo "<input type='submit' value='Update Results'>";
echo "</form>";

// -------------------------------
// DB Query
// -------------------------------
require '/var/www/seal_wp_script/seal_db.inc';
$db = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

$loc = mysqli_real_escape_string($db, $loc);
$SQLBASE = "SELECT *, DATE_FORMAT(`Timestamp`, '%Y/%m/%d') AS ts_fmt FROM `$sealSTAT` WHERE `Requester LOC` = '$loc'";
$SQLEND  = " ORDER BY `Timestamp` DESC";
$SQL_DAYS = ($filter_days == "all") ? "" : " AND (DATE(`Timestamp`) BETWEEN NOW() - INTERVAL ".intval($filter_days)." DAY AND NOW())";
$SQLILL  = (strlen($filter_illnum) > 2) ? " AND `illNUB` LIKE '%".mysqli_real_escape_string($db, $filter_illnum)."%' " : "";
$conds = [];
if ($filter_yes == "yes") {
    $conds[] = "`fill`=1";
}
if ($filter_no == "yes") {
    $conds[] = "`fill`=0";
}
if ($filter_noans == "yes") {
    $conds[] = "`fill`=3";
}
if ($filter_expire == "yes") {
    $conds[] = "`fill`=4";
}
if ($filter_cancel == "yes") {
    $conds[] = "`fill`=6";
}
if ($filter_checkin == "yes") {
    $conds[] = "`checkinAccount` IS NOT NULL";
}
if ($filter_recevied == "yes") {
    $conds[] = "`receiveAccount` IS NOT NULL AND `returnAccount` IS NULL";
}
if ($filter_return == "yes") {
    $conds[] = "`returnAccount` IS NOT NULL AND `checkinAccount` IS NULL";
}
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
    echo "No results found.";
} else {
    ?>
    <hr>
    <h4>Perform Bulk Action</h4>
    <select id="bulkaction" required>
        <option value="">--Select Action--</option>
        <option value="1">Cancel Requests</option>
        <option value="3">Receive Items</option>
        <option value="2">Renew Requests</option>
        <option value="4">Return Items</option>
    </select>
    <button id="bulkSubmit">Submit</button>
    <br><br>

    <?php echo "$GETLISTCOUNTwhole results<br>"; ?>

    <table class="responsive-table">
      <thead>
        <tr>
          <th><input type="checkbox" id="check_all"></th>
          <th>ILL #</th>
          <th>Title/Author</th>
          <th>Type/Need By</th>
          <th>Lender/Contact</th>
          <th>Due Date/Shipping</th>
          <th>Timestamp/Status/ILLiad ID</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
    <?php
    $rowtype = 1;
    while ($row = mysqli_fetch_assoc($GETLIST)) {
        $illNUB = $row["illNUB"];
        $rowclass = ($rowtype & 1) ? "group-odd" : "group-even";
        echo "<tr class='$rowclass'>
              <td><input type='checkbox' class='check_item' value='".htmlspecialchars($illNUB, ENT_QUOTES)."'></td>
              <td>$illNUB</td>
              <td>{$row["Title"]}<br><i>{$row["Author"]}</i></td>
              <td>{$row["Itype"]}<br>{$row["needbydate"]}</td>";


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

        // Output Lender / Contact
        echo "<td>" . htmlspecialchars($dest);
        if (!empty($destemail)) {
            echo "<br><a href='mailto:" . htmlspecialchars($destemail) . "'>" . htmlspecialchars($destemail) . "</a>";
        }
        echo "</td>";


        echo "<td>{$row["DueDate"]}<br>".shipmtotxt($row["shipMethod"])."</td>
              <td>".date("Y-m-d", strtotime($row["Timestamp"]))."<br>".itemstatus($row["Fill"], $row["receiveAccount"], $row["returnAccount"], $row["returnDate"], $row["receiveDate"], $row["checkinAccount"], $row["checkinTimeStamp"], $row["fillNofillDate"])."<br>{$row["IlliadTransID"]}</td>
              <td>";

        // row-level actions preserved
        if ($row["Fill"] == 3) {
            echo "<form method='post' action='/status'>
                    <input type='hidden' name='a' value='6'>
                    <input type='hidden' name='num' value='".htmlspecialchars($illNUB, ENT_QUOTES)."'>
                    <button type='submit'>Cancel Request</button>
                  </form>";
        } elseif ($row["Fill"] == 1 && strlen($row["receiveAccount"]) < 2) {
            echo "<form method='post' action='/status'>
                    <input type='hidden' name='a' value='1'>
                    <input type='hidden' name='num' value='".htmlspecialchars($illNUB, ENT_QUOTES)."'>
                    <button type='submit'>Receive Item</button>
                  </form>";
        } elseif ($row["Fill"] == 1 && strlen($row["receiveAccount"]) > 1 && strlen($row["returnAccount"]) < 1) {
            echo "<form method='post' action='/renew'>
                    <input type='hidden' name='a' value='3'>
                    <input type='hidden' name='num' value='".htmlspecialchars($illNUB, ENT_QUOTES)."'>
                    <button type='submit'>Request Renewal</button>
                  </form><hr>
                  <form method='post' action='/status'>
                    <input type='hidden' name='a' value='2'>
                    <input type='hidden' name='num' value='".htmlspecialchars($illNUB, ENT_QUOTES)."'>
                    <button type='submit'>Return Item</button>
                  </form>";
        }
        echo "</td></tr>";
        // ----------------------------------------------
        // Display notes as separate rows beneath the item
        // ----------------------------------------------
        $reqnote     = trim($row["reqnote"] ?? '');
        $patronnote  = trim($row["patronnote"] ?? '');
        $lendnote    = trim($row["responderNOTE"] ?? '');
        $returnnote  = trim($row["returnNote"] ?? '');
        $returnmethod = trim($row["returnmethod"] ?? '');
        $renewNote   = trim($row["renewNote"] ?? '');
        $renewNoteLender = trim($row["renewNoteLender"] ?? '');

        // combine and format
        $displaynotes = '';
        if (strlen($reqnote) > 2) {
            $displaynotes .= "<b>Requester Note:</b> " . htmlspecialchars($reqnote) . "<br>";
        }
        if (strlen($patronnote) > 2) {
            $displaynotes .= "<b>Patron Note:</b> " . htmlspecialchars($patronnote) . "<br>";
        }
        if (strlen($lendnote) > 2) {
            $displaynotes .= "<b>Lender Note:</b> " . htmlspecialchars($lendnote) . "<br>";
        }

        if (!empty($displaynotes)) {
            echo "<tr class='$rowclass'><td></td><td></td><td colspan='9' style='background:#f9f9f9;padding:6px;border-left:3px solid #ccc;'>$displaynotes</td></tr>";
        }

        // Return Notes
        if ((strlen($returnnote) > 2) || (strlen($returnmethod) > 2)) {
            $displayreturnnotes = '';
            if (strlen($returnnote) > 2) {
                $displayreturnnotes .= "<b>Return Note:</b> " . htmlspecialchars($returnnote) . "<br>";
            }
            if (strlen($returnmethod) > 2) {
                $displayreturnnotes .= "<b>Return Method:</b> " . htmlspecialchars($returnmethod);
            }
            echo "<tr class='$rowclass'><td></td><td></td><td colspan='9' style='background:#eef8ff;padding:6px;border-left:3px solid #6ba4d9;'>$displayreturnnotes</td></tr>";
        }

        // Renewal Notes
        if ((strlen($renewNote) > 2) || (strlen($renewNoteLender) > 2)) {
            $displayrenewnotes = '';
            if (strlen($renewNote) > 2) {
                $displayrenewnotes .= "<b>Renewal Note (Borrower):</b> " . htmlspecialchars($renewNote) . "<br>";
            }
            if (strlen($renewNoteLender) > 2) {
                $displayrenewnotes .= "<b>Renewal Note (Lender):</b> " . htmlspecialchars($renewNoteLender);
            }
            echo "<tr class='$rowclass'><td></td><td></td><td colspan='9' style='background:#f3fff1;padding:6px;border-left:3px solid #76c67a;'>$displayrenewnotes</td></tr>";
        }

        $rowtype++;
    }
    echo "</tbody></table>";
}
?>

<script>
// check all toggle
document.getElementById("check_all").addEventListener("click",function(e){
  document.querySelectorAll(".check_item").forEach(cb=>cb.checked=e.target.checked);
});
// bulk submit
document.getElementById("bulkSubmit").addEventListener("click",function(){
  const action=document.getElementById("bulkaction").value;
  const selected=Array.from(document.querySelectorAll(".check_item:checked")).map(cb=>cb.value);
  if(!action){alert("Please select an action.");return;}
  if(selected.length===0){alert("Please select at least one request.");return;}
  if(!confirm("Confirm bulk update?"))return;
  const params=new URLSearchParams();
  params.append("bulkaction",action);
  selected.forEach(v=>params.append("check_list[]",v));
  fetch("/bulkaction",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:params})
    .then(r=>r.text())
    .then(()=>{alert("Bulk update complete.");location.reload();})
    .catch(err=>alert("Error: "+err));
});
</script>