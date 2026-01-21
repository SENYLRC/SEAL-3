<?php
// resend a request to ILLiad
$illnumb = 12;

// DB connect
require 'seal_db.inc';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
$db->set_charset("utf8mb4");

// Fetch request
$sqlselect = "SELECT * FROM `$sealSTAT` WHERE `index`='$illnumb' LIMIT 1";
echo "$sqlselect\n";
$result = mysqli_query($db, $sqlselect);
$row = mysqli_fetch_assoc($result);
if (!$row) die("No record found.\n");

// Pull fields
$destloc      = trim($row["Destination"]);
$illnum       = $row["illNUB"];
$ititle       = addslashes($row["Title"]);
$iauthor      = addslashes($row["Author"]);
$itemcall     = $row["Call Number"];
$pubdate      = $row["pubdate"];
$reqnote      = addslashes($row["reqnote"]);
$reqLOCcode   = trim($row["Requester LOC"]);

// Lookup ILLiad settings
$illiad = mysqli_fetch_assoc(mysqli_query($db, "SELECT IlliadDATE, IlliadURL, APIkey FROM `$sealLIB` WHERE `loc`='$destloc'"));
$map    = mysqli_fetch_assoc(mysqli_query($db, "SELECT illiadADDnumb, illiadLIBSymbol FROM `$sealILLiadMapping` WHERE `LOC`='$reqLOCcode' AND `illiadID`='$destloc'"));

// Due date
$days = (int)$illiad['IlliadDATE'];
$illduedateCAL = date('Y-m-d', strtotime("+$days days"));
echo "Due date: $illduedateCAL\n";

// Build JSON
$json = [
  'Username' => 'Lending',
  'LendingString' => $reqnote,
  'RequestType' => 'Loan',
  'DueDate' => "{$illduedateCAL}T00:00:00-04:00",
  'ProcessType' => 'Lending',
  'LenderAddressNumber' => $map['illiadADDnumb'],
  'LendingLibrary' => $map['illiadLIBSymbol'],
  'TransactionStatus' => 'Awaiting Lending Request Processing',
  'LoanTitle' => $ititle,
  'LoanAuthor' => $iauthor,
  'CallNumber' => $itemcall,
  'LoanDate' => $pubdate,
  'ILLNumber' => $illnum
];
$json_enc = json_encode($json, JSON_PRETTY_PRINT);
echo "Payload:\n$json_enc\n";

// Send to ILLiad
$ch = curl_init($illiad['IlliadURL']);
curl_setopt_array($ch, [
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS => $json_enc,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    'Content-Type: application/json',
    'ApiKey: '.$illiad['APIkey']
  ]
]);
$output = curl_exec($ch);
if ($output === false) die("cURL error: ".curl_error($ch)."\n");
curl_close($ch);
echo "Response: $output\n";

// Parse & update
$out = json_decode($output, true);
$txn = mysqli_real_escape_string($db, $out['TransactionNumber'] ?? '');
$status = mysqli_real_escape_string($db, $out['TransactionStatus'] ?? '');

// If no ILLiad transaction number returned, email + stop
if ($txn === '') {
    $to      = 'noc@senylrc.org';
    $subject = "SEAL → ILLiad ERROR: No Transaction Number Returned";

    $body = "ILLiad did not return a TransactionNumber.\n\n"
          . "Local index: $illnumb\n"
          . "Local ILL number: $illnum\n"
          . "Destination LOC: $destloc\n"
          . "Requester LOC: $reqLOCcode\n"
          . "Due Date Sent: $illduedateCAL\n\n"
          . "Payload sent:\n$json_enc\n\n"
          . "Raw ILLiad response:\n$output\n";

    $headers = [];
    $headers[] = "From: donotreply@senylrc.org";
    $headers[] = "Reply-To: noc@senylrc.org";
    $headers[] = "Content-Type: text/plain; charset=UTF-8";

    @mail($to, $subject, $body, implode("\r\n", $headers));

    die("ERROR: ILLiad response missing TransactionNumber. Email sent.\n");
}

// Escape for DB
$txn_esc    = mysqli_real_escape_string($db, $txn);
$status_esc = mysqli_real_escape_string($db, $status);

// Update database
mysqli_query(
    $db,
    "UPDATE `$sealSTAT`
     SET IlliadStatus='$status_esc',
         IlliadTransID='$txn_esc'
     WHERE `index`=$illnumb"
);

echo "Updated ILLiadTransID=$txn, IlliadStatus=$status\n";


mysqli_query($db, "UPDATE `$sealSTAT` SET IlliadStatus='$status', IlliadTransID='$txn' WHERE `index`=$illnumb");
echo "Updated ILLiadTransID=$txn, IlliadStatus=$status\n";
