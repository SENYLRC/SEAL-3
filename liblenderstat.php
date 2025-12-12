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

if (!array_intersect(['administrator', 'libstaff'], $user_roles)) {
    die("<div style='padding:20px;color:red;font-weight:bold;'>
        Access Denied<br>You must have the <b>Administrator</b> or <b>Library Staff</b> role to access this page.
    </div>");
}

// ==========================================================
// Load SEAL functions + DB config
// ==========================================================
require '/var/www/seal_wp_script/seal_function.php';
require '/var/www/seal_wp_script/seal_db.inc';

// Connect
$db = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$db) {
    die("Database connection failed: " . mysqli_connect_error());
}
$db->set_charset("utf8mb4");

// ==========================================================
// Build LOC scope (primary + extra LOCs) for dropdown
// ==========================================================
$primary_loc = strtoupper(trim($field_loc_location_code ?? ''));
if ($primary_loc === '') {
    $primary_loc = strtoupper(trim(get_user_meta($current_user->ID, 'address_loc_code', true) ?? ''));
}

$extra_locs_raw = get_user_meta($current_user->ID, 'seal_extra_locs', true);
$extra_locs_raw = is_string($extra_locs_raw) ? trim($extra_locs_raw) : '';

$extra_locs = [];
if ($extra_locs_raw !== '') {
    foreach (preg_split('/[,\s;]+/', $extra_locs_raw) as $c) {
        $c = strtoupper(trim($c));
        if ($c !== '') $extra_locs[] = $c;
    }
}

$all_locs  = array_values(array_unique(array_filter(array_merge([$primary_loc], $extra_locs))));
$has_multi = (count($all_locs) > 1);

// Selection (POST sticky)
$filter_loc = $_REQUEST['filter_loc'] ?? '';
if (!$has_multi) {
    $filter_loc = $all_locs[0] ?? $primary_loc;
} else {
    if ($filter_loc === '') $filter_loc = $primary_loc; // default to primary
    $filter_loc = strtoupper(trim($filter_loc));
    if ($filter_loc !== 'ALL' && !in_array($filter_loc, $all_locs, true)) {
        $filter_loc = $primary_loc;
    }
}

