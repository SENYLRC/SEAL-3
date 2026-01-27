<?php
/**
 * SEAL Admin Stats Console (admins only)
 * - Borrowing stats by system
 * - Lending stats by system
 * - Borrowing stats by specific library (LOC)
 * - Lending stats by specific library (LOC)
 * - Expired list
 * - Top 10 requesters
 * - Top 10 fillers
 */

require_once('/var/www/wpSEAL/wp-load.php');

// ---- CSRF (WordPress nonce; no PHP sessions) ----
$csrf = wp_create_nonce('seal_stats_console');

// ---- Admin gate ----
if (!is_user_logged_in() || !current_user_can('administrator')) {
    http_response_code(403);
    die("<div style='padding:20px;color:red;font-weight:bold;'>Access Denied<br>Admins only.</div>");
}

// ---- DB ----
require '/var/www/seal_wp_script/seal_function.php';
require '/var/www/seal_wp_script/seal_db.inc';

$db = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$db) {
    die("DB connection failed: " . htmlspecialchars(mysqli_connect_error()));
}
mysqli_set_charset($db, 'utf8mb4');


// ---- Helpers ----
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function require_csrf()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $posted = $_POST['csrf'] ?? '';
        if (!$posted || !wp_verify_nonce($posted, 'seal_stats_console')) {
            http_response_code(400);
            die("<div style='padding:20px;color:red;font-weight:bold;'>Bad request (CSRF).</div>");
        }
    }
}

function normalize_date($v)
{
    // expects yyyy-mm-dd from datepicker
    $v = trim((string)$v);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        return null;
    }
    return $v;
}

function system_whitelist($v)
{
    $allowed = ['DU','MH','OU','RC','RB','SE','SB','UB'];
    return in_array($v, $allowed, true) ? $v : null;
}

function loc_whitelist($v)
{
    // adjust if LOC format differs; this keeps it safe
    $v = trim((string)$v);
    return preg_match('/^[A-Za-z0-9_-]{1,30}$/', $v) ? $v : null;
}

// ---- Load libraries for dropdowns ----
$libs = [];
$res = mysqli_query($db, "SELECT loc, Name FROM `$sealLIB` WHERE participant=1 ORDER BY Name");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $libs[] = $row;
    }
}

