<?php
// allrequests_ls.php

require '/var/www/seal_wp_script/seal_function.php';
require '/var/www/seal_wp_script/seal_db.inc';

// ----------------------------------------
// Load WordPress environment to get user data
// ----------------------------------------
require_once('/var/www/wpSEAL/wp-load.php');
wp_get_current_user();
global $current_user;

$home_system = get_user_meta($current_user->ID, 'home_system', true);
if (empty($home_system)) {
    die("<div style='padding:20px;color:red;font-weight:bold;'>Your account does not have a Home Library System assigned.</div>");
}

// ----------------------------------------
// Configuration
// ----------------------------------------
$results_per_page_options = [25, 50, 100, 'all'];

// ----------------------------------------
// Handle Filters
// ----------------------------------------
$filter_startdate  = $_GET['filter_startdate']  ?? "2022-08-01";
$filter_enddate    = $_GET['filter_enddate']    ?? date("Y-m-d");
$filter_title      = $_GET['filter_title']      ?? "";
$filter_illnum     = $_GET['filter_illnum']     ?? "";
$filter_lender     = $_GET['filter_lender']     ?? "";
$filter_borrower   = $_GET['filter_borrower']   ?? "";
$filter_numresults = $_GET['filter_numresults'] ?? 25;
$pg                = isset($_GET['pg']) ? max(1, (int)$_GET['pg']) : 1;

if (!in_array($filter_numresults, ['all', 25, 50, 100], true)) {
    $filter_numresults = 25;
}

// ----------------------------------------
// Database connection
// ----------------------------------------
$db = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$db) {
    die('DB connection failed: ' . mysqli_connect_error());
}
mysqli_set_charset($db, 'utf8mb4');

// ----------------------------------------
// Base SQL and filters
// ----------------------------------------
$SQLBASE = "SELECT *, DATE_FORMAT(`Timestamp`, '%Y/%m/%d') AS ts_fmt FROM `$sealSTAT` WHERE 1=1";
$conds   = [];

// Apply system restriction
$conds[] = "(`ReqSystem` = '".mysqli_real_escape_string($db, $home_system)."' 
             OR `DestSystem` = '".mysqli_real_escape_string($db, $home_system)."')";

// Date range
if ($filter_startdate && $filter_enddate) {
    $sd = mysqli_real_escape_string($db, $filter_startdate);
    $ed = mysqli_real_escape_string($db, $filter_enddate);
    $conds[] = "`Timestamp` BETWEEN '$sd 00:00:00' AND '$ed 23:59:59'";
}

// Other filters
if ($filter_title) {
    $conds[] = "`Title` LIKE '%".mysqli_real_escape_string($db, $filter_title)."%'";
}
if ($filter_illnum) {
    $conds[] = "`illNUB` = '".mysqli_real_escape_string($db, $filter_illnum)."'";
}
if ($filter_lender) {
    $conds[] = "`Destination` LIKE '%".mysqli_real_escape_string($db, $filter_lender)."%'";
}
if ($filter_borrower) {
    $conds[] = "(`Requester person` LIKE '%".mysqli_real_escape_string($db, $filter_borrower)."%' 
               OR `Requester lib` LIKE '%".mysqli_real_escape_string($db, $filter_borrower)."%')";
}

$where = " AND ".implode(" AND ", $conds);

// Count total
$count_sql = "SELECT COUNT(*) AS total FROM `$sealSTAT` WHERE 1=1 $where";
$count_res = mysqli_query($db, $count_sql);
$row = mysqli_fetch_assoc($count_res);
$totalResults = $row ? (int)$row['total'] : 0;

// ----------------------------------------
// Pagination math
// ----------------------------------------
if ($filter_numresults === 'all') {
    $limit = $totalResults > 0 ? $totalResults : 1;
    $totalPages = 1;
    $pg = 1;
    $offset = 0;
} else {
    $limit = (int)$filter_numresults;
    if ($limit <= 0) {
        $limit = 25;
    }
    $totalPages = max(1, ceil($totalResults / $limit));
    if ($pg > $totalPages) {
        $pg = $totalPages;
    }
    $offset = ($pg - 1) * $limit;
}

// ----------------------------------------
// Main SQL
// ----------------------------------------
$sql = "$SQLBASE $where ORDER BY `Timestamp` DESC";
if ($filter_numresults !== 'all') {
    $sql .= " LIMIT $offset, $limit";
}
$GETLIST = mysqli_query($db, $sql);
?>

<link rel="stylesheet" href="https://sealbeta.senylrc.org/assets/jquery-ui.css">
<link rel="stylesheet" href="/wp-content/themes/neve/css/seal-admin.css">
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
  $('form.filter-bar').on('submit', function(){
    $(this).find('input[name="pg"]').val('1');
  });
});
</script>

