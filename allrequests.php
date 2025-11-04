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

require '/var/www/seal_wp_script/seal_function.php';
require '/var/www/seal_wp_script/seal_db.inc';

/**
 * All Requests (AdminLib style) with robust pagination & filters
 * - Uses pg instead of page to avoid WP conflicts
 * - No custom function declarations (avoids WP collisions)
 * - Resets to pg=1 on Update Results
 */

// ---------------------------------
// Config
// ---------------------------------
$results_per_page_options = [25, 50, 100, 'all'];

// ---------------------------------
// Input / Filters
// ---------------------------------
$firstpass         = isset($_REQUEST['firstpass']) ? "no" : "yes";
$filter_startdate  = $_GET['filter_startdate']  ?? "2024-07-01";
$filter_enddate    = $_GET['filter_enddate']    ?? date("Y-m-d");
$filter_system     = $_GET['filter_system']     ?? "";
$filter_title      = $_GET['filter_title']      ?? "";
$filter_illnum     = $_GET['filter_illnum']     ?? "";
$filter_lender     = $_GET['filter_lender']     ?? "";
$filter_borrower   = $_GET['filter_borrower']   ?? "";
$filter_numresults = $_GET['filter_numresults'] ?? 25;

// pagination param (avoid WP's 'page' var)
$pg = isset($_GET['pg']) ? max(1, (int)$_GET['pg']) : 1;

// normalize numresults
if (!in_array($filter_numresults, ['all', 25, 50, 100], true)) {
    $filter_numresults = 25;
}

// ---------------------------------
// DB & base query
// ---------------------------------

$db = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$db) {
    die('DB connection failed');
}
mysqli_set_charset($db, 'utf8mb4');

$SQLBASE = "SELECT *, DATE_FORMAT(`Timestamp`, '%Y/%m/%d') AS ts_fmt FROM `$sealSTAT` WHERE 1=1";
$conds   = [];

// filters
if ($filter_startdate && $filter_enddate) {
    $sd = mysqli_real_escape_string($db, $filter_startdate);
    $ed = mysqli_real_escape_string($db, $filter_enddate);
    $conds[] = "`Timestamp` BETWEEN '$sd 00:00:00' AND '$ed 23:59:59'";
}
if ($filter_title) {
    $conds[] = "`Title` LIKE '%" . mysqli_real_escape_string($db, $filter_title) . "%'";
}
if ($filter_illnum) {
    $conds[] = "`illNUB` = '" . mysqli_real_escape_string($db, $filter_illnum) . "'";
}
if ($filter_system) {
    $fs = mysqli_real_escape_string($db, $filter_system);
    $conds[] = "(`ReqSystem` = '$fs' OR `DestSystem` = '$fs')";
}
if ($filter_lender) {
    $conds[] = "`Destination` LIKE '%" . mysqli_real_escape_string($db, $filter_lender) . "%'";
}
if ($filter_borrower) {
    $fb = mysqli_real_escape_string($db, $filter_borrower);
    $conds[] = "(`Requester person` LIKE '%$fb%' OR `Requester lib` LIKE '%$fb%')";
}

$where = $conds ? " AND " . implode(" AND ", $conds) : "";

// total count
$count_sql = "SELECT COUNT(*) AS total FROM `$sealSTAT` WHERE 1=1 $where";
$count_res = mysqli_query($db, $count_sql);
$count_row = $count_res ? mysqli_fetch_assoc($count_res) : null;
$totalResults = $count_row ? (int)$count_row['total'] : 0;

// ---------------------------------
// Pagination math
// ---------------------------------
if ($filter_numresults === 'all') {
    $limit = ($totalResults > 0) ? $totalResults : 1; // avoid LIMIT 0
    $totalPages = 1;
    $pg = 1;
    $offset = 0;
} else {
    $limit = (int)$filter_numresults;
    if ($limit <= 0) {
        $limit = 25;
    }
    $totalPages = max(1, (int)ceil($totalResults / $limit));
    if ($pg > $totalPages) {
        $pg = $totalPages;
    }
    $offset = ($pg - 1) * $limit;
    if ($offset < 0) {
        $offset = 0;
    }
}

// final query
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
  // Set pg=1 on filter submit so we always start on first page
  $('form.filter-bar').on('submit', function(){
    $(this).find('input[name="pg"]').val('1');
  });
});
</script>