// ---- Handle actions ----
$action = $_POST['action'] ?? '';
$output_html = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $start = normalize_date($_POST['startdate'] ?? '');
    $end   = normalize_date($_POST['enddate'] ?? '');

    if (!$start || !$end) {
        $output_html = "<div style='padding:10px;border:1px solid #c00;color:#c00;'>Please enter valid Start and End dates (YYYY-MM-DD).</div>";
    } else {
        // Use full-day inclusive end by adding 1 day and using < end+1day
        $start_ts = $start . " 00:00:00";
        $end_next = date('Y-m-d', strtotime($end . ' +1 day')) . " 00:00:00";

        // ---- Action routing ----
        switch ($action) {

            case 'borrow_system': {
                $system = $_POST['system'] ?? '';
                $system = $system === '' ? '' : system_whitelist($system);
                if ($system === null) {
                    $output_html = "<div style='color:#c00;'>Select a valid system.</div>";
                    break;
                }

                // --- Summary (exclude canceled: Fill <> 6) ---
                if ($system === '') {
                    $sql = "
            SELECT
              COUNT(*) AS total,
              SUM(CASE WHEN Fill=1 THEN 1 ELSE 0 END) AS filled,
              SUM(CASE WHEN Fill=0 THEN 1 ELSE 0 END) AS notfilled,
              SUM(CASE WHEN Fill=4 THEN 1 ELSE 0 END) AS expired,
              SUM(CASE WHEN Fill=3 THEN 1 ELSE 0 END) AS notanswered
            FROM `$sealSTAT`
            WHERE Fill <> 6
              AND `Timestamp` >= ? AND `Timestamp` < ?
        ";
                    $stmt = mysqli_prepare($db, $sql);
                    mysqli_stmt_bind_param($stmt, 'ss', $start_ts, $end_next);
                } else {
                    $sql = "
            SELECT
              COUNT(*) AS total,
              SUM(CASE WHEN Fill=1 THEN 1 ELSE 0 END) AS filled,
              SUM(CASE WHEN Fill=0 THEN 1 ELSE 0 END) AS notfilled,
              SUM(CASE WHEN Fill=4 THEN 1 ELSE 0 END) AS expired,
              SUM(CASE WHEN Fill=3 THEN 1 ELSE 0 END) AS notanswered
            FROM `$sealSTAT`
            WHERE Fill <> 6
              AND `ReqSystem` LIKE CONCAT('%', ?, '%')
              AND `Timestamp` >= ? AND `Timestamp` < ?
        ";
                    $stmt = mysqli_prepare($db, $sql);
                    mysqli_stmt_bind_param($stmt, 'sss', $system, $start_ts, $end_next);
                }

                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $row = mysqli_fetch_assoc($res) ?: [];
                mysqli_stmt_close($stmt);

                $total       = (int)($row['total'] ?? 0);
                $filled      = (int)($row['filled'] ?? 0);
                $notfilled   = (int)($row['notfilled'] ?? 0);
                $expired     = (int)($row['expired'] ?? 0);
                $notanswered = (int)($row['notanswered'] ?? 0);

                $label = ($system === '') ? 'All systems' : $system;

                $output_html  = "<h2>Borrowing stats (by system)</h2>";
                $output_html .= "<div><b>System:</b> ".h($label)." &nbsp; <b>Range:</b> ".h($start)." to ".h($end)."</div>";
                $output_html .= "<div><b>Total requests:</b> ".h($total)."</div>";
                $output_html .= "<div>Filled: ".h($filled)." (".h(pct2($filled, $total)).")</div>";
                $output_html .= "<div>Not filled: ".h($notfilled)." (".h(pct2($notfilled, $total)).")</div>";
                $output_html .= "<div>Expired: ".h($expired)." (".h(pct2($expired, $total)).")</div>";
                $output_html .= "<div>Not answered yet: ".h($notanswered)." (".h(pct2($notanswered, $total)).")</div>";

                // --- Narrative Breakdown ---
                $output_html .= "<h3>Break down of requests</h3>";
                if ($total <= 0) {
                    break;
                }

                // Borrowing system -> group by DestSystem (exclude canceled)
                if ($system === '') {
                    $sqlSys = "
            SELECT s.DestSystem AS other_system, COUNT(*) AS cnt
            FROM `$sealSTAT` s
            WHERE s.Fill <> 6
              AND s.`Timestamp` >= ? AND s.`Timestamp` < ?
            GROUP BY s.DestSystem
            ORDER BY cnt DESC
        ";
                    $stmt = mysqli_prepare($db, $sqlSys);
                    mysqli_stmt_bind_param($stmt, 'ss', $start_ts, $end_next);
                } else {
                    $sqlSys = "
            SELECT s.DestSystem AS other_system, COUNT(*) AS cnt
            FROM `$sealSTAT` s
            WHERE s.Fill <> 6
              AND s.`ReqSystem` LIKE CONCAT('%', ?, '%')
              AND s.`Timestamp` >= ? AND s.`Timestamp` < ?
            GROUP BY s.DestSystem
            ORDER BY cnt DESC
        ";
                    $stmt = mysqli_prepare($db, $sqlSys);
                    mysqli_stmt_bind_param($stmt, 'sss', $system, $start_ts, $end_next);
                }

                mysqli_stmt_execute($stmt);
                $resSys = mysqli_stmt_get_result($stmt);

                while ($sysRow = mysqli_fetch_assoc($resSys)) {
                    $other = (string)($sysRow['other_system'] ?? '');
                    $cnt   = (int)($sysRow['cnt'] ?? 0);

                    $output_html .= "<div style='margin-top:14px;'>
            ".h($cnt)." (".h(pct2($cnt, $total)).") overall requests were made to <b>".h(system_name($other))."</b>
        </div>";

                    // Itype within that destination system (exclude canceled)
                    if ($system === '') {
                        $sqlType = "
                SELECT
                  COALESCE(NULLIF(TRIM(s.Itype),''),'(none)') AS itype,
                  COUNT(*) AS cnt,
                  SUM(CASE WHEN s.Fill=1 THEN 1 ELSE 0 END) AS filled,
                  SUM(CASE WHEN s.Fill=0 THEN 1 ELSE 0 END) AS notfilled,
                  SUM(CASE WHEN s.Fill=4 THEN 1 ELSE 0 END) AS expired,
                  SUM(CASE WHEN s.Fill=3 THEN 1 ELSE 0 END) AS notanswered
                FROM `$sealSTAT` s
                WHERE s.Fill <> 6
                  AND s.DestSystem = ?
                  AND s.`Timestamp` >= ? AND s.`Timestamp` < ?
                GROUP BY COALESCE(NULLIF(TRIM(s.Itype),''),'(none)')
                ORDER BY cnt DESC
            ";
                        $stmt2 = mysqli_prepare($db, $sqlType);
                        mysqli_stmt_bind_param($stmt2, 'sss', $other, $start_ts, $end_next);
                    } else {
                        $sqlType = "
                SELECT
                  COALESCE(NULLIF(TRIM(s.Itype),''),'(none)') AS itype,
                  COUNT(*) AS cnt,
                  SUM(CASE WHEN s.Fill=1 THEN 1 ELSE 0 END) AS filled,
                  SUM(CASE WHEN s.Fill=0 THEN 1 ELSE 0 END) AS notfilled,
                  SUM(CASE WHEN s.Fill=4 THEN 1 ELSE 0 END) AS expired,
                  SUM(CASE WHEN s.Fill=3 THEN 1 ELSE 0 END) AS notanswered
                FROM `$sealSTAT` s
                WHERE s.Fill <> 6
                  AND s.`ReqSystem` LIKE CONCAT('%', ?, '%')
                  AND s.DestSystem = ?
                  AND s.`Timestamp` >= ? AND s.`Timestamp` < ?
                GROUP BY COALESCE(NULLIF(TRIM(s.Itype),''),'(none)')
                ORDER BY cnt DESC
            ";
                        $stmt2 = mysqli_prepare($db, $sqlType);
                        mysqli_stmt_bind_param($stmt2, 'ssss', $system, $other, $start_ts, $end_next);
                    }

                    mysqli_stmt_execute($stmt2);
                    $resType = mysqli_stmt_get_result($stmt2);

                    while ($t = mysqli_fetch_assoc($resType)) {
                        $itype = (string)($t['itype'] ?? '');
                        $tCnt  = (int)($t['cnt'] ?? 0);

                        $tFilled      = (int)($t['filled'] ?? 0);
                        $tNotfilled   = (int)($t['notfilled'] ?? 0);
                        $tExpired     = (int)($t['expired'] ?? 0);
                        $tNotanswered = (int)($t['notanswered'] ?? 0);

                        $output_html .= "<div style='margin-left:18px;margin-top:6px;'>
                ".h($tCnt)." (".h(pct2($tCnt, $cnt)).") of the requests to ".h(system_name($other))." were <b>".h($itype)."</b>
            </div>";

                        $output_html .= "<div style='margin-left:34px;line-height:1.6;'>
                ".h($tFilled)." (".h(pct2($tFilled, $tCnt)).") were filled<br>
                ".h($tNotfilled)." (".h(pct2($tNotfilled, $tCnt)).") were not filled<br>
                ".h($tExpired)." (".h(pct2($tExpired, $tCnt)).") were expired<br>
                ".h($tNotanswered)." (".h(pct2($tNotanswered, $tCnt)).") were not answered
            </div>";
                    }

                    mysqli_stmt_close($stmt2);
                }

                mysqli_stmt_close($stmt);
                break;
            } //end case borror system



            case 'lend_system': {
                $system = $_POST['system'] ?? '';
                $system = $system === '' ? '' : system_whitelist($system);
                if ($system === null) {
                    $output_html = "<div style='color:#c00;'>Select a valid system.</div>";
                    break;
                }

                // --- Summary (exclude canceled: Fill <> 6) ---
                if ($system === '') {
                    $sql = "
            SELECT
              COUNT(*) AS total,
              SUM(CASE WHEN Fill=1 THEN 1 ELSE 0 END) AS filled,
              SUM(CASE WHEN Fill=0 THEN 1 ELSE 0 END) AS notfilled,
              SUM(CASE WHEN Fill=4 THEN 1 ELSE 0 END) AS expired,
              SUM(CASE WHEN Fill=3 THEN 1 ELSE 0 END) AS notanswered
            FROM `$sealSTAT`
            WHERE Fill <> 6
              AND `Timestamp` >= ? AND `Timestamp` < ?
        ";
                    $stmt = mysqli_prepare($db, $sql);
                    mysqli_stmt_bind_param($stmt, 'ss', $start_ts, $end_next);
                } else {
                    $sql = "
            SELECT
              COUNT(*) AS total,
              SUM(CASE WHEN Fill=1 THEN 1 ELSE 0 END) AS filled,
              SUM(CASE WHEN Fill=0 THEN 1 ELSE 0 END) AS notfilled,
              SUM(CASE WHEN Fill=4 THEN 1 ELSE 0 END) AS expired,
              SUM(CASE WHEN Fill=3 THEN 1 ELSE 0 END) AS notanswered
            FROM `$sealSTAT`
            WHERE Fill <> 6
              AND `DestSystem` LIKE CONCAT('%', ?, '%')
              AND `Timestamp` >= ? AND `Timestamp` < ?
        ";
                    $stmt = mysqli_prepare($db, $sql);
                    mysqli_stmt_bind_param($stmt, 'sss', $system, $start_ts, $end_next);
                }

                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $row = mysqli_fetch_assoc($res) ?: [];
                mysqli_stmt_close($stmt);

                $total       = (int)($row['total'] ?? 0);
                $filled      = (int)($row['filled'] ?? 0);
                $notfilled   = (int)($row['notfilled'] ?? 0);
                $expired     = (int)($row['expired'] ?? 0);
                $notanswered = (int)($row['notanswered'] ?? 0);

                $label = ($system === '') ? 'All systems' : $system;

                $output_html  = "<h2>Lending stats (by system)</h2>";
                $output_html .= "<div><b>System:</b> ".h($label)." &nbsp; <b>Range:</b> ".h($start)." to ".h($end)."</div>";
                $output_html .= "<div><b>Total requests received:</b> ".h($total)."</div>";
                $output_html .= "<div>Filled: ".h($filled)." (".h(pct2($filled, $total)).")</div>";
                $output_html .= "<div>Not filled: ".h($notfilled)." (".h(pct2($notfilled, $total)).")</div>";
                $output_html .= "<div>Expired: ".h($expired)." (".h(pct2($expired, $total)).")</div>";
                $output_html .= "<div>Not answered yet: ".h($notanswered)." (".h(pct2($notanswered, $total)).")</div>";

                // --- Narrative Breakdown ---
                $output_html .= "<h3>Break down of requests</h3>";
                if ($total <= 0) {
                    break;
                }

                // Lending system -> group by ReqSystem (exclude canceled)
                if ($system === '') {
                    $sqlSys = "
            SELECT s.ReqSystem AS other_system, COUNT(*) AS cnt
            FROM `$sealSTAT` s
            WHERE s.Fill <> 6
              AND s.`Timestamp` >= ? AND s.`Timestamp` < ?
            GROUP BY s.ReqSystem
            ORDER BY cnt DESC
        ";
                    $stmt = mysqli_prepare($db, $sqlSys);
                    mysqli_stmt_bind_param($stmt, 'ss', $start_ts, $end_next);
                } else {
                    $sqlSys = "
            SELECT s.ReqSystem AS other_system, COUNT(*) AS cnt
            FROM `$sealSTAT` s
            WHERE s.Fill <> 6
              AND s.`DestSystem` LIKE CONCAT('%', ?, '%')
              AND s.`Timestamp` >= ? AND s.`Timestamp` < ?
            GROUP BY s.ReqSystem
            ORDER BY cnt DESC
        ";
                    $stmt = mysqli_prepare($db, $sqlSys);
                    mysqli_stmt_bind_param($stmt, 'sss', $system, $start_ts, $end_next);
                }

                mysqli_stmt_execute($stmt);
                $resSys = mysqli_stmt_get_result($stmt);

                while ($sysRow = mysqli_fetch_assoc($resSys)) {
                    $other = (string)($sysRow['other_system'] ?? '');
                    $cnt   = (int)($sysRow['cnt'] ?? 0);

                    $output_html .= "<div style='margin-top:14px;'>
            ".h($cnt)." (".h(pct2($cnt, $total)).") overall requests were made from <b>".h(system_name($other))."</b>
        </div>";

                    // Itype within that requesting system (exclude canceled)
                    if ($system === '') {
                        $sqlType = "
                SELECT
                  COALESCE(NULLIF(TRIM(s.Itype),''),'(none)') AS itype,
                  COUNT(*) AS cnt,
                  SUM(CASE WHEN s.Fill=1 THEN 1 ELSE 0 END) AS filled,
                  SUM(CASE WHEN s.Fill=0 THEN 1 ELSE 0 END) AS notfilled,
                  SUM(CASE WHEN s.Fill=4 THEN 1 ELSE 0 END) AS expired,
                  SUM(CASE WHEN s.Fill=3 THEN 1 ELSE 0 END) AS notanswered
                FROM `$sealSTAT` s
                WHERE s.Fill <> 6
                  AND s.ReqSystem = ?
                  AND s.`Timestamp` >= ? AND s.`Timestamp` < ?
                GROUP BY COALESCE(NULLIF(TRIM(s.Itype),''),'(none)')
                ORDER BY cnt DESC
            ";
                        $stmt2 = mysqli_prepare($db, $sqlType);
                        mysqli_stmt_bind_param($stmt2, 'sss', $other, $start_ts, $end_next);
                    } else {
                        $sqlType = "
                SELECT
                  COALESCE(NULLIF(TRIM(s.Itype),''),'(none)') AS itype,
                  COUNT(*) AS cnt,
                  SUM(CASE WHEN s.Fill=1 THEN 1 ELSE 0 END) AS filled,
                  SUM(CASE WHEN s.Fill=0 THEN 1 ELSE 0 END) AS notfilled,
                  SUM(CASE WHEN s.Fill=4 THEN 1 ELSE 0 END) AS expired,
                  SUM(CASE WHEN s.Fill=3 THEN 1 ELSE 0 END) AS notanswered
                FROM `$sealSTAT` s
                WHERE s.Fill <> 6
                  AND s.`DestSystem` LIKE CONCAT('%', ?, '%')
                  AND s.ReqSystem = ?
                  AND s.`Timestamp` >= ? AND s.`Timestamp` < ?
                GROUP BY COALESCE(NULLIF(TRIM(s.Itype),''),'(none)')
                ORDER BY cnt DESC
            ";
                        $stmt2 = mysqli_prepare($db, $sqlType);
                        mysqli_stmt_bind_param($stmt2, 'ssss', $system, $other, $start_ts, $end_next);
                    }

                    mysqli_stmt_execute($stmt2);
                    $resType = mysqli_stmt_get_result($stmt2);

                    while ($t = mysqli_fetch_assoc($resType)) {
                        $itype = (string)($t['itype'] ?? '');
                        $tCnt  = (int)($t['cnt'] ?? 0);

                        $tFilled      = (int)($t['filled'] ?? 0);
                        $tNotfilled   = (int)($t['notfilled'] ?? 0);
                        $tExpired     = (int)($t['expired'] ?? 0);
                        $tNotanswered = (int)($t['notanswered'] ?? 0);

                        $output_html .= "<div style='margin-left:18px;margin-top:6px;'>
                ".h($tCnt)." (".h(pct2($tCnt, $cnt)).") of the requests from ".h(system_name($other))." were <b>".h($itype)."</b>
            </div>";

                        $output_html .= "<div style='margin-left:34px;line-height:1.6;'>
                ".h($tFilled)." (".h(pct2($tFilled, $tCnt)).") were filled<br>
                ".h($tNotfilled)." (".h(pct2($tNotfilled, $tCnt)).") were not filled<br>
                ".h($tExpired)." (".h(pct2($tExpired, $tCnt)).") were expired<br>
                ".h($tNotanswered)." (".h(pct2($tNotanswered, $tCnt)).") were not answered
            </div>";
                    }

                    mysqli_stmt_close($stmt2);
                }

                mysqli_stmt_close($stmt);
                break;
            } //end case lend system



            case 'borrow_library': {
                $loc = loc_whitelist($_POST['loc'] ?? '');
                if (!$loc) {
                    $output_html = "<div style='color:#c00;'>Select a valid library.</div>";
                    break;
                }

                // --- Summary (exclude canceled) ---
                $sql = "
        SELECT
          COUNT(*) AS total,
          SUM(CASE WHEN Fill=1 THEN 1 ELSE 0 END) AS filled,
          SUM(CASE WHEN Fill=0 THEN 1 ELSE 0 END) AS notfilled,
          SUM(CASE WHEN Fill=4 THEN 1 ELSE 0 END) AS expired,
          SUM(CASE WHEN Fill=3 THEN 1 ELSE 0 END) AS notanswered,
          COALESCE(l.Name, ?) AS libname
        FROM `$sealSTAT` s
        LEFT JOIN `$sealLIB` l ON l.loc = ?
        WHERE s.Fill <> 6
          AND s.`Requester LOC` = ?
          AND s.`Timestamp` >= ? AND s.`Timestamp` < ?
    ";
                $stmt = mysqli_prepare($db, $sql);
                mysqli_stmt_bind_param($stmt, 'sssss', $loc, $loc, $loc, $start_ts, $end_next);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $row = mysqli_fetch_assoc($res) ?: [];
                mysqli_stmt_close($stmt);

                $total       = (int)($row['total'] ?? 0);
                $filled      = (int)($row['filled'] ?? 0);
                $notfilled   = (int)($row['notfilled'] ?? 0);
                $expired     = (int)($row['expired'] ?? 0);
                $notanswered = (int)($row['notanswered'] ?? 0);

                $output_html  = "<h2>Borrowing stats (specific library)</h2>";
                $output_html .= "<div><b>Library:</b> ".h($row['libname'] ?? $loc)." (".h($loc).") &nbsp; <b>Range:</b> ".h($start)." to ".h($end)."</div>";
                $output_html .= "<div><b>Total requests:</b> ".h($total)."</div>";
                $output_html .= "<div>Filled: ".h($filled)." (".h(pct2($filled, $total)).")</div>";
                $output_html .= "<div>Not filled: ".h($notfilled)." (".h(pct2($notfilled, $total)).")</div>";
                $output_html .= "<div>Expired: ".h($expired)." (".h(pct2($expired, $total)).")</div>";
                $output_html .= "<div>Not answered yet: ".h($notanswered)." (".h(pct2($notanswered, $total)).")</div>";

                // --- Breakdown ---
                $output_html .= "<h3>Break down of requests</h3>";
                if ($total <= 0) {
                    break;
                }

                // Borrowing library -> group by destination system (exclude canceled)
                $sqlSys = "
        SELECT s.DestSystem AS other_system, COUNT(*) AS cnt
        FROM `$sealSTAT` s
        WHERE s.Fill <> 6
          AND s.`Requester LOC` = ?
          AND s.`Timestamp` >= ? AND s.`Timestamp` < ?
        GROUP BY s.DestSystem
        ORDER BY cnt DESC
    ";
                $stmt = mysqli_prepare($db, $sqlSys);
                mysqli_stmt_bind_param($stmt, 'sss', $loc, $start_ts, $end_next);
                mysqli_stmt_execute($stmt);
                $resSys = mysqli_stmt_get_result($stmt);

                while ($sysRow = mysqli_fetch_assoc($resSys)) {
                    $other = (string)($sysRow['other_system'] ?? '');
                    $cnt   = (int)($sysRow['cnt'] ?? 0);

                    $output_html .= "<div style='margin-top:14px;'>
            ".h($cnt)." (".h(pct2($cnt, $total)).") overall requests were made to <b>".h(system_name($other))."</b>
        </div>";

                    $sqlType = "
            SELECT
              COALESCE(NULLIF(TRIM(s.Itype),''),'(none)') AS itype,
              COUNT(*) AS cnt,
              SUM(CASE WHEN s.Fill=1 THEN 1 ELSE 0 END) AS filled,
              SUM(CASE WHEN s.Fill=0 THEN 1 ELSE 0 END) AS notfilled,
              SUM(CASE WHEN s.Fill=4 THEN 1 ELSE 0 END) AS expired,
              SUM(CASE WHEN s.Fill=3 THEN 1 ELSE 0 END) AS notanswered
            FROM `$sealSTAT` s
            WHERE s.Fill <> 6
              AND s.`Requester LOC` = ?
              AND s.DestSystem = ?
              AND s.`Timestamp` >= ? AND s.`Timestamp` < ?
            GROUP BY COALESCE(NULLIF(TRIM(s.Itype),''),'(none)')
            ORDER BY cnt DESC
        ";
                    $stmt2 = mysqli_prepare($db, $sqlType);
                    mysqli_stmt_bind_param($stmt2, 'ssss', $loc, $other, $start_ts, $end_next);
                    mysqli_stmt_execute($stmt2);
                    $resType = mysqli_stmt_get_result($stmt2);

                    while ($t = mysqli_fetch_assoc($resType)) {
                        $itype = (string)($t['itype'] ?? '');
                        $tCnt  = (int)($t['cnt'] ?? 0);

                        $tFilled      = (int)($t['filled'] ?? 0);
                        $tNotfilled   = (int)($t['notfilled'] ?? 0);
                        $tExpired     = (int)($t['expired'] ?? 0);
                        $tNotanswered = (int)($t['notanswered'] ?? 0);

                        $output_html .= "<div style='margin-left:18px;margin-top:6px;'>
                ".h($tCnt)." (".h(pct2($tCnt, $cnt)).") of the requests to ".h(system_name($other))." were <b>".h($itype)."</b>
            </div>";

                        $output_html .= "<div style='margin-left:34px;line-height:1.6;'>
                ".h($tFilled)." (".h(pct2($tFilled, $tCnt)).") were filled<br>
                ".h($tNotfilled)." (".h(pct2($tNotfilled, $tCnt)).") were not filled<br>
                ".h($tExpired)." (".h(pct2($tExpired, $tCnt)).") were expired<br>
                ".h($tNotanswered)." (".h(pct2($tNotanswered, $tCnt)).") were not answered
            </div>";
                    }

                    mysqli_stmt_close($stmt2);
                }

                mysqli_stmt_close($stmt);
                break;
            }//end case borrow library


            case 'lend_library': {
                $loc = loc_whitelist($_POST['loc'] ?? '');
                if (!$loc) {
                    $output_html = "<div style='color:#c00;'>Select a valid library.</div>";
                    break;
                }

                // --- Summary (exclude canceled) ---
                $sql = "
        SELECT
          COUNT(*) AS total,
          SUM(CASE WHEN Fill=1 THEN 1 ELSE 0 END) AS filled,
          SUM(CASE WHEN Fill=0 THEN 1 ELSE 0 END) AS notfilled,
          SUM(CASE WHEN Fill=4 THEN 1 ELSE 0 END) AS expired,
          SUM(CASE WHEN Fill=3 THEN 1 ELSE 0 END) AS notanswered,
          COALESCE(l.Name, ?) AS libname
        FROM `$sealSTAT` s
        LEFT JOIN `$sealLIB` l ON l.loc = ?
        WHERE s.Fill <> 6
          AND s.`Destination` = ?
          AND s.`Timestamp` >= ? AND s.`Timestamp` < ?
    ";
                $stmt = mysqli_prepare($db, $sql);
                mysqli_stmt_bind_param($stmt, 'sssss', $loc, $loc, $loc, $start_ts, $end_next);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $row = mysqli_fetch_assoc($res) ?: [];
                mysqli_stmt_close($stmt);

                $total       = (int)($row['total'] ?? 0);
                $filled      = (int)($row['filled'] ?? 0);
                $notfilled   = (int)($row['notfilled'] ?? 0);
                $expired     = (int)($row['expired'] ?? 0);
                $notanswered = (int)($row['notanswered'] ?? 0);

                $pctLocal = function ($n) use ($total) {
                    return $total > 0 ? number_format(($n / $total) * 100, 2).'%' : '0.00%';
                };

                $output_html  = "<h2>Lending stats (specific library)</h2>";
                $output_html .= "<div><b>Library:</b> ".h($row['libname'] ?? $loc)." (".h($loc).") &nbsp; <b>Range:</b> ".h($start)." to ".h($end)."</div>";
                $output_html .= "<div><b>Total requests received:</b> ".h($total)."</div>";
                $output_html .= "<div>Filled: ".h($filled)." (".$pctLocal($filled).")</div>";
                $output_html .= "<div>Not filled: ".h($notfilled)." (".$pctLocal($notfilled).")</div>";
                $output_html .= "<div>Expired: ".h($expired)." (".$pctLocal($expired).")</div>";
                $output_html .= "<div>Not answered yet: ".h($notanswered)." (".$pctLocal($notanswered).")</div>";

                // --- Breakdown ---
                $output_html .= "<h3>Break down of requests</h3>";
                if ($total <= 0) {
                    break;
                }

                // Group by requesting system (exclude canceled)
                $sqlSys = "
        SELECT s.ReqSystem AS other_system, COUNT(*) AS cnt
        FROM `$sealSTAT` s
        WHERE s.Fill <> 6
          AND s.`Destination` = ?
          AND s.`Timestamp` >= ? AND s.`Timestamp` < ?
        GROUP BY s.ReqSystem
        ORDER BY cnt DESC
    ";
                $stmt = mysqli_prepare($db, $sqlSys);
                mysqli_stmt_bind_param($stmt, 'sss', $loc, $start_ts, $end_next);
                mysqli_stmt_execute($stmt);
                $resSys = mysqli_stmt_get_result($stmt);

                while ($sysRow = mysqli_fetch_assoc($resSys)) {
                    $other = (string)($sysRow['other_system'] ?? '');
                    $cnt   = (int)($sysRow['cnt'] ?? 0);

                    $output_html .= "<div style='margin-top:14px;'>
            ".h($cnt)." (".h(pct2($cnt, $total)).") overall requests were made from <b>".h(system_name($other))."</b>
        </div>";

                    $sqlType = "
            SELECT
              COALESCE(NULLIF(TRIM(s.Itype),''),'(none)') AS itype,
              COUNT(*) AS cnt,
              SUM(CASE WHEN s.Fill=1 THEN 1 ELSE 0 END) AS filled,
              SUM(CASE WHEN s.Fill=0 THEN 1 ELSE 0 END) AS notfilled,
              SUM(CASE WHEN s.Fill=4 THEN 1 ELSE 0 END) AS expired,
              SUM(CASE WHEN s.Fill=3 THEN 1 ELSE 0 END) AS notanswered
            FROM `$sealSTAT` s
            WHERE s.Fill <> 6
              AND s.`Destination` = ?
              AND s.ReqSystem = ?
              AND s.`Timestamp` >= ? AND s.`Timestamp` < ?
            GROUP BY COALESCE(NULLIF(TRIM(s.Itype),''),'(none)')
            ORDER BY cnt DESC
        ";
                    $stmt2 = mysqli_prepare($db, $sqlType);
                    mysqli_stmt_bind_param($stmt2, 'ssss', $loc, $other, $start_ts, $end_next);
                    mysqli_stmt_execute($stmt2);
                    $resType = mysqli_stmt_get_result($stmt2);

                    while ($t = mysqli_fetch_assoc($resType)) {
                        $itype = (string)($t['itype'] ?? '');
                        $tCnt  = (int)($t['cnt'] ?? 0);

                        $tFilled      = (int)($t['filled'] ?? 0);
                        $tNotfilled   = (int)($t['notfilled'] ?? 0);
                        $tExpired     = (int)($t['expired'] ?? 0);
                        $tNotanswered = (int)($t['notanswered'] ?? 0);

                        $output_html .= "<div style='margin-left:18px;margin-top:6px;'>
                ".h($tCnt)." (".h(pct2($tCnt, $cnt)).") of the requests from ".h(system_name($other))." were <b>".h($itype)."</b>
            </div>";

                        $output_html .= "<div style='margin-left:34px;line-height:1.6;'>
                ".h($tFilled)." (".h(pct2($tFilled, $tCnt)).") were filled<br>
                ".h($tNotfilled)." (".h(pct2($tNotfilled, $tCnt)).") were not filled<br>
                ".h($tExpired)." (".h(pct2($tExpired, $tCnt)).") were expired<br>
                ".h($tNotanswered)." (".h(pct2($tNotanswered, $tCnt)).") were not answered
            </div>";
                    }

                    mysqli_stmt_close($stmt2);
                }

                mysqli_stmt_close($stmt);
                break;
            }//end case lend library



            case 'top10_fillers': {
                $sql = "
    SELECT
      s.`Destination` AS loc,
      COALESCE(l.Name, s.`Destination`) AS name,
      COUNT(*) AS cnt
    FROM `$sealSTAT` s
    LEFT JOIN `$sealLIB` l ON l.loc = s.`Destination`
    WHERE s.`Timestamp` >= ? AND s.`Timestamp` < ?
      AND s.Fill = 1
    GROUP BY s.`Destination`
    ORDER BY cnt DESC
    LIMIT 10
  ";
                $stmt = mysqli_prepare($db, $sql);
                mysqli_stmt_bind_param($stmt, 'ss', $start_ts, $end_next);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);

                $output_html  = "<h2>Top 10 libraries filling requests</h2>";
                $output_html .= "<div><b>Range:</b> ".h($start)." to ".h($end)."</div>";
                $output_html .= "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;margin-top:8px;'>
    <tr><th>LOC</th><th>Library</th><th>Fills</th></tr>";

                while ($r = mysqli_fetch_assoc($res)) {
                    $output_html .= "<tr>
      <td>".h($r['loc'])."</td>
      <td>".h($r['name'])."</td>
      <td>".h($r['cnt'])."</td>
    </tr>";
                }
                $output_html .= "</table>";

                mysqli_stmt_close($stmt);
                break;
            }


            default:
                $output_html = "<div style='color:#c00;'>Unknown action.</div>";
        }
    }
}

