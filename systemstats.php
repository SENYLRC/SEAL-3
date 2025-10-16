<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/themes/smoothness/jquery-ui.css">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js"></script>
<script>
$(document).ready(function() {
  $("#datepicker").datepicker();
  $("#datepicker2").datepicker();
});
</script>

<?php
// ==========================================================
// systemstats.php — SEAL + WordPress system overview
// ==========================================================

// --- WordPress Role Enforcement ---
require_once('/var/www/wpSEAL/wp-load.php');
$current_user = wp_get_current_user();
$user_roles = (array)$current_user->roles;

// Restrict to Administrator only
if (!in_array('administrator', $user_roles, true)) {
    die("<div style='padding:20px;color:red;font-weight:bold;'>Access Denied<br>
         You must have the <b>Administrator</b> role to access this page.</div>");
}

echo "<h2>Database SSL/TLS Connection Tests</h2>";

// ==========================================================
// 1️⃣ Test WordPress Database Connection ($wpdb)
// ==========================================================
global $wpdb;
$wp_ssl = $wpdb->get_row("SHOW SESSION STATUS LIKE 'Ssl_cipher'");

if (!empty($wp_ssl->Value)) {
    echo "<div style='color:green;font-weight:bold;'>✅ WordPress DB connection encrypted via: "
       . htmlspecialchars($wp_ssl->Value) . "</div>";
} else {
    echo "<div style='color:red;font-weight:bold;'>❌ WordPress DB connection is NOT encrypted!</div>";
}

// ==========================================================
// 2️⃣ Test SEAL Script Database Connection ($db)
// ==========================================================
require '/var/www/seal_wp_script/seal_db.inc';
require '/var/www/seal_wp_script/seal_function.php';

$seal_ssl_res = mysqli_query($db, "SHOW SESSION STATUS LIKE 'Ssl_cipher'");
$seal_ssl_row = mysqli_fetch_assoc($seal_ssl_res);

if (!empty($seal_ssl_row['Value'])) {
    echo "<div style='color:green;font-weight:bold;'>✅ SEAL DB connection encrypted via: "
       . htmlspecialchars($seal_ssl_row['Value']) . "</div><br>";
} else {
    echo "<div style='color:red;font-weight:bold;'>❌ SEAL DB connection is NOT encrypted!</div><br>";
}

// ==========================================================
// 3️⃣ SEAL System Statistics
// ==========================================================
echo "<h2>SEAL System Statistics</h2>";

$db = mysqli_connect($dbhost, $dbuser, $dbpass);
mysqli_select_db($db, $dbname);

// Total libraries
$TotalLibraryQuery = "SELECT * FROM `$sealLIB`";
$RetVal = mysqli_query($db, $TotalLibraryQuery);
$TotalLibraryCount = mysqli_num_rows($RetVal);

// Closed Libraries
$ClosedLibraryQuery = "SELECT * FROM `$sealLIB` WHERE participant = 0 OR `ill_email` = '' OR alias = ''";
$RetVal = mysqli_query($db, $ClosedLibraryQuery);
$ClosedLibraryCount = mysqli_num_rows($RetVal);
$ClosedLibraryPercent = $ClosedLibraryCount / $TotalLibraryCount;

// Open Libraries
$OpenLibraryCount = $TotalLibraryCount - $ClosedLibraryCount;
$OpenLibraryPercent = $OpenLibraryCount / $TotalLibraryCount;

// Non-Participating Libraries
$NonPartLibrariesQuery = "SELECT * FROM `$sealLIB` WHERE participant = 0";
$RetVal = mysqli_query($db, $NonPartLibrariesQuery);
$NonPartLibraries = mysqli_num_rows($RetVal);

// Suspended Libraries
$SuspendedLibrariesQuery = "SELECT * FROM `$sealLIB` WHERE suspend = 1 AND participant = 1";
$RetVal = mysqli_query($db, $SuspendedLibrariesQuery);
$SuspendedLibraries = mysqli_num_rows($RetVal);

// Configuration Problems
$ConfigProblemsEmail = mysqli_num_rows(mysqli_query($db, "SELECT * FROM `$sealLIB` WHERE `ill_email` = '' AND participant = 1"));
$ConfigProblemsAlias = mysqli_num_rows(mysqli_query($db, "SELECT * FROM `$sealLIB` WHERE alias = '' AND participant = 1"));

// Active Libraries (last 30 days)
$RequestedMaterials = mysqli_num_rows(mysqli_query($db,
    "SELECT DISTINCT(`Requester LOC`) FROM `$sealSTAT` WHERE Timestamp BETWEEN DATE_SUB(NOW(), INTERVAL 30 DAY) AND NOW()"
));
$RequestedMaterialsPercent = $RequestedMaterials / $OpenLibraryCount;

$RespondedRequests = mysqli_num_rows(mysqli_query($db,
    "SELECT DISTINCT(Destination) FROM `$sealSTAT` WHERE Timestamp BETWEEN DATE_SUB(NOW(), INTERVAL 30 DAY) AND NOW() AND Fill < 2"
));
$RespondedRequestsPercent = $RespondedRequests / $OpenLibraryCount;

// ==========================================================
// 4️⃣ Display Statistics
// ==========================================================
echo "<b>Total Library Count:</b> $TotalLibraryCount<br>";
echo "<b>Loaning Libraries:</b> $OpenLibraryCount (" . number_format($OpenLibraryPercent * 100, 2) . "%)<br>";
echo "<b>Non-Loaning Libraries:</b> $ClosedLibraryCount (" . number_format($ClosedLibraryPercent * 100, 2) . "%)<br>";
echo "- Non-participating Libraries: $NonPartLibraries<br>";
echo "- Suspended Libraries: $SuspendedLibraries<br>";
echo "- Configuration Problems (missing email): $ConfigProblemsEmail<br>";
echo "- Configuration Problems (missing alias): $ConfigProblemsAlias<br><br>";

echo "<h3>Active Library Users (Last 30 Days)</h3>";
echo "- Requested materials: $RequestedMaterials (" . number_format($RequestedMaterialsPercent * 100, 2) . "%)<br>";
echo "- Responded to requests: $RespondedRequests (" . number_format($RespondedRequestsPercent * 100, 2) . "%)<br>";
?>