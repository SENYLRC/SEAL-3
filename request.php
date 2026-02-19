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

$staff_debug = (bool)array_intersect(['administrator','libstaff'], $user_roles);

require '/var/www/seal_wp_script/seal_function.php';
require '/var/www/seal_wp_script/seal_db.inc';
mysqli_set_charset($db, 'utf8mb4');


// ==========================================================
// Small helpers
// ==========================================================
function seal_clean_loc_list($csv): array
{
    $out = [];
    $csv = is_string($csv) ? trim($csv) : '';
    if ($csv === '') {
        return $out;
    }
    foreach (explode(',', $csv) as $c) {
        $c = strtoupper(trim($c));
        if ($c !== '') {
            $out[] = $c;
        }
    }
    return array_values(array_unique($out));
}

function seal_get_user_primary_loc($user_id): string
{
    $keys = [
        'address_loc_code',     // ✅ this is from lib profile
        'loc_location_code',
        'field_loc_location_code',
        'seal_primary_loc',
        'seal_loc',
        'loc'
    ];

    foreach ($keys as $k) {
        $v = get_user_meta($user_id, $k, true);
        if (is_string($v) && trim($v) !== '') {
            return strtoupper(trim($v));
        }
    }
    return '';
}


function seal_fetch_lib_profile(mysqli $db, string $table, string $loc): array
{
    $loc = strtoupper(trim((string)$loc));
    if ($loc === '') {
        return ['ok' => false];
    }

    $loc_esc = mysqli_real_escape_string($db, $loc);


    $sql = "
      SELECT
        `loc`,
        `Name`,
        `system`,
        `ill_email`,
        `phone`,
        `address1`,
        `address2`,
        `address3`
      FROM `$table`
      WHERE UPPER(TRIM(`loc`)) = '$loc_esc'
      LIMIT 1
    ";

    $q = mysqli_query($db, $sql);
    if (!$q) {
        return ['ok' => false,'sql' => $sql,'sql_error' => mysqli_error($db)];
    }
    $row = mysqli_fetch_assoc($q);
    if (!$row) {
        return ['ok' => false,'sql' => $sql];
    }

    $name = trim((string)($row['Name'] ?? $loc));

    $a1 = trim((string)($row['address1'] ?? ''));
    $a2 = trim((string)($row['address2'] ?? ''));
    $a3 = trim((string)($row['address3'] ?? ''));


    // street line(s) + city/state/zip (often stored in address3)
    $street1 = $a1;
    $street2 = $a2;
    $citystzip = $a3;

    return [
        'ok' => true,
        'loc' => $loc,
        'Name' => $name,
        'system' => trim((string)($row['system'] ?? '')),
        'ill_email' => trim((string)($row['ill_email'] ?? '')),
        'phone' => trim((string)($row['phone'] ?? '')),
        'street1' => $street1,
        'street2' => $street2,
        'citystzip' => $citystzip,
    ];
}

// ==========================================================
// Form state flags
// ==========================================================
$library_error = false;
$submission_success = false;

// ==========================================================
// Pre-load XML data from curl (DO NOT CHANGE as requested)
// ==========================================================
$jession  = $_GET['jsessionid'] ?? '';
$windowid = $_GET['windowid'] ?? '';
$idc      = $_GET['id'] ?? '';

$reqserverurl = 'https://senylrc.indexdata.com/service-proxy/?command=record\\&windowid=';
$cmd = "curl -b JSESSIONID=$jession $reqserverurl$windowid\\&id=" . urlencode($idc);
$output = shell_exec($cmd);

// Parse XML
$records = new SimpleXMLElement($output);
$requestedtitle  = htmlspecialchars($records->{'md-title-complete'}, ENT_QUOTES);
$requestedtitle2 = htmlspecialchars($records->{'md-title-number-section'}, ENT_QUOTES);
$requestedauthor = htmlspecialchars($records->{'md-author'}, ENT_QUOTES);
$itemtype        = trim($records->{'md-medium'});
$pubdate         = $records->{'md-date'};
$isbn            = $records->{'md-isbn'};
$issn            = $records->location->{'md-issn'};

// ==========================================================
// Requester identity
// ==========================================================
$field_first_name = get_user_meta($current_user->ID, 'first_name', true);
$field_last_name  = get_user_meta($current_user->ID, 'last_name', true);
$email            = $current_user->user_email;

// Work phone
$field_work_phone = get_user_meta($current_user->ID, 'work_phone', true);
if (!is_string($field_work_phone)) {
    $field_work_phone = '';
}

// ==========================================================
// Library Profile Selector: primary LOC + seal_extra_locs
// ==========================================================
$primary_loc = seal_get_user_primary_loc($current_user->ID);

// extra LOCs from user meta: "NHIGS,NWATTJ"
$extra_locs_raw = get_user_meta($current_user->ID, 'seal_extra_locs', true);
$extra_locs_raw = is_string($extra_locs_raw) ? trim($extra_locs_raw) : '';
$extra_locs     = seal_clean_loc_list($extra_locs_raw);

// normalize list
$all_locs = array_values(array_unique(array_filter(array_merge([strtoupper($primary_loc)], $extra_locs))));
$has_multi = (count($all_locs) > 1);

// selected requester loc (from POST/GET), default primary
$selected_req_loc = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_req_loc = strtoupper(trim((string)($_POST['requester_loc_select'] ?? '')));
} else {
    $selected_req_loc = strtoupper(trim((string)($_GET['requester_loc_select'] ?? '')));
}
if ($selected_req_loc === '' || !in_array($selected_req_loc, $all_locs, true)) {
    $selected_req_loc = $all_locs[0] ?? strtoupper($primary_loc);
}

// Fetch selected profile
$profile = ['ok' => false];
if (isset($db) && ($db instanceof mysqli)) {
    $profile = seal_fetch_lib_profile($db, $sealLIB, $selected_req_loc);
}

// Map profile to the fields for existing page uses
$field_loc_location_code  = $selected_req_loc;
$field_your_institution   = ($profile['ok'] ?? false) ? $profile['Name'] : $selected_req_loc;
$field_req_system         = ($profile['ok'] ?? false) ? trim((string)($profile['system'] ?? '')) : '';

// address lines for existing layout
$field_street_address  = ($profile['ok'] ?? false) ? $profile['street1'] : '';
$field_street_address2 = ($profile['ok'] ?? false) ? $profile['street2'] : '';
$field_city_state_zip  = ($profile['ok'] ?? false) ? $profile['citystzip'] : '';

// ==========================================================
// Check for form submission
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_REQUEST['libdestination'])) {
        $library_error = true;
    } else {
        $submission_success = true;
    }
}