// Default date values (last 7 days)
$default_start = date('Y-m-d', strtotime('-7 days'));
$default_end   = date('Y-m-d');
?>
<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/themes/smoothness/jquery-ui.css">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js"></script>
<script>
jQuery(function($){
  $(".seal-date").datepicker({ dateFormat: "yy-mm-dd" });
});
</script>

<div style="max-width:1100px;margin:0 auto;padding:10px;">
  <h1>SEAL Admin Stats</h1>

  <?php if ($output_html) {
      echo "<div style='padding:12px;border:1px solid #ddd;margin:10px 0;'>$output_html</div>";
  } ?>

  <hr>

  <h2>Borrowing stats (by system)</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
    <input type="hidden" name="action" value="borrow_system">
    Start Date: <input class="seal-date" name="startdate" value="<?php echo h($default_start); ?>">
    End Date: <input class="seal-date" name="enddate" value="<?php echo h($default_end); ?>">
    <br><br>
    Requesting Library System:
    <select name="system">
      <option value="">All systems</option>
      <option value="DU">Dutchess BOCES</option>
      <option value="MH">Mid-Hudson Library System</option>
      <option value="OU">Orange Ulster BOCES</option>
      <option value="RC">Ramapo Catskill Library System</option>
      <option value="RB">Rockland BOCES</option>
      <option value="SE">SENYLRC</option>
      <option value="SB">Sullivan BOCES</option>
      <option value="UB">Ulster BOCES</option>
    </select>
    <button type="submit">Run</button>
  </form>

  <hr>

  <h2>Lending stats (by system)</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
    <input type="hidden" name="action" value="lend_system">
    Start Date: <input class="seal-date" name="startdate" value="<?php echo h($default_start); ?>">
    End Date: <input class="seal-date" name="enddate" value="<?php echo h($default_end); ?>">
    <br><br>
    Requesting Library System:
    <select name="system" >
      <option value="">All systems</option>
      <option value="DU">Dutchess BOCES</option>
      <option value="MH">Mid-Hudson Library System</option>
      <option value="OU">Orange Ulster BOCES</option>
      <option value="RC">Ramapo Catskill Library System</option>
      <option value="RB">Rockland BOCES</option>
      <option value="SE">SENYLRC</option>
      <option value="SB">Sullivan BOCES</option>
      <option value="UB">Ulster BOCES</option>
    </select>
    <button type="submit">Run</button>
  </form>

  <hr>

  <h2>Borrowing stats (specific library)</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
    <input type="hidden" name="action" value="borrow_library">
    Start Date: <input class="seal-date" name="startdate" value="<?php echo h($default_start); ?>">
    End Date: <input class="seal-date" name="enddate" value="<?php echo h($default_end); ?>">
    <br><br>
    Library:
    <select name="loc" required>
      <option value="">Select a library</option>
      <?php foreach ($libs as $l) { ?>
        <option value="<?php echo h($l['loc']); ?>"><?php echo h($l['Name']); ?></option>
      <?php } ?>
    </select>
    <button type="submit">Run</button>
  </form>

  <hr>

  <h2>Lending stats (specific library)</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
    <input type="hidden" name="action" value="lend_library">
    Start Date: <input class="seal-date" name="startdate" value="<?php echo h($default_start); ?>">
    End Date: <input class="seal-date" name="enddate" value="<?php echo h($default_end); ?>">
    <br><br>
    Library:
    <select name="loc" required>
      <option value="">Select a library</option>
      <?php foreach ($libs as $l) { ?>
        <option value="<?php echo h($l['loc']); ?>"><?php echo h($l['Name']); ?></option>
      <?php } ?>
    </select>
    <button type="submit">Run</button>
  </form>

  <hr>

  <h2>Expired requests</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
    <input type="hidden" name="action" value="expired_list">
    Start Date: <input class="seal-date" name="startdate" value="<?php echo h($default_start); ?>">
    End Date: <input class="seal-date" name="enddate" value="<?php echo h($default_end); ?>">
    <button type="submit">Run</button>
  </form>

  <hr>

  <h2>Top 10 libraries making requests</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
    <input type="hidden" name="action" value="top10_requesters">
    Start Date: <input class="seal-date" name="startdate" value="<?php echo h($default_start); ?>">
    End Date: <input class="seal-date" name="enddate" value="<?php echo h($default_end); ?>">
    <button type="submit">Run</button>
  </form>

  <hr>

  <h2>Top 10 libraries filling requests</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
    <input type="hidden" name="action" value="top10_fillers">
    Start Date: <input class="seal-date" name="startdate" value="<?php echo h($default_start); ?>">
    End Date: <input class="seal-date" name="enddate" value="<?php echo h($default_end); ?>">
    <button type="submit">Run</button>
  </form>

</div>