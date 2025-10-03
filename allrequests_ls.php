<?php
// allrequests_ls.php — requesthistory/ look & feel
?>
<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/themes/smoothness/jquery-ui.css">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js"></script>
<script>
  jQuery(function($){
    $("#startdate").datepicker({ dateFormat: "mm/dd/yy" });
    $("#enddate").datepicker({ dateFormat: "mm/dd/yy" });
  });
</script>
<style>
/* ===== Request History look ===== */
.rh-shell { font-family: system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif; color:#111; background:#f7f7fb; }
.rh-wrap { max-width:1200px; margin:0 auto; padding:18px; }

.rh-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
.rh-header .title { font-size:22px; font-weight:800; letter-spacing:.2px; }
.rh-header .meta { color:#6b7280; font-size:13px; }

.rh-card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; box-shadow:0 1px 2px rgba(0,0,0,.04); padding:16px; margin-bottom:14px; }
.rh-hr { border:0; border-top:1px solid #eef2f7; margin:12px 0; }

.rh-row { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; }
.rh-row .input, .rh-row select { padding:8px 10px; border:1px solid #d1d5db; border-radius:8px; min-width:190px; background:#fff; }
.rh-note { color:#6b7280; font-size:12px; }

.rh-pills { display:flex; gap:12px; flex-wrap:wrap; margin-top:6px; }
.rh-pill { display:flex; align-items:center; gap:6px; background:#f7fafc; border:1px solid #e5e7eb; padding:6px 10px; border-radius:999px; font-size:13px; }
.rh-pill input[type="checkbox"] { transform:scale(1.15); }

.rh-btn { display:inline-block; padding:9px 14px; border-radius:10px; border:1px solid #0f766e; background:#0d9488; color:#fff; text-decoration:none; font-weight:600; cursor:pointer; }
.rh-btn.secondary { background:#fff; color:#0f766e; }
.rh-btn.link { border:0; background:transparent; color:#0d9488; padding:0; }

.rh-tablewrap { overflow:auto; border:1px solid #e5e7eb; border-radius:12px; background:#fff; }
.rh-table { width:100%; border-collapse:separate; border-spacing:0; }
.rh-table th, .rh-table td { padding:10px 10px; border-bottom:1px solid #f0f2f6; text-align:left; }
.rh-table thead th { position:sticky; top:0; background:#fafafa; z-index:2; font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#6b7280; }
.rh-table tr:nth-child(even) td { background:#fcfcfe; }

.badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; border:1px solid #e5e7eb; background:#f9fafb; color:#374151; }
.badge.ok { color:#065f46; background:#ecfdf5; border-color:#d1fae5; }
.badge.warn { color:#92400e; background:#fffbeb; border-color:#fde68a; }
.badge.err { color:#991b1b; background:#fef2f2; border-color:#fecaca; }

@media (max-width: 900px) { .hide-md { display:none; } }
</style>

<div class="rh-shell"><div class="rh-wrap">
  <div class="rh-header">
    <div class="title">All Requests</div>
    <div class="meta">Filter, search, and review requests in your system.</div>
  </div>

<?php
// ---------- PHP logic ----------
require '/var/www/seal_wp_script/seal_function.php';
require '/var/www/seal_wp_script/seal_db.inc';


// firstpass & inputs
$firstpass      = (isset($_REQUEST['firstpass']) ? "no" : "yes");
$filter_illnum  = $_REQUEST['filter_illnum'] ?? "";
$filter_system  = $_REQUEST['filter_system'] ?? "";  

// defaults
if ($firstpass === "no") {
    if ($filter_illnum !== "") {
        $filter_startdate = "01/01/2023";
        $filter_enddate   = date("m/d/Y");
        $filter_lender    = "";
        $filter_borrower  = "";
        $filter_numresults= "all";
        $filter_title     = "";
        $filter_yes       = "yes";
        $filter_no        = "yes";
        $filter_noans     = "yes";
        $filter_expire    = "yes";
        $filter_cancel    = "yes";
        $filter_recevied  = "yes";
        $filter_return    = "yes";
        $filter_checkin   = "yes";
        $filter_destination = "";
        $filter_offset    = 0;
    } else {
        $filter_yes      = $_REQUEST['filter_yes']      ?? "";
        $filter_no       = $_REQUEST['filter_no']       ?? "";
        $filter_noans    = $_REQUEST['filter_noans']    ?? "";
        $filter_expire   = $_REQUEST['filter_expire']   ?? "";
        $filter_cancel   = $_REQUEST['filter_cancel']   ?? "";
        $filter_recevied = $_REQUEST['filter_recevied'] ?? "";
        $filter_return   = $_REQUEST['filter_return']   ?? "";
        $filter_checkin  = $_REQUEST['filter_checkin']  ?? "";
        $filter_lender   = $_REQUEST['filter_lender']   ?? "";
        $filter_borrower = $_REQUEST['filter_borrower'] ?? "";
        $filter_title    = $_REQUEST['filter_title']    ?? "";
        $filter_startdate= $_REQUEST['filter_startdate']?? "01/01/2023";
        $filter_enddate  = $_REQUEST['filter_enddate']  ?? date("m/d/Y");
        $filter_numresults= $_REQUEST['filter_numresults'] ?? "25";
        $filter_offset   = (int)($_REQUEST['filter_offset'] ?? 0);
    }
} else {
    $firstpass       = "no";
    $filter_illnum   = "";
    $filter_startdate= "01/01/2023";
    $filter_enddate  = date("m/d/Y");
    $filter_lender   = "";
    $filter_borrower = "";
    $filter_title    = "";
    $filter_numresults = "25";
    $filter_yes      = "yes";
    $filter_no       = "yes";
    $filter_noans    = "yes";
    $filter_expire   = "yes";
    $filter_cancel   = "yes";
    $filter_recevied = "yes";
    $filter_return   = "yes";
    $filter_checkin  = "yes";
    $filter_offset   = 0;
}

// Connect to database
$db = mysqli_connect($dbhost, $dbuser, $dbpass);
mysqli_select_db($db, $dbname);

// Initialize SQL parts
$SQLILL = '';
$SQL_LENDER = '';
$SQL_BORROWER = '';

// Base
$SQLBASE = "SELECT *, DATE_FORMAT(`Timestamp`, '%Y/%m/%d') AS `ts_fmt` FROM `$sealSTAT` WHERE ";
$SQLEND  = " ORDER BY `Timestamp` DESC ";

// ILL number filter
if (strlen($filter_illnum) > 2) {
    $SQLILL = " AND `illNUB` = '".mysqli_real_escape_string($db, $filter_illnum)."'";
}

// Lender name -> destination LOC(s)
if (strlen($filter_lender) > 2) {
    $f = mysqli_real_escape_string($db, $filter_lender);
    $SQL_Search = "SELECT `loc` FROM `$sealLIB` WHERE `Name` LIKE '%$f%'";
    $Possibles  = mysqli_query($db, $SQL_Search);
    if ($Possibles && mysqli_num_rows($Possibles) > 0) {
        while ($rowposs = mysqli_fetch_assoc($Possibles)) {
            $possloc = mysqli_real_escape_string($db, $rowposs["loc"]);
            $SQL_LENDER .= (strlen($SQL_LENDER) ? " OR " : " AND (") . "`destination` = '$possloc'";
        }
        $SQL_LENDER .= ")";
    }
}

// Borrower name -> requester LOC(s)
if (strlen($filter_borrower) > 2) {
    $f = mysqli_real_escape_string($db, $filter_borrower);
    $SQL_Search = "SELECT `loc` FROM `$sealLIB` WHERE `Name` LIKE '%$f%'";
    $Possibles  = mysqli_query($db, $SQL_Search);
    if ($Possibles && mysqli_num_rows($Possibles) > 0) {
        while ($rowposs = mysqli_fetch_assoc($Possibles)) {
            $possloc = mysqli_real_escape_string($db, $rowposs["loc"]);
            $SQL_BORROWER .= (strlen($SQL_BORROWER) ? " OR " : " AND (") . "`Requester LOC` = '$possloc'";
        }
        $SQL_BORROWER .= ")";
    }
}

// Title filter
$SQLTITLE = '';
if (strlen($filter_title) > 2) {
    $SQLTITLE = " AND `Title` LIKE '%".mysqli_real_escape_string($db, $filter_title)."%'";
}

// Dates
$sql_startdate = convertDate($filter_startdate); // helper in seal_function.php
$sql_enddate   = convertDate($filter_enddate);
$SQLDATES = "`Timestamp` >= '".$sql_startdate." 00:00:00' AND `Timestamp` <= '".$sql_enddate." 23:59:59' ";

// Fill/status filters
$SQLMIDDLE = '';
if ($filter_yes === "yes")      $SQLMIDDLE .= (strlen($SQLMIDDLE)?' OR ':'') . "`fill` = 1";
if ($filter_no === "yes")       $SQLMIDDLE .= (strlen($SQLMIDDLE)?' OR ':'') . "`fill` = 0";
if ($filter_noans === "yes")    $SQLMIDDLE .= (strlen($SQLMIDDLE)?' OR ':'') . "`fill` = 3";
if ($filter_expire === "yes")   $SQLMIDDLE .= (strlen($SQLMIDDLE)?' OR ':'') . "`fill` = 4";
if ($filter_cancel === "yes")   $SQLMIDDLE .= (strlen($SQLMIDDLE)?' OR ':'') . "`fill` = 6";
if ($filter_checkin === "yes")  $SQLMIDDLE .= (strlen($SQLMIDDLE)?' OR ':'') . "`checkinAccount` IS NOT NULL";
if ($filter_recevied === "yes") $SQLMIDDLE .= (strlen($SQLMIDDLE)?' OR ':'') . "(`receiveAccount` IS NOT NULL AND `returnAccount` IS NULL)";
if ($filter_return === "yes")   $SQLMIDDLE .= (strlen($SQLMIDDLE)?' OR ':'') . "(`returnAccount` IS NOT NULL AND `checkinAccount` IS NULL)";
if (strlen($SQLMIDDLE) < 3)     $SQLMIDDLE = "`fill` = ''"; // just in case

// Pagination
if ($filter_numresults !== "all") {
    $limit = max(1, (int)$filter_numresults);
    $sqllimiter = $limit * max(0, (int)$filter_offset);
    $SQLLIMIT = " LIMIT ".$sqllimiter.", ".$limit;
} else {
    $SQLLIMIT = "";
}

// System scoping (only if we actually have a system value)
$SQLSYSTEM = '';
if (strlen(trim($filter_system)) > 0) {
    $escsys = mysqli_real_escape_string($db, $filter_system);
    $SQLSYSTEM = " AND (`ReqSystem` = '$escsys' OR `DestSystem` = '$escsys')";
}

// Final SQLs
$GETFULLSQL = $SQLBASE . $SQLDATES . $SQLTITLE . $SQL_LENDER . $SQL_BORROWER . $SQLILL . $SQLSYSTEM . " AND (" . $SQLMIDDLE . ")" . $SQLEND;
$GETLISTSQL = $GETFULLSQL . $SQLLIMIT;

$GETLIST  = mysqli_query($db, $GETLISTSQL);
$GETCOUNT = mysqli_query($db, $GETFULLSQL);
$GETLISTCOUNTwhole = $GETCOUNT ? mysqli_num_rows($GETCOUNT) : 0;

if (!$GETLIST) {
    echo '<div class="rh-card"><div class="rh-row"><span class="badge err">Query Error</span><span class="rh-note">'.htmlspecialchars(mysqli_error($db)).'</span></div></div>';
    exit;
}

/* ===== FILTER CARD ===== */
echo '<div class="rh-card">';
echo '  <div class="rh-row" style="justify-content:space-between;align-items:center;">';
echo '    <div><strong>Filters</strong> <span class="rh-note">System: '.htmlspecialchars($filter_system).'</span></div>';
echo '    <div class="rh-note">'.(int)$GETLISTCOUNTwhole.' results</div>';
echo '  </div>';

$actionUrl = $_SERVER['REQUEST_URI'] ?? '/allrequests';
echo '  <form action="'.htmlspecialchars($actionUrl, ENT_QUOTES).'" method="post">';
echo '    <input type="hidden" name="firstpass" value="no">';
echo '    <input type="hidden" name="filter_offset" value="'.(int)$filter_offset.'">';

echo '    <div class="rh-pills">';
echo '      <label class="rh-pill"><input type="checkbox" name="filter_yes" value="yes" '.attr_checked($filter_yes).'> Yes</label>';
echo '      <label class="rh-pill"><input type="checkbox" name="filter_no" value="yes" '.attr_checked($filter_no).'> No</label>';
echo '      <label class="rh-pill"><input type="checkbox" name="filter_noans" value="yes" '.attr_checked($filter_noans).'> No Answer</label>';
echo '      <label class="rh-pill"><input type="checkbox" name="filter_expire" value="yes" '.attr_checked($filter_expire).'> Expired</label>';
echo '      <label class="rh-pill"><input type="checkbox" name="filter_cancel" value="yes" '.attr_checked($filter_cancel).'> Canceled</label>';
echo '      <label class="rh-pill"><input type="checkbox" name="filter_recevied" value="yes" '.attr_checked($filter_recevied).'> Received</label>';
echo '      <label class="rh-pill"><input type="checkbox" name="filter_return" value="yes" '.attr_checked($filter_return).'> Return</label>';
echo '      <label class="rh-pill"><input type="checkbox" name="filter_checkin" value="yes" '.attr_checked($filter_checkin).'> Check In</label>';
echo '    </div>';

echo '    <div class="rh-hr"></div>';

echo '      <label><div class="rh-note">Library System</div>
  <select class="input" name="filter_system">';
echo '    <option value=""'   . ($filter_system == ''   ? " selected='selected'" : "") . '>All</option>';
echo '    <option value="MH"' . ($filter_system == 'MH' ? " selected='selected'" : "") . '>Mid Hudson Library System</option>';
echo '    <option value="RC"' . ($filter_system == 'RC' ? " selected='selected'" : "") . '>Ramapo Catskill Library System</option>';
echo '    <option value="DU"' . ($filter_system == 'DU' ? " selected='selected'" : "") . '>Dutchess BOCES</option>';
echo '    <option value="OU"' . ($filter_system == 'OU' ? " selected='selected'" : "") . '>Orange Ulster BOCES</option>';
echo '    <option value="RB"' . ($filter_system == 'RB' ? " selected='selected'" : "") . '>Rockland BOCES</option>';
echo '    <option value="SB"' . ($filter_system == 'SB' ? " selected='selected'" : "") . '>Sullivan BOCES</option>';
echo '    <option value="UB"' . ($filter_system == 'UB' ? " selected='selected'" : "") . '>Ulster BOCES</option>';
echo '    <option value="SE"' . ($filter_system == 'SE' ? " selected='selected'" : "") . '>Southeastern Group</option>';
echo '  </select>
      </label>';



echo '    <div class="rh-row">';
echo '      <label><div class="rh-note">Start Date</div><input id="startdate" class="input" name="filter_startdate" value="'.htmlspecialchars($filter_startdate, ENT_QUOTES).'"></label>';
echo '      <label><div class="rh-note">End Date</div><input id="enddate" class="input" name="filter_enddate" value="'.htmlspecialchars($filter_enddate, ENT_QUOTES).'"></label>';

echo '      <label><div class="rh-note">Lender</div><input class="input" name="filter_lender" type="text" value="'.htmlspecialchars($filter_lender, ENT_QUOTES).'"></label>';
echo '      <label><div class="rh-note">Borrower</div><input class="input" name="filter_borrower" type="text" value="'.htmlspecialchars($filter_borrower, ENT_QUOTES).'"></label>';
echo '      <label class="hide-md"><div class="rh-note">Title</div><input class="input" name="filter_title" type="text" value="'.htmlspecialchars($filter_title, ENT_QUOTES).'"></label>';
echo '      <label><div class="rh-note">ILL #</div><input class="input" name="filter_illnum" type="text" value="'.htmlspecialchars($filter_illnum, ENT_QUOTES).'"></label>';
echo '      <label><div class="rh-note">Per page</div><select class="input" name="filter_numresults">
                  <option '.attr_selected("25",$filter_numresults).'  value="25">25</option>
                  <option '.attr_selected("50",$filter_numresults).'  value="50">50</option>
                  <option '.attr_selected("100",$filter_numresults).' value="100">100</option>
                  <option '.attr_selected("all",$filter_numresults).' value="all">All</option>
                </select></label>';
echo '      <div style="margin-left:auto; display:flex; gap:8px;">
                <a class="rh-btn secondary" href="allrequests">Clear</a>
                <button class="rh-btn" type="submit">Update</button>
              </div>';
echo '    </div>';

if ($filter_numresults !== "all") {
    $per_page = max(1, (int)$filter_numresults);
    $resultpages = max(1, (int)ceil($GETLISTCOUNTwhole / $per_page));
    echo '<div class="rh-row" style="margin-top:8px;">
            <label><div class="rh-note">Page</div>
              <select class="input" name="filter_offset">';
    for ($x = 1; $x <= $resultpages; $x++) {
        $localoffset = $x - 1;
        echo '<option '.attr_selected($localoffset, $filter_offset).' value="'.$localoffset.'">'.$x.'</option>';
    }
    echo '  </select>
            <span class="rh-note"> of '.$resultpages.'</span>
          </label>
          </div>';
}

echo '  </form>';
echo '</div>';

/* ===== RESULTS ===== */
if (mysqli_num_rows($GETLIST) == 0) {
    echo '<div class="rh-card"><span class="badge">No results found.</span></div>';
} else {
    echo '<div class="rh-tablewrap">';
    echo '<table class="rh-table">';
    echo '<thead><tr>
            <th width="7%">ILL #</th>
            <th width="28%">Title / Author</th>
            <th>Type</th>
            <th class="hide-md">Need By</th>
            <th>Lender</th>
            <th>Borrower</th>
            <th class="hide-md">Due / Ship / ILLiad</th>
            <th>Timestamp</th>
            <th>Status</th>
          </tr></thead><tbody>';

    while ($row = mysqli_fetch_assoc($GETLIST)) {
        $illNUB   = $row["illNUB"];
        $title    = $row["Title"];
        $author   = $row["Author"];
        $itype    = $row["Itype"];
        $reqnote  = $row["reqnote"];
        $lendnote = $row["responderNOTE"];
        $needby   = $row["needbydate"];
        $dest     = trim($row["Destination"]);
        $reqp     = $row["Requester person"];
        $reql     = $row["Requester lib"];
        $destsys  = $row["DestSystem"];
        $reqsys   = $row["ReqSystem"];
        $reqemail = $row["requesterEMAIL"];
        $timestamp= $row["Timestamp"];
        $shipmethod = $row["shipMethod"];
        $receiveAccount = $row['receiveAccount'];
        $returnAccount  = $row['returnAccount'];
        $returnnote     = $row['returnNote'];
        $returnmethod   = $row['returnMethod'];
        $returndate     = $row['returnDate'];
        $fillNoFillDate = $row['fillNofillDate'];
        $receivedate    = $row['receiveDate'];
        $checkinAccount = $row['checkinAccount'];
        $checkindate    = $row['checkinTimeStamp'];
        $duedate        = $row["DueDate"];
        $illiadnumb     = $row["IlliadTransID"];
        $renewNote      = $row["renewNote"];
        $renewNoteLender= $row["renewNoteLender"];

        $statusText = itemstatus($row["Fill"], $receiveAccount, $returnAccount, $returndate, $receivedate, $checkinAccount, $checkindate, $fillNoFillDate);
        $shiptxt    = shipmtotxt($shipmethod);
        $returnmethodtxt = shipmtotxt($returnmethod);

        // Lookup destination name/email
        $destemail = '';
        if (strlen($dest) > 0) {
            $GETLISTSQLDEST = "SELECT `Name`,`ill_email` FROM `$sealLIB` WHERE `loc` LIKE '".mysqli_real_escape_string($db, $dest)."' LIMIT 1";
            if ($resultdest = mysqli_query($db, $GETLISTSQLDEST)) {
                if ($rowdest = mysqli_fetch_assoc($resultdest)) {
                    $dest      = $rowdest["Name"];
                    $destemail = $rowdest["ill_email"];
                }
            }
        } else { $dest = "Error No Library Selected"; }

        $badgeClass = 'badge';
        if (stripos($statusText,'Filled') !== false) $badgeClass .= ' ok';
        elseif (stripos($statusText,'Expired') !== false || stripos($statusText,'Canceled') !== false) $badgeClass .= ' err';
        elseif (stripos($statusText,'No Answer') !== false) $badgeClass .= ' warn';

        echo '<tr>'.
             '<td>'.htmlspecialchars($illNUB).'</td>'.
             '<td>'.htmlspecialchars($title).'<br><i>'.htmlspecialchars($author).'</i></td>'.
             '<td>'.htmlspecialchars($itype).'</td>'.
             '<td class="hide-md">'.htmlspecialchars($needby).'</td>'.
             '<td><a class="rh-btn link" href="mailto:'.htmlspecialchars($destemail).'?Subject='.rawurlencode('NOTE Request ILL# '.$illNUB).'" target="_blank">'.htmlspecialchars($dest).'</a><br><span class="rh-note">'.htmlspecialchars($destsys).'</span></td>'.
             '<td><a class="rh-btn link" href="mailto:'.htmlspecialchars($reqemail).'?Subject='.rawurlencode('NOTE Request ILL# '.$illNUB).'" target="_blank">'.htmlspecialchars($reqp).'</a><br>'.htmlspecialchars($reql).'<br><span class="rh-note">'.htmlspecialchars($reqsys).'</span></td>'.
             '<td class="hide-md">'.htmlspecialchars($duedate).'<br>'.htmlspecialchars($shiptxt).'<br>'.htmlspecialchars($illiadnumb).'</td>'.
             '<td>'.htmlspecialchars($timestamp).'</td>'.
             '<td><span class="'.$badgeClass.'">'.$statusText.'</span></td>'.
             '</tr>';

        // Notes rows
        if ((strlen($reqnote) > 2) || (strlen($lendnote) > 2)) {
            $displaynotes = build_notes($reqnote, $lendnote);
            echo '<tr><td></td><td></td><td colspan="7">'.$displaynotes.'</td></tr>';
        }
        if ((strlen($returnnote) > 2) || (strlen($returnmethod) > 2)) {
            $displayreturnnotes = build_return_notes($returnnote, $returnmethodtxt);
            echo '<tr><td></td><td></td><td colspan="7">'.$displayreturnnotes.'</td></tr>';
        }
        if ((strlen($renewNote) > 2) || (strlen($renewNoteLender) > 2)) {
            $displayrenewnotes = build_renewnotes($renewNote, $renewNoteLender);
            echo '<tr><td></td><td></td><td colspan="7">'.$displayrenewnotes.'</td></tr>';
        }
    }

    echo '</tbody></table></div>';
}

?>
</div></div>