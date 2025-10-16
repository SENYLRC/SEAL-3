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
if (!in_array('administrator', $user_roles, true) && !in_array('lib-systems-staff', $user_roles, true)) {
    die("<div style='padding:20px;color:red;font-weight:bold;'>Access Denied<br>You must have the <b>Lib Systems Staff</b> role to access this page.</div>");
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

$sealSTAT = "SENYLRC-SEAL2-STATS"; // Make sure this matches your DB table name

// ----------------------------------------
// Filters
// ----------------------------------------
$filter_startdate = $_GET['filter_startdate'] ?? "2025-05-01";
$filter_enddate   = $_GET['filter_enddate'] ?? date("Y-m-d");

// ----------------------------------------
// Build Query
// ----------------------------------------
$esc_home = mysqli_real_escape_string($db, $home_system);
$esc_sd   = mysqli_real_escape_string($db, $filter_startdate);
$esc_ed   = mysqli_real_escape_string($db, $filter_enddate);

$sql = "SELECT *, DATE_FORMAT(`Timestamp`, '%Y/%m/%d') AS ts_fmt 
        FROM `$sealSTAT` 
        WHERE (`ReqSystem`='$esc_home' OR `DestSystem`='$esc_home')
        AND `Timestamp` >= '$esc_sd'
        ORDER BY `Timestamp` DESC
        LIMIT 0, 50";

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

// ----------------------------------------
// Handle inline actions
// ----------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_action'])) {
    $action = $_POST['do_action'];
    $recid  = (int)$_POST['recid'];
    $csrf   = $_POST['csrf'] ?? '';

    // CSRF basic check
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

    if ($csrf !== $_SESSION['csrf']) {
        echo "<div class='status-banner error'>Invalid security token.</div>";
    } else {
        if ($action === 'fill_save') {
            $due = mysqli_real_escape_string($db, $_POST['fill_due']);
            $ship = mysqli_real_escape_string($db, $_POST['fill_ship']);
            $sqlu = "UPDATE `$sealSTAT` 
                     SET `Fill`=1, `fillNofillDate`=CURDATE(), `DueDate`='$due', `shipMethod`='$ship' 
                     WHERE `index`=$recid LIMIT 1";
            mysqli_query($db, $sqlu);
            echo "<div class='status-banner success'>✅ Marked as Filled — Due: $due, Ship: $ship</div>";
        }
        elseif ($action === 'fill_no') {
            $sqlu = "UPDATE `$sealSTAT` SET `Fill`=0, `fillNofillDate`=CURDATE() WHERE `index`=$recid LIMIT 1";
            mysqli_query($db, $sqlu);
            echo "<div class='status-banner warn'>🚫 Marked as Not Filled</div>";
        }
        elseif ($action === 'checkin') {
            $sqlu = "UPDATE `$sealSTAT` SET `checkinAccount`='".$current_user->user_login."', `checkinTimeStamp`=NOW() WHERE `index`=$recid LIMIT 1";
            mysqli_query($db, $sqlu);
            echo "<div class='status-banner success'>📦 Item Checked Back In</div>";
        }
        elseif ($action === 'renew_approve') {
            $sqlu = "UPDATE `$sealSTAT` SET `renewAnswer`=1, `renewAccountLender`='".$current_user->user_login."', `renewTimeStamp`=NOW() WHERE `index`=$recid LIMIT 1";
            mysqli_query($db, $sqlu);
            echo "<div class='status-banner success'>🔁 Renewal Approved</div>";
        }
        elseif ($action === 'renew_deny') {
            $sqlu = "UPDATE `$sealSTAT` SET `renewAnswer`=2, `renewAccountLender`='".$current_user->user_login."', `renewTimeStamp`=NOW() WHERE `index`=$recid LIMIT 1";
            mysqli_query($db, $sqlu);
            echo "<div class='status-banner warn'>❌ Renewal Denied</div>";
        }
    }
}

