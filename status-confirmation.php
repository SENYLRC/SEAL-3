<?php
// ==========================================================
// WordPress Access Control — Restrict to Logged-In Users
// with Role: Administrator or Library Staff
// ==========================================================
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
// status-confirmation.php

// Load env / helpers (use the same paths as the rest of your app)
require '/var/www/seal_wp_script/seal_function.php';
require '/var/www/seal_wp_script/seal_db.inc';

// Request values (with sane defaults)
$task    = isset($_REQUEST['task'])    ? trim($_REQUEST['task'])    : '';
$proceed = isset($_REQUEST['proceed']) ? trim($_REQUEST['proceed']) : '';
$enddate = isset($_REQUEST['enddate']) ? trim($_REQUEST['enddate']) : '';

// Always take system from the signed-in user's profile if available
$system  = isset($field_home_library_system) && $field_home_library_system !== ''
         ? $field_home_library_system
         : (isset($_REQUEST['system']) ? trim($_REQUEST['system']) : '');

// Normalize task
$task = strtolower($task);
if (!in_array($task, ['suspend','activate'], true)) {
  $task = '';
}

// If suspending with no end date, default to +7 days. Otherwise normalize to Y-m-d.
if ($task === 'suspend') {
  if ($enddate === '' || strlen($enddate) < 2) {
    $enddate = date('Y-m-d', strtotime('+7 day'));
  } else {
    $ts = strtotime(str_replace('-', '/', $enddate));
    $enddate = $ts ? date('Y-m-d', $ts) : date('Y-m-d', strtotime('+7 day'));
  }
} else {
  // not used on activate, but keep a normalized value around
  if ($enddate !== '') {
    $ts = strtotime(str_replace('-', '/', $enddate));
    $enddate = $ts ? date('Y-m-d', $ts) : '';
  }
}

// Decide action flow
if ($system === '' || $system === 'none' || $task === '') {
  $action = 'stop';
} elseif ($proceed === 'Proceed') {
  $action = 'doit';
} else {
  $action = 'go';
}

// Pretty names for confirmation
$systemNames = [
  'DU'    => 'Dutchess BOCES',
  'MH'    => 'Mid-Hudson Library System',
  'OU'    => 'Orange Ulster BOCES',
  'RC'    => 'Ramapo Catskill Library System',
  'RB'    => 'Rockland BOCES',
  'SE'    => 'SENYLRC',
  'SB'    => 'Sullivan BOCES',
  'UB'    => 'Ulster BOCES',
];
$displaysystem = isset($systemNames[$system]) ? $systemNames[$system] : $system;

// ---------- RENDER ----------
if ($action === 'go') {
  $verb = ($task === 'suspend') ? 'suspend' : 'activate';
  echo "You have chosen to <b>" . htmlspecialchars($verb, ENT_QUOTES) . " lending</b> for all libraries of the <b>" . htmlspecialchars($displaysystem, ENT_QUOTES) . "</b>.<br><br>";
  echo "This will overwrite the setting for these libraries. Are you sure you wish to proceed? ";
  ?>
  <form action="/status-confirmation" method="post">
    <input type="hidden" name="task" value="<?php echo htmlspecialchars($task, ENT_QUOTES); ?>">
    <input type="hidden" name="system" value="<?php echo htmlspecialchars($system, ENT_QUOTES); ?>">
    <input type="hidden" name="enddate" value="<?php echo htmlspecialchars($enddate, ENT_QUOTES); ?>">
    <input type="submit" name="proceed" value="Proceed"> <a href="/adminlib">Cancel</a>
  </form>
  <?php
} elseif ($action === 'doit') {

  // Connect DB
  $db = mysqli_connect($dbhost, $dbuser, $dbpass);
  mysqli_select_db($db, $dbname);

  $timestamp = date('Y-m-d H:i:s');

  // Decide ModEmail — prefer a known profile/email; fall back to blank
  $modEmail = '';
  if (!empty($field_user_email)) $modEmail = $field_user_email;
  elseif (!empty($_SESSION['user_email'])) $modEmail = $_SESSION['user_email'];

  // Escape
  $sysEsc   = mysqli_real_escape_string($db, $system);
  $endEsc   = mysqli_real_escape_string($db, $enddate);
  $tsEsc    = mysqli_real_escape_string($db, $timestamp);
  $modEsc   = mysqli_real_escape_string($db, $modEmail);

  if ($task === 'suspend') {
    $sqlupdate = "UPDATE `$sealLIB`
                    SET suspend='1',
                        SuspendDateEnd='$endEsc',
                        ModifyDate='$tsEsc',
                        ModEmail='$modEsc'
                  WHERE loc <> ''
                    AND `participant` = '1'
                    AND `suspend` = '0'
                    AND `system` = '$sysEsc'";
  } else {
    $sqlupdate = "UPDATE `$sealLIB`
                    SET suspend='0',
                        ModifyDate='$tsEsc',
                        ModEmail='$modEsc'
                  WHERE loc <> ''
                    AND `participant` = '1'
                    AND `suspend` = '1'
                    AND `system` = '$sysEsc'";
  }

  $result = mysqli_query($db, $sqlupdate);

  if ($result === false) {
    echo "<b>Update failed:</b> " . htmlspecialchars(mysqli_error($db), ENT_QUOTES);
  } else {
    $count = mysqli_affected_rows($db);
    echo "<b>The libraries have been updated!</b><br>";
    echo "Affected rows: " . htmlspecialchars((string)$count, ENT_QUOTES) . "<br>";
    echo "<a href='/adminlib_ls'>Admin System Libraries</a>";
  }

  mysqli_close($db);

} else {
  echo "Sorry! We cannot complete your action.";
}