<?php
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
// Filter Form
// -------------------------------
echo "<form action='/lender-history' method='post'>";
echo "<input type='hidden' name='loc' value='" . htmlspecialchars($loc, ENT_QUOTES, 'UTF-8') . "'>";
echo "<h3>Lending Requests Received By Your Library</h3>";
echo "<h3>Limit Results</h3>";

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
        <input name='filter_illnum' type='text' value='" . htmlspecialchars($filter_illnum ?? '', ENT_QUOTES, 'UTF-8') . "' style='min-width:160px;'></label>";
echo "<label><b>&nbsp;Lender Destination&nbsp;</b>
        <input name='filter_destination' type='text' value='" . htmlspecialchars($filter_destination ?? '', ENT_QUOTES, 'UTF-8') . "' style='min-width:220px;'></label>";
echo "</div><br>";

echo "<button><a style='color:#fff;' href='" . $_SERVER['REDIRECT_URL'] . "?clear=yes'>Reset Filters</a></button> <b>OR</b> ";
echo "<input type='submit' value='Update Results'>";
echo "</form>";

// -------------------------------
// DB Query
// -------------------------------
require '/var/www/seal_wp_script/seal_db.inc';
$db = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

// Build query
$SQLBASE = "SELECT *, DATE_FORMAT(`Timestamp`, '%Y/%m/%d') AS `Timestamp_fmt`
            FROM `$sealSTAT`
            WHERE `Destination` = '" . mysqli_real_escape_string($db, $loc) . "'";

$SQLEND  = " ORDER BY `Timestamp` DESC";
$SQL_DAYS = ($filter_days === "all") ? "" : " AND (DATE(`Timestamp`) BETWEEN NOW() - INTERVAL " . intval($filter_days) . " DAY AND NOW())";
$SQLILL  = (strlen($filter_illnum ?? '') > 2) ? " AND `illNUB` LIKE '%" . mysqli_real_escape_string($db, $filter_illnum) . "%'" : "";