<main id="content">
  <div class="adminlib-wrapper">

    <div class="adminlib-header">
      <h3>(<?php echo htmlspecialchars($home_system); ?> System) Requests </h3>
      <div class="actions">
        <a href="/var/www/seal_wp_script/export.php" class="export-btn">Export CSV</a>
      </div>
    </div>

    <form method="get" class="filter-bar">
      <input type="hidden" name="pg" value="1">
      <div class="filter-grid">
        <div class="form-group">
          <label>Start Date</label>
          <input id="startdate" name="filter_startdate" value="<?php echo htmlspecialchars($filter_startdate); ?>">
        </div>
        <div class="form-group">
          <label>End Date</label>
          <input id="enddate" name="filter_enddate" value="<?php echo htmlspecialchars($filter_enddate); ?>">
        </div>
        <div class="form-group">
          <label>Title</label>
          <input name="filter_title" value="<?php echo htmlspecialchars($filter_title); ?>">
        </div>
        <div class="form-group">
          <label>ILL #</label>
          <input name="filter_illnum" value="<?php echo htmlspecialchars($filter_illnum); ?>">
        </div>
        <div class="form-group">
          <label>Lender</label>
          <input name="filter_lender" value="<?php echo htmlspecialchars($filter_lender); ?>">
        </div>
        <div class="form-group">
          <label>Borrower</label>
          <input name="filter_borrower" value="<?php echo htmlspecialchars($filter_borrower); ?>">
        </div>
        <div class="form-group">
          <label>Results per page</label>
          <select name="filter_numresults">
            <?php
            foreach ($results_per_page_options as $opt) {
                $sel = ((string)$filter_numresults === (string)$opt) ? 'selected' : '';
                echo "<option value='$opt' $sel>$opt</option>";
            }
?>
          </select>
        </div>
      </div>
      <div class="filter-actions">
        <button type="submit" class="btn-primary">Update Results</button>
        <a href="myrequests.php" class="btn-secondary">Reset</a>
      </div>
    </form>

<?php
if (!$GETLIST || $totalResults == 0) {
    echo "<div class='status-message status-error'><strong>No results found.</strong></div>";
} else {
    echo "<div class='pagination-info'>".number_format($totalResults)." total results</div>";
    echo "<table class='rh-table'><thead><tr>
    <th>ILL #</th><th>Title / Author</th><th>Type</th><th>Need By</th>
    <th>Lender</th><th>Borrower</th><th>Due Date / Shipping</th>
    <th>Timestamp</th><th>Status</th></tr></thead><tbody>";

    $rowtype = 1;
    while ($r = mysqli_fetch_assoc($GETLIST)) {
        $class = ($rowtype++ & 1) ? "group-odd" : "group-even";
        $status = itemstatus(
            $r["Fill"],
            $r["receiveAccount"],
            $r["returnAccount"],
            $r["returnDate"],
            $r["receiveDate"],
            $r["checkinAccount"],
            $r["checkinTimeStamp"],
            $r["fillNofillDate"]
        );
        $shiptxt = shipmtotxt($r["shipMethod"]);

        // ----------------------------------------
        // Lookup library name for lender (Destination LOC)
        // ----------------------------------------
        $lender_loc = trim($r['Destination']);
        $lender_name = '';

        if (!empty($lender_loc)) {
            $lib_query = "SELECT Name FROM `$sealLIB` WHERE LOC = '".mysqli_real_escape_string($db, $lender_loc)."' LIMIT 1";
            $lib_result = mysqli_query($db, $lib_query);
            if ($lib_result && $lib_row = mysqli_fetch_assoc($lib_result)) {
                $lender_name = $lib_row['Name'];
            }
        }
        if (empty($lender_name)) {
            $lender_name = $lender_loc; // fallback to LOC if not found
        }

        echo "<tr class='$class'>
          <td>".htmlspecialchars($r['illNUB'])."</td>
          <td>".htmlspecialchars($r['Title'])."<br><i>".htmlspecialchars($r['Author'])."</i></td>
          <td>".htmlspecialchars($r['Itype'])."</td>
          <td>".htmlspecialchars($r['needbydate'])."</td>
          <td>".htmlspecialchars($lender_name)."</td>
          <td>".htmlspecialchars($r['Requester person'])."<br>".htmlspecialchars($r['Requester lib'])."</td>
          <td>".htmlspecialchars($r['DueDate'])."<br>".htmlspecialchars($shiptxt)."</td>
          <td>".htmlspecialchars($r['ts_fmt'])."</td>
          <td>$status</td>
        </tr>";
    }

    echo "</tbody></table>";

    // ----------------------------------------
    // Pagination controls
    // ----------------------------------------
    if ($filter_numresults !== 'all' && $totalPages > 1) {
        echo "<div class='filter-actions'>";
        $base_qs = $_GET;

        if ($pg > 1) {
            $prev_qs = $base_qs;
            $prev_qs['pg'] = $pg - 1;
            echo "<a class='btn-secondary' href='?".htmlspecialchars(http_build_query($prev_qs))."'>Previous</a> ";
        } else {
            echo "<span class='btn-secondary' style='opacity:.6;pointer-events:none;'>Previous</span> ";
        }

        if ($pg < $totalPages) {
            $next_qs = $base_qs;
            $next_qs['pg'] = $pg + 1;
            echo "<a class='btn-primary' href='?".htmlspecialchars(http_build_query($next_qs))."'>Next</a>";
        } else {
            echo "<span class='btn-primary' style='opacity:.6;pointer-events:none;'>Next</span>";
        }

        echo "<p style='margin-top:10px;'>Page ".number_format($pg)." of ".number_format($totalPages)."</p>";
        echo "</div>";
    }
}
?>
  </div>
</main>