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
// request.php— make ill request
require '/var/www/seal_wp_script/seal_function.php';

$library_error = false;
$submission_success = false;

// Check for form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_REQUEST['libdestination'])) {
        $library_error = true;
        echo "<script>alert('Please select at least one library to send your request to.');</script>";
    } else {
        $submission_success = true;
    }
}

// Pre-load XML data from curl
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
?>

<!DOCTYPE html>
<html>
<head>
  <title>SEAL Request</title>
  <style>
    .alert.success { background: #dff0d8; color: #3c763d; padding: 10px; margin-bottom: 15px; border: 1px solid #d6e9c6; }
    .alert.error { background: #f2dede; color: #a94442; padding: 10px; margin-bottom: 15px; border: 1px solid #ebccd1; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1em; }
    .grayout { color: #999; font-style: italic; margin-top: 5px; }
    .actions { margin-top: 20px; }
  </style>
</head>
<body>

<div class="seal-respond">

 <?php if ($submission_success): ?>
 
 

<?php
$illsystemhost = $_SERVER["SERVER_NAME"];
// Connect to database
require '/var/www/seal_wp_script/seal_db.inc';
$db = mysqli_connect($dbhost, $dbuser, $dbpass);
mysqli_select_db($db, $dbname);
$today = date('Y-m-d H:i:s');
// Loop through all selected libraries
$destinationCount = count($_POST['libdestination']);
foreach ($_POST['libdestination'] as $destination) {
    list($libcode, $library, $destsystem, $itemavail, $itemcall, $itemlocation, $destemail, $destloc) = explode(":", $destination);

    // Query the lending library's Illiad details
    $illiadchecksql = "SELECT IlliadDATE, IlliadURL, Illiad, APIkey, LibEmailAlert FROM `$sealLIB` WHERE `loc` = '" . mysqli_real_escape_string($db, $destloc) . "'";
    //for testing
    //echo  $illiadchecksql;

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
    $inst = trim(mysqli_real_escape_string($db, $_POST['inst']));
    $fname = trim(mysqli_real_escape_string($db, $_POST['fname']));
    $lname = trim(mysqli_real_escape_string($db, $_POST['lname']));
    $email = trim(mysqli_real_escape_string($db, $_POST['email']));
    $needbydate = trim(mysqli_real_escape_string($db, $_POST['needbydate']));
    $reqnote = trim(mysqli_real_escape_string($db, $_POST['reqnote']));
    $patronnote = trim(mysqli_real_escape_string($db, $_POST['patronnote']));
    $reqLOCcode = trim(mysqli_real_escape_string($db, $_POST['reqLOCcode']));
    $wphone = trim(mysqli_real_escape_string($db, $_POST['wphone']));
    $saddress = trim(mysqli_real_escape_string($db, $_POST['address']));
    $saddress2 = trim(mysqli_real_escape_string($db, $_POST['address2']));
    $caddress = trim(mysqli_real_escape_string($db, $_POST['caddress']));
    $reqsystem = ''; // Add logic to determine system if needed

// -------------------------------
// Article field handling
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

// Combine article details into one string for database
$article = "Article Title: $arttile <br>Article Author: $artauthor <br>Volume: $artvolume <br>Issue: $artissue <br>Pages: $artpage <br>Month: $artmonth <br>Year: $artyear <br>Copyright: $artcopyright";



    // Final INSERT SQL
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
            '$reqsystem',
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
//for debuing
//echo $sql;
    // Run the insert
    if (!mysqli_query($db, $sql)) {
        // Failsafe email to admin
        $headers  = "From: Southeastern SEAL <dontreply@senylrc.org>\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
        $headers = preg_replace('/(?<!\r)\n/', "\r\n", $headers);
        $msg = "INSERT FAILED:<br><br><code>" . htmlspecialchars($sql) . "</code><br><br>Error: " . mysqli_error($db);
        mail("spalding@senylrc.org", "SEAL Request DB Insert Failure", $msg, $headers, "-f donotreply@senylrc.org");
    }

// Get the auto-increment ID from the last insert
$sqlidnumb = mysqli_insert_id($db);

if ($sqlidnumb > 0) {
    // Build the ILL number (e.g., 2025-1234)
    $yearid = date('Y');
    $illnum = "$yearid-$sqlidnumb";

    // Escape the ILL number (in case formatting changes in future)
    $illnum = mysqli_real_escape_string($db, $illnum);

    // Update the record with the ILL number
    $sqlupdate = "UPDATE `$sealSTAT` SET `illNUB` = '$illnum' WHERE `index` = $sqlidnumb";

    if (!mysqli_query($db, $sqlupdate)) {
        // Log or email error if the update fails
        $headers  = "From: Southeastern SEAL <dontreply@senylrc.org>\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
        $headers = preg_replace('/(?<!\r)\n/', "\r\n", $headers);

        $errormsg = "Failed to update illNUB:<br><br><code>$sqlupdate</code><br><br>Error: " . mysqli_error($db);
        mail("spalding@senylrc.org", "SEAL ILL Number Update Failure", $errormsg, $headers, "-f donotreply@senylrc.org");
    }
} else {
    // Log insert failure (if insert ID is 0 or false)
    $headers  = "From: Southeastern SEAL <dontreply@senylrc.org>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
    $headers = preg_replace('/(?<!\r)\n/', "\r\n", $headers);

    $msg = "mysqli_insert_id() failed. Could not retrieve ID for assigning illNUB.";
    mail("spalding@senylrc.org", "SEAL Insert ID Retrieval Failure", $msg, $headers, "-f donotreply@senylrc.org");
}

 // Send to ILLiad via API
            if ($libilliad=='1') {
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
                $libreqaddress3=trim($libreqaddress3);
                $libreqaddress3 = str_replace(',', '', $libreqaddress3);
                $pieces = explode(" ", $libreqaddress3);
                $libreqcity= $pieces[0];
                $libreqstate= $pieces[1];
                $libreqzip= $pieces[2];
        
                $sqlilliadmp = "SELECT * FROM `$sealILLiadMapping` WHERE `LOC`='$reqLOCcode' and `illiadID`='$destloc'";
                // echo $sqlilliadmp."<br>";
                $sqlilliadmpGETLIST = mysqli_query($db, $sqlilliadmp);
                $sqlilliadmpGETLISTCOUNT = '1';
                $sqlilliadmprow = mysqli_fetch_assoc($sqlilliadmpGETLIST);
                $illiadADDnumb  = $sqlilliadmprow["illiadADDnumb"];
                $illiadLIBSymbol =  $sqlilliadmprow["illiadLIBSymbol"];
                // Add slashes to these string to prevent coding issue
                $ititle=addslashes($ititle);
                $iauthor=addslashes($iauthor);   
                //Generate the due date, requrired for ILLiad Loans
                if (ctype_digit($libilliaddate)) {
                    $date = date("Y-m-d");
                    $illduedateCAL= date('Y-m-d', strtotime($date. ' + '.$libilliaddate.' days'));
                }
                //for testing
                //echo $illduedateCAL."part 2".$date."part 3".$libilliaddate."";
                // Store data for request in array
                if (empty($arttile)) {
                    //book request have to be sent as an article or API won't take them
                    //note about being a book loan is set so ILLiad users know to press loan radio button
                    $jsonstr = array( 'Username' =>'Lending','LendingString'=> $reqnote, 'RequestType'=>'Loan','DueDate'=>$illduedateCAL.'T00:00:00-04:00','ProcessType'=>'Lending','LenderAddressNumber'=>$illiadADDnumb,'LendingLibrary'=>$illiadLIBSymbol,'TransactionStatus'=>'Awaiting Lending Request Processing','LoanTitle'=>$ititle,'LoanAuthor'=>$iauthor,'CallNumber'=>$itemcall,'LoanDate'=>$pubdate,'ISSN'=>$isbn,'ILLNumber'=>$illnum ,'TAddress'=>$libreqname,'TAddress2'=>$libreqaddress2,'TCity'=>$libreqcity,'TState'=>$libreqcity,'TZip'=>$libreqzip,'TEMailAddress'=>$libreqemail);
                } else {
                    $jsonstr = array('Username' =>'Lending','LendingString'=> $reqnote, 'ProcessType'=>'Lending','LenderAddressNumber'=>$illiadADDnumb,'LendingLibrary'=>$illiadLIBSymbol,'TransactionStatus'=>'Awaiting Lending Request Processing','LoanTitle'=>$ititle,'LoanAuthor'=>$iauthor,'CallNumber'=>$itemcall,'LoanDate'=>$pubdate,'PhotoArticleTitle'=>$arttile,'PhotoArticleAuthor'=>$artauthor,'PhotoJournalVolume'=>$artvolume,'PhotoJournalIssue'=>$artissue,'PhotoJournalYear'=>$artyear,'PhotoJournalInclusivePages'=>$artpage,'ISSN'=>$issn,'ILLNumber'=>$illnum,'TAddress'=>$libreqname,'TAddress2'=>$libreqaddress2,'TCity'=>$libreqcity,'TState'=>$libreqcity,'TZip'=>$libreqzip,'TEMailAddress'=>$libreqemail );
                }
        
                // Enocde the array in to json data
                $json_enc=json_encode($jsonstr);
        
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
                $output_decoded = json_decode($output, true);
                $illiadtxnub= $output_decoded['TransactionNumber'];
                $illstatus = $output_decoded['TransactionStatus'];
        
                if (strlen($illiadtxnub)<4) {
                    $headers = "From: Southeastern SEAL <dontreply@senylrc.org>\r\n" ;
                    $headers .= "MIME-Version: 1.0\r\n";
                    $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
                    $messagereq = "Request did not go to ILLiad Ill ".$illnum." ".$output." ";
                    $headers = preg_replace('/(?<!\r)\n/', "\r\n", $headers);
                    mail("spalding@senylrc.org", "ILLiad Failure", $messagereq, $headers, "-f donotreply@senylrc.org");
                } //end check if ILLad transaction did not happen
        
                //save API output to the request
                $sqlupdate2 = "UPDATE `$sealSTAT` SET `IlliadStatus` = '$illstatus', `IlliadTransID` = '$illiadtxnub' WHERE `index` = $sqlidnumb";
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



$requester_email = $_POST['email'];
$lib_parts = explode(':', $_POST['libdestination'][0]);
$destination_email = $lib_parts[6]; // Can be semicolon-separated list

// Build shared email headers
$headers  = "From: Southeastern SEAL <dontreply@senylrc.org>\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
$headers = preg_replace('/(?<!\r)\n/', "\r\n", $headers);

// Email to requester
$subject_to_requester = "Your SEAL ILL Request Confirmation";

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
$subject_to_library = "New SEAL ILL Request for \"" . htmlspecialchars($_POST['bibtitle']) . "\"";

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
  <li><strong>Institution:</strong> " . htmlspecialchars($_POST['inst']) . "</li>
  <li><strong>Email:</strong> " . htmlspecialchars($_POST['email']) . "</li>
  <li><strong>Phone:</strong> " . htmlspecialchars($_POST['wphone']) . "</li>
</ul>

<h4>Requested Item</h4>
<ul>
  <li><strong>ILL#:</strong> $illnum</li>
  <li><strong>Title:</strong> " . htmlspecialchars($_POST['bibtitle']) . "</li>
  <li><strong>Author:</strong> " . htmlspecialchars($_POST['bibauthor']) . "</li>
  <li><strong>Publication Date:</strong> " . htmlspecialchars($_POST['pubdate']) . "</li>
  <li><strong>ISBN:</strong> " . htmlspecialchars($_POST['isbn']) . "</li>
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


}//end of the lib dest for each loop
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
  <li><strong>Institution:</strong> <?php echo htmlspecialchars($inst); ?></li>
</ul>

<p><a href="/" style="display:inline-block; margin-top:15px; padding:10px 20px; background:#337ab7; color:#fff; text-decoration:none; border-radius:5px;">Submit another request</a></p>
</body>
</html>

<?php exit; ?>
<?php endif; ?>

  <p>Please review the details of your request and then select a library to send your request to.</p>

  <form action="" method="post">
    <h4>Requester Details</h4>
    <div class="form-grid">
      <div><label>Name:&nbsp</label><strong><?php echo $field_first_name . " " . $field_last_name; ?></strong></div>
      <div><label>E-mail:&nbsp</label><strong><?php echo $email; ?></strong></div>
      <div><label>Institution:&nbsp</label><strong><?php echo $field_your_institution; ?></strong></div>
      <div><label>Work Phone:&nbsp</label><strong><?php echo $field_work_phone; ?></strong></div>
      <div style="grid-column:1/-1">
        <label>Mailing Address:&nbsp</label><br>
        <strong><?php echo $field_street_address . "<br>" . $field_city_state_zip; ?></strong>
      </div>
    </div>

    <!-- Hidden fields -->
    <input type="hidden" name="fname" value="<?php echo $field_first_name; ?>">
    <input type="hidden" name="lname" value="<?php echo $field_last_name; ?>">
    <input type="hidden" name="email" value="<?php echo $email; ?>">
    <input type="hidden" name="inst" value="<?php echo htmlspecialchars($field_your_institution, ENT_QUOTES); ?>">
    <input type="hidden" name="address" value="<?php echo $field_street_address; ?>">
    <input type="hidden" name="address2" value="<?php echo $field_street_address2; ?>">
    <input type="hidden" name="caddress" value="<?php echo $field_city_state_zip; ?>">
    <input type="hidden" name="wphone" value="<?php echo $field_work_phone; ?>">
    <input type="hidden" name="reqLOCcode" value="<?php echo $field_loc_location_code; ?>">

<h4>Request Details</h4>
<div class="form-grid">
  <div class="full-row">
    <label>Need by date</label><br>
    <input type="text" name="needbydate" style="width:100%;">
  </div>
  <div class="full-row">
    <label>Note</label><br>
    <input type="text" name="reqnote" style="width:100%;">
  </div>
  <div class="full-row">
    <p><em>Patron information is optional; please follow your local policies regarding patron privacy.</em></p>
    <label>Patron Name or Barcode</label><br>
    <input type="text" name="patronnote" style="width:100%;">
  </div>
</div>


  <!-- Article Request Section -->
  <div class="full-row">
    <b>Is this a request for an article?</b><br>
    <label>
      <input type="radio" name="yesno" id="yesCheck" onclick="yesnoCheck();"> Yes
    </label>
    <label>
      <input type="radio" name="yesno" id="noCheck" checked onclick="yesnoCheck();"> No
    </label>

    <div id="ifYes" style="display:none; margin-top:10px;">
      <b>Article Title:</b><br>
      <input type="text" name="arttile" size="80" style="width:100%;"><br>

      <b>Article Author:</b><br>
      <input type="text" name="artauthor" size="80" style="width:100%;"><br>

      <b>Volume:</b><br>
      <input type="text" name="artvolume" size="80" style="width:100%;"><br>

      <b>Issue:</b><br>
      <input type="text" name="artissue" style="width:100%;"><br>

      <b>Pages:</b><br>
      <input type="text" name="artpage" style="width:100%;"><br>

      <b>Issue Month:</b><br>
      <input type="text" name="artmonth" style="width:100%;"><br>

      <b>Issue Year:</b><br>
      <input type="text" name="artyear" style="width:100%;"><br>

      <b>Copyright compliance:</b><br>
      <select name="artcopyright" style="width:100%;">
        <option value=""></option>
        <option value="ccl">CCL</option>
        <option value="ccg">CCG</option>
      </select>
    </div>
  </div>
</div>


    <h4>Bibliographic Details</h4>
    <div class="form-grid">
      <div><label>Requested Title:&nbsp</label><?php echo $requestedtitle . " " . $requestedtitle2; ?></div>
      <div><label>Author:&nbsp</label><?php echo $requestedauthor; ?></div>
      <div><label>Item Type:&nbsp</label><?php echo $itemtype; ?></div>
      <div><label>Publication Date:&nbsp</label><?php echo $pubdate; ?></div>
      <?php if (strlen($issn) > 0): ?>
        <div><label>ISSN:&nbsp</label><?php echo $issn; ?></div>
      <?php endif; ?>
      <?php if (strlen($isbn) > 0): ?>
        <div><label>ISBN:&nbsp</label><?php echo $isbn; ?></div>
      <?php endif; ?>
    </div>

    <!-- Hidden bib fields -->
    <input type="hidden" name="bibtitle" value="<?php echo $requestedtitle . " " . $requestedtitle2; ?>">
    <input type="hidden" name="bibauthor" value="<?php echo $requestedauthor; ?>">
    <input type="hidden" name="bibtype" value="<?php echo $itemtype; ?>">
    <input type="hidden" name="pubdate" value="<?php echo $pubdate; ?>">
    <input type="hidden" name="isbn" value="<?php echo $isbn; ?>">
    <input type="hidden" name="issn" value="<?php echo $issn; ?>">

    <h4>Library Selection</h4>
    <p><b>This is a request for:</b></p>
    <label><input type="radio" name="singlemulti" id="singleCheck" checked onclick="multiRequest();"> Single copy</label>
    <label><input type="radio" name="singlemulti" id="multiCheck" onclick="multiRequest();"> Multiple copies</label>
<br><br>
    <?php
$loccount = 0;
    $deadlibraries = array();
    foreach ($records->location as $location) {
        $catalogtype = find_catalog($location['name']);
    $urlrecipe = $location->{'md-url_recipe'};
    $mdid = $location->{'md-id'};
    //echo "zack my location is ".$location['name']."<br>";
    //echo "zack my catalog type is ".$catalogtype."<br>";
    foreach ($location->holdings->holding as $holding) { // generic holding loop start
        $itemavail = $holding->localAvailability;
        // if ($catalogtype == "OPALS") {
        // $itemavail=$itemavail>0 ? $itemavail="-" : $itemavail="0";
        // echo "the OPALS itemavail is ".$itemavail."<br>";
        // } #OPALS might return (-1 through +X
        $itemavail = normalize_availability($itemavail); // 0=No, 1=Yes

        $itemavailtext = set_availability($itemavail);
        $itemcallnum = $holding->callNumber;
        $itemcallnum = htmlspecialchars($itemcallnum, ENT_QUOTES); // Sanitizes callnumbers with special characters in them
        $itemlocation = $holding->localLocation; // Gets the alias
        if ($catalogtype == "Worldcat" || $catalogtype == 'cdlc' || $catalogtype == "Millennium") {
            $itemlocation = $location['name'];
        }
        if (($catalogtype == "Innovative") || ($catalogtype == "Alma") ||  ($catalogtype == "Voyager") || ($catalogtype == "Folio") || ($catalogtype == "Symphony") || ($catalogtype == "SirsiDynix")) {
            //checking to make sure we only do request for Adelphi
            if (strpos($location['name'], "Adelphi") !== false) {
                //we are working with an adelphi location
                $itemlocationAD =  $holding->localLocation;
                if (strpos($itemlocationAD, "Hudson Valley") !== false) {
                    //we are working with a hudson valley loction
                    $itemlocation = $location['name']; // Gets the alias
                } else {
                    //not a hudson valley location, set avail to 0
                    $itemavail = 0;
                }
            } else {
                //not adelphi
                $itemlocation = $location['name'];
            }
        }
        if (($catalogtype == "OPALS") || ($catalogtype == "Polaris")) {
            $itemlocation =  $holding->localLocation;
        }
        if ($catalogtype == "TLC") {
            $itemlocation = $holding->localLocation; // Gets the alias
        }
        if ($catalogtype == "SymphonyRCLS") {
            $itemlocation = $holding->localLocation; // Gets the alias
        }
        if ($catalogtype == "InnovativeMHLS") {
            $itemlocation = $holding->localLocation; // Gets the alias
        }



        $locationinfo = find_locationinfo($itemlocation, $location['name']);
        $itemlocation = htmlspecialchars($itemlocation, ENT_QUOTES); // Sanitizes locations with special characters in them
        $destill = $locationinfo[0]; // Destination ILL Code
        $destpart = $locationinfo[1]; // 0=No, 1=Yes

        $destemail = $locationinfo[2]; // Destination emails
        $destsuspend = $locationinfo[3]; // 0=No, 1=Yes
        $destlibsystem = $locationinfo[4]; // Destination library system
        $destlibname = $locationinfo[5]; // Destination library name
        $destAlias = $locationinfo[6]; // Destination Alias
        // translate system code to text name
        if (strcmp($destlibsystem, 'MH') == 0) {
            $destlibsystemtxt = "Mid Hudson Library System";
        } else if (strcmp($destlibsystem, 'RC') == 0) {
            $destlibsystemtxt = "Ramapo Catskill Library System";
        } else if (strcmp($destlibsystem, 'SE') == 0) {
            $destlibsystemtxt = "SENYLRC";
        } else if (strcmp($destlibsystem, 'DU') == 0) {
            $destlibsystemtxt = "Dutchess BOCES";
        } else if (strcmp($destlibsystem, 'OU') == 0) {
            $destlibsystemtxt = "Orange Ulster BOCES";
        } else if (strcmp($destlibsystem, 'RB') == 0) {
            $destlibsystemtxt = "Rockland BOCES";
        } else if (strcmp($destlibsystem, 'SB') == 0) {
            $destlibsystemtxt = "Sullivan BOCES";
        } else if (strcmp($destlibsystem, 'UB') == 0) {
            $destlibsystemtxt = "Ulster BOCES";
        } else if (strlen($destlibsystem) < 1) {
            $destlibsystemtxt = "All";
        } else {
            $destlibsystemtxt = "SENYLRC Group";
        }
        $destlibname = htmlspecialchars($destlibname, ENT_QUOTES); // Sanitizes library names with special characters in them
        //only check item type if they are active in the ILL program
        if ($destpart == 1) {
            $desttypeloan = check_itemtype($destill, $itemtype, $destlibsystem); // 0=No, 1=Yes
        }
        if (($catalogtype == "Innovative") && ($itemlocation == "ODY Folio")) {
            $desttypeloan = 1;
        }
        $itemlocallocation = $itemlocation; // Needed in sent.php
        echo "<!-- \n";
        echo "catalogtype: $catalogtype \n";
        echo "itemavail: $itemavail (1) \n";
        echo "itemavailtext: $itemavailtext \n";
        echo "itemlocallocation: $itemlocallocation \n";
        echo "itemlocation: $itemlocation \n";
        echo "destill: $destill \n";
        echo "destpart: $destpart (1)\n";
        echo "destemail: $destemail \n";
        echo "destsuspend: $destsuspend (0)\n";
        echo "destlibsystem: $destlibsystem \n";
        echo "destlibname: $destlibname \n";
        echo "desttypeloan: $desttypeloan (1)\n";
        echo "failmessage: $failmessage\n";
        echo "--> \n\n";
        $destfail = 0; // 0=No, 1=Yes
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
        if (($destlibsystem == $field_home_library_system) && ($field_filter_own_system == 1)) {
            $destfail = 1;
            $failmessage = "Library a member of your system, please request through your ILS/LMS";
        }
        if ($destill == "") {
            $destfail = 1;
            $destlibname = $itemlocation;
            $destlibsystem = "Unknown";
            $failmessage = "No alias match in SEAL directory";
        }
        if ($destfail == 0) {
            $itemcallnum = preg_replace('/[:]/', ' ', $itemcallnum);
            $itemlocation = preg_replace('/[:]/', ' ', $itemlocation);
            $itemlocallocation = preg_replace('/[:]/', ' ', $itemlocallocation);
            echo "<div class='multiplereq'><input type='checkbox' class='librarycheck' name='libdestination[]' value='" . $itemlocation . ":" . $destlibname . ":" . $destlibsystem . ":" . $itemavailtext . ":" . $itemcallnum . ":" . $itemlocallocation . ":" . $destemail . ":" . $destill . "'><strong>" . $destlibname . "</strong> (" . $destlibsystemtxt . "), Availability: $itemavailtext, Call Number:$itemcallnum  </br></div>";
            echo "<div class='singlereq'><input type='radio' class='librarycheck' name='libdestination[]' value='" . $itemlocation . ":" . $destlibname . ":" . $destlibsystem . ":" . $itemavailtext . ":" . $itemcallnum . ":" . $itemlocallocation . ":" . $destemail . ":" . $destill . "'><strong>" . $destlibname . "</strong> (" . $destlibsystemtxt . "), Availability: $itemavailtext, Call Number:$itemcallnum  </br></div>";
            $loccount = $loccount + 1;
        } elseif ($destfail == 1) {
            //only showing error code 2
        } else {
            $deadlibraries[] = "<div class='grayout'>$destlibname ($destlibsystemtxt), $failmessage</div>";
            echo "<!-- Holding location failed checks. --> \n";
        }
    } // Generic holding loop end



    //want to add Koha locations to selection
    if (($catalogtype == "Koha") || ($catalogtype == "Alexandria")) {
        // Pull the checksum for the location
        $seslcchecksum = $location['checksum'];
        // redo the curl statement to includes the checksum
        $cmdseslc = "curl -b JSESSIONID=$jession $reqserverurl$windowid\\&id=" . urlencode($idc) . "\&checksum=$seslcchecksum\&offset=1";
        $outputseslc = shell_exec($cmdseslc);
        // This echo will show the CURL statment as an HTML comment
        //echo "\n<br><!-- my cmd koha is $cmdseslc \n-->";
        $recordssSESLC = new SimpleXMLElement($outputseslc); // for production
        // Go through the holding records
        foreach ($recordssSESLC->d952 as $d952) {
            //$itemavai=$d952['i1'];
            $itemlocation = $d952->sa;
            $itemcallnum = $d952->so;
            $itemavail = $d952->s7;
            // Remove colon from call numbers
          $itemcallnum = str_replace(':', '.', $itemcallnum);
            // Check if the location is 'Ramapo-Catskill Library System' and adjust item availability if needed
            if ($location['name'] == 'Ramapo-Catskill Library System') {
                $itemavail = trim(strtolower($d952->sk)); // Adjusting item availability based on the location
            }

            // Process the availability status
            $result = set_koha_availability($itemavail);
            $itemavailtext = $result['status']; // Outputs the HTML formatted status
            $itemavail = $result['code']; // Use this code for further processing

            $locationinfo = find_locationinfo($itemlocation, $location['name']);
            $itemlocation = htmlspecialchars($itemlocation, ENT_QUOTES); // Sanitizes locations with special characters in them
            $destill = $locationinfo[0]; // Destination ILL Code
            $destpart = $locationinfo[1]; // 0=No, 1=Yes
            $destemail = $locationinfo[2]; // Destination emails
            $destsuspend = $locationinfo[3]; // 0=No, 1=Yes
            $destlibsystem = $locationinfo[4]; // Destination library system
            $destlibname = $locationinfo[5]; // Destination library name
            $destAlias = $locationinfo[6]; // Destination Alias
            $destlibname = htmlspecialchars($destlibname, ENT_QUOTES); // Sanitizes library names with special characters in them
            $desttypeloan = check_itemtype($destill, $itemtype, $destlibsystem); // 0=No, 1=Yes
            $itemlocallocation = $itemlocation; // Needed in sent.php
            // translate system code to text name
            if (strcmp($destlibsystem, 'MH') == 0) {
                $destlibsystemtxt = "Mid Hudson Library System";
            } else if (strcmp($destlibsystem, 'RC') == 0) {
                $destlibsystemtxt = "Ramapo Catskill Library System";
            } else if (strcmp($destlibsystem, 'SE') == 0) {
                $destlibsystemtxt = "SENYLRC";
            } else if (strcmp($destlibsystem, 'DU') == 0) {
                $destlibsystemtxt = "Dutchess BOCES";
            } else if (strcmp($destlibsystem, 'OU') == 0) {
                $destlibsystemtxt = "Orange Ulster BOCES";
            } else if (strcmp($destlibsystem, 'RB') == 0) {
                $destlibsystemtxt = "Rockland BOCES";
            } else if (strcmp($destlibsystem, 'SB') == 0) {
                $destlibsystemtxt = "Sullivan BOCES";
            } else if (strcmp($destlibsystem, 'UB') == 0) {
                $destlibsystemtxt = "Ulster BOCES";
            } else if (strlen($destlibsystem) < 1) {
                $destlibsystemtxt = "All";
            } else {
                $destlibsystemtxt = "SENYLRC Group";
            }
            echo "<!-- \n";
            echo "catalogtype: $catalogtype \n";
            echo "itemavail: $itemavail (1) \n";
            echo "itemavailtext: $itemavailtext \n";
            echo "itemlocallocation: $itemlocallocation \n";
            echo "itemlocation: $itemlocation \n";
            echo "destill: $destill \n";
            echo "destpart: $destpart (1)\n";
            echo "destemail: $destemail \n";
            echo "destsuspend: $destsuspend (0)\n";
            echo "destlibsystem: $destlibsystem \n";
            echo "destlibname: $destlibname \n";
            echo "desttypeloan: $desttypeloan (1)\n";
            echo "--> \n\n";
            $destfail = 0; // 0=No, 1=Yes
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
            echo "<!-- \n";
            echo "destfail: $destfail\n";
            echo "--> \n\n";
            if ($destfail == 0) {
                $itemcallnum = preg_replace('/[:]/', ' ', $itemcallnum);
                $itemlocation = preg_replace('/[:]/', ' ', $itemlocation);
                $itemlocallocation = preg_replace('/[:]/', ' ', $itemlocallocation);

                echo "<div class='multiplereq'><input type='checkbox' class='librarycheck' name='libdestination[]' value='" . $itemlocation . ":" . $destlibname . ":" . $destlibsystem . ":" . $itemavailtext . ":" . $itemcallnum . ":" . $itemlocallocation . ":" . $destemail . ":" . $destill . "'><strong>" . $destlibname . "</strong> (" . $destlibsystemtxt . "), Availability: $itemavailtext, Call Number:$itemcallnum  </br></div>";
                echo "<div class='singlereq'><input type='radio' class='librarycheck[]' name='libdestination[]' value='" . $itemlocation . ":" . $destlibname . ":" . $destlibsystem . ":" . $itemavailtext . ":" . $itemcallnum . ":" . $itemlocallocation . ":" . $destemail . ":" . $destill . "'><strong>" . $destlibname . "</strong> (" . $destlibsystemtxt . "), Availability: $itemavailtext, Call Number:$itemcallnum  </br></div>";
                $loccount = $loccount + 1;
            } elseif ($destfail == 1) {
                //not showing fail code 1 to end user
                $deadlibraries[] = "<div class='grayout'>$destlibname ($destlibsystemtxt), $failmessage</div>";
            } else {
                //will show other error to inform end user
                $deadlibraries[] = "<div class='grayout'>$destlibname ($destlibsystemtxt), $failmessage</div>";
                echo "<!-- Holding location failed checks. --> \n";
            }
        } //end foreach $recordssSESLC
    } //end if cat type koha
    }
    foreach ($deadlibraries as $line) { echo $line; }

      if ($loccount > 0) {
          echo "<div class='actions'><input type='submit' value='Submit'></div>";
      } else {
          echo "<p class='alert error'><b>Sorry, no available library to route your request at this time.</b> <a href='/'>Try another search?</a></p>";
      }
    ?>
  </form>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
  multiRequest();
  document.getElementById("multiCheck").addEventListener("click", multiRequest);
  document.getElementById("singleCheck").addEventListener("click", multiRequest);
});

function multiRequest() {
  const multi = document.getElementById('multiCheck').checked;
  document.querySelectorAll(".librarycheck").forEach(el => el.checked = false);
  document.querySelectorAll(".multiplereq").forEach(el => {
    el.style.display = multi ? 'block' : 'none';
  });
  document.querySelectorAll(".singlereq").forEach(el => {
    el.style.display = multi ? 'none' : 'block';
  });
}
</script>

</body>
</html>