?>
<!DOCTYPE html>
<html>
<head>
  <title>SEAL Request</title>
  <style>
    .alert.success { background: #dff0d8; color: #3c763d; padding: 10px; margin-bottom: 15px; border: 1px solid #d6e9c6; }
    .alert.error { background: #f2dede; color: #a94442; padding: 10px; margin-bottom: 15px; border: 1px solid #ebccd1; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1em; }
    .grayout {
  color: #595959;      /* higher contrast on white (ADA-friendly) */
  font-style: italic;
  margin-top: 5px;
  font-size: 0.95em;  
}

    .actions { margin-top: 20px; }

    :focus-visible { outline: 3px solid #ffbf47; outline-offset: 2px; }

    
    .multiplereq, .singlereq { margin-bottom: 6px; }
    .librarycheck { margin-right: 6px; }
  </style>

  <script>
 
  document.addEventListener("DOMContentLoaded", function () {
    multiRequest();
    var m = document.getElementById("multiCheck");
    var s = document.getElementById("singleCheck");
    if (m) m.addEventListener("click", multiRequest);
    if (s) s.addEventListener("click", multiRequest);
  });

  function multiRequest() {
    var multi = document.getElementById('multiCheck') && document.getElementById('multiCheck').checked;
    document.querySelectorAll(".librarycheck").forEach(function(el){ el.checked = false; });
    document.querySelectorAll(".multiplereq").forEach(function(el){ el.style.display = multi ? 'block' : 'none'; });
    document.querySelectorAll(".singlereq").forEach(function(el){ el.style.display = multi ? 'none' : 'block'; });
  }

  // ADA-friendly show/hide for article section
  function yesnoCheck() {
    var yes = document.getElementById('yesCheck') && document.getElementById('yesCheck').checked;
    var ifYes = document.getElementById('ifYes');
    if (ifYes) ifYes.style.display = yes ? 'block' : 'none';
  }
  </script>
</head>
<body>

<div class="seal-respond">

<?php if ($submission_success): ?>

<?php
// IMPORTANT: We only ensure requester inst/address now come from the profile selection.
$illsystemhost = $_SERVER["SERVER_NAME"];

    require '/var/www/seal_wp_script/seal_db.inc';
    $db = mysqli_connect($dbhost, $dbuser, $dbpass);
    mysqli_select_db($db, $dbname);
    $today = date('Y-m-d H:i:s');


    $borrower_confirm_html = '';
    $borrower_confirm_text = '';
    // Loop through all selected libraries
    $destinationCount = count($_POST['libdestination']);
    $borrower_ills = [];
    $borrower_dests = [];

    foreach ($_POST['libdestination'] as $destination) {
        list($libcode, $library, $destsystem, $itemavail, $itemcall, $itemlocation, $destemail, $destloc) = explode(":", $destination);

        // Query the lending library's Illiad details
        $illiadchecksql = "SELECT IlliadDATE, IlliadURL, Illiad, APIkey, LibEmailAlert FROM `$sealLIB` WHERE `loc` = '" . mysqli_real_escape_string($db, $destloc) . "'";
        $illiadGETLIST = mysqli_query($db, $illiadchecksql);
        $illiadrow = mysqli_fetch_assoc($illiadGETLIST);

        $libilliadurl = $illiadrow["IlliadURL"] ?? '';
        $libilliaddate = $illiadrow["IlliadDATE"] ?? '';
        $libilliad = $illiadrow["Illiad"] ?? '';
        $libilliadkey = $illiadrow["APIkey"] ?? '';
        $libemailalert = $illiadrow["LibEmailAlert"] ?? '';

        // Sanitize and trim inputs
        $ititle = trim(mysqli_real_escape_string($db, $_POST['bibtitle']));
        $article = trim(mysqli_real_escape_string($db, $_POST['article'] ?? ''));
        $iauthor = trim(mysqli_real_escape_string($db, $_POST['bibauthor']));
        $pubdate = trim(mysqli_real_escape_string($db, $_POST['pubdate']));
        $isbn = trim(mysqli_real_escape_string($db, $_POST['isbn']));
        $issn = trim(mysqli_real_escape_string($db, $_POST['issn']));
        $itemcall = trim(mysqli_real_escape_string($db, $itemcall));
        $itemlocation = trim(mysqli_real_escape_string($db, $itemlocation));
        $itype = trim(mysqli_real_escape_string($db, $_POST['bibtype']));
        $itemavail = trim(mysqli_real_escape_string($db, $itemavail));

        // These now come from selected library profile
        $inst = trim(mysqli_real_escape_string($db, $_POST['inst']));
        $reqLOCcode = $selected_req_loc;
        $saddress  = trim(mysqli_real_escape_string($db, $_POST['address']));
        $saddress2 = trim(mysqli_real_escape_string($db, $_POST['address2']));
        $caddress  = trim(mysqli_real_escape_string($db, $_POST['caddress']));

        $fname = trim(mysqli_real_escape_string($db, $_POST['fname']));
        $lname = trim(mysqli_real_escape_string($db, $_POST['lname']));
        
        $primary_email = trim($_POST['email']);

$alt_email = get_user_meta($current_user->ID, 'alt_email', true);
$alt_email = is_string($alt_email) ? trim($alt_email) : '';

$combined_email = $primary_email;

if ($alt_email !== '' && is_email($alt_email)) {
    $combined_email .= ', ' . $alt_email;
}

$email = mysqli_real_escape_string($db, $combined_email);

        $needbydate = trim(mysqli_real_escape_string($db, $_POST['needbydate']));
        $reqnote = trim(mysqli_real_escape_string($db, $_POST['reqnote']));
        $patronnote = trim(mysqli_real_escape_string($db, $_POST['patronnote']));
        $wphone = trim(mysqli_real_escape_string($db, $_POST['wphone']));
      
        // -------------------------------
        // Article field handling (unchanged)
        // -------------------------------
        $arttile = $artauthor = $artissue = $artvolume = $artpage = $artmonth = $artyear = $artcopyright = '';

        if (isset($_REQUEST['arttile'])) {
            $arttile = mysqli_real_escape_string($db, $_REQUEST['arttile']);
        }
        if (isset($_REQUEST['artauthor'])) {
            $artauthor = mysqli_real_escape_string($db, $_REQUEST['artauthor']);
        }
        if (isset($_REQUEST['artissue'])) {
            $artissue = mysqli_real_escape_string($db, $_REQUEST['artissue']);
        }
        if (isset($_REQUEST['artvolume'])) {
            $artvolume = mysqli_real_escape_string($db, $_REQUEST['artvolume']);
        }
        if (isset($_REQUEST['artpage'])) {
            $artpage = mysqli_real_escape_string($db, $_REQUEST['artpage']);
        }
        if (isset($_REQUEST['artmonth'])) {
            $artmonth = mysqli_real_escape_string($db, $_REQUEST['artmonth']);
        }
        if (isset($_REQUEST['artyear'])) {
            $artyear = mysqli_real_escape_string($db, $_REQUEST['artyear']);
        }
        if (isset($_REQUEST['artcopyright'])) {
            $artcopyright = mysqli_real_escape_string($db, $_REQUEST['artcopyright']);
        }

        $article = "Article Title: $arttile <br>Article Author: $artauthor <br>Volume: $artvolume <br>Issue: $artissue <br>Pages: $artpage <br>Month: $artmonth <br>Year: $artyear <br>Copyright: $artcopyright";

        // Final INSERT SQL (unchanged)
        $sql = "INSERT INTO `$sealSTAT`
      (`illNUB`,`Title`,`Author`,`pubdate`,`reqisbn`,`reqissn`,`itype`,`Call Number`,`Location`,`Available`,`article`,`needbydate`,`reqnote`,`patronnote`,`Destination`,`DestSystem`,`Requester lib`,`Requester LOC`,`ReqSystem`,`Requester person`,`requesterEMAIL`,`Timestamp`,`Fill`,`responderNOTE`,`requesterPhone`,`saddress`,`saddress2`,`caddress`)
      VALUES (
          '0',
          '$ititle',
          '$iauthor',
          '$pubdate',
          '$isbn',
          '$issn',
          '$itype',
          '$itemcall',
          '$itemlocation',
          '$itemavail',
          '$article',
          '$needbydate',
          '$reqnote',
          '$patronnote',
          '$destloc',
          '$destsystem',
          '$inst',
          '$reqLOCcode',
          '$field_req_system ',
          '$fname $lname',
          '$email',
          '$today',
          '3',
          '',
          '$wphone',
          '$saddress',
          '$saddress2',
          '$caddress'
      )";

        if (!mysqli_query($db, $sql)) {
            $headers  = "From: Southeastern SEAL <donotreply@senylrc.org>\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
            $headers = preg_replace('/(?<!\r)\n/', "\r\n", $headers);
            $msg = "INSERT FAILED:<br><br><code>" . htmlspecialchars($sql) . "</code><br><br>Error: " . mysqli_error($db);
            mail("spalding@senylrc.org", "SEAL Request DB Insert Failure", $msg, $headers, "-f donotreply@senylrc.org");
        }

        $sqlidnumb = mysqli_insert_id($db);

        if ($sqlidnumb > 0) {
            $yearid = date('Y');
            $illnum = "$yearid-$sqlidnumb";
            $illnum = mysqli_real_escape_string($db, $illnum);
            $borrower_ills[]  = $illnum;
            $borrower_dests[] = $destlibname;


            $sqlupdate = "UPDATE `$sealSTAT` SET `illNUB` = '$illnum' WHERE `index` = $sqlidnumb";
            if (!mysqli_query($db, $sqlupdate)) {
                $headers  = "From: Southeastern SEAL <donotreply@senylrc.org>\r\n";
                $headers .= "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
                $headers = preg_replace('/(?<!\r)\n/', "\r\n", $headers);
                $errormsg = "Failed to update illNUB:<br><br><code>$sqlupdate</code><br><br>Error: " . mysqli_error($db);
                mail("spalding@senylrc.org", "SEAL ILL Number Update Failure", $errormsg, $headers, "-f donotreply@senylrc.org");
            }
        } else {
            $headers  = "From: Southeastern SEAL <donotreply@senylrc.org>\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
            $headers = preg_replace('/(?<!\r)\n/', "\r\n", $headers);
            $msg = "mysqli_insert_id() failed. Could not retrieve ID for assigning illNUB.";
            mail("spalding@senylrc.org", "SEAL Insert ID Retrieval Failure", $msg, $headers, "-f donotreply@senylrc.org");
        }

        // Send to ILLiad via API
        if ($libilliad == '1') {
            $sqlseloclc = "SELECT loc,Name,`ill_email`,address2,address3,OCLC,`system` FROM `$sealLIB` WHERE `loc`='$reqLOCcode'";
            //for debugging
            //echo $sqlseloclc;
            $sqlseloclcGETLIST = mysqli_query($db, $sqlseloclc);
            $sqlseloclcGETLISTCOUNT = '1';
            $sqlseloclcrow = mysqli_fetch_assoc($sqlseloclcGETLIST);
            $libreqOCLC = $sqlseloclcrow["oclc"];
            $libreqLOC = $sqlseloclcrow["loc"];
            $libreqemail = $sqlseloclcrow["ill_email"];
            $libreqname = $sqlseloclcrow["Name"];
            $libreqsystem =  $sqlseloclcrow["system"];
            $libreqaddress2 = $sqlseloclcrow["address2"];
            $libreqaddress3 = $sqlseloclcrow["address3"];
            $libreqaddress3 = trim($libreqaddress3);
            $libreqaddress3 = str_replace(',', '', $libreqaddress3);
            $pieces = explode(" ", $libreqaddress3);
            $libreqcity = $pieces[0];
            $libreqstate = $pieces[1];
            $libreqzip = $pieces[2];

            $sqlilliadmp = "SELECT * FROM `$sealILLiadMapping` WHERE `LOC`='$reqLOCcode' and `illiadID`='$destloc'";
            // echo $sqlilliadmp."<br>";
            $sqlilliadmpGETLIST = mysqli_query($db, $sqlilliadmp);
            $sqlilliadmpGETLISTCOUNT = '1';
            $sqlilliadmprow = mysqli_fetch_assoc($sqlilliadmpGETLIST);
            $illiadADDnumb  = $sqlilliadmprow["illiadADDnumb"];
            $illiadLIBSymbol =  $sqlilliadmprow["illiadLIBSymbol"];
            // Add slashes to these string to prevent coding issue
            $ititle = addslashes($ititle);
            $iauthor = addslashes($iauthor);
            //Generate the due date, requrired for ILLiad Loans
            if (ctype_digit($libilliaddate)) {
                $date = date("Y-m-d");
                $illduedateCAL = date('Y-m-d', strtotime($date. ' + '.$libilliaddate.' days'));
            }
            //for testing
            //echo $illduedateCAL."part 2".$date."part 3".$libilliaddate."";
            // Store data for request in array
            if (empty($arttile)) {
                //book request have to be sent as an article or API won't take them
                //note about being a book loan is set so ILLiad users know to press loan radio button
                $jsonstr = array( 'Username' => 'Lending','LendingString' => $reqnote, 'RequestType' => 'Loan','DueDate' => $illduedateCAL.'T00:00:00-04:00','ProcessType' => 'Lending','LenderAddressNumber' => $illiadADDnumb,'LendingLibrary' => $illiadLIBSymbol,'TransactionStatus' => 'Awaiting Lending Request Processing','LoanTitle' => $ititle,'LoanAuthor' => $iauthor,'CallNumber' => $itemcall,'LoanDate' => $pubdate,'ISSN' => $isbn,'ILLNumber' => $illnum ,'TAddress' => $libreqname,'TAddress2' => $libreqaddress2,'TCity' => $libreqcity,'TState' => $libreqcity,'TZip' => $libreqzip,'TEMailAddress' => $libreqemail);
            } else {
                $jsonstr = array('Username' => 'Lending','LendingString' => $reqnote, 'ProcessType' => 'Lending','LenderAddressNumber' => $illiadADDnumb,'LendingLibrary' => $illiadLIBSymbol,'TransactionStatus' => 'Awaiting Lending Request Processing','LoanTitle' => $ititle,'LoanAuthor' => $iauthor,'CallNumber' => $itemcall,'LoanDate' => $pubdate,'PhotoArticleTitle' => $arttile,'PhotoArticleAuthor' => $artauthor,'PhotoJournalVolume' => $artvolume,'PhotoJournalIssue' => $artissue,'PhotoJournalYear' => $artyear,'PhotoJournalInclusivePages' => $artpage,'ISSN' => $issn,'ILLNumber' => $illnum,'TAddress' => $libreqname,'TAddress2' => $libreqaddress2,'TCity' => $libreqcity,'TState' => $libreqcity,'TZip' => $libreqzip,'TEMailAddress' => $libreqemail );
            }

            // Enocde the array in to json data
            $json_enc = json_encode($jsonstr);

            //just so we can see this on screen
            //echo "<br /><br /><br />";
            //echo $json_enc;
            //echo "<br /><br /><br />";
            // variables to pass through cURL

            define("ILLIAD_REQUEST_TOKEN_URL", $libilliadurl);

            $key = $libilliadkey;
            // create the cURL request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, ILLIAD_REQUEST_TOKEN_URL);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_enc);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // commenting this out prints to screen (via echo)
            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,
                array(
                  "Content-Type: application/json",
                  "Content-Length: " . strlen($json_enc),
                  "ApiKey: $key")
            );

            // make the call
            if (!curl_errno($ch)) {
                // $output contains the output string
                $output = curl_exec($ch);
            }

            // close curl resource to free up system resources
            curl_close($ch);


            // print the results of the call to the screen
            echo "<!--API output-->";
            echo "<!--".$output."-->";
            // Decode response (may be JSON, may be HTML/text)
            $output_decoded = json_decode($output, true);
            $illiadtxnub = $output_decoded['TransactionNumber'];
            $illstatus = $output_decoded['TransactionStatus'];

            // If response does not have a TransactionNumber, send email
            // (treat as failure if missing OR not numeric OR too short)
if ($illiadtxnub === '' || !ctype_digit($illiadtxnub) || strlen($illiadtxnub) < 4) {

    $headers  = "From: Southeastern SEAL <donotreply@senylrc.org>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
    $headers  = preg_replace('/(?<!\r)\n/', "\r\n", $headers);

    $messagereq = ""
      . "<p><b>ILLiad API Failure</b></p>"
      . "<p><b>SEAL ILL:</b> " . htmlspecialchars($illnum) . "</p>"
      . "<p><b>Destination LOC:</b> " . htmlspecialchars($destloc) . "</p>"
      . "<p><b>Requester LOC:</b> " . htmlspecialchars($reqLOCcode) . "</p>"
      . "<p><b>ILLiad URL:</b> " . htmlspecialchars($libilliadurl) . "</p>"
      . "<p><b>Returned TransactionNumber:</b> " . htmlspecialchars($illiadtxnub) . "</p>"
      . "<p><b>Returned TransactionStatus:</b> " . htmlspecialchars($illstatus) . "</p>"
      . "<p><b>Payload Sent (JSON):</b><br><pre>" . htmlspecialchars($json_enc) . "</pre></p>"
      . "<p><b>Raw Response:</b><br><pre>" . htmlspecialchars($output) . "</pre></p>";

    mail("noc@senylrc.org, spalding@senylrc.org", "ILLiad Failure (no TransactionNumber)", $messagereq, $headers, "-f donotreply@senylrc.org");
}

            // save API output to the request (even if blank, but we at least won't throw notices)
            $illiadtxnub_esc = mysqli_real_escape_string($db, $illiadtxnub);
            $illstatus_esc   = mysqli_real_escape_string($db, $illstatus);
            $sqlupdate2 = "UPDATE `$sealSTAT` SET `IlliadStatus` = '$illstatus_esc', `IlliadTransID` = '$illiadtxnub_esc' WHERE `index` = $sqlidnumb";
            
            //echo $sqlupdate2;

            if (mysqli_query($db, $sqlupdate2)) {
                //mysqli_query($db, $sqlupdate2);
                //no error and everthing is fine
            } else {
                // Something happen and could not update request, will email the sql to admin
                $headers = "From: Southeastern SEAL <dontreply@senylrc.org>\r\n" ;
                $headers .= "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
                $messagereq = "UPDATE SENYLRC-SEAL2-STATS SET IlliadStatus = ".$illstatus.", IlliadTransID = ".$illiadtxnub." WHERE index = ".$sqlidnumb." ";
                $headers = preg_replace('/(?<!\r)\n/', "\r\n", $headers);
                mail("spalding@senylrc.org", "sql update Failure", $messagereq, $headers, "-f donotreply@senylrc.org");
            }
        }// end the $libilliad check

        $requester_email = $combined_email;

        $lib_parts = explode(':', $_POST['libdestination'][0]);
        $destination_email = $lib_parts[6]; // Can be semicolon-separated list

        // Build shared email headers
        $headers  = "From: Southeastern SEAL <dontreply@senylrc.org>\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
        $headers = preg_replace('/(?<!\r)\n/', "\r\n", $headers);

        // Email to requester
        $subject_to_requester = "SEAL ILL #$illnum — Request to $library Confirmation";


        // Check if it's an article request
        $isArticle = !empty($_POST['arttile']);

        $articleDetails = "";
        if ($isArticle) {
            $articleDetails = "
    <h4>Article Details</h4>
    <ul>
      <li><strong>Article Title:</strong> " . htmlspecialchars($_POST['arttile']) . "</li>
      <li><strong>Article Author:</strong> " . htmlspecialchars($_POST['artauthor']) . "</li>
      <li><strong>Volume:</strong> " . htmlspecialchars($_POST['artvolume']) . "</li>
      <li><strong>Issue:</strong> " . htmlspecialchars($_POST['artissue']) . "</li>
      <li><strong>Pages:</strong> " . htmlspecialchars($_POST['artpage']) . "</li>
      <li><strong>Month:</strong> " . htmlspecialchars($_POST['artmonth']) . "</li>
      <li><strong>Year:</strong> " . htmlspecialchars($_POST['artyear']) . "</li>
      <li><strong>Copyright Compliance:</strong> " . htmlspecialchars($_POST['artcopyright']) . "</li>
    </ul>";
        }

        $message_to_requester = "
<html>
<body>
<p>Dear " . htmlspecialchars($_POST['fname']) . " " . htmlspecialchars($_POST['lname']) . ",</p>

<p>Your ILL request has been submitted successfully. Your request number is <strong>$illnum</strong>.</p>

<h4>Request Summary</h4>
<ul>
  <li><strong>ILL#:</strong> $illnum</li>
  <li><strong>Title:</strong> " . htmlspecialchars($_POST['bibtitle']) . "</li>
  <li><strong>Author:</strong> " . htmlspecialchars($_POST['bibauthor']) . "</li>
  <li><strong>Publication Date:</strong> " . htmlspecialchars($_POST['pubdate']) . "</li>
  <li><strong>ISBN:</strong> " . htmlspecialchars($_POST['isbn']) . "</li>
  <li><strong>Need By Date:</strong> " . htmlspecialchars($_POST['needbydate']) . "</li>
  <li><strong>Request Note:</strong> " . nl2br(htmlspecialchars($_POST['reqnote'])) . "</li>
</ul>

$articleDetails

<p>You will be notified once the request is processed.</p>
<p><a href='https://$illsystemhost/status?num=$illnum&a=6'>Do you need to cancel this request?</a></p>

</body>
</html>
";

        mail($requester_email, $subject_to_requester, $message_to_requester, $headers, "-f donotreply@senylrc.org");

        // Email to lending library
        $subject_to_library = "SEAL ILL #$illnum — New Request for \"" . htmlspecialchars($_POST['bibtitle']) . "\"";
        $articleDetailsLibrary = "";
        if ($isArticle) {
            $articleDetailsLibrary = "
    <h4>Article Details</h4>
    <ul>
      <li><strong>Article Title:</strong> " . htmlspecialchars($_POST['arttile']) . "</li>
      <li><strong>Article Author:</strong> " . htmlspecialchars($_POST['artauthor']) . "</li>
      <li><strong>Volume:</strong> " . htmlspecialchars($_POST['artvolume']) . "</li>
      <li><strong>Issue:</strong> " . htmlspecialchars($_POST['artissue']) . "</li>
      <li><strong>Pages:</strong> " . htmlspecialchars($_POST['artpage']) . "</li>
      <li><strong>Month:</strong> " . htmlspecialchars($_POST['artmonth']) . "</li>
      <li><strong>Year:</strong> " . htmlspecialchars($_POST['artyear']) . "</li>
      <li><strong>Copyright Compliance:</strong> " . htmlspecialchars($_POST['artcopyright']) . "</li>
    </ul>";
        }

        $message_to_library = "
<html>
<body>
<p>A new ILL request has been submitted to your library:</p>

<ul>
  <li><strong>Requester Name:</strong> " . htmlspecialchars($_POST['fname']) . " " . htmlspecialchars($_POST['lname']) . "</li>
  <li><strong>Library:</strong> " . htmlspecialchars($_POST['inst']) . "</li>
  <li><strong>Email:</strong> " . htmlspecialchars($_POST['email']) . "</li>
  <li><strong>Phone:</strong> " . htmlspecialchars($_POST['wphone']) . "</li>
    <li><strong>Mailing Address:</strong><br>
      " . nl2br(htmlspecialchars($_POST['address'])) . "<br>
      " . nl2br(htmlspecialchars($_POST['address2'])) . "<br>
      " . nl2br(htmlspecialchars($_POST['caddress'])) . "
  </li>
</ul>

<h4>Requested Item</h4>
<ul>
  <li><strong>ILL#:</strong> $illnum</li>
  <li><strong>Title:</strong> " . htmlspecialchars($_POST['bibtitle']) . "</li>
  <li><strong>Author:</strong> " . htmlspecialchars($_POST['bibauthor']) . "</li>
  <li><strong>Item Type:</strong> " . htmlspecialchars($_POST['bibtype']) . "</li>
  <li><strong>Publication Date:</strong> " . htmlspecialchars($_POST['pubdate']) . "</li>
  <li><strong>ISBN:</strong> " . htmlspecialchars($_POST['isbn']) . "</li>
  <li><strong>Call Number:</strong> " . htmlspecialchars($itemcall) . "</li>
  <li><strong>Availability Status:</strong> " . htmlspecialchars($_POST['isbn']) . "</li>
  <li><strong>Location:</strong> " . htmlspecialchars($_POST['needbydate']) . "</li>
  <li><strong>Need By Date:</strong> " . htmlspecialchars($_POST['needbydate']) . "</li>
  <li><strong>Request Note:</strong> " . nl2br(htmlspecialchars($_POST['reqnote'])) . "</li>
</ul>

$articleDetailsLibrary

<p>
Will you fill this request?<br>
<a href='https://$illsystemhost/respond?num=$illnum&fill=1'>Yes</a>&nbsp;&nbsp;&nbsp;&nbsp;
<a href='https://$illsystemhost/respond?num=$illnum&fill=0'>No</a>
</p>

</body>
</html>
";

        mail($destination_email, $subject_to_library, $message_to_library, $headers, "-f donotreply@senylrc.org");

    }
// end foreach destination


?>
<p class="alert success">✔️ Your request has been submitted successfully.</p>
<p><strong>Your request number is <?php echo htmlspecialchars($illnum); ?></strong></p>

<h3>Request Summary</h3>
<ul>
  <li><strong>Title:</strong> <?php echo htmlspecialchars($ititle); ?></li>
  <li><strong>Author:</strong> <?php echo htmlspecialchars($iauthor); ?></li>
  <li><strong>Publication Date:</strong> <?php echo htmlspecialchars($pubdate); ?></li>
  <?php if (!empty($isbn)): ?>
    <li><strong>ISBN:</strong> <?php echo htmlspecialchars($isbn); ?></li>
  <?php endif; ?>
  <?php if (!empty($issn)): ?>
    <li><strong>ISSN:</strong> <?php echo htmlspecialchars($issn); ?></li>
  <?php endif; ?>
  <li><strong>Item Type:</strong> <?php echo htmlspecialchars($itype); ?></li>
  <li><strong>Call Number:</strong> <?php echo htmlspecialchars($itemcall); ?></li>
  <?php if ($destinationCount === 1): ?>
    <li><strong>Requested From:</strong> <?php echo htmlspecialchars($library); ?></li>
  <?php endif; ?>
</ul>
<?php if (!empty($arttile)) : ?>
  <h4>Article Details</h4>
  <ul>
    <li><strong>Article Title:</strong> <?php echo htmlspecialchars($arttile); ?></li>
    <li><strong>Article Author:</strong> <?php echo htmlspecialchars($artauthor); ?></li>
    <li><strong>Volume:</strong> <?php echo htmlspecialchars($artvolume); ?></li>
    <li><strong>Issue:</strong> <?php echo htmlspecialchars($artissue); ?></li>
    <li><strong>Pages:</strong> <?php echo htmlspecialchars($artpage); ?></li>
    <li><strong>Month:</strong> <?php echo htmlspecialchars($artmonth); ?></li>
    <li><strong>Year:</strong> <?php echo htmlspecialchars($artyear); ?></li>
    <li><strong>Copyright:</strong> <?php echo htmlspecialchars($artcopyright); ?></li>
  </ul>
<?php endif; ?>


<?php if ($destinationCount > 1): ?>
  <p><strong>This request was sent to <?php echo $destinationCount; ?> libraries.</strong></p>
<?php endif; ?>

<h4>Requester Info</h4>
<ul>
  <li><strong>Name:</strong> <?php echo htmlspecialchars($fname . ' ' . $lname); ?></li>
  <li><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></li>
  <li><strong>Institution:</strong> <?php echo htmlspecialchars($field_your_institution); ?></li>
</ul>

<p><a href="/" style="display:inline-block; margin-top:15px; padding:10px 20px; background:#337ab7; color:#fff; text-decoration:none; border-radius:5px;">Submit another request</a></p>

<?php
// ✅ Allow WordPress to continue rendering the sidebar and footer
get_footer();
return;
?>

<?php endif; // end submission_success?>

  <p>Please review the details of your request and then select a library to send your request to.</p>

  <?php if ($library_error): ?>
    <div class="alert error" role="alert" aria-live="assertive">
      Please select at least one library to send your request to.
    </div>
  <?php endif; ?>

  <form action="" method="post" novalidate>

    <h4>Requester Details</h4>

    <?php if ($has_multi): ?>
      <div style="margin-bottom:12px;">
        <label for="requester_loc_select"><strong>Use Library Profile</strong></label><br>
        <select id="requester_loc_select" name="requester_loc_select" onchange="this.form.submit()">
          <?php foreach ($all_locs as $lc): ?>
            <option value="<?php echo esc_attr($lc); ?>" <?php echo selected($lc, $selected_req_loc, false); ?>>
  <?php echo ($lc === $selected_req_loc)
      ? esc_html($field_your_institution . " ($lc)")
      : esc_html($lc);
  ?>
</option>

          <?php endforeach; ?>
        </select>
        <div class="grayout">Default is your primary library. Changing this updates Institution and Mailing Address.</div>
      </div>
    <?php else: ?>
      <input type="hidden" name="requester_loc_select" value="<?php echo esc_attr($selected_req_loc); ?>">
    <?php endif; ?>

    <div class="form-grid">
      <div><span>Name:&nbsp;</span><strong><?php echo esc_html(trim($field_first_name . " " . $field_last_name)); ?></strong></div>
      <div><span>E-mail:&nbsp;</span><strong><?php echo esc_html($email); ?></strong></div>
      <div><span>Institution:&nbsp;</span><strong><?php echo esc_html($field_your_institution); ?></strong></div>
      <div><span>Work Phone:&nbsp;</span><strong><?php echo esc_html($field_work_phone); ?></strong></div>
      <div style="grid-column:1/-1">
        <span>Mailing Address:&nbsp;</span><br>
        <strong>
          <?php
            if (trim($field_street_address . $field_city_state_zip) === '') {
                echo "<span class='grayout'>(No mailing address on file for {$selected_req_loc}.)</span>";
            } else {
                echo esc_html($field_street_address) . "<br>";
                if (trim($field_street_address2) !== '') {
                    echo esc_html($field_street_address2) . "<br>";
                }
                echo esc_html($field_city_state_zip);
            }
?>
        </strong>
      </div>
    </div>

    <!-- Hidden fields (now sourced from selected profile) -->
    <input type="hidden" name="fname" value="<?php echo esc_attr($field_first_name); ?>">
    <input type="hidden" name="lname" value="<?php echo esc_attr($field_last_name); ?>">
    <input type="hidden" name="email" value="<?php echo esc_attr($email); ?>">
    <input type="hidden" name="inst" value="<?php echo esc_attr($field_your_institution); ?>">
    <input type="hidden" name="address" value="<?php echo esc_attr($field_street_address); ?>">
    <input type="hidden" name="address2" value="<?php echo esc_attr($field_street_address2); ?>">
    <input type="hidden" name="caddress" value="<?php echo esc_attr($field_city_state_zip); ?>">
    <input type="hidden" name="wphone" value="<?php echo esc_attr($field_work_phone); ?>">
    <input type="hidden" name="reqLOCcode" value="<?php echo esc_attr($field_loc_location_code); ?>">

    <h4>Request Details</h4>
    <div class="form-grid">
      <div class="full-row" style="grid-column:1/-1;">
        <label for="needbydate">Need by date</label><br>
        <input type="text" id="needbydate" name="needbydate" style="width:100%;">
      </div>
      <div class="full-row" style="grid-column:1/-1;">
        <label for="reqnote">Note</label><br>
        <input type="text" id="reqnote" name="reqnote" style="width:100%;">
      </div>
      <div class="full-row" style="grid-column:1/-1;">
       
        <label for="patronnote">Patron Name or Barcode</label><br>
 <em>Patron information is optional; please follow your local policies regarding patron privacy.</em>
        <input type="text" id="patronnote" name="patronnote" style="width:100%;">
      </div>

      <!-- Article Request Section (ADA fieldset/legend) -->
      <div class="full-row" style="grid-column:1/-1;">
        <fieldset style="border:0;padding:0;margin:0;">
          <legend><b>Is this a request for an article?</b></legend>

          <label for="yesCheck">
            <input type="radio" name="yesno" id="yesCheck" onclick="yesnoCheck();"> Yes
          </label>

          <label for="noCheck" style="margin-left:10px;">
            <input type="radio" name="yesno" id="noCheck" checked onclick="yesnoCheck();"> No
          </label>

          <div id="ifYes" style="display:none; margin-top:10px;">
            <label for="arttile"><b>Article Title:</b></label><br>
            <input type="text" id="arttile" name="arttile" style="width:100%;"><br>

            <label for="artauthor"><b>Article Author:</b></label><br>
            <input type="text" id="artauthor" name="artauthor" style="width:100%;"><br>

            <label for="artvolume"><b>Volume:</b></label><br>
            <input type="text" id="artvolume" name="artvolume" style="width:100%;"><br>

            <label for="artissue"><b>Issue:</b></label><br>
            <input type="text" id="artissue" name="artissue" style="width:100%;"><br>

            <label for="artpage"><b>Pages:</b></label><br>
            <input type="text" id="artpage" name="artpage" style="width:100%;"><br>

            <label for="artmonth"><b>Issue Month:</b></label><br>
            <input type="text" id="artmonth" name="artmonth" style="width:100%;"><br>

            <label for="artyear"><b>Issue Year:</b></label><br>
            <input type="text" id="artyear" name="artyear" style="width:100%;"><br>

            <label for="artcopyright"><b>Copyright compliance:</b></label><br>
            <select id="artcopyright" name="artcopyright" style="width:100%;">
              <option value=""></option>
              <option value="ccl">CCL</option>
              <option value="ccg">CCG</option>
            </select>
          </div>
        </fieldset>
      </div>
    </div>

    <h4>Bibliographic Details</h4>
    <table style="width:100%; border-collapse:collapse; margin-bottom:10px;">
      <tr>
        <td style="width:25%; font-weight:bold; border-bottom:0; padding:5px;">Requested Title:</td>
        <td style="width:75%; border-bottom:0; padding:5px;"><?php echo $requestedtitle . " " . $requestedtitle2; ?></td>
      </tr>
      <tr>
        <td style="font-weight:bold; border-bottom:0; padding:5px;">Author:</td>
        <td style="border-bottom:0; padding:5px;"><?php echo $requestedauthor; ?></td>
      </tr>
      <tr>
        <td style="font-weight:bold; border-bottom:0; padding:5px;">Item Type:</td>
        <td style="border-bottom:0; padding:5px;"><?php echo $itemtype; ?></td>
      </tr>
      <tr>
        <td style="font-weight:bold; border-bottom:0; padding:5px;">Publication Date:</td>
        <td style="border-bottom:0; padding:5px;"><?php echo $pubdate; ?></td>
      </tr>
      <?php if (strlen($isbn) > 0): ?>
      <tr>
        <td style="font-weight:bold; border-bottom:0; padding:5px;">ISBN:</td>
        <td style="border-bottom:0; padding:5px;"><?php echo $isbn; ?></td>
      </tr>
      <?php endif; ?>
      <?php if (strlen($issn) > 0): ?>
      <tr>
        <td style="font-weight:bold; border-bottom:0; padding:5px;">ISSN:</td>
        <td style="border-bottom:0; padding:5px;"><?php echo $issn; ?></td>
      </tr>
      <?php endif; ?>
    </table>

    <!-- Hidden bib fields -->
    <input type="hidden" name="bibtitle" value="<?php echo esc_attr($requestedtitle . " " . $requestedtitle2); ?>">
    <input type="hidden" name="bibauthor" value="<?php echo esc_attr($requestedauthor); ?>">
    <input type="hidden" name="bibtype" value="<?php echo esc_attr($itemtype); ?>">
    <input type="hidden" name="pubdate" value="<?php echo esc_attr($pubdate); ?>">
    <input type="hidden" name="isbn" value="<?php echo esc_attr($isbn); ?>">
    <input type="hidden" name="issn" value="<?php echo esc_attr($issn); ?>">

    <h4>Library Selection</h4>

    <fieldset style="border:0;padding:0;margin:0;">
      <legend><b>This is a request for:</b></legend>

      <label for="singleCheck">
        <input type="radio" name="singlemulti" id="singleCheck" checked onclick="multiRequest();">
        Single copy
      </label>

      <label for="multiCheck" style="margin-left:10px;">
        <input type="radio" name="singlemulti" id="multiCheck" onclick="multiRequest();">
        Multiple copies
      </label>
    </fieldset>

    <br><br>

<?php

$loccount = 0;
$deadlibraries = array();

foreach ($records->location as $location) {
    $catalogtype = find_catalog($location['name']);
    $urlrecipe = $location->{'md-url_recipe'};
    $mdid = $location->{'md-id'};

    foreach ($location->holdings->holding as $holding) {

        $itemavail = $holding->localAvailability;
        $itemavail = normalize_availability($itemavail);
        $itemavailtext = set_availability($itemavail);

        $itemcallnum = $holding->callNumber;
        $itemcallnum = htmlspecialchars($itemcallnum, ENT_QUOTES);

        $itemlocation = $holding->localLocation;

        if ($catalogtype == "Worldcat" || $catalogtype == 'cdlc' || $catalogtype == "Millennium") {
            $itemlocation = $location['name'];
        }

        if (($catalogtype == "Innovative") || ($catalogtype == "Alma") ||  ($catalogtype == "Voyager") || ($catalogtype == "Folio") || ($catalogtype == "Symphony") || ($catalogtype == "SirsiDynix")) {
            if (strpos($location['name'], "Adelphi") !== false) {
                $itemlocationAD =  $holding->localLocation;
                if (strpos($itemlocationAD, "Hudson Valley") !== false) {
                    $itemlocation = $location['name'];
                } else {
                    $itemavail = 0;
                }
            } else {
                $itemlocation = $location['name'];
            }
        }

        if (($catalogtype == "OPALS") || ($catalogtype == "Polaris")) {
            $itemlocation =  $holding->localLocation;
        }
        if ($catalogtype == "TLC") {
            $itemlocation = $holding->localLocation;
        }
        if ($catalogtype == "SymphonyRCLS") {
            $itemlocation = $holding->localLocation;
        }
        if ($catalogtype == "InnovativeMHLS") {
            $itemlocation = $holding->localLocation;
        }

        $locationinfo = find_locationinfo($itemlocation, $location['name']);
        $itemlocation = htmlspecialchars($itemlocation, ENT_QUOTES);

        $destill = $locationinfo[0];
        $destpart = $locationinfo[1];
        $destemail = $locationinfo[2];
        $destsuspend = $locationinfo[3];
        $destlibsystem = $locationinfo[4];
        $destlibname = $locationinfo[5];
        $destAlias = $locationinfo[6];

        if (strcmp($destlibsystem, 'MH') == 0) {
            $destlibsystemtxt = "Mid Hudson Library System";
        } elseif (strcmp($destlibsystem, 'RC') == 0) {
            $destlibsystemtxt = "Ramapo Catskill Library System";
        } elseif (strcmp($destlibsystem, 'SE') == 0) {
            $destlibsystemtxt = "SENYLRC";
        } elseif (strcmp($destlibsystem, 'DU') == 0) {
            $destlibsystemtxt = "Dutchess BOCES";
        } elseif (strcmp($destlibsystem, 'OU') == 0) {
            $destlibsystemtxt = "Orange Ulster BOCES";
        } elseif (strcmp($destlibsystem, 'RB') == 0) {
            $destlibsystemtxt = "Rockland BOCES";
        } elseif (strcmp($destlibsystem, 'SB') == 0) {
            $destlibsystemtxt = "Sullivan BOCES";
        } elseif (strcmp($destlibsystem, 'UB') == 0) {
            $destlibsystemtxt = "Ulster BOCES";
        } elseif (strlen($destlibsystem) < 1) {
            $destlibsystemtxt = "All";
        } else {
            $destlibsystemtxt = "SENYLRC Group";
        }

        $destlibname = htmlspecialchars($destlibname, ENT_QUOTES);

        if ($destpart == 1) {
            $desttypeloan = check_itemtype($destill, $itemtype, $destlibsystem);
        }
        if (($catalogtype == "Innovative") && ($itemlocation == "ODY Folio")) {
            $desttypeloan = 1;
        }

        $itemlocallocation = $itemlocation;


        $destfail = 0;
        if ($itemavail == 0) {
            $destfail = 1;
            $failmessage = "Material unavailable, see source ILS/LMS for details";
        }
        if ($destpart == 0) {
            $destfail = 1;
            $failmessage = "Library not particpating in SEAL";
        }
        if (strlen($destemail) < 2) {
            $destfail = 1;
            $failmessage = "Library has no ILL email configured";
        }
        if (($destsuspend == 1) && ($destill != 'ntr')) {
            $destfail = 2;
            $failmessage = "Library not loaning / SEAL ILL Suspend";
        }
        if ($desttypeloan == 0) {
            $destfail = 2;
            $failmessage = "Library not loaning this material type";
        }

        if ($destill == "") {
            $destfail = 1;
            $destlibname = $itemlocation;
            $destlibsystem = "Unknown";
            $failmessage = "No alias match in SEAL directory";
        }
                // -------------------------------
        // DEBUG (only for staff) — emit when a holding fails OR always if you prefer
        // -------------------------------
        if ($staff_debug && $destfail != 0) {
            echo "<!-- \n";
            echo "catalogtype: $catalogtype \n";
            echo "itemavail: $itemavail \n";
            echo "itemavailtext: $itemavailtext \n";
            echo "itemlocallocation: $itemlocallocation \n";
            echo "itemlocation: $itemlocation \n";
            echo "destill: $destill \n";
            echo "destpart: $destpart \n";
            echo "destemail: $destemail \n";
            echo "destsuspend: $destsuspend \n";
            echo "destlibsystem: $destlibsystem \n";
            echo "destlibname: $destlibname \n";
            echo "desttypeloan: $desttypeloan \n";
            echo "failmessage: $failmessage\n";
            echo "--> \n\n";
        }


        if ($destfail == 0) {
            $itemcallnum = preg_replace('/[:]/', ' ', $itemcallnum);
            $itemlocation = preg_replace('/[:]/', ' ', $itemlocation);
            $itemlocallocation = preg_replace('/[:]/', ' ', $itemlocallocation);

            // NOTE: visual output unchanged; ADA is handled by earlier labels/fieldsets.
            $packed = $itemlocation . ":" . $destlibname . ":" . $destlibsystem . ":" . $itemavailtext . ":" . $itemcallnum . ":" . $itemlocallocation . ":" . $destemail . ":" . $destill;
            $uid = 'libdest_cb_' . md5($packed);

            echo "<div class='multiplereq'>
  <input type='checkbox'
         class='librarycheck'
         id='" . esc_attr($uid) . "'
         name='libdestination[]'
         value='" . esc_attr($packed) . "'>
  <label for='" . esc_attr($uid) . "'>
    <strong>" . esc_html($destlibname) . "</strong> (" . esc_html($destlibsystemtxt) . "),
    Availability: " . esc_html($itemavailtext) . ",
    Call Number: " . esc_html($itemcallnum) . "
  </label>
</div>";


            $packed = $itemlocation . ":" . $destlibname . ":" . $destlibsystem . ":" . $itemavailtext . ":" . $itemcallnum . ":" . $itemlocallocation . ":" . $destemail . ":" . $destill;
            $uid = 'libdest_' . md5($packed);

            echo "<div class='singlereq'>
  <input type='radio'
         class='librarycheck'
         id='" . esc_attr($uid) . "'
         name='libdestination[]'
         value='" . esc_attr($packed) . "'>
  <label for='" . esc_attr($uid) . "'>
    <strong>" . esc_html($destlibname) . "</strong> (" . esc_html($destlibsystemtxt) . "),
    Availability: " . esc_html($itemavailtext) . ",
    Call Number: " . esc_html($itemcallnum) . "
  </label>
</div>";


            $loccount = $loccount + 1;
        } elseif ($destfail == 1) {
            // only showing error code 2
        } else {
            $deadlibraries[] = "<div class='grayout'>$destlibname ($destlibsystemtxt), $failmessage</div>";
            echo "<!-- Holding location failed checks. --> \n";
        }
    }

    // Koha/Alexandria section left as-is (unchanged)
    if (($catalogtype == "Koha") || ($catalogtype == "Alexandria")) {
        $seslcchecksum = $location['checksum'];
        $cmdseslc = "curl -b JSESSIONID=$jession $reqserverurl$windowid\\&id=" . urlencode($idc) . "\&checksum=$seslcchecksum\&offset=1";
        $outputseslc = shell_exec($cmdseslc);
        $recordssSESLC = new SimpleXMLElement($outputseslc);

        foreach ($recordssSESLC->d952 as $d952) {
            $itemlocation = $d952->sa;
            $itemcallnum = $d952->so;
            $itemavail = $d952->s7;

            $itemcallnum = str_replace(':', '.', $itemcallnum);

            $itemlocation    = htmlspecialchars($itemlocation, ENT_QUOTES);
            $itemcallnum     = htmlspecialchars($itemcallnum, ENT_QUOTES);
            $itemlocallocation = $itemlocation;

            if ($location['name'] == 'Ramapo-Catskill Library System') {
                $itemavail = trim(strtolower($d952->sk));
            }

            $result = set_koha_availability($itemavail);
            $itemavailtext = $result['status'];
            $itemavail = $result['code'];

            $locationinfo = find_locationinfo($itemlocation, $location['name']);
            $itemlocation = htmlspecialchars($itemlocation, ENT_QUOTES);

            $destill = $locationinfo[0];
            $destpart = $locationinfo[1];
            $destemail = $locationinfo[2];
            $destsuspend = $locationinfo[3];
            $destlibsystem = $locationinfo[4];
            $destlibname = $locationinfo[5];
            $destAlias = $locationinfo[6];

            $destlibname = htmlspecialchars($destlibname, ENT_QUOTES);
            $desttypeloan = check_itemtype($destill, $itemtype, $destlibsystem);
            $itemlocallocation = $itemlocation;

            if (strcmp($destlibsystem, 'MH') == 0) {
                $destlibsystemtxt = "Mid Hudson Library System";
            } elseif (strcmp($destlibsystem, 'RC') == 0) {
                $destlibsystemtxt = "Ramapo Catskill Library System";
            } elseif (strcmp($destlibsystem, 'SE') == 0) {
                $destlibsystemtxt = "SENYLRC";
            } elseif (strcmp($destlibsystem, 'DU') == 0) {
                $destlibsystemtxt = "Dutchess BOCES";
            } elseif (strcmp($destlibsystem, 'OU') == 0) {
                $destlibsystemtxt = "Orange Ulster BOCES";
            } elseif (strcmp($destlibsystem, 'RB') == 0) {
                $destlibsystemtxt = "Rockland BOCES";
            } elseif (strcmp($destlibsystem, 'SB') == 0) {
                $destlibsystemtxt = "Sullivan BOCES";
            } elseif (strcmp($destlibsystem, 'UB') == 0) {
                $destlibsystemtxt = "Ulster BOCES";
            } elseif (strlen($destlibsystem) < 1) {
                $destlibsystemtxt = "All";
            } else {
                $destlibsystemtxt = "SENYLRC Group";
            }


            $destfail = 0;
            if ($destpart == 0) {
                $destfail = 1;
                $failmessage = "Library not particpating in SEAL";
            }
            if ($itemavail == 1) {
                $destfail = 1;
                $failmessage = "Material unavailable, see source ILS/LMS for details";
            }
            if (strlen($destemail) < 2) {
                $destfail = 1;
                $failmessage = "Library has no ILL email configured";
            }
            if ($destsuspend == 1) {
                $destfail = 2;
                $failmessage = "Library not loaning / closed";
            }
            if ($desttypeloan == 0) {
                $destfail = 2;
                $failmessage = "Library not loaning this material type";
            }
            if ($destAlias == "") {
                $destfail = 1;
                $destlibname = $itemlocation;
                $destlibsystem = "Unknown";
                $failmessage = "No alias match in SEAL directory";
            }

            // -------------------------------
            // DEBUG (only for staff)
            // -------------------------------
            if ($staff_debug && $destfail != 0) {
                echo "<!-- \n";
                echo "catalogtype: $catalogtype \n";
                echo "itemavail: $itemavail \n";
                echo "itemavailtext: $itemavailtext \n";
                echo "itemlocallocation: $itemlocallocation \n";
                echo "itemlocation: $itemlocation \n";
                echo "destill: $destill \n";
                echo "destpart: $destpart \n";
                echo "destemail: $destemail \n";
                echo "destsuspend: $destsuspend \n";
                echo "destlibsystem: $destlibsystem \n";
                echo "destlibname: $destlibname \n";
                echo "desttypeloan: $desttypeloan \n";
                echo "failmessage: $failmessage\n";
                echo "--> \n\n";
            }

            
            if ($destfail == 0) {
                $itemcallnum = preg_replace('/[:]/', ' ', $itemcallnum);
                $itemlocation = preg_replace('/[:]/', ' ', $itemlocation);
                $itemlocallocation = preg_replace('/[:]/', ' ', $itemlocallocation);

                $packed = $itemlocation . ":" . $destlibname . ":" . $destlibsystem . ":" . $itemavailtext . ":" . $itemcallnum . ":" . $itemlocallocation . ":" . $destemail . ":" . $destill;
                $uid = 'libdest_cb_' . md5($packed);

                echo "<div class='multiplereq'>
  <input type='checkbox'
         class='librarycheck'
         id='" . esc_attr($uid) . "'
         name='libdestination[]'
         value='" . esc_attr($packed) . "'>
  <label for='" . esc_attr($uid) . "'>
    <strong>" . esc_html($destlibname) . "</strong> (" . esc_html($destlibsystemtxt) . "),
    Availability: " . esc_html($itemavailtext) . ",
    Call Number: " . esc_html($itemcallnum) . "
  </label>
</div>";



                $packed = $itemlocation . ":" . $destlibname . ":" . $destlibsystem . ":" . $itemavailtext . ":" . $itemcallnum . ":" . $itemlocallocation . ":" . $destemail . ":" . $destill;
                $uid = 'libdest_' . md5($packed);

                echo "<div class='singlereq'>
  <input type='radio'
         class='librarycheck'
         id='" . esc_attr($uid) . "'
         name='libdestination[]'
         value='" . esc_attr($packed) . "'>
  <label for='" . esc_attr($uid) . "'>
    <strong>" . esc_html($destlibname) . "</strong> (" . esc_html($destlibsystemtxt) . "),
    Availability: " . esc_html($itemavailtext) . ",
    Call Number: " . esc_html($itemcallnum) . "
  </label>
</div>";


                $loccount = $loccount + 1;
            } elseif ($destfail == 1) {
                $deadlibraries[] = "<div class='grayout'>$destlibname ($destlibsystemtxt), $failmessage</div>";
            } else {
                $deadlibraries[] = "<div class='grayout'>$destlibname ($destlibsystemtxt), $failmessage</div>";
                echo "<!-- Holding location failed checks. --> \n";
            }
        }
        
    }
    
}

foreach ($deadlibraries as $line) {
    echo $line;
}

if ($loccount > 0) {
    echo "<div class='actions'><input type='submit' value='Submit'></div>";
} else {
    echo "<p class='alert error' role='alert' aria-live='polite'><b>Sorry, no available library to route your request at this time.</b> <a href='/'>Try another search?</a></p>";
}
?>

  </form>
</div>

</body>
</html>