// Destination filter
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
if ($filter_yes === "yes") $conds[] = "`Fill` = 1";
if ($filter_no === "yes") $conds[] = "`Fill` = 0";
if ($filter_noans === "yes") $conds[] = "`Fill` = 3";
if ($filter_expire === "yes") $conds[] = "`Fill` = 4";
if ($filter_cancel === "yes") $conds[] = "`Fill` = 6";
if ($filter_checkin === "yes") $conds[] = "`checkinAccount` IS NOT NULL";
if ($filter_recevied === "yes") $conds[] = "`receiveAccount` IS NOT NULL AND `returnAccount` IS NULL";
if ($filter_return === "yes") $conds[] = "`returnAccount` IS NOT NULL AND `checkinAccount` IS NULL";
if ($filter_renew === "yes") $conds[] = "`renewAnswer` > 1";

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
    echo "No results found.";
} else {
    ?>
    <hr>
    <h4>Perform Bulk Action</h4>
    <select id="bulkaction" required>
        <option value="">--Select Action--</option>
        <option value="5">Request Not Filled</option>
        <option value="6">Check Item Back In</option>
    </select>
    <button id="bulkSubmit">Submit</button>
    <br><br>

    <?php echo $GETLISTCOUNTwhole . " results<br>"; ?>
    <table class='responsive-table'>
        <thead>
          <tr>
            <th><input type='checkbox' id='check_all'></th>
            <th>ILL #</th>
            <th>Title/Author</th>
            <th>Type/Need By</th>
            <th>Borrower/Contact</th>
            <th>Due Date/Shipping</th>
            <th>Timestamp/Status/ILLiad ID</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
    <?php
    $rowtype = 1;
    while ($row = mysqli_fetch_assoc($GETLIST)) {
        $illNUB   = $row["illNUB"];
        $fill     = $row["Fill"];
        $rowclass = ($rowtype & 1) ? "group-odd" : "group-even";

        echo "<tr class='$rowclass'>
                <td><input type='checkbox' class='check_item' value='" . htmlspecialchars($illNUB, ENT_QUOTES) . "'></td>
                <td>$illNUB</td>
                <td>{$row["Title"]}<br><i>{$row["Author"]}</i></td>
                <td>{$row["Itype"]}<br>{$row["needbydate"]}</td>
                <td>{$row["Requester person"]}<br><a href='mailto:{$row["requesterEMAIL"]}?Subject=NOTE Request ILL# $illNUB' target='_blank'>{$row["Requester lib"]}</a></td>
                <td>{$row["DueDate"]}<br>" . shipmtotxt($row["shipMethod"]) . "</td>
                <td>" . date("Y-m-d", strtotime($row["Timestamp"])) . "<br>" .
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
                  "<br>{$row["IlliadTransID"]}</td>
                <td>";

        // ==== Actions ====
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
                        <input type='hidden' name='num' value='" . htmlspecialchars($illNUB, ENT_QUOTES) . "'>
                        <input type='hidden' name='fill' value='1'>
                        <button type='submit'>Yes, Will Fill</button>
                      </form>
                      <hr>
                      <form method='post' action='/respond'>
                        <input type='hidden' name='FromLender' value='1'>
                        <input type='hidden' name='num' value='" . htmlspecialchars($illNUB, ENT_QUOTES) . "'>
                        <input type='hidden' name='fill' value='0'>
                        <button type='submit'>No, Can&#39;t Fill</button>
                      </form>";
        } elseif ((strlen($return) < 2) && ($fill == 1) && (strlen($renewReq) > 1) && (strlen($checkin) < 2)) {
            echo "<form method='post' action='/renew'>
                        <input type='hidden' name='num' value='" . htmlspecialchars($illNUB, ENT_QUOTES) . "'>
                        <input type='hidden' name='a' value='1'>
                        <button type='submit'>Approve Renewal</button>
                      </form>
                      <form method='post' action='/renew'>
                        <input type='hidden' name='num' value='" . htmlspecialchars($illNUB, ENT_QUOTES) . "'>
                        <input type='hidden' name='a' value='2'>
                        <button type='submit'>Deny Renewal</button>
                      </form>
                      <hr>
                      <form method='post' action='/status'>
                        <input type='hidden' name='num' value='" . htmlspecialchars($illNUB, ENT_QUOTES) . "'>
                        <input type='hidden' name='a' value='3'>
                        <button type='submit'>Check Item Back In</button>
                      </form>";
        } elseif (($daysdiff > 14) && (strlen($checkin) < 2) && ($fill != 4) && ($fill != 6)) {
            echo "<form method='post' action='/status'>
                        <input type='hidden' name='num' value='" . htmlspecialchars($illNUB, ENT_QUOTES) . "'>
                        <input type='hidden' name='a' value='3'>
                        <button type='submit'>Check Item Back In</button>
                      </form>";
        } elseif ((strlen($return) < 2) && (strlen($renewReq) < 1) && (strlen($receive) > 1) && (strlen($checkin) < 2)) {
            echo "<form method='post' action='/renew'>
                        <input type='hidden' name='num' value='" . htmlspecialchars($illNUB, ENT_QUOTES) . "'>
                        <input type='hidden' name='a' value='4'>
                        <button type='submit'>Edit Due Date</button>
                      </form>";
            if ($daysdiff > 14) {
                echo "<form method='post' action='/status'>
                            <input type='hidden' name='num' value='" . htmlspecialchars($illNUB, ENT_QUOTES) . "'>
                            <input type='hidden' name='a' value='3'>
                            <button type='submit'>Check Item Back In</button>
                          </form>";
            }
        } elseif ((strlen($checkin) < 2) && (strlen($receive) > 1)) {
            echo "<form method='post' action='/status'>
                        <input type='hidden' name='num' value='" . htmlspecialchars($illNUB, ENT_QUOTES) . "'>
                        <input type='hidden' name='a' value='3'>
                        <button type='submit'>Check Item Back In</button>
                      </form>";
        } else {
            echo "&nbsp;";
        }

        echo "</td></tr>";
        $rowtype++;
    }
    echo "</tbody></table>";
}
?>

<script>
// "check all" toggle
document.getElementById("check_all").addEventListener("click", function(e) {
  document.querySelectorAll(".check_item").forEach(cb => cb.checked = e.target.checked);
});

// Handle bulk submit via JS only
document.getElementById("bulkSubmit").addEventListener("click", function() {
  const action = document.getElementById("bulkaction").value;
  const selected = Array.from(document.querySelectorAll(".check_item:checked"))
                       .map(cb => cb.value);

  if (!action) {
    alert("Please select an action.");
    return;
  }
  if (selected.length === 0) {
    alert("Please select at least one request.");
    return;
  }
  if (!confirm("Confirm, you want to continue with bulk update.")) return;

  // Build params correctly for multiple values
  const params = new URLSearchParams();
  params.append("bulkaction", action);
  selected.forEach(val => params.append("check_list[]", val));

  fetch("/bulkaction", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: params
  })
  .then(r => r.text())
  .then(txt => {
    alert("Bulk update complete.");
    location.reload();
  })
  .catch(err => alert("Error: " + err));
});
</script>