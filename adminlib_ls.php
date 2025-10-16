<?php
// adminlib_ls.php — Library list and editor for system-level users

session_name('seal_admin_session');
if (session_status() === PHP_SESSION_NONE) session_start();

require '/var/www/seal_wp_script/seal_function.php';
require '/var/www/seal_wp_script/seal_db.inc';

require_once('/var/www/wpSEAL/wp-load.php');
$current_user = wp_get_current_user();
$user_roles = (array)$current_user->roles;

if (!in_array('administrator', $user_roles, true) && !in_array('lib-systems-staff', $user_roles, true)) {
    die("<div style='padding:20px;color:red;font-weight:bold;'>Access Denied<br>You must have the <b>Lib Systems Staff</b> role to access this page.</div>");
}

$field_home_library_system = get_user_meta($current_user->ID, 'home_system', true);
if (empty($field_home_library_system)) {
    die("<div style='padding:20px;color:red;font-weight:bold;'>Your account does not have a Home Library System assigned.</div>");
}

if (!function_exists('selected')) {
    function selected($value, $current) { return ((string)$value === (string)$current) ? 'selected="selected"' : ''; }
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$db = mysqli_connect($dbhost, $dbuser, $dbpass);
mysqli_select_db($db, $dbname);

$filter_library = $_REQUEST['library'] ?? "";
$filter_loc     = $_REQUEST['loc'] ?? "";
$filter_alias   = $_REQUEST['filter_alias'] ?? "";
$filter_illemail= $_REQUEST['filter_illemail'] ?? "";
$pageaction     = $_REQUEST['action'] ?? '0';
$librecnumb     = $_REQUEST['librecnumb'] ?? null;
?>
<style>
.seal-shell {font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, "Helvetica Neue", Arial, sans-serif; color:#111;}
.seal-wrap {max-width:1100px; margin:0 auto; padding:18px;}
.seal-card {background:#fff; border:1px solid #e5e7eb; border-radius:14px; box-shadow:0 1px 2px rgba(0,0,0,.05); padding:18px; margin-bottom:16px;}
.seal-title {font-size:22px; font-weight:700; margin:0 0 8px;}
.seal-sub {font-size:14px; color:#555; margin-bottom:12px;}
.seal-row {display:flex; gap:12px; flex-wrap:wrap; align-items:center;}
.seal-row .input {padding:8px 10px; border:1px solid #d1d5db; border-radius:8px; min-width:220px;}
.seal-btn {display:inline-block; padding:6px 12px; border-radius:8px; border:1px solid #0f766e; background:#0d9488; color:#fff; text-decoration:none; font-weight:600; cursor:pointer;}
.seal-btn.secondary {background:#fff; color:#0f766e;}
.seal-btn.secondary:hover {background:#0f766e; color:#fff;}
.seal-table {width:100%; border-collapse:collapse;}
.seal-table th, .seal-table td {padding:10px 8px; border-bottom:1px solid #eef2f7; text-align:left; vertical-align:middle;}
.seal-table th {font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#6b7280; background:#fafafa;}
.action-buttons {display:flex; gap:6px; flex-wrap:wrap; justify-content:flex-start;}
.status-yes {color:#166534; font-weight:600;} /* Green for Yes */
.status-no {color:#b91c1c; font-weight:600;}  /* Red for No */
@media (max-width:720px){
  .seal-row .input{min-width:100%;}
  .action-buttons {flex-direction:column; align-items:flex-start;}
}
</style>

<div class="seal-shell"><div class="seal-wrap">
<?php
// DELETE
if ($pageaction == '3') {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $librecnumb = mysqli_real_escape_string($db, $librecnumb);
        mysqli_query($db, "DELETE FROM `$sealLIB` WHERE recnum='$librecnumb'");
        echo '<div class="seal-card"><div class="seal-title">Library deleted</div>
              <a class="seal-btn" href="'.h($_SERVER['REDIRECT_URL']).'">Return to list</a></div>';
    } else {
        echo '<div class="seal-card"><div class="seal-title">Confirm delete</div>
              <form method="post" action="'.h($_SERVER['REDIRECT_URL']).'?'.h($_SERVER['QUERY_STRING']).'">
              <input type="hidden" name="action" value="3">
              <input type="hidden" name="librecnumb" value="'.h($librecnumb).'">
              <button class="seal-btn">Confirm</button>
              <a class="seal-btn secondary" href="'.h($_SERVER['REDIRECT_URL']).'">Cancel</a>
              </form></div>';
    }

// MASS SUSPEND
} elseif ($pageaction == '5') {
    echo '<div class="seal-card"><div class="seal-title">Mass Suspend / Activate</div>
          <div class="seal-sub">Applies to all libraries in '.h($field_home_library_system).'</div>
          <form method="post" action="/status-confirmation">
          <input type="hidden" name="system" value="'.h($field_home_library_system).'">
          <div class="seal-row">
          <label><input type="radio" name="task" value="suspend"> Suspend</label>
          <label><input type="radio" name="task" value="activate" checked> Activate</label>
          </div>
          <div class="seal-row"><label><b>Suspension End Date:</b> 
          <input class="input" id="suspend_enddate" name="enddate" type="text" placeholder="MM/DD/YYYY"></label></div>
          <div style="margin-top:10px;"><button class="seal-btn" type="submit">Submit</button></div></form></div>';

// DEFAULT LIST
} else {
    $SQL = "SELECT * FROM `$sealLIB` WHERE `system` LIKE '%" . mysqli_real_escape_string($db, $field_home_library_system) . "%'";
    if ($filter_library)  $SQL .= " AND `Name` LIKE '%" . mysqli_real_escape_string($db, $filter_library) . "%'";
    if ($filter_alias)    $SQL .= " AND `alias` LIKE '%" . mysqli_real_escape_string($db, $filter_alias) . "%'";
    if ($filter_loc)      $SQL .= " AND `loc` LIKE '%" . mysqli_real_escape_string($db, $filter_loc) . "%'";
    if ($filter_illemail) $SQL .= " AND `ill_email` LIKE '%" . mysqli_real_escape_string($db, $filter_illemail) . "%'";
    $SQL .= " ORDER BY `Name` ASC";
    $GETLIST = mysqli_query($db, $SQL);
    $count = $GETLIST ? mysqli_num_rows($GETLIST) : 0;

    echo '<div class="seal-card">';
    echo '<div class="seal-title">Libraries in '.h($field_home_library_system).' System</div>';
    echo '<div class="seal-sub">Showing all libraries within your system.</div>';
    echo '<form method="post" action="'.h($_SERVER['REDIRECT_URL']).'">';
    echo '<div class="seal-row">';
    echo '<label><b>Library Name</b><br><input class="input" name="library" value="'.h($filter_library).'"></label>';
    echo '<label><b>Alias</b><br><input class="input" name="filter_alias" value="'.h($filter_alias).'"></label>';
    echo '<label><b>LOC Code</b><br><input class="input" name="loc" value="'.h($filter_loc).'"></label>';
    echo '<label><b>Email</b><br><input class="input" name="filter_illemail" value="'.h($filter_illemail).'"></label>';
    echo '</div>';
    echo '<div class="seal-row"><button class="seal-btn" type="submit">Search</button></div>';
    echo '</form></div>';

    echo '<div class="seal-card"><div class="seal-row">';
    echo '<a class="seal-btn secondary" href="'.h($_SERVER['REDIRECT_URL']).'?action=5">Mass Suspend/Activate</a>';
    echo '</div></div>';

    echo '<div class="seal-card">';
    echo '<table class="seal-table">';
    echo '<tr><th>Library</th><th>Alias</th><th>Participant</th><th>Suspend</th><th>System</th><th>OCLC</th><th>LOC</th><th>Actions</th></tr>';
    if ($count == 0) {
        echo '<tr><td colspan="8">No libraries found in this system.</td></tr>';
    } else {
        while ($row = mysqli_fetch_assoc($GETLIST)) {
            $librecnumb = $row["recnum"];
            $partClass = ($row["participant"] ? "status-yes" : "status-no");
            $partText  = ($row["participant"] ? "Yes" : "No");
            $suspendClass = ($row["suspend"] ? "status-no" : "status-yes");
            $suspendText  = ($row["suspend"] ? "Yes" : "No");

            echo '<tr>';
            echo '<td>'.h($row["Name"]).'</td>';
            echo '<td>'.h($row["alias"]).'</td>';
            echo '<td class="'.$partClass.'">'.$partText.'</td>';
            echo '<td class="'.$suspendClass.'">'.$suspendText.'</td>';
            echo '<td>'.h($row["system"]).'</td>';
            echo '<td>'.h($row["oclc"]).'</td>';
            echo '<td>'.h($row["loc"]).'</td>';
            echo '<td><div class="action-buttons">
                    <a class="seal-btn secondary" href="'.h($_SERVER['REDIRECT_URL']).'?action=2&librecnumb='.h($librecnumb).'">Edit</a>
                    <a class="seal-btn secondary" href="'.h($_SERVER['REDIRECT_URL']).'?action=3&librecnumb='.h($librecnumb).'">Delete</a>
                  </div></td>';
            echo '</tr>';
        }
    }
    echo '</table></div>';
}
?>
</div></div>