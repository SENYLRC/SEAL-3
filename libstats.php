<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/themes/smoothness/jquery-ui.css">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js"></script>

<style>
/* Screen-reader-only utility (no visible change) */
.screen-reader-text{
  position:absolute!important;
  width:1px;height:1px;
  padding:0;margin:-1px;
  overflow:hidden;
  clip:rect(0,0,0,0);
  white-space:nowrap;border:0;
}
</style>

<div id="sr-status" class="screen-reader-text" aria-live="polite" aria-atomic="true"></div>

<script>
jQuery(function($) {
  // Keep your datepicker behavior the same, just add SR-friendly attributes
  $("#datepicker").attr({
    "autocomplete":"off",
    "inputmode":"numeric",
    "aria-describedby":"startdate-help",
    "aria-label":"Start date, format MM slash DD slash YYYY"
  }).datepicker();

  $("#datepicker2").attr({
    "autocomplete":"off",
    "inputmode":"numeric",
    "aria-describedby":"enddate-help",
    "aria-label":"End date, format MM slash DD slash YYYY"
  }).datepicker();

  function announce(msg){
    var sr = document.getElementById('sr-status');
    if(!sr) return;
    sr.textContent = '';
    setTimeout(function(){ sr.textContent = msg; }, 20);
  }

  $("#datepicker").on("change", function(){ announce("Start date set to " + this.value); });
  $("#datepicker2").on("change", function(){ announce("End date set to " + this.value); });
});
</script>

<?php
// ==========================================================
// Load WordPress & Restrict to Administrator or Library Staff
// ==========================================================
require_once('/var/www/wpSEAL/wp-load.php');