<main id="content">
  <div class="adminlib-wrapper">

    <div class="adminlib-header">
      <h3>All Requests (Borrowing & Lending)</h3>
      <div class="actions">
        <a href="/var/www/seal_wp_script/export.php" class="export-btn">Export CSV</a>
      </div>
    </div>

    <form method="get" class="filter-bar">
      <input type="hidden" name="firstpass" value="no">
      <input type="hidden" name="pg" value="1"><!-- reset to page 1 on submit -->

      <div class="filter-grid">
        <div class="form-group">
          <label for="startdate">Start Date</label>
          <input id="startdate" name="filter_startdate" value="<?php echo htmlspecialchars($filter_startdate); ?>">
        </div>
        <div class="form-group">
          <label for="enddate">End Date</label>
          <input id="enddate" name="filter_enddate" value="<?php echo htmlspecialchars($filter_enddate); ?>">
        </div>
        <div class="form-group">
          <label for="filter_system">Library System</label>
          <select name="filter_system" id="filter_system">
            <option value="">All</option>
            <?php
            $systems = [
              'MH' => 'Mid Hudson','RC' => 'Ramapo Catskill','DU' => 'Dutchess BOCES','OU' => 'Orange Ulster',
              'RB' => 'Rockland BOCES','SB' => 'Sullivan BOCES','UB' => 'Ulster BOCES','SE' => 'Southeastern'
            ];
foreach ($systems as $code => $name) {
    $sel = ($filter_system === $code) ? 'selected' : '';
    echo "<option value='$code' $sel>$name</option>";
}
?>
          </select>
        </div>
        <div class="form-group">
          <label for="filter_title">Title</label>
          <input id="filter_title" name="filter_title" value="<?php echo htmlspecialchars($filter_title); ?>">
        </div>
        <div class="form-group">
          <label for="filter_illnum">ILL #</label>
          <input id="filter_illnum" name="filter_illnum" value="<?php echo htmlspecialchars($filter_illnum); ?>">
        </div>
        <div class="form-group">
          <label for="filter_lender">Lender</label>
          <input id="filter_lender" name="filter_lender" value="<?php echo htmlspecialchars($filter_lender); ?>">
        </div>
        <div class="form-group">
          <label for="filter_borrower">Borrower</label>
          <input id="filter_borrower" name="filter_borrower" value="<?php echo htmlspecialchars($filter_borrower); ?>">
        </div>
        <div class="form-group">
          <label for="filter_numresults">Results per page</label>
          <select id="filter_numresults" name="filter_numresults">
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
        <a href="allrequests.php" class="btn-secondary">Reset</a>
      </div>
    </form>

<?php
// ---------------------------------
// Results table
// ---------------------------------
if (!$GETLIST || $totalResults == 0) {
    echo "<div class='status-message status-error'><strong>No results found.</strong></div>";
} else {
    echo "<div class='pagination-info'>".number_format($totalResults)." total results</div>";
    echo "<table class='rh-table'><thead><tr>
    <th>ILL #</th>
    <th>Title / Author</th>
    <th>Type</th>
    <th>Need By</th>
    <th>Lender</th>
    <th>Borrower</th>
    <th>Due Date / Shipping</th>
    <th>Timestamp</th>
    <th>Status/Illiad#</th>
  </tr></thead><tbody>";

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

        echo "<tr class='$class'>
      <td>".htmlspecialchars($r['illNUB'])."</td>
      <td>".htmlspecialchars($r['Title'])."<br><i>".htmlspecialchars($r['Author'])."</i></td>
      <td>".htmlspecialchars($r['Itype'])."</td>
      <td>".htmlspecialchars($r['needbydate'])."</td>";
        // Get the Destination Name
        $dest = $r['Destination'];
        if (strlen($dest) > 0) {
            $GETLISTSQLDEST = "SELECT`Name`,`ill_email` FROM `$sealLIB` where loc like '$dest'  limit 1";
            //for testing
            //echo $GETLISTSQLDEST."<br>";
            $resultdest = mysqli_query($db, $GETLISTSQLDEST);
            while ($rowdest = mysqli_fetch_assoc($resultdest)) {
                $dest = $rowdest["Name"];
                $destemail = $rowdest["ill_email"];
            }
        } else {
            $dest = "Error No Library Selected";
        }
        echo "<td>$dest</td>";
        echo"
      <td>".htmlspecialchars($r['Requester person'])."<br>".htmlspecialchars($r['Requester lib'])."</td>
      <td>".htmlspecialchars($r['DueDate'])."</td>
      <td>".htmlspecialchars($r['ts_fmt'])."</td>
      <td>
  $status<br>
  ".htmlspecialchars($shiptxt)."
  ".(!empty($r['IlliadTransID']) ? "<br><span style='font-size:0.9em;color:#555;'><b>ILLiad ID:</b> " . htmlspecialchars($r['IlliadTransID']) . "</span>" : "")."
</td>
    </tr>";
    }
    echo "</tbody></table>";

    // ---------------------------------
    // Pagination controls (no custom funcs)
    // ---------------------------------
    if ($filter_numresults !== 'all' && $totalPages > 1) {
        echo "<div class='filter-actions'>";
        $base_qs = $_GET;

        // Prev
        if ($pg > 1) {
            $prev_qs = $base_qs;
            $prev_qs['pg'] = $pg - 1;
            echo "<a class='btn-secondary' href='?".htmlspecialchars(http_build_query($prev_qs))."'>Previous</a> ";
        } else {
            echo "<span class='btn-secondary' style='opacity:.6;pointer-events:none;'>Previous</span> ";
        }

        // Next
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
  </div><!-- /.adminlib-wrapper -->
</main>