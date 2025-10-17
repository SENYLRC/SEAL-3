<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/themes/smoothness/jquery-ui.css">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js"></script>

<script>
jQuery(function($) {
  $("#datepicker").datepicker();
  $("#datepicker2").datepicker();
});
</script>

<?php
// ==========================================================
// Load WordPress & Restrict to Administrator or Library Staff
// ==========================================================
require_once('/var/www/wpSEAL/wp-load.php');

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

// ==========================================================
// Load SEAL DB Config and Functions
// ==========================================================
require '/var/www/seal_wp_script/seal_function.php';
require '/var/www/seal_wp_script/seal_db.inc';

// Connect to database securely
$db = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$db) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Sanitize table names (from config)
$sealSTAT = mysqli_real_escape_string($db, $sealSTAT);
$sealLIB  = mysqli_real_escape_string($db, $sealLIB);

// ==========================================================
// Handle Form Submission
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $startdated = $_REQUEST['startdate'] ?? '';
    $enddated   = $_REQUEST['enddate'] ?? '';

    // Default to last 7 days if not provided
    if (empty($startdated)) $startdated = date('m/d/Y', strtotime('-7 days'));
    if (empty($enddated))   $enddated   = date('m/d/Y');

    // User’s library location
    $loc = get_user_meta($current_user->ID, 'address_loc_code', true) ?? '';
    $loc = trim($loc);

    // Date validation regex (mm/dd/yyyy or mm-dd-yyyy)
    $reg = '~(0[1-9]|1[0-2])[-/](0[1-9]|[12][0-9]|3[01])[-/](19|20)\d\d~';

    if (!preg_match($reg, $startdated) || !preg_match($reg, $enddated)) {
        echo "<h1 style='color:red;'>Date must be in the format mm/dd/yyyy</h1>";
    } elseif (empty($loc)) {
        echo "<h3 style='color:red;'>No library location code found for your account.</h3>";
    } else {
        // Sanitize + format
        $loc = mysqli_real_escape_string($db, $loc);
        $startdate = date('Y-m-d 00:00:00', strtotime(str_replace('-', '/', $startdated)));
        $enddate   = date('Y-m-d 23:59:59', strtotime(str_replace('-', '/', $enddated)));

        // Build base query
        $GETREQUESTCOUNTSQL = "
            SELECT * FROM `$sealSTAT`
            WHERE `Requester LOC` = '$loc'
            AND `Timestamp` BETWEEN '$startdate' AND '$enddate'";

        $retval = mysqli_query($db, $GETREQUESTCOUNTSQL);
        if (!$retval) {
            die('Error: ' . mysqli_error($db));
        }

        if (mysqli_num_rows($retval) === 0) {
            echo "<div style='color:red;font-weight:bold;'>No results found for $loc.</div>";
        } else {
            $row_cnt = mysqli_num_rows($retval);

            // Helper function for counts
            function get_count($db, $sealSTAT, $loc, $startdate, $enddate, $fillVal) {
                $fillVal = (int)$fillVal;
                $sql = "SELECT COUNT(*) AS cnt FROM `$sealSTAT`
                        WHERE `Requester LOC` = '$loc'
                        AND `Timestamp` BETWEEN '$startdate' AND '$enddate'
                        AND `Fill` = '$fillVal'";
                $r = mysqli_query($db, $sql);
                $d = mysqli_fetch_assoc($r);
                return (int)($d['cnt'] ?? 0);
            }

            $row_fill    = get_count($db, $sealSTAT, $loc, $startdate, $enddate, 1);
            $row_notfill = get_count($db, $sealSTAT, $loc, $startdate, $enddate, 0);
            $row_expire  = get_count($db, $sealSTAT, $loc, $startdate, $enddate, 4);
            $row_noansw  = get_count($db, $sealSTAT, $loc, $startdate, $enddate, 3);
            $row_cancel  = get_count($db, $sealSTAT, $loc, $startdate, $enddate, 6);

            // Calculate percentages safely
            $safe_pct = fn($num) => ($row_cnt > 0) ? number_format(($num / $row_cnt) * 100, 2) . '%' : '0%';
            $percent_friendly_fill    = $safe_pct($row_fill);
            $percent_friendly_notfill = $safe_pct($row_notfill);
            $percent_friendly_expire  = $safe_pct($row_expire);
            $percent_friendly_noansw  = $safe_pct($row_noansw);
            $percent_friendly_cancel  = $safe_pct($row_cancel);

            // Get library name
            $libnames = "SELECT Name FROM `$sealLIB` WHERE `LOC` = '$loc'";
            $libnameq = mysqli_query($db, $libnames);
            $libname  = ($row = mysqli_fetch_assoc($libnameq)) ? $row["Name"] : $loc;

            // Output
            echo "<h3>From " . htmlspecialchars($startdated) . " to " . htmlspecialchars($enddated) . "</h3>";
            echo "<h4>Borrower statistics for <b>" . htmlspecialchars($libname) . "</b></h4>";
            echo "Total Requests Placed: " . number_format($row_cnt) . "<br>";
            echo "Filled: $row_fill ($percent_friendly_fill)<br>";
            echo "Not Filled: $row_notfill ($percent_friendly_notfill)<br>";
            echo "Expired: $row_expire ($percent_friendly_expire)<br>";
            echo "Canceled: $row_cancel ($percent_friendly_cancel)<br>";
            echo "No Answer: $row_noansw ($percent_friendly_noansw)<br><br>";

            // Breakdown by Destination System
            echo "<hr><h4>Breakdown by Destination System</h4>";
            $destsystem = "
                SELECT DISTINCT(`DestSystem`)
                FROM `$sealSTAT`
                WHERE `Requester LOC` = '$loc'
                AND `Timestamp` BETWEEN '$startdate' AND '$enddate'";

            $destsystemq = mysqli_query($db, $destsystem);
            $system_names = [
                'MH' => 'Mid Hudson Library System',
                'RC' => 'Ramapo Catskill Library System',
                'DU' => 'Dutchess BOCES',
                'OU' => 'Orange Ulster BOCES',
                'RB' => 'Rockland BOCES',
                'SB' => 'Sullivan BOCES',
                'UB' => 'Ulster BOCES',
                'SE' => 'SENYLRC Group'
            ];

            while ($row = mysqli_fetch_assoc($destsystemq)) {
                $dessysvar = $row['DestSystem'];
                $dessysvartxt = $system_names[$dessysvar] ?? 'SENYLRC Group';

                $destcount = "SELECT COUNT(*) AS cnt FROM `$sealSTAT`
                              WHERE `Requester LOC` = '$loc'
                              AND `DestSystem` = '$dessysvar'
                              AND `Timestamp` BETWEEN '$startdate' AND '$enddate'";
                $destcountq = mysqli_query($db, $destcount);
                $destnum_rows = (int)mysqli_fetch_assoc($destcountq)['cnt'];
                $percent_friendly_dest = $safe_pct($destnum_rows);

                echo "<strong>" . number_format($destnum_rows) . " ($percent_friendly_dest)</strong> requests sent to <strong>" . htmlspecialchars($dessysvartxt) . "</strong><br>";

                // Per item-type breakdown
                $destitype = "
                    SELECT DISTINCT(`Itype`)
                    FROM `$sealSTAT`
                    WHERE `Requester LOC` = '$loc'
                    AND `DestSystem` = '$dessysvar'
                    AND `Timestamp` BETWEEN '$startdate' AND '$enddate'";
                $destitypeq = mysqli_query($db, $destitype);

                while ($row2 = mysqli_fetch_assoc($destitypeq)) {
                    $itype = trim($row2['Itype']);
                    if (empty($itype)) continue;

                    $destitemcount = "SELECT COUNT(*) AS cnt FROM `$sealSTAT`
                                      WHERE `Itype` = '$itype'
                                      AND `Requester LOC` = '$loc'
                                      AND `DestSystem` = '$dessysvar'
                                      AND `Timestamp` BETWEEN '$startdate' AND '$enddate'";
                    $destitemcountq = mysqli_query($db, $destitemcount);
                    $destnumitype_rows = (int)mysqli_fetch_assoc($destitemcountq)['cnt'];
                    if ($destnumitype_rows === 0) continue;

                    echo "&nbsp;&nbsp;• " . number_format($destnumitype_rows) . " requests for <strong>" . htmlspecialchars($itype) . "</strong><br>";

                    foreach ([1 => 'filled', 0 => 'not filled', 4 => 'expired', 6 => 'canceled', 3 => 'not answered'] as $fillVal => $desc) {
                        $countq = mysqli_query($db, "
                            SELECT COUNT(*) AS cnt FROM `$sealSTAT`
                            WHERE `Fill` = '$fillVal'
                            AND `Itype` = '$itype'
                            AND `Requester LOC` = '$loc'
                            AND `DestSystem` = '$dessysvar'
                            AND `Timestamp` BETWEEN '$startdate' AND '$enddate'");
                        $num = (int)mysqli_fetch_assoc($countq)['cnt'];
                        $percent = ($destnumitype_rows > 0)
                            ? number_format(($num / $destnumitype_rows) * 100, 2) . '%'
                            : '0%';
                        echo "&nbsp;&nbsp;&nbsp;&nbsp;– " . number_format($num) . " ($percent) were $desc<br>";
                    }
                    echo "<br>";
                }
                echo "<hr>";
            }
        }
    }
} else {
    // ==========================================================
    // Show Date Selection Form
    // ==========================================================
    ?>
    <h2>Borrowing Statistics</h2>
    <h3>Enter your desired date range:</h3>
    <form action="" method="post">
        Start Date: <input id="datepicker" name="startdate" value="<?php echo date('m/d/Y', strtotime('-7 days')); ?>" required>
        End Date: <input id="datepicker2" name="enddate" value="<?php echo date('m/d/Y'); ?>" required>
        <br><br>
        <input type="submit" value="Submit">
    </form>
    <?php
}
?>