if (!is_user_logged_in()) {
    die("<div style='padding:20px;color:red;font-weight:bold;' role='alert' aria-live='assertive'>
        Access Denied<br>You must be logged in to view this page.
    </div>");
}

$current_user = wp_get_current_user();
$primary_loc = strtoupper(trim(get_user_meta($current_user->ID, 'address_loc_code', true) ?? ''));

$extra_locs_raw = get_user_meta($current_user->ID, 'seal_extra_locs', true);
$extra_locs_raw = is_string($extra_locs_raw) ? trim($extra_locs_raw) : '';

$extra_locs = [];
if ($extra_locs_raw !== '') {
    foreach (explode(',', $extra_locs_raw) as $c) {
        $c = strtoupper(trim($c));
        if ($c !== '') $extra_locs[] = $c;
    }
}

$all_locs  = array_values(array_unique(array_filter(array_merge([$primary_loc], $extra_locs))));
$has_multi = (count($all_locs) > 1);
$user_roles   = (array)$current_user->roles;

// Only allow Administrator or Library Staff roles
if (!array_intersect(['administrator', 'libstaff'], $user_roles)) {
    die("<div style='padding:20px;color:red;font-weight:bold;' role='alert' aria-live='assertive'>
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


// ==========================================================
// Build LOC => Library Name map (for dropdown labels)
// ==========================================================
$loc_name_map = [];

if (!empty($all_locs)) {
    $in = [];
    foreach ($all_locs as $c) {
        $c = strtoupper(trim((string)$c));
        if ($c !== '') {
            $in[] = "'" . mysqli_real_escape_string($db, $c) . "'";
        }
    }

    if (!empty($in)) {
        $sql_names = "SELECT `loc`, `Name` FROM `$sealLIB` WHERE `loc` IN (" . implode(',', $in) . ")";
        $res_names = mysqli_query($db, $sql_names);
        if ($res_names) {
            while ($rr = mysqli_fetch_assoc($res_names)) {
                $k = strtoupper(trim((string)($rr['loc'] ?? '')));
                $v = trim((string)($rr['Name'] ?? ''));
                if ($k !== '' && $v !== '') {
                    $loc_name_map[$k] = $v;
                }
            }
        }
    }
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

    // --------------------------------------------------
    // Library scope (primary + extra LOCs) + selection
    // --------------------------------------------------
    $primary_loc = get_user_meta($current_user->ID, 'address_loc_code', true) ?? '';
    $primary_loc = strtoupper(trim($primary_loc));

    $extra_locs_raw = get_user_meta($current_user->ID, 'seal_extra_locs', true);
    $extra_locs_raw = is_string($extra_locs_raw) ? trim($extra_locs_raw) : '';

    $extra_locs = [];
    if ($extra_locs_raw !== '') {
        foreach (explode(',', $extra_locs_raw) as $c) {
            $c = strtoupper(trim($c));
            if ($c !== '') $extra_locs[] = $c;
        }
    }

    $all_locs = array_values(array_unique(array_filter(array_merge([$primary_loc], $extra_locs))));
    $has_multi = (count($all_locs) > 1);

    // dropdown selection: 'all' or a LOC
    $filter_loc = $_REQUEST['filter_loc'] ?? '';
    if (!$has_multi) {
        $filter_loc = $all_locs[0] ?? $primary_loc;
    } else {
        if ($filter_loc === '') $filter_loc = 'all';
        if ($filter_loc !== 'all' && !in_array(strtoupper($filter_loc), $all_locs, true)) {
            $filter_loc = 'all';
        }
    }

    // Date validation regex (mm/dd/yyyy or mm-dd-yyyy)
    $reg = '~(0[1-9]|1[0-2])[-/](0[1-9]|[12][0-9]|3[01])[-/](19|20)\d\d~';

    if (!preg_match($reg, $startdated) || !preg_match($reg, $enddated)) {
        echo "<h1 style='color:red;' role='alert' aria-live='assertive'>Date must be in the format mm/dd/yyyy</h1>";
    } elseif (empty($primary_loc)) {
        echo "<h3 style='color:red;' role='alert' aria-live='assertive'>No library location code found for your account.</h3>";
    } else {
        // Sanitize + format
        $startdate = date('Y-m-d 00:00:00', strtotime(str_replace('-', '/', $startdated)));
        $enddate   = date('Y-m-d 23:59:59', strtotime(str_replace('-', '/', $enddated)));

        // Build WHERE clause for Requester LOC based on filter_loc
        if ($has_multi && $filter_loc === 'all') {
            $esc = [];
            foreach ($all_locs as $c) {
                $esc[] = "'" . mysqli_real_escape_string($db, $c) . "'";
            }
            $where_loc = "`Requester LOC` IN (" . implode(',', $esc) . ")";
        } else {
            $chosen = strtoupper($filter_loc ?: $primary_loc);
            $chosen = mysqli_real_escape_string($db, $chosen);
            $where_loc = "`Requester LOC` = '$chosen'";
        }

        // Build base query
        $GETREQUESTCOUNTSQL = "
            SELECT * FROM `$sealSTAT`
            WHERE $where_loc
            AND `Timestamp` BETWEEN '$startdate' AND '$enddate'";

        $retval = mysqli_query($db, $GETREQUESTCOUNTSQL);
        if (!$retval) {
            die('Error: ' . mysqli_error($db));
        }

        if (mysqli_num_rows($retval) === 0) {
            // Note: $loc wasn’t defined here in your snippet; keep text same but avoid undefined var.
            echo "<div style='color:red;font-weight:bold;' role='status' aria-live='polite'>No results found for your selected library.</div>";
        } else {
            $row_cnt = mysqli_num_rows($retval);

            // Helper function for counts
            function get_count($db, $sealSTAT, $where_loc, $startdate, $enddate, $fillVal) {
                $fillVal = (int)$fillVal;
                $sql = "SELECT COUNT(*) AS cnt FROM `$sealSTAT`
                        WHERE $where_loc
                        AND `Timestamp` BETWEEN '$startdate' AND '$enddate'
                        AND `Fill` = '$fillVal'";
                $r = mysqli_query($db, $sql);
                $d = mysqli_fetch_assoc($r);
                return (int)($d['cnt'] ?? 0);
            }

            $row_fill    = get_count($db, $sealSTAT, $where_loc, $startdate, $enddate, 1);
            $row_notfill = get_count($db, $sealSTAT, $where_loc, $startdate, $enddate, 0);
            $row_expire  = get_count($db, $sealSTAT, $where_loc, $startdate, $enddate, 4);
            $row_noansw  = get_count($db, $sealSTAT, $where_loc, $startdate, $enddate, 3);
            $row_cancel  = get_count($db, $sealSTAT, $where_loc, $startdate, $enddate, 6);

            // Calculate percentages safely
            $safe_pct = fn($num) => ($row_cnt > 0) ? number_format(($num / $row_cnt) * 100, 2) . '%' : '0%';
            $percent_friendly_fill    = $safe_pct($row_fill);
            $percent_friendly_notfill = $safe_pct($row_notfill);
            $percent_friendly_expire  = $safe_pct($row_expire);
            $percent_friendly_noansw  = $safe_pct($row_noansw);
            $percent_friendly_cancel  = $safe_pct($row_cancel);

            // Get library name
            if ($has_multi && $filter_loc === 'all') {
                $libname = "All My Libraries";
            } else {
                $chosen = strtoupper($filter_loc ?: $primary_loc);
                $chosen_esc = mysqli_real_escape_string($db, $chosen);
                $libnames = "SELECT Name FROM `$sealLIB` WHERE `LOC` = '$chosen_esc' LIMIT 1";
                $libnameq = mysqli_query($db, $libnames);
                $libname  = ($row = mysqli_fetch_assoc($libnameq)) ? $row["Name"] : $chosen;
            }

            // Output (unchanged visible text)
            echo "<h3>From " . htmlspecialchars($startdated) . " to " . htmlspecialchars($enddated) . "</h3>";
            echo "<h4>Borrower statistics for <b>" . htmlspecialchars($libname) . "</b></h4>";
            echo "<div role='status' aria-live='polite' aria-atomic='true'>";
            echo "Total Requests Placed: " . number_format($row_cnt) . "<br>";
            echo "Filled: $row_fill ($percent_friendly_fill)<br>";
            echo "Not Filled: $row_notfill ($percent_friendly_notfill)<br>";
            echo "Expired: $row_expire ($percent_friendly_expire)<br>";
            echo "Canceled: $row_cancel ($percent_friendly_cancel)<br>";
            echo "No Answer: $row_noansw ($percent_friendly_noansw)<br><br>";
            echo "</div>";

            // Breakdown by Destination System
            echo "<hr><h4>Breakdown by Destination System</h4>";
            $destsystem = "
                SELECT DISTINCT(`DestSystem`)
                FROM `$sealSTAT`
                WHERE $where_loc
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
                              WHERE $where_loc
                              AND `DestSystem` = '$dessysvar'
                              AND `Timestamp` BETWEEN '$startdate' AND '$enddate'";
                $destcountq = mysqli_query($db, $destcount);
                $destnum_rows = (int)(mysqli_fetch_assoc($destcountq)['cnt'] ?? 0);
                $percent_friendly_dest = $safe_pct($destnum_rows);

                echo "<strong>" . number_format($destnum_rows) . " ($percent_friendly_dest)</strong> requests sent to <strong>" . htmlspecialchars($dessysvartxt) . "</strong><br>";

                // Per item-type breakdown
                $destitype = "
                    SELECT DISTINCT(`Itype`)
                    FROM `$sealSTAT`
                    WHERE $where_loc
                    AND `DestSystem` = '$dessysvar'
                    AND `Timestamp` BETWEEN '$startdate' AND '$enddate'";
                $destitypeq = mysqli_query($db, $destitype);

                while ($row2 = mysqli_fetch_assoc($destitypeq)) {
                    $itype = trim($row2['Itype']);
                    if (empty($itype)) continue;

                    $destitemcount = "SELECT COUNT(*) AS cnt FROM `$sealSTAT`
                                      WHERE `Itype` = '$itype'
                                      AND $where_loc
                                      AND `DestSystem` = '$dessysvar'
                                      AND `Timestamp` BETWEEN '$startdate' AND '$enddate'";
                    $destitemcountq = mysqli_query($db, $destitemcount);
                    $destnumitype_rows = (int)(mysqli_fetch_assoc($destitemcountq)['cnt'] ?? 0);
                    if ($destnumitype_rows === 0) continue;

                    echo "&nbsp;&nbsp;• " . number_format($destnumitype_rows) . " requests for <strong>" . htmlspecialchars($itype) . "</strong><br>";

                    foreach ([1 => 'filled', 0 => 'not filled', 4 => 'expired', 6 => 'canceled', 3 => 'not answered'] as $fillVal => $desc) {
                        $countq = mysqli_query($db, "
                            SELECT COUNT(*) AS cnt FROM `$sealSTAT`
                            WHERE `Fill` = '$fillVal'
                            AND `Itype` = '$itype'
                            AND $where_loc
                            AND `DestSystem` = '$dessysvar'
                            AND `Timestamp` BETWEEN '$startdate' AND '$enddate'");
                        $num = (int)(mysqli_fetch_assoc($countq)['cnt'] ?? 0);
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
    // Show Date Selection Form (same visible display)
    // ==========================================================
    ?>
    <h2>Borrowing Statistics</h2>
    <h3>Enter your desired date range:</h3>

    <form action="" method="post" aria-labelledby="borrowstats-title">
        <span id="borrowstats-title" class="screen-reader-text">Borrowing statistics date range form</span>

        <?php if ($has_multi): ?>
            Library:
            <!-- add SR-only label + id (no visible change) -->
            <label class="screen-reader-text" for="filter_loc">Library</label>
            <select id="filter_loc" name="filter_loc" style="min-width:240px;">
                <option value="all">All My Libraries</option>
              <?php foreach ($all_locs as $code): ?>
  <?php
    $code_u = strtoupper(trim((string)$code));
    $name   = $loc_name_map[$code_u] ?? $code_u; // fallback if name missing
    $label  = $name . " (" . $code_u . ")";
  ?>
  <option value="<?php echo esc_attr($code_u); ?>">
      <?php echo esc_html($label); ?>
  </option>
<?php endforeach; ?>

            </select>
            <br><br>
        <?php else: ?>
            <input type="hidden" name="filter_loc" value="<?php echo esc_attr($all_locs[0] ?? ''); ?>">
        <?php endif; ?>

        <!-- Real labels added, hidden so display stays the same -->
        <label class="screen-reader-text" for="datepicker">Start Date</label>
        Start Date:
        <input
          id="datepicker"
          name="startdate"
          value="<?php echo date('m/d/Y', strtotime('-7 days')); ?>"
          required
          aria-required="true"
        >
        <span id="startdate-help" class="screen-reader-text">
          Enter a start date in MM slash DD slash YYYY format. A date picker is available.
        </span>

        <label class="screen-reader-text" for="datepicker2">End Date</label>
        End Date:
        <input
          id="datepicker2"
          name="enddate"
          value="<?php echo date('m/d/Y'); ?>"
          required
          aria-required="true"
        >
        <span id="enddate-help" class="screen-reader-text">
          Enter an end date in MM slash DD slash YYYY format. A date picker is available.
        </span>

        <br><br>
        <input type="submit" value="Submit">
    </form>
    <?php
}
?>