// ----------------------------------------
// CSRF setup
// ----------------------------------------
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];
?>
<link rel="stylesheet" href="https://sealbeta.senylrc.org/assets/jquery-ui.css">
<script src="https://sealbeta.senylrc.org/assets/jquery.min.js"></script>
<script src="https://sealbeta.senylrc.org/assets/jquery-ui.min.js"></script>
<style>
.inline-editor { display:none; background:#f9f9f9; padding:10px; margin-top:6px; border:1px solid #ddd; border-radius:8px; }
.seal-btn { padding:6px 10px; border-radius:6px; border:none; cursor:pointer; font-weight:600; }
.seal-btn.success { background:#059669; color:#fff; }
.seal-btn.warn { background:#b91c1c; color:#fff; }
.seal-btn.secondary { background:#e5e7eb; color:#111; }
.status-banner { margin:10px 0; padding:10px; border-radius:6px; }
.status-banner.success { background:#d1fae5; color:#065f46; }
.status-banner.warn { background:#fee2e2; color:#7f1d1d; }
.status-banner.error { background:#fef2f2; color:#7f1d1d; }
</style>

<main id="content">
  <div class="adminlib-wrapper">
    <h3><?php echo strtoupper(htmlspecialchars($home_system)); ?> System Requests</h3>

    <?php
    if ($totalResults == 0) {
        echo "<div class='status-banner warn'>No results found.</div>";
    } else {
        echo "<div class='pagination-info'>".number_format($totalResults)." total results</div>";
        echo "<table class='rh-table' style='width:100%;border-collapse:collapse;'>";
        echo "<thead><tr>
                <th>ILL #</th>
                <th>Title</th>
                <th>Need By</th>
                <th>Lender</th>
                <th>Borrower</th>
                <th>Due/Ship</th>
                <th>Status</th>
                <th>Actions</th>
              </tr></thead><tbody>";

        while ($r = mysqli_fetch_assoc($GETLIST)) {
            $recid  = (int)$r['index'];
            $ill    = htmlspecialchars($r['illNUB']);
            $title  = htmlspecialchars($r['Title']);
            $author = htmlspecialchars($r['Author']);
            $needby = htmlspecialchars($r['needbydate']);
            $lender = htmlspecialchars($r['Destination']);
            $borrower = htmlspecialchars($r['Requester lib']);
            $due    = htmlspecialchars($r['DueDate']);
            $ship   = htmlspecialchars($r['shipMethod']);
            $fill   = $r['Fill'];

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
                    <td>$status</td>
                    <td>";

            if ($fill == 3 || $fill === '') {
                echo "<a href='#' class='seal-btn success open-fill-editor' data-recid='$recid'>Will Fill (set details)</a>
                      <form method='post' style='display:inline;'>
                        <input type='hidden' name='csrf' value='$csrf'>
                        <input type='hidden' name='recid' value='$recid'>
                        <button class='seal-btn warn' name='do_action' value='fill_no'>No, Can't Fill</button>
                      </form>
                      <div id='fill-editor-$recid' class='inline-editor'>
                        <form method='post'>
                          <input type='hidden' name='csrf' value='$csrf'>
                          <input type='hidden' name='recid' value='$recid'>
                          <label>Due Date 
                            <input type='text' id='fill-date-input-$recid' name='fill_due' placeholder='YYYY-MM-DD'>
                          </label>
                          <label>Ship Method 
                            <select name='fill_ship'>";
                foreach ($shipping_methods as $k => $v) {
                    echo "<option value='".htmlspecialchars($k)."'>".htmlspecialchars($v)."</option>";
                }
                echo "      </select></label>
                          <button class='seal-btn success' name='do_action' value='fill_save'>Save</button>
                          <a href='#' class='seal-btn secondary open-fill-editor' data-recid='$recid'>Cancel</a>
                        </form>
                      </div>";
            } else {
                echo "<span class='small-note'>—</span>";
            }

            echo "</td></tr>";
        }
        echo "</tbody></table>";
    }
    ?>
  </div>
</main>

<script>
jQuery(function($){
  $(".open-fill-editor").on("click", function(e){
    e.preventDefault();
    var rec = $(this).data("recid");
    var editor = $("#fill-editor-"+rec);
    $(".inline-editor").not(editor).slideUp(); // close others
    editor.slideToggle(); // open this one
    $("#fill-date-input-"+rec).datepicker({ dateFormat:"yy-mm-dd" });
    $('html, body').animate({
        scrollTop: editor.offset().top - 100
    }, 300); // smooth scroll to form
  });
});
</script>