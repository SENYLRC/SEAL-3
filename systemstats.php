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
//systemstats.php

#####Connect to database
    require '/var/www/seal_wp_script/seal_db.inc';

require '/var/www/seal_wp_script/seal_function.php';
$db = mysqli_connect($dbhost, $dbuser, $dbpass);
mysqli_select_db($db, $dbname);
# Total library Count
$TotalLibraryQuery = "SELECT * FROM `$sealLIB` ";
$RetVal = mysqli_query($db, $TotalLibraryQuery);
$TotalLibraryCount = mysqli_num_rows($RetVal);

# Closed Libraries
$ClosedLibraryQuery = "SELECT * FROM `$sealLIB` where  participant = 0 OR `ill_email` = '' OR alias = '' ";
$RetVal = mysqli_query($db, $ClosedLibraryQuery);
$ClosedLibraryCount = mysqli_num_rows($RetVal);
$ClosedLibraryPercent = $ClosedLibraryCount / $TotalLibraryCount;
# Open Libraries
$OpenLibraryCount = $TotalLibraryCount - $ClosedLibraryCount;
$OpenLibraryPercent = $OpenLibraryCount / $TotalLibraryCount;

# Non Participating Libraries
$NonPartLibrariesQuery = "SELECT * FROM `$sealLIB` where participant = 0 ";
$RetVal = mysqli_query($db, $NonPartLibrariesQuery);
$NonPartLibraries = mysqli_num_rows($RetVal);
# Suspended Libraries
$SuspendedLibrariesQuery = "SELECT * FROM `$sealLIB` where suspend = 1 and participant = 1 ";
$RetVal = mysqli_query($db, $SuspendedLibrariesQuery);
$SuspendedLibraries = mysqli_num_rows($RetVal);

# Configuration Problems
$ConfigProblemsQuery = "SELECT * FROM `$sealLIB` where `ill_email` = '' and participant = 1  ";
$RetVal = mysqli_query($db, $ConfigProblemsQuery);
$ConfigProblemsEmail = mysqli_num_rows($RetVal);

# Configuration Problems ALIAS missing
$ConfigProblemsQuery = "SELECT * FROM `$sealLIB` where alias = '' and participant = 1 ";
$RetVal = mysqli_query($db, $ConfigProblemsQuery);
$ConfigProblemsAlias = mysqli_num_rows($RetVal);

#Active Stats -> Requested materials
$RequestedMaterialsQuery = "SELECT distinct(`Requester LOC`) FROM `$sealSTAT` where Timestamp between date_sub(now(), interval 30 day) and now(); ";
$RetVal = mysqli_query($db, $RequestedMaterialsQuery);
$RequestedMaterials = mysqli_num_rows($RetVal);
$RequestedMaterialsPercent = $RequestedMaterials / $OpenLibraryCount;

$RespondedRequestsQuery = "SELECT distinct(Destination) FROM `$sealSTAT` where Timestamp between date_sub(now(), interval 30 day) and now() AND Fill < 2 ";
$RetVal = mysqli_query($db, $RespondedRequestsQuery);
$RespondedRequests = mysqli_num_rows($RetVal);
$RespondedRequestsPercent = $RespondedRequests / $OpenLibraryCount;

# Report!
echo "Total Library Count: " . $TotalLibraryCount . "<br>";
echo "Loaning Libraries: " . $OpenLibraryCount . " (" . number_format($OpenLibraryPercent * 100, 2) . "%) <br>";
echo "Non-Loaning Libraries: " . $ClosedLibraryCount . " (" . number_format($ClosedLibraryPercent * 100, 2) . "%) <br>";
echo "-Non-participating Libraries: " . $NonPartLibraries . "<br>";
echo "-Suspended Libraries: " . $SuspendedLibraries . "<br>";
echo "-Configuration Problems (missing email): " . $ConfigProblemsEmail . "<br>";
echo "-Configuration Problems (missing alias): " . $ConfigProblemsAlias . "<br><br>";
echo "Active Library Users (Last 30 days) <br>";
echo "-Requested materials: " . $RequestedMaterials . " (" . number_format($RequestedMaterialsPercent * 100, 2) . "%) <br>";
echo "-Responded to requests: " . $RespondedRequests . " (" . number_format($RespondedRequestsPercent * 100, 2) . "%) <br>";
?>