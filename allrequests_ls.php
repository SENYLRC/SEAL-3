<?php
// allrequests_ls.php — SE System Requests page (Borrower + Lender)

session_name('seal_admin_session');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

require '/var/www/seal_wp_script/seal_function.php';
require '/var/www/seal_wp_script/seal_db.inc';
require_once('/var/www/wpSEAL/wp-load.php');

// ----------------------------------------
// WordPress role check
// ----------------------------------------
$current_user = wp_get_current_user();
$user_roles = (array)$current_user->roles;
if (!in_array('administrator', $user_roles, true) && !in_array('libsys', $user_roles, true)) {
    die("<div style='padding:20px;color:red;font-weight:bold;'>Access Denied<br>You must have the <b>Library System Staff</b> role to access this page.</div>");
}

$home_system = get_user_meta($current_user->ID, 'home_system', true);
if (empty($home_system)) {
    die("<div style='padding:20px;color:red;font-weight:bold;'>Your account does not have a Home Library System assigned.</div>");
}

// ----------------------------------------
// DB Connection
// ----------------------------------------
$db = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$db) {
    die("<div style='color:red;'>DB connection failed: " . mysqli_connect_error() . "</div>");
}
mysqli_set_charset($db, 'utf8mb4');


// ----------------------------------------
// Filters
// ----------------------------------------
$filter_startdate = $_GET['filter_startdate'] ?? "2023-05-01";
$filter_enddate   = $_GET['filter_enddate'] ?? date("Y-m-d");
$filter_ill       = trim($_GET['filter_ill'] ?? "");
$filter_title     = trim($_GET['filter_title'] ?? "");
$filter_lender    = trim($_GET['filter_lender'] ?? "");
$filter_status    = trim($_GET['filter_status'] ?? "");

// escape inputs
$esc_home  = mysqli_real_escape_string($db, $home_system);
$esc_sd    = mysqli_real_escape_string($db, $filter_startdate);
$esc_ed    = mysqli_real_escape_string($db, $filter_enddate);
$esc_ill   = mysqli_real_escape_string($db, $filter_ill);
$esc_title = mysqli_real_escape_string($db, $filter_title);
$esc_lender = mysqli_real_escape_string($db, $filter_lender);
$esc_status = mysqli_real_escape_string($db, $filter_status);

// ----------------------------------------
// Build Query with library name translation
// ----------------------------------------
$sql = "SELECT s.*, DATE_FORMAT(s.`Timestamp`, '%Y/%m/%d') AS ts_fmt,
               lender.`Name` AS lender_name,
               borrower.`Name` AS borrower_name
        FROM `$sealSTAT` s
        LEFT JOIN `$sealLIB` lender ON s.`Destination` = lender.`LOC`
        LEFT JOIN `$sealLIB` borrower ON s.`Requester lib` = borrower.`LOC`
        WHERE (s.`ReqSystem`='$esc_home' OR s.`DestSystem`='$esc_home')
        AND DATE(s.`Timestamp`) BETWEEN '$esc_sd' AND '$esc_ed'";
//for debuging
//echo "<pre style='background:#fff;padding:10px;border:1px solid #ccc;'>$sql</pre>";


if ($esc_ill !== '') {
    $sql .= " AND s.`illNUB` LIKE '%$esc_ill%'";
}
if ($esc_title !== '') {
    $sql .= " AND s.`Title` LIKE '%$esc_title%'";
}
if ($esc_lender !== '') {
    $sql .= " AND (lender.`Name` LIKE '%$esc_lender%' OR s.`Destination` LIKE '%$esc_lender%')";
}
if ($esc_status !== '') {
    if ($esc_status === 'filled') {
        $sql .= " AND s.`Fill`=1";
    } elseif ($esc_status === 'notfilled') {
        $sql .= " AND s.`Fill`=0";
    } elseif ($esc_status === 'pending') {
        $sql .= " AND (s.`Fill` IS NULL OR s.`Fill`=3)";
    } elseif ($esc_status === 'expired') {
        $sql .= " AND (s.`emailsent` = 3 OR LOWER(COALESCE(s.`responderNOTE`, '')) LIKE '%expire%')";
    }
}


$sql .= " ORDER BY s.`Timestamp` DESC LIMIT 0, 500";

$GETLIST = mysqli_query($db, $sql);
$totalResults = $GETLIST ? mysqli_num_rows($GETLIST) : 0;

// ----------------------------------------
// Shipping methods
// ----------------------------------------
$shipping_methods = [
    '' => 'Select Method',
    'Delivery Van' => 'Delivery Van',
    'USPS' => 'USPS',
    'UPS' => 'UPS',
    'FedEx' => 'FedEx',
    'Courier' => 'Courier',
    'Pickup' => 'Pickup'
];
?>
<link rel="stylesheet" href="https://seal.senylrc.org/assets/jquery-ui.css">
<script src="https://seal.senylrc.org/assets/jquery.min.js"></script>
<script src="https://seal.senylrc.org/assets/jquery-ui.min.js"></script>

<style>
/* ===== Filter Bar ===== */
.filter-bar {
  background: #f9fafc;
  border: 1px solid #d4dae0;
  border-radius: 10px;
  padding: 20px 25px;
  margin-bottom: 25px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.08);
}
.filter-bar form {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px 18px;
  align-items: center;
}
.filter-bar label {
  display: block;
  font-size: 0.9rem;
  font-weight: 600;
  color: #333;
  margin-bottom: 4px;
}
.filter-bar input[type="text"],
.filter-bar select {
  width: 100%;
  padding: 8px 10px;
  border: 1px solid #ccc;
  border-radius: 6px;
  font-size: 0.9rem;
  box-sizing: border-box;
}
.filter-actions {
  display: flex;
  gap: 8px;
  align-items: center;
  justify-content: flex-start;
  margin-top: 5px;
}
.filter-bar .seal-btn {
  background: #0d9488;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 8px 16px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}
.filter-bar .seal-btn:hover { background: #0f766e; }
.filter-bar .btn-secondary {
  background: #e5e7eb;
  color: #111;
  border: none;
  border-radius: 6px;
  padding: 8px 16px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}
.filter-bar .btn-secondary:hover { background: #d1d5db; }

/* ===== Table ===== */
.rh-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
  overflow: hidden;
  margin-top: 15px;
  font-size: 0.9rem;
}
.rh-table thead th {
  background: #0d9488;
  color: #fff;
  padding: 10px 12px;
  text-align: left;
  font-weight: 600;
  border-bottom: 2px solid #0b8077;
}
.rh-table tbody td {
  padding: 10px 12px;
  border-bottom: 1px solid #e5e7eb;
  vertical-align: top;
}
.rh-table tbody tr:hover { background: #f9fafb; }
.rh-table tbody tr:last-child td { border-bottom: none; }

/* ===== Pagination ===== */
.pagination-info {
  text-align: right;
  font-size: 0.9rem;
  color: #555;
  margin: 10px 0;
}

/* ===== Banners ===== */
.status-banner { margin:10px 0; padding:10px; border-radius:6px; font-size:0.9rem; }
.status-banner.success { background:#d1fae5; color:#065f46; }
.status-banner.warn { background:#fee2e2; color:#7f1d1d; }
.status-banner.error { background:#fef2f2; color:#7f1d1d; }

/* Responsive */
@media (max-width: 700px) {
  .filter-bar form { grid-template-columns: 1fr; }
  .rh-table thead { display: none; }
  .rh-table tbody td { display: block; width: 100%; }
}
</style>

<main id="content">
  <div class="adminlib-wrapper">
    <h3><?php echo strtoupper(htmlspecialchars($home_system)); ?> System Requests</h3>

    <!-- Filter Bar -->
    <form method="get" class="filter-bar">
      <label>Start Date:<input type="text" id="filter_startdate" name="filter_startdate" value="<?php echo htmlspecialchars($filter_startdate); ?>"></label>
      <label>End Date:<input type="text" id="filter_enddate" name="filter_enddate" value="<?php echo htmlspecialchars($filter_enddate); ?>"></label>
      <label>ILL #:<input type="text" name="filter_ill" value="<?php echo htmlspecialchars($filter_ill); ?>"></label>
      <label>Title:<input type="text" name="filter_title" value="<?php echo htmlspecialchars($filter_title); ?>"></label>
      <label>Lender:<input type="text" name="filter_lender" value="<?php echo htmlspecialchars($filter_lender); ?>"></label>
      <label>Status:
     <select name="filter_status">
  <option value="">Any</option>
  <option value="filled" <?php if ($filter_status === 'filled') {
      echo 'selected';
  } ?>>Filled</option>
  <option value="notfilled" <?php if ($filter_status === 'notfilled') {
      echo 'selected';
  } ?>>Not Filled</option>
  <option value="pending" <?php if ($filter_status === 'pending') {
      echo 'selected';
  } ?>>Pending</option>
  <option value="expired" <?php if ($filter_status === 'expired') {
      echo 'selected';
  } ?>>Expired</option>
</select>

      </label>
      <div class="filter-actions">
        <button type="submit" class="seal-btn">Search</button>
<a href="/allrequests_ls/" class="btn-secondary">Reset</a>

      </div>
    </form>

    <script>
    jQuery(function($){
      $("#filter_startdate,#filter_enddate").datepicker({ dateFormat:"yy-mm-dd" });
    });
    </script>

    <?php
    if ($totalResults == 0) {
        echo "<div class='status-banner warn'>No results found for given criteria.</div>";
    } else {
        echo "<div class='pagination-info'>".number_format($totalResults)." total results</div>";
        echo "<table class='rh-table'>";
        echo "<thead><tr>
                <th>ILL #</th>
                <th>Title</th>
                <th>Need By</th>
                <th>Lender</th>
                <th>Borrower</th>
                <th>Due / Ship</th>
                <th>Status</th>
              </tr></thead><tbody>";

        while ($r = mysqli_fetch_assoc($GETLIST)) {
            $ill      = htmlspecialchars($r['illNUB']);
            $title    = htmlspecialchars($r['Title']);
            $author   = htmlspecialchars($r['Author']);
            $needby   = htmlspecialchars($r['needbydate']);
            $lender   = htmlspecialchars($r['lender_name'] ?: $r['Destination']);
            $borrower = htmlspecialchars($r['borrower_name'] ?: $r['Requester lib']);
            $due      = htmlspecialchars($r['DueDate']);
            $ship     = htmlspecialchars($r['shipMethod']);
            $fill     = $r['Fill'];

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

            echo "<tr>
                    <td>$ill</td>
                    <td>$title<br><i>$author</i></td>
                    <td>$needby</td>
                    <td>$lender</td>
                    <td>$borrower</td>
                    <td>$due<br>$ship</td>
                    <td>";
            echo "<div class='status-text'>$status</div>";
            echo "<div class='shiptxt'>" . htmlspecialchars($ship) . "</div>";

            $emailsent = (int)($r['emailsent'] ?? 0);
            $note = strtolower($r['responderNOTE'] ?? '');
            $fill = (int)($r['Fill'] ?? 0);

            // 🟢 Filled
            if ($fill === 1) {
                echo "<div class='status-badge filled' title='Request filled successfully'>✅ Filled</div>";
            } elseif ($fill === 0) {
                echo "<div class='status-badge notfilled' title='Request not filled'>❌ Not Filled</div>";
                // 🟡 Reminder Sent
            } elseif ($emailsent === 2 || str_contains($note, 'reminder')) {
                echo "<div class='status-badge reminder' title='3-Day Reminder sent automatically'>⚠️ Reminder Sent</div>";
            }
            // 🔴 Expired
            elseif ($emailsent === 3 || str_contains($note, 'expire')) {
                echo "<div class='status-badge expired' title='5-Day Expired automatically'>⏰ Expired</div>";
            }
            // Display ILLiad Transaction ID
            if (!empty($r['IlliadTransID'])) {
                echo "<div class='illiad-id'><b>ILLiad ID:</b> "
                   . htmlspecialchars($r['IlliadTransID']) . "</div>";
            }
            // --------------------------
            // Notes under the request row
            // Exclude Patron Note
            // Include Reason Not Filled (nofillreason -> text)
            // --------------------------

            // Map no-fill reason codes
            $nofill_map = [
                '20' => 'In Use',
                '21' => 'Lost',
                '22' => 'Non-Circulating',
                '23' => 'Not on shelf',
                '24' => 'Poor condition',
                '25' => 'Too New',
'26' => 'Not owned',
'27' => 'Not found as cited',
            ];

            // Pull notes (trim)
            $reqnote         = trim((string)($r["reqnote"] ?? ''));
            $lendnote        = trim((string)($r["responderNOTE"] ?? ''));
            $returnnote      = trim((string)($r["returnNote"] ?? ''));
            $returnmethod    = trim((string)($r["returnmethod"] ?? ''));
            $renewNote       = trim((string)($r["renewNote"] ?? ''));
            $renewNoteLender = trim((string)($r["renewNoteLender"] ?? ''));

            // No-fill reason from DB (code stored)
            $nofillreason = trim((string)($r["nofillreason"] ?? ''));
            $reasontxt    = $nofill_map[$nofillreason] ?? '';

            // Build labeled list (NO patronnote)
            $notes = [];
            if ($reqnote !== '') {
                $notes['Request Note'] = $reqnote;
            }
            if ($lendnote !== '') {
                $notes['Lender Note'] = $lendnote;
            }
            if ($returnnote !== '') {
                $notes['Return Note'] = $returnnote;
            }
            if ($returnmethod !== '') {
                $notes['Return Method'] = $returnmethod;
            }
            if ($renewNote !== '') {
                $notes['Renew Note (Borrower)'] = $renewNote;
            }
            if ($renewNoteLender !== '') {
                $notes['Renew Note (Lender)'] = $renewNoteLender;
            }

            // Only show reason if it exists (optionally only for Fill=0)
            if ($reasontxt !== '') {
                $notes['Reason Not Filled'] = $reasontxt;
            }

            echo "</td>

                  </tr>";



            // If notes exist, print a full-width sub-row underneath
            if (!empty($notes)) {
                $colspan = 7; // your table has 7 columns (ILL, Title, Need By, Lender, Borrower, Due/Ship, Status)

                echo "<tr class='rh-subrow rh-notes-row'><td colspan='{$colspan}'>";
                echo "<div class='rh-subwrap'>";
                echo "<div class='rh-subtitle'>Notes <span class='rh-muted'>(" . count($notes) . ")</span></div>";
                echo "<div class='rh-notes-grid'>";

                foreach ($notes as $label => $val) {
                    $extraClass = ($label === 'Reason Not Filled') ? ' rh-note-warning' : '';
                    echo "<div class='rh-note-item{$extraClass}'>";
                    echo "<div class='rh-note-label'>" . htmlspecialchars($label) . "</div>";
                    echo "<div class='rh-note-text'>" . nl2br(htmlspecialchars($val)) . "</div>";
                    echo "</div>";
                }

                echo "</div></div>";
                echo "</td></tr>";
            }

        }
        echo "</tbody></table>";
    }
?>
  </div>
</main>