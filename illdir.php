<?php
// illdir.php####

require '/var/www/seal_wp_script/seal_function.php';
require '/var/www/seal_wp_script/seal_db.inc';

$db = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
if (!$db) {
    die("Database connection failed: " . mysqli_connect_error());
}

$GETLISTCOUNTwhole = 0;
$GETLIST = false;

// search params (GET method)
$libname = $_GET['libname'] ?? '';
$system  = $_GET['system'] ?? '';

// pagination using pagenum instead of page
$rec_limit = 50;
$pagenum = 0;
if (isset($_GET['pagenum']) && is_numeric($_GET['pagenum'])) {
    $pagenum = (int) $_GET['pagenum'];
    if ($pagenum < 0) $pagenum = 0;
}
$offset = $pagenum * $rec_limit;

// build query
$baseSQL = "SELECT * FROM `$sealLIB` WHERE participant = '1'";
if (!empty($libname)) {
    $safe_libname = mysqli_real_escape_string($db, $libname);
    $baseSQL .= " AND `Name` LIKE '%$safe_libname%'";
}
if (!empty($system)) {
    $safe_system = mysqli_real_escape_string($db, $system);
    $baseSQL .= " AND `system` LIKE '%$safe_system%'";
}

// count total
$retval = mysqli_query($db, $baseSQL);
if ($retval) {
    $GETLISTCOUNTwhole = mysqli_num_rows($retval);
}

// fetch results with limit
$GETLISTSQL = $baseSQL . " ORDER BY Name ASC LIMIT $offset, $rec_limit";
$GETLIST    = mysqli_query($db, $GETLISTSQL);

// ----------------- DEBUGGING -----------------
echo "<!-- DEBUG: pagenum=$pagenum, offset=$offset, rec_limit=$rec_limit -->";
echo "<!-- DEBUG: SQL=$GETLISTSQL -->";
// ---------------------------------------------
?>

<h3>Search the directory</h3>
<form action="/illdir" method="get">
  <b>Library Name:</b> 
  <input type="text" size="60" maxlength="255" name="libname" 
         value="<?php echo htmlspecialchars($libname); ?>"><br>

  <b>Library System:</b> 
  <select name="system">
    <option value=""></option>
    <option value="DU" <?php if($system=="DU") echo "selected"; ?>>Dutchess BOCES</option>
    <option value="MH" <?php if($system=="MH") echo "selected"; ?>>Mid-Hudson Library System</option>
    <option value="OU" <?php if($system=="OU") echo "selected"; ?>>Orange Ulster BOCES</option>
    <option value="RC" <?php if($system=="RC") echo "selected"; ?>>Ramapo Catskill Library System</option>
    <option value="RB" <?php if($system=="RB") echo "selected"; ?>>Rockland BOCES</option>
    <option value="SE" <?php if($system=="SE") echo "selected"; ?>>SENYLRC</option>
    <option value="SB" <?php if($system=="SB") echo "selected"; ?>>Sullivan BOCES</option>
    <option value="UB" <?php if($system=="UB") echo "selected"; ?>>Ulster BOCES</option>
  </select>
  <br>
  <input type="submit" value="Submit">
  <a href="/illdir" class="clear-btn">Clear</a>
</form>

<?php
echo "<p><strong>$GETLISTCOUNTwhole</strong> results</p>";
echo "<!-- DEBUG: total_results=$GETLISTCOUNTwhole -->";

if ($GETLIST && mysqli_num_rows($GETLIST) > 0) {
    echo "<div class='illDirGrid'>";
    $counter = 0;
    while ($row = mysqli_fetch_assoc($GETLIST)) {
        $display_name = $row["Name"];
        $libaddress2  = $row["address2"];
        $libaddress3  = $row["address3"];
        $libphone     = $row["phone"];
        $illemail     = $row["ill_email"];
        $system_code  = $row["system"];
        $oclc         = $row["oclc"];
        $loc          = $row["loc"];
        $libsuspend   = ($row["suspend"] == "0") ? "Yes" : "No";

        $systems = [
            "MH" => "Mid-Hudson Library System",
            "RC" => "Ramapo Catskill Library System",
            "SE" => "Southeastern",
            "OU" => "Orange Ulster BOCES",
            "SB" => "Sullivan BOCES",
            "UB" => "Ulster BOCES",
            "RB" => "Rockland BOCES",
            "DU" => "Dutchess BOCES"
        ];
        $system_name = $systems[$system_code] ?? "Unknown";

        $loan_id = "loaning-" . $counter;

        echo "<div class='illDirCard'>";
        echo "<h4>$display_name</h4>";
        echo "<p><strong>Address:</strong><br>$libaddress2<br>$libaddress3</p>";
        echo "<p><strong>Phone:</strong> $libphone</p>";
        echo "<p><strong>Library System:</strong> $system_name</p>";

        if (!empty($illemail) && isset($user_id) && $user_id > 0) {
            echo "<p><strong>ILL Email(s):</strong> <a href='mailto:$illemail'>$illemail</a></p>";
        }

        echo "<p><strong>OCLC Symbol:</strong> $oclc<br>";
        echo "<strong>LOC Code:</strong> $loc<br>";
        echo "<strong>Accepting Requests:</strong> $libsuspend</p>";

        echo "<button class='loan-btn' data-target='$loan_id'>Show loaning options</button>";
        echo "<div id='$loan_id' class='loaning-options'>";
        echo "Loaning Print Book: <strong>{$row["book_loan"]}</strong><br>";
        echo "Loaning Print Journal or Article: <strong>{$row["periodical_loan"]}</strong><br>";
        echo "Loaning Audio Video Materials: <strong>{$row["av_loan"]}</strong><br>";
        echo "Loaning Reference/Microfilm: <strong>{$row["theses_loan"]}</strong><br>";
        echo "Loaning Electronic Book: <strong>{$row["ebook_request"]}</strong><br>";
        echo "Loaning Electronic Journal: <strong>{$row["ejournal_request"]}</strong><br>";
        echo "</div>";

        echo "</div>";
        $counter++;
    }
    echo "</div>";
} else {
    echo "<p>No results found.</p>";
}

// pagination
if ($GETLISTCOUNTwhole > $rec_limit) {
    echo "<div class='pagination'>";
    $query_params = [];
    if (!empty($libname)) $query_params['libname'] = $libname;
    if (!empty($system))  $query_params['system']  = $system;

    if ($pagenum > 0) {
        $last = $pagenum - 1;
        $qs = http_build_query(array_merge($query_params, ['pagenum'=>$last]));
        echo "<a href='/illdir?$qs'>&laquo; Previous</a>";
    }
    if (($offset + $rec_limit) < $GETLISTCOUNTwhole) {
        $next = $pagenum + 1;
        $qs = http_build_query(array_merge($query_params, ['pagenum'=>$next]));
        echo "<a href='/illdir?$qs'>Next &raquo;</a>";
    }
    echo "</div>";
}
?>

<style>
.illDirGrid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(45%, 1fr));
  gap: 20px;
  margin-top: 20px;
}
.illDirCard {
  background: #fff;
  border: 1px solid #ddd;
  border-radius: 8px;
  padding: 15px 20px;
  box-shadow: 0 2px 5px rgba(0,0,0,0.08);
  font-size: 14px;
  line-height: 1.5;
}
.illDirCard h4 {
  margin-top: 0;
  margin-bottom: 10px;
  font-size: 16px;
  color: #003366;
}
.illDirCard p {
  margin: 6px 0;
}
.loan-btn {
  background: #005ea2;
  color: #fff;
  border: none;
  padding: 6px 12px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 13px;
  margin-top: 10px;
}
.loan-btn:hover {
  background: #004578;
}
.loaning-options {
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.4s ease-out, padding 0.4s ease-out;
  margin-top: 0;
  padding: 0 2px;
}
.loaning-options.open {
  max-height: 500px;
  padding: 10px 2px;
  transition: max-height 0.5s ease-in, padding 0.5s ease-in;
  background: #f9f9f9;
  border-top: 1px solid #ddd;
  margin-top: 8px;
}
.pagination {
  margin-top: 20px;
  text-align: center;
}
.pagination a {
  margin: 0 5px;
  padding: 6px 12px;
  background: #eee;
  border-radius: 4px;
  text-decoration: none;
}
.pagination a:hover {
  background: #ddd;
}
.clear-btn {
  margin-left: 10px;
  padding: 6px 12px;
  background: #ccc;
  border-radius: 4px;
  text-decoration: none;
  font-size: 13px;
  color: #000;
}
.clear-btn:hover {
  background: #bbb;
}
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
  document.querySelectorAll(".loan-btn").forEach(function(button) {
    button.addEventListener("click", function() {
      const targetId = button.getAttribute("data-target");
      const target = document.getElementById(targetId);

      if (target.classList.contains("open")) {
        target.classList.remove("open");
        button.textContent = "Show loaning options";
      } else {
        target.classList.add("open");
        button.textContent = "Hide loaning options";
      }
    });
  });
});
</script>