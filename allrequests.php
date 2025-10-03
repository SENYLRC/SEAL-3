<link rel="stylesheet" href="https://sealbeta.senylrc.org/assets/jquery-ui.css">
<script src="https://sealbeta.senylrc.org/assets/jquery.min.js"></script>
<script src="https://sealbeta.senylrc.org/assets/jquery-ui.min.js"></script>

<script>
jQuery(function($){
  $("#startdate, #enddate").datepicker({
    dateFormat: "yy-mm-dd",
    changeMonth: true,
    changeYear: true,
    showAnim: "fadeIn"
  });
});
</script>

<style>
/* Override jQuery UI arrows if icons missing */
.ui-datepicker .ui-datepicker-prev span,
.ui-datepicker .ui-datepicker-next span {
  background-image: none !important;
  text-indent: 0 !important;
  font-size: 16px;
  line-height: 1.2;
  color: #333;
}
.ui-datepicker .ui-datepicker-prev span::before { content: "◀"; }
.ui-datepicker .ui-datepicker-next span::before { content: "▶"; }
</style>

<?php
// allrequests.php###
require '/var/www/seal_wp_script/seal_function.php';

// -------------------------------
// Handle Filters
// -------------------------------
$firstpass = isset($_REQUEST['firstpass']) ? "no" : "yes";

$filter_illnum     = $_REQUEST['filter_illnum']     ?? "";
$filter_startdate  = $_REQUEST['filter_startdate']  ?? "";
$filter_enddate    = $_REQUEST['filter_enddate']    ?? "";
$filter_lender     = $_REQUEST['filter_lender']     ?? "";
$filter_borrower   = $_REQUEST['filter_borrower']   ?? "";
$filter_title      = $_REQUEST['filter_title']      ?? "";
$filter_system     = $_REQUEST['filter_system']     ?? "";
$filter_numresults = $_REQUEST['filter_numresults'] ?? 25;
$filter_offset     = $_REQUEST['filter_offset']     ?? 0;

$filter_yes      = $_REQUEST['filter_yes']      ?? "yes";
$filter_no       = $_REQUEST['filter_no']       ?? "yes";
$filter_noans    = $_REQUEST['filter_noans']    ?? "yes";
$filter_expire   = $_REQUEST['filter_expire']   ?? "yes";
$filter_cancel   = $_REQUEST['filter_cancel']   ?? "yes";
$filter_recevied = $_REQUEST['filter_recevied'] ?? "yes";
$filter_return   = $_REQUEST['filter_return']   ?? "yes";
$filter_checkin  = $_REQUEST['filter_checkin']  ?? "yes";

// Defaults if first load
if ($firstpass === "yes") {
  $filter_startdate = "2022-08-01";
  $filter_enddate   = date("Y-m-d");
}

// -------------------------------
// DB Connect
// -------------------------------
require '/var/www/seal_wp_script/seal_db.inc';
$db = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

// -------------------------------
// Build SQL
// -------------------------------
$SQLBASE = "SELECT *, DATE_FORMAT(`Timestamp`, '%Y/%m/%d') AS ts_fmt FROM `$sealSTAT` WHERE 1=1 ";
$SQLEND  = " ORDER BY `Timestamp` DESC ";

$conds = [];

// Dates
if ($filter_startdate && $filter_enddate) {
  $sql_startdate = mysqli_real_escape_string($db, $filter_startdate);
  $sql_enddate   = mysqli_real_escape_string($db, $filter_enddate);
  $conds[] = "`Timestamp` BETWEEN '$sql_startdate 00:00:00' AND '$sql_enddate 23:59:59'";
}

// ILL number
if (strlen($filter_illnum) > 2) {
  $conds[] = "`illNUB` = '".mysqli_real_escape_string($db, $filter_illnum)."'";
}

// Lender name
if (strlen($filter_lender) > 2) {
  $SQL_Search = "SELECT `loc` FROM `$sealLIB` WHERE `Name` LIKE '%".mysqli_real_escape_string($db, $filter_lender)."%'";
  $res = mysqli_query($db, $SQL_Search);
  $or = [];
  while ($r = mysqli_fetch_assoc($res)) {
    $or[] = "`Destination` = '".mysqli_real_escape_string($db, $r['loc'])."'";
  }
  if ($or) $conds[] = "(".implode(" OR ", $or).")";
}

// Borrower name
if (strlen($filter_borrower) > 2) {
  $SQL_Search = "SELECT `loc` FROM `$sealLIB` WHERE `Name` LIKE '%".mysqli_real_escape_string($db, $filter_borrower)."%'";
  $res = mysqli_query($db, $SQL_Search);
  $or = [];
  while ($r = mysqli_fetch_assoc($res)) {
    $or[] = "`Requester LOC` = '".mysqli_real_escape_string($db, $r['loc'])."'";
  }
  if ($or) $conds[] = "(".implode(" OR ", $or).")";
}

// Title
if (strlen($filter_title) > 2) {
  $conds[] = "`Title` LIKE '%".mysqli_real_escape_string($db, $filter_title)."%'";
}

// System
if ($filter_system) {
  $conds[] = "(`ReqSystem` = '".mysqli_real_escape_string($db, $filter_system)."' OR `DestSystem` = '".mysqli_real_escape_string($db, $filter_system)."')";
}

// Fill status
$statusConds = [];
if ($filter_yes === "yes")      $statusConds[] = "`Fill` = 1";
if ($filter_no === "yes")       $statusConds[] = "`Fill` = 0";
if ($filter_noans === "yes")    $statusConds[] = "`Fill` = 3";
if ($filter_expire === "yes")   $statusConds[] = "`Fill` = 4";
if ($filter_cancel === "yes")   $statusConds[] = "`Fill` = 6";
if ($filter_checkin === "yes")  $statusConds[] = "`checkinAccount` IS NOT NULL";
if ($filter_recevied === "yes") $statusConds[] = "`receiveAccount` IS NOT NULL AND `returnAccount` IS NULL";
if ($filter_return === "yes")   $statusConds[] = "`returnAccount` IS NOT NULL AND `checkinAccount` IS NULL";

if ($statusConds) {
  $conds[] = "(".implode(" OR ", $statusConds).")";
}

// Combine SQL
$WHERE = $conds ? " AND ".implode(" AND ", $conds) : "";
$SQLFULL  = $SQLBASE.$WHERE.$SQLEND;
$SQLLIMIT = $SQLFULL;

if ($filter_numresults != "all") {
  $offset = intval($filter_offset) * intval($filter_numresults);
  $SQLLIMIT .= " LIMIT $offset, ".intval($filter_numresults);
}

// -------------------------------
// Query
// -------------------------------
$GETLIST = mysqli_query($db, $SQLLIMIT);
$GETCOUNT = mysqli_query($db, $SQLFULL);
$totalResults = $GETCOUNT ? mysqli_num_rows($GETCOUNT) : 0;

// -------------------------------
// Filter Form
// -------------------------------
echo "<form action='".$_SERVER['REDIRECT_URL']."' method='post'>";
echo "<input type='hidden' name='firstpass' value='no'>";
echo "<input type='hidden' name='filter_offset' value='".htmlspecialchars($filter_offset,ENT_QUOTES)."'>";

echo "<h3>All Requests (Borrowing & Lending)</h3>";
echo "<h3>Limit Results</h3>";

echo "<div style='display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:6px;max-width:900px;'>";
echo "<label><input type='checkbox' name='filter_yes' value='yes' ".checked($filter_yes,'yes',false)."> Yes</label>";
echo "<label><input type='checkbox' name='filter_no' value='yes' ".checked($filter_no,'yes',false)."> No</label>";
echo "<label><input type='checkbox' name='filter_noans' value='yes' ".checked($filter_noans,'yes',false)."> No Answer</label>";
echo "<label><input type='checkbox' name='filter_expire' value='yes' ".checked($filter_expire,'yes',false)."> Expired</label>";
echo "<label><input type='checkbox' name='filter_cancel' value='yes' ".checked($filter_cancel,'yes',false)."> Canceled</label>";
echo "<label><input type='checkbox' name='filter_recevied' value='yes' ".checked($filter_recevied,'yes',false)."> Received</label>";
echo "<label><input type='checkbox' name='filter_return' value='yes' ".checked($filter_return,'yes',false)."> Return</label>";
echo "<label><input type='checkbox' name='filter_checkin' value='yes' ".checked($filter_checkin,'yes',false)."> Check In</label>";
echo "</div><br>";

echo "<div style='display:flex;flex-wrap:wrap;gap:12px;align-items:center;'>";
echo "Start Date <input id='startdate' name='filter_startdate' value='".htmlspecialchars($filter_startdate,ENT_QUOTES)."'>";
echo " End Date <input id='enddate' name='filter_enddate' value='".htmlspecialchars($filter_enddate,ENT_QUOTES)."'>";
echo "</div><br>";

echo "Library System <select name='filter_system'>
<option ".selected('', $filter_system,false)." value=''>All</option>
<option ".selected('MH',$filter_system,false)." value='MH'>Mid Hudson</option>
<option ".selected('RC',$filter_system,false)." value='RC'>Ramapo Catskill</option>
<option ".selected('DU',$filter_system,false)." value='DU'>Dutchess BOCES</option>
<option ".selected('OU',$filter_system,false)." value='OU'>Orange Ulster BOCES</option>
<option ".selected('RB',$filter_system,false)." value='RB'>Rockland BOCES</option>
<option ".selected('SB',$filter_system,false)." value='SB'>Sullivan BOCES</option>
<option ".selected('UB',$filter_system,false)." value='UB'>Ulster BOCES</option>
<option ".selected('SE',$filter_system,false)." value='SE'>Southeastern Group</option>
</select><br>";

echo "Lender <input name='filter_lender' value='".htmlspecialchars($filter_lender,ENT_QUOTES)."'> ";
echo "Borrower <input name='filter_borrower' value='".htmlspecialchars($filter_borrower,ENT_QUOTES)."'><br>";
echo "Title <input name='filter_title' value='".htmlspecialchars($filter_title,ENT_QUOTES)."'><br>";
echo "ILL # <input name='filter_illnum' value='".htmlspecialchars($filter_illnum,ENT_QUOTES)."'><br><br>";

echo "$totalResults results with 
<select name='filter_numresults'>
<option ".selected("25",$filter_numresults,false)." value='25'>25</option>
<option ".selected("50",$filter_numresults,false)." value='50'>50</option>
<option ".selected("100",$filter_numresults,false)." value='100'>100</option>
<option ".selected("all",$filter_numresults,false)." value='all'>All</option>
</select> per page.";

if (is_numeric($filter_numresults) && $filter_numresults > 0) {
  $pages = ceil($totalResults / $filter_numresults);
  $current = $filter_offset + 1;
  echo " Page <select name='filter_offset'>";
  for ($i=1;$i<=$pages;$i++) {
    $off = $i-1;
    echo "<option ".selected($off,$filter_offset,false)." value='$off'>$i</option>";
  }
  echo "</select> of $pages.";
}

echo "<br><br><input type='submit' value='Update Results'> ";
echo "<a href='allrequests'>Reset</a>";
echo "</form>";

// -------------------------------
// Results Table
// -------------------------------
if (!$GETLIST || $totalResults == 0) {
  echo "<p>No results found.</p>";
} else {
  echo "<table class='responsive-table'>
  <thead><tr>
    <th>ILL #</th><th>Title / Author</th><th>Type</th><th>Need By</th>
    <th>Lender</th><th>Borrower</th>
    <th>Due Date / Shipping / ILLiad #</th>
    <th>Timestamp</th><th>Status</th>
  </tr></thead><tbody>";

  $rowtype=1;
  while ($row=mysqli_fetch_assoc($GETLIST)) {
    $rowclass = ($rowtype & 1) ? "group-odd" : "group-even";
    $illNUB = $row["illNUB"];
    $title  = $row["Title"];
    $author = $row["Author"];
    $itype  = $row["Itype"];
    $needby = $row["needbydate"];
    $dest   = $row["Destination"];
    $reqp   = $row["Requester person"];
    $reql   = $row["Requester lib"];
    $reqemail=$row["requesterEMAIL"];
    $timestamp=$row["Timestamp"];
    $duedate=$row["DueDate"];
    $illiadnumb=$row["IlliadTransID"];
    $fill=$row["Fill"];
    $statustxt=itemstatus($fill,$row["receiveAccount"],$row["returnAccount"],$row["returnDate"],$row["receiveDate"],$row["checkinAccount"],$row["checkinTimeStamp"],$row["fillNofillDate"]);
    $shiptxt=shipmtotxt($row["shipMethod"]);
    $returnmethodtxt=shipmtotxt($row["returnMethod"]);

    // Destination name
    if (strlen(trim($dest))>0) {
      $resDest=mysqli_query($db,"SELECT `Name`,`ill_email` FROM `$sealLIB` WHERE loc='$dest' LIMIT 1");
      if ($r=mysqli_fetch_assoc($resDest)) {
        $dest=$r["Name"];
        $destemail=$r["ill_email"];
      }
    }

    echo "<tr class='$rowclass'>
      <td>$illNUB</td>
      <td>$title<br><i>$author</i></td>
      <td>$itype</td>
      <td>$needby</td>
      <td><a href='mailto:$destemail?Subject=ILL# $illNUB'>$dest</a></td>
      <td><a href='mailto:$reqemail?Subject=ILL# $illNUB'>$reqp</a><br>$reql</td>
      <td>$duedate<br>$shiptxt<br>$illiadnumb</td>
      <td>".date("Y-m-d",strtotime($timestamp))."</td>
      <td>$statustxt</td>
    </tr>";

    $rowtype++;
  }
  echo "</tbody></table>";
}
?>