// ==========================================================
// Handle POST
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $startdated = $_REQUEST["startdate"] ?? '';
    $enddated   = $_REQUEST["enddate"] ?? '';

    // Defaults
    if ($startdated === '') $startdated = date('m/d/Y', strtotime('-7 days'));
    if ($enddated === '')   $enddated   = date('m/d/Y');

    // Validate mm/dd/yyyy (or mm-dd-yyyy)
    $reg = '~(0[1-9]|1[0-2])[-/](0[1-9]|[12][0-9]|3[01])[-/](19|20)\d\d~';
    if ((!preg_match($reg, $startdated)) || (!preg_match($reg, $enddated))) {
        echo "<h1 style='color:red;'>Date is not in the correct format of mm/dd/yyyy</h1>";
    } elseif (empty($primary_loc)) {
        echo "<h3 style='color:red;'>No library location code found for your account.</h3>";
    } else {

        // Build timestamps (full day)
        $start_ts = date('Y-m-d 00:00:00', strtotime(str_replace('-', '/', $startdated)));
        $end_ts   = date('Y-m-d 23:59:59', strtotime(str_replace('-', '/', $enddated)));

        // Build Destination filter (operator/value style)
        if ($has_multi && $filter_loc === 'ALL') {
            $esc = [];
            foreach ($all_locs as $c) {
                $esc[] = "'" . mysqli_real_escape_string($db, $c) . "'";
            }
            $dest_where = "IN (" . implode(',', $esc) . ")";
            $loc_label  = "All My Libraries";
        } else {
            $chosen = mysqli_real_escape_string($db, $filter_loc ?: $primary_loc);
            $dest_where = "= '$chosen'";
            $loc_label  = $filter_loc ?: $primary_loc;
        }

        // Helper: COUNT(*) for fill buckets
        $count_fill = function($fillVal) use ($db, $sealSTAT, $dest_where, $start_ts, $end_ts) {
            $fillVal = (int)$fillVal;
            $sql = "SELECT COUNT(*) AS cnt
                    FROM `$sealSTAT`
                    WHERE `Destination` $dest_where
                    AND `Timestamp` BETWEEN '$start_ts' AND '$end_ts'
                    AND `Fill` = $fillVal";
            $q = mysqli_query($db, $sql);
            $r = $q ? mysqli_fetch_assoc($q) : null;
            return (int)($r['cnt'] ?? 0);
        };

        // Total
        $sql_total = "SELECT COUNT(*) AS cnt
                      FROM `$sealSTAT`
                      WHERE `Destination` $dest_where
                      AND `Timestamp` BETWEEN '$start_ts' AND '$end_ts'";
        $q_total = mysqli_query($db, $sql_total);
        if (!$q_total) die('Error: ' . mysqli_error($db));
        $row_cnt = (int)(mysqli_fetch_assoc($q_total)['cnt'] ?? 0);

        if ($row_cnt === 0) {
            echo "<div style='color:red;font-weight:bold;'>No results found for the selected library and date range.</div>";
        } else {

            $row_fill    = $count_fill(1);
            $row_notfill = $count_fill(0);
            $row_expire  = $count_fill(4);
            $row_noansw  = $count_fill(3);
            $row_cancel  = $count_fill(6);

            $safe_pct = fn($num) => ($row_cnt > 0) ? number_format(($num / $row_cnt) * 100, 2) . '%' : '0%';

            // Get library name
            if ($has_multi && $filter_loc === 'ALL') {
                $libname = "All My Libraries";
            } else {
                $loc_esc = mysqli_real_escape_string($db, $loc_label);
                $libnames = "SELECT Name FROM `$sealLIB` WHERE `LOC` = '$loc_esc' LIMIT 1";
                $libnameq = mysqli_query($db, $libnames);
                $libname  = ($row = mysqli_fetch_assoc($libnameq)) ? $row["Name"] : $loc_label;
            }

            echo "<h3>From " . htmlspecialchars($startdated) . " to " . htmlspecialchars($enddated) . "</h3>";
            echo "<h4>Lender request statistics for <b>" . htmlspecialchars($libname) . "</b></h4>";
            echo "Total Requests received: " . number_format($row_cnt) . "<br>";
            echo "Number of Requests Filled: " . number_format($row_fill) . " (" . $safe_pct($row_fill) . ")<br>";
            echo "Number of Requests Not Filled: " . number_format($row_notfill) . " (" . $safe_pct($row_notfill) . ")<br>";
            echo "Number of Requests Expired: " . number_format($row_expire) . " (" . $safe_pct($row_expire) . ")<br>";
            echo "Number of Requests Canceled: " . number_format($row_cancel) . " (" . $safe_pct($row_cancel) . ")<br>";
            echo "Number of Requests Not Answered Yet: " . number_format($row_noansw) . " (" . $safe_pct($row_noansw) . ")<br><br>";

            echo "<hr><h3>Break down of requests</h3>";

            // Requesting systems (ReqSystem) that sent to this Destination
            $reqsystem_sql = "SELECT DISTINCT(`ReqSystem`)
                              FROM `$sealSTAT`
                              WHERE `Destination` $dest_where
                              AND `Timestamp` BETWEEN '$start_ts' AND '$end_ts'";
            $reqsystemq = mysqli_query($db, $reqsystem_sql);
            if (!$reqsystemq) die('Error: ' . mysqli_error($db));

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

            while ($row = mysqli_fetch_assoc($reqsystemq)) {
                $reqsysvar = trim($row['ReqSystem'] ?? '');
                if ($reqsysvar === '') continue;

                $reqsysvartxt = $system_names[$reqsysvar] ?? 'SENYLRC Group';

                // Count total for this ReqSystem
                $req_count_sql = "SELECT COUNT(*) AS cnt
                                  FROM `$sealSTAT`
                                  WHERE `Destination` $dest_where
                                  AND `ReqSystem` = '" . mysqli_real_escape_string($db, $reqsysvar) . "'
                                  AND `Timestamp` BETWEEN '$start_ts' AND '$end_ts'";
                $req_count_q = mysqli_query($db, $req_count_sql);
                $reqnum_rows = (int)(mysqli_fetch_assoc($req_count_q)['cnt'] ?? 0);
                if ($reqnum_rows === 0) continue;

                $percent_friendly_reqnum = number_format(($reqnum_rows / $row_cnt) * 100, 2) . '%';
                echo number_format($reqnum_rows) . " ($percent_friendly_reqnum) overall requests were made from <strong>" . htmlspecialchars($reqsysvartxt) . "</strong><br>";

                // Itype list per ReqSystem
                $itype_sql = "SELECT DISTINCT(`Itype`)
                              FROM `$sealSTAT`
                              WHERE `Destination` $dest_where
                              AND `ReqSystem` = '" . mysqli_real_escape_string($db, $reqsysvar) . "'
                              AND `Timestamp` BETWEEN '$start_ts' AND '$end_ts'";
                $itypeq = mysqli_query($db, $itype_sql);

                while ($row2 = mysqli_fetch_assoc($itypeq)) {
                    $reqsysitype = trim($row2['Itype'] ?? '');
                    if ($reqsysitype === '') continue;

                    // Total for this itype within this ReqSystem
                    $itype_cnt_sql = "SELECT COUNT(*) AS cnt
                                      FROM `$sealSTAT`
                                      WHERE `Destination` $dest_where
                                      AND `ReqSystem` = '" . mysqli_real_escape_string($db, $reqsysvar) . "'
                                      AND `Itype` = '" . mysqli_real_escape_string($db, $reqsysitype) . "'
                                      AND `Timestamp` BETWEEN '$start_ts' AND '$end_ts'";
                    $itype_cnt_q = mysqli_query($db, $itype_cnt_sql);
                    $reqnumitype_rows = (int)(mysqli_fetch_assoc($itype_cnt_q)['cnt'] ?? 0);
                    if ($reqnumitype_rows === 0) continue;

                    $percent_friendly_typesys = number_format(($reqnumitype_rows / $reqnum_rows) * 100, 2) . '%';
                    echo "&nbsp;&nbsp;&nbsp;" . number_format($reqnumitype_rows) . " ($percent_friendly_typesys) of the requests from " . htmlspecialchars($reqsysvartxt) . " were <strong>" . htmlspecialchars($reqsysitype) . "</strong><br>";

                    // Fill bucket breakdown for this itype+ReqSystem
                    $bucket = function($fillVal) use ($db, $sealSTAT, $dest_where, $reqsysvar, $reqsysitype, $start_ts, $end_ts) {
                        $fillVal = (int)$fillVal;
                        $sql = "SELECT COUNT(*) AS cnt
                                FROM `$sealSTAT`
                                WHERE `Destination` $dest_where
                                AND `ReqSystem` = '" . mysqli_real_escape_string($db, $reqsysvar) . "'
                                AND `Itype` = '" . mysqli_real_escape_string($db, $reqsysitype) . "'
                                AND `Fill` = $fillVal
                                AND `Timestamp` BETWEEN '$start_ts' AND '$end_ts'";
                        $q = mysqli_query($db, $sql);
                        return (int)(mysqli_fetch_assoc($q)['cnt'] ?? 0);
                    };

                    $filled   = $bucket(1);
                    $unfilled = $bucket(0);
                    $expired  = $bucket(4);
                    $canceled = $bucket(6);
                    $noansw   = $bucket(3);

                    $pct = fn($n) => ($reqnumitype_rows > 0) ? number_format(($n / $reqnumitype_rows) * 100, 2) . '%' : '0%';

                    echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . number_format($filled)   . " (" . $pct($filled)   . ") were filled<br>";
                    echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . number_format($unfilled) . " (" . $pct($unfilled) . ") were not filled<br>";
                    echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . number_format($expired)  . " (" . $pct($expired)  . ") were expired<br>";
                    echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . number_format($canceled) . " (" . $pct($canceled) . ") were canceled<br>";
                    echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . number_format($noansw)   . " (" . $pct($noansw)   . ") were not answered<br><br>";
                }

                echo "<br><hr><br>";
            }
        }
    }

} else {
    // ==========================================================
    // Show form
    // ==========================================================
    ?>
    <h2>Lending Statistics</h2>
    <h3>Enter your desired date range:</h3>

    <form action="/liblenderstat?<?php echo htmlspecialchars($_SERVER['QUERY_STRING'] ?? '', ENT_QUOTES); ?>" method="post">

        <?php if ($has_multi): ?>
            Library:
            <select name="filter_loc" style="min-width:240px;">
                <option value="<?php echo esc_attr($primary_loc); ?>" <?php echo ($filter_loc === $primary_loc ? "selected" : ""); ?>>
                    Primary: <?php echo esc_html($primary_loc); ?>
                </option>

                <?php foreach ($extra_locs as $code): ?>
                    <option value="<?php echo esc_attr($code); ?>" <?php echo ($filter_loc === $code ? "selected" : ""); ?>>
                        <?php echo esc_html($code); ?>
                    </option>
                <?php endforeach; ?>

                <option value="ALL" <?php echo ($filter_loc === 'ALL' ? "selected" : ""); ?>>
                    All My Libraries
                </option>
            </select>
            <br><br>
        <?php else: ?>
            <input type="hidden" name="filter_loc" value="<?php echo esc_attr($primary_loc); ?>">
        <?php endif; ?>

        Start Date:
        <input id="datepicker" name="startdate" value="<?php echo date('m/d/Y', strtotime('-7 days')); ?>" required>
        End Date:
        <input id="datepicker2" name="enddate" value="<?php echo date('m/d/Y'); ?>" required>

        <br><br>
        <input type="submit" value="Submit">
    </form>
    <?php
}
?>
