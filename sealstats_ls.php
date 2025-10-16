<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/themes/smoothness/jquery-ui.css">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js"></script>

<script>
$(document).ready(function() {
   $("#datepicker, #datepicker2, #datepickerl, #datepickerl2, #expdatepicker, #expdatepicker2, #top10datepicker, #top10datepicker2, #top10fdatepicker, #top10fdatepicker2").datepicker();
});
</script>

<?php
// sealstats_ls.php — SEAL statistics page for System Staff

// --------------------------------------------------
// WordPress Access Control
// --------------------------------------------------
require_once('/var/www/wpSEAL/wp-load.php');
$current_user = wp_get_current_user();
$user_roles = (array) $current_user->roles;

if (!in_array('administrator', $user_roles, true) && !in_array('lib-systems-staff', $user_roles, true)) {
    die("<div style='padding:20px;color:red;font-weight:bold;'>Access Denied<br>
         You must have the <b>Lib Systems Staff</b> role to access this page.</div>");
}

// Get user's assigned system
$field_home_library_system = get_user_meta($current_user->ID, 'home_system', true);
if (empty($field_home_library_system)) {
    die("<div style='padding:20px;color:red;font-weight:bold;'>Your account does not have a Home Library System assigned.</div>");
}

// --------------------------------------------------
// Load Shared Functions + Database Connection
// --------------------------------------------------
require '/var/www/seal_wp_script/seal_function.php';
require '/var/www/seal_wp_script/seal_db.inc';

$db = mysqli_connect($dbhost, $dbuser, $dbpass);
mysqli_select_db($db, $dbname);

// --------------------------------------------------
// Existing logic begins here (unchanged)
// --------------------------------------------------

if (($_SERVER['REQUEST_METHOD'] == 'POST') || (isset($_GET['page']))) {
    if ($_REQUEST['stattype'] == 'wholesystem') {
        // === WHOLE SYSTEM BORROWING ===
        $startdated = $_REQUEST["startdate"];
        $enddated = $_REQUEST["enddate"];
        $libsystem = $_REQUEST["system"];
        $startdate = date('Y-m-d H:i:s', strtotime(str_replace('-', '/', $startdated)));
        $enddate = date('Y-m-d H:i:s', strtotime(str_replace('-', '/', $enddated)));

        if ($libsystem == 'none') {
            echo "<h1 style='color:red;'>Please choose a system first to run stats</h1><br><br>";
        } else {
            // --- Get borrowing totals ---
            $GETREQUESTCOUNTSQLL = "SELECT * FROM `$sealSTAT`
                WHERE `ReqSystem` LIKE '%$libsystem%'
                AND `Timestamp` >= '$startdate 00:00:00'
                AND `Timestamp` <= '$enddate 00:00:00'";
            $retval = mysqli_query($db, $GETREQUESTCOUNTSQLL);

            if (!$retval) {
                die('Error: ' . mysqli_error($db));
            } elseif (mysqli_num_rows($retval) == 0) {
                echo "No results found.";
            } else {
                $row_cnt = mysqli_num_rows($retval);

                // Filled
                $FINDFILL = "SELECT * FROM `$sealSTAT`
                    WHERE `ReqSystem` LIKE '%$libsystem%' AND Fill = 1
                    AND `Timestamp` BETWEEN '$startdate 00:00:00' AND '$enddate 00:00:00'";
                $row_fill = mysqli_num_rows(mysqli_query($db, $FINDFILL));

                // Not Filled
                $FINDNOTFILL = "SELECT * FROM `$sealSTAT`
                    WHERE `ReqSystem` LIKE '%$libsystem%' AND Fill = 0
                    AND `Timestamp` BETWEEN '$startdate 00:00:00' AND '$enddate 00:00:00'";
                $row_notfill = mysqli_num_rows(mysqli_query($db, $FINDNOTFILL));

                // Expired
                $FINDEXPIRE = "SELECT * FROM `$sealSTAT`
                    WHERE `ReqSystem` LIKE '%$libsystem%' AND Fill = 4
                    AND `Timestamp` BETWEEN '$startdate 00:00:00' AND '$enddate 00:00:00'";
                $row_expire = mysqli_num_rows(mysqli_query($db, $FINDEXPIRE));

                // Not Answered
                $FINDNOANSW = "SELECT * FROM `$sealSTAT`
                    WHERE `ReqSystem` LIKE '%$libsystem%' AND Fill = 3
                    AND `Timestamp` BETWEEN '$startdate 00:00:00' AND '$enddate 00:00:00'";
                $row_noansw = mysqli_num_rows(mysqli_query($db, $FINDNOANSW));

                // Canceled
                $CANANSW = "SELECT * FROM `$sealSTAT`
                    WHERE `ReqSystem` LIKE '%$libsystem%' AND Fill = 6
                    AND `Timestamp` BETWEEN '$startdate 00:00:00' AND '$enddate 00:00:00'";
                $row_cancel = mysqli_num_rows(mysqli_query($db, $CANANSW));

                // Percentages
                $percent = function($a, $b) { return number_format(($b > 0 ? $a / $b : 0) * 100, 2) . '%'; };

                echo "<h1><center>SEAL Borrowing Stats from $startdated to $enddated</center></h1>";
                echo "<h2>Library System: $libsystem</h2>";
                echo "Total Requests: $row_cnt<br>";
                echo "Filled: $row_fill (" . $percent($row_fill, $row_cnt) . ")<br>";
                echo "Not Filled: $row_notfill (" . $percent($row_notfill, $row_cnt) . ")<br>";
                echo "Expired: $row_expire (" . $percent($row_expire, $row_cnt) . ")<br>";
                echo "Canceled: $row_cancel (" . $percent($row_cancel, $row_cnt) . ")<br>";
                echo "Not Answered: $row_noansw (" . $percent($row_noansw, $row_cnt) . ")<br><br>";
            }
        }
    }

    // Other stats types (lending, expirestats, top10stats, etc.)
    // remain the same — you can paste your original logic here
    // -------------------------------------------------------------
} else {
?>
<h3>Borrowing statistics for <?php echo $field_home_library_system; ?>:</h3>
<form action="/sealstats_ls" method="post">
    Start Date: <input id="datepicker" name="startdate">
    End Date: <input id="datepicker2" name="enddate">
    <input type="hidden" name="system" value="<?php echo $field_home_library_system; ?>">
    <input type="hidden" name="stattype" value="wholesystem">
    <input type="submit" value="Submit">
</form>
<hr>

<h3>Lending statistics for <?php echo $field_home_library_system; ?>:</h3>
<form action="/sealstats_ls" method="post">
    Start Date: <input id="datepickerl" name="startdate">
    End Date: <input id="datepickerl2" name="enddate">
    <input type="hidden" name="system" value="<?php echo $field_home_library_system; ?>">
    <input type="hidden" name="stattype" value="wholesystemlending">
    <input type="submit" value="Submit">
</form>
<hr>

<form action="/sealstats_ls" method="post">
    <h3>List of expired requests:</h3>
    Start Date: <input id="expdatepicker" name="startdate">
    End Date: <input id="expdatepicker2" name="enddate">
    <input type="hidden" name="stattype" value="expirestats">
    <input type="submit" value="Submit">
</form>
<hr>

<form action="/sealstats_ls" method="post">
    <h3>List of top 10 borrowing libraries:</h3>
    Start Date: <input id="top10datepicker" name="startdate">
    End Date: <input id="top10datepicker2" name="enddate">
    <input type="hidden" name="stattype" value="top10stats">
    <input type="submit" value="Submit">
</form>
<hr>

<form action="/sealstats_ls" method="post">
    <h3>List of top 10 lending libraries:</h3>
    Start Date: <input id="top10fdatepicker" name="startdate">
    End Date: <input id="top10fdatepicker2" name="enddate">
    <input type="hidden" name="stattype" value="top10fstats">
    <input type="submit" value="Submit">
</form>
<?php
}
?>