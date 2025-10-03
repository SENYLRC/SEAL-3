<link rel="stylesheet" href="https://sealbeta.senylrc.org/assets/jquery-ui.css">
<script src="https://sealbeta.senylrc.org/assets/jquery.min.js"></script>
<script src="https://sealbeta.senylrc.org/assets/jquery-ui.min.js"></script>

<script>
  jQuery(function($){
    $("#datepicker").datepicker({
      dateFormat: "yy-mm-dd",
      changeMonth: true,
      changeYear: true,
      showAnim: "fadeIn"
    });
  });
</script>
<?php
// respond.php — Lender response page

// --- Config / Setup ---
$illsystemhost = $_SERVER["SERVER_NAME"];

// Connect to database
require '/var/www/seal_wp_script/seal_db.inc';
require '/var/www/seal_wp_script/seal_function.php'; // ensure $sealSTAT, $sealLIB are available
$db = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

// Get values from POST/REQUEST safely
$reqnumb   = $_POST["num"] ?? ($_REQUEST["num"] ?? '');
$reqanswer = $_POST["fill"]   ?? ($_REQUEST["fill"] ?? '');
$FromLender = $_POST["FromLender"]   ?? ($_REQUEST["FromLender"] ?? '');



// Escape values AFTER db connection
$reqnumb   = mysqli_real_escape_string($db, $reqnumb);
$reqanswer = mysqli_real_escape_string($db, $reqanswer);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $FromLender != 1) {
 $respnote=$_REQUEST["respondnote"];
    $resfill=$_REQUEST["fill"];
    $duedate=$_REQUEST["duedate"];
    $nofillreason=$_REQUEST["nofillreason"];
    $shipmethod=$_REQUEST["shipmethod"];
    // Escape values for security
    $respnote = mysqli_real_escape_string($db,  $respnote);
    $resfill = mysqli_real_escape_string($db,  $resfill);
    $todaydate = date("Y-m-d");

    $sqlupdate = "UPDATE `$sealSTAT` SET `emailsent` = '1', `Fill` = '$resfill'  , `responderNOTE` =  '$respnote',`shipMethod`='$shipmethod',`DueDate`='$duedate',`ReasonNotFilled`='$nofillreason',`fillNofillDate` = '$todaydate'  WHERE `illNUB` = '$reqnumb'";

    //for testing
     if (mysqli_query($db, $sqlupdate)) {
        echo "Thank you.  Your response has been recorded to the request.<br><a href='/lender-history'>Click here to view your lender history</a><br>";
        // Setup the note data to be in email
        $respnote=stripslashes($respnote);
        if (strlen($respnote)>0) {  $respnote="The lending library has noted the following <br> $respnote";
        }
            $sqlselect="select responderNOTE,requesterEMAIL,Title,Destination from  `$sealSTAT` where illNUB='$reqnumb'  LIMIT 1 ";
            $result = mysqli_query($db, $sqlselect);
            $row = mysqli_fetch_array($result);
            $title =$row['Title'];
            $requesterEMAIL=$row['requesterEMAIL'];
            $destlib=$row['Destination'];
            // Get the Destination Name
            $GETLISTSQLDEST="SELECT`Name`, `ill_email` FROM `$sealLIB` where loc like '$destlib'  limit 1";
            $resultdest=mysqli_query($db, $GETLISTSQLDEST);
        while ($rowdest = mysqli_fetch_assoc($resultdest)) {
                        $destlib=$rowdest["Name"];
                        $destemail=$rowdest["ill_email"];
        }
          // In case the ILL email for the destination library is more than one, break it down to comma for php mail
          $destemailarray = explode(';', $destemail);
          $destemail_to = implode(',', $destemailarray);

        $headers = "From: Southeastern SEAL <donotreply@senylrc.org>\r\n" ;
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";



            // sending filled email#####
        if ($resfill=='1') {

                // Setting up email notification
            if($shipmethod=="usps") { $shiptxt='US Mail';
            }
            if($shipmethod=="mhls") { $shiptxt='Mid-Hudson Courier';
            }
            if($shipmethod=="rcls") { $shiptxt='RCLS Courier';
            }
            if($shipmethod=="empire") { $shiptxt='Empire Delivery';
            }
            if($shipmethod=="ups") { $shiptxt='UPS';
            }
            if($shipmethod=="fedex") { $shiptxt='FedEx';
            }
            if($shipmethod=="other") { $shiptxt='Other';
            }
            if($shipmethod=="") { $shiptxt='';
            }

                $message = "Your ILL request $reqnumb for $title will be filled by $destlib <br>Due Date: $duedate<br><br>Shipped via: $shiptxt<br><br>$respnote ".
                                     "<br><br>Please email <b>".$destemail_to."</b> for future communications regarding this request ";
                // Setup php email headers
                $to=$requesterEMAIL;
                $subject = "ILL Request Filled ILL# $reqnumb  ";
                // SEND requester an email to let them know the request will be filled
                $message = preg_replace('/(?<!\r)\n/', "\r\n", $message);
                $headers = preg_replace('/(?<!\r)\n/', "\r\n", $headers);
                //mail has been sent to zack at SENYLRC for development
                //$to="spalding@senylrc.org";
                mail($to, $subject, $message, $headers, "-f donotreply@senylrc.org");

            // Sending not filledmessage#########
        }else{
            // Setting up email notification
            if ($nofillreason=='20') {
                $reasontxt='In Use';
            }
            if ($nofillreason=='21') {
                $reasontxt='Lost';
            }
            if ($nofillreason=='22') {
                $reasontxt='Non-Circulating';
            }
            if ($nofillreason=='23') {
                $reasontxt='Not on shelf';
            }
            if ($nofillreason=='24') {
                $reasontxt='Poor condition';
            }
            if ($nofillreason=='25') {
                $reasontxt='Too New';
            }
               $message = "Your ILL request $reqnumb for $title can not be filled by $destlib.<br>".
               "Reason request can not be filled: $reasontxt".
               "<br><br>$respnote<br><br> <a href='http://".$illsystemhost."'>Would you like to try a different library</a>?";
              // Setup php email headers
               $to=$requesterEMAIL;
               $subject = "ILL Request Not Filled ILL# $reqnumb  ";
               // SEND requester an email to let them know the request will be filled
               $message = preg_replace('/(?<!\r)\n/', "\r\n", $message);
               $headers = preg_replace('/(?<!\r)\n/', "\r\n", $headers);
               //mail has been sent to zack at SENYLRC for development
               //$to="spalding@senylrc.org";
               mail($to, $subject, $message, $headers, "-f donotreply@senylrc.org");

        }
    }else{
        echo "Unable to record answer for ILL request $reqnumb please call SENYLRC to report this error";
    }//end check for mysql update
}else{

  // The Request will be filled##############################################
    if ($reqanswer=='1') {
        echo "Please click the submit button to confirm you will fill the request.<br>  Thank You.<br><br>";
        echo "<h6>Recommend shipping methods</h6>";
        //echo "<b>This is an experimental feature, if unsure please verify correct shipping method</b>";
        //try to recommend delivery options
        //get the library ID with this transaction
        $LibInLISTSQL="SELECT `Requester LOC`, `Destination` FROM  `$sealSTAT`  WHERE `illNUB` = '$reqnumb'";
        //for testing
        //echo $LibInLISTSQL;
        $LibGETLIST = mysqli_query($db, $LibInLISTSQL);
        $GETLISTCOUNT = '1';
        $Librow = mysqli_fetch_assoc($LibGETLIST);
        $reqlib = $Librow["Requester LOC"];
        $destlib = $Librow["Destination"];
        $destlib = strtolower($destlib);
        //we need to find out the lending options
        $getlenderoptions="SELECT lbsyscourier,lbUSPS,lbEmpire,lbCommCourier FROM  `$sealLIB` WHERE `loc` ='$destlib'";
        // for testing
        // echo $getlenderoptions."<br>";
        $GETLISTlendOPT = mysqli_query($db, $getlenderoptions);
        $GETLISTCOUNT = '1';
        $rowlendopt = mysqli_fetch_assoc($GETLISTlendOPT);
        $lbsyscourier1 = $rowlendopt["lbsyscourier"];
        $lbUSPS1 = $rowlendopt["lbUSPS"];
        $lbEmpire1 = $rowlendopt["lbEmpire"];
        $lbCommCourier1 = $rowlendopt["lbCommCourier"];

        //we need to find out the borrower options
        $getborrowptions="SELECT lbsyscourier,lbUSPS,lbEmpire,lbCommCourier FROM  `$sealLIB` WHERE `loc` ='$reqlib'";
        // for testing
         //echo $getborrowptions."<br>";
        $GETLISTborrowOPT = mysqli_query($db, $getborrowptions);
        $GETLISTCOUNT = '1';
        $rowborrowdopt = mysqli_fetch_assoc($GETLISTborrowOPT);
        $lbsyscourier2 = $rowborrowdopt["lbsyscourier"];
        $lbUSPS2 = $rowborrowdopt["lbUSPS"];
        $lbEmpire2 = $rowborrowdopt["lbEmpire"];
        $lbCommCourier2 = $rowborrowdopt["lbCommCourier"];

        $illdelmes='0';
        if (($lbEmpire1 === "Yes") && ($lbEmpire2 === "Yes")) {
            echo "<p class='green-text'>OK to ship via Empire Library Delivery</p>";
            $illdelmes++;         
        }
        if (($lbsyscourier1 ===  "Yes") && ($lbsyscourier2===  "Yes")) {
            echo "<p class='green-text'>OK to ship via Public Library System Courier</p>";
                        $illdelmes++;         
        }
        if (($lbUSPS1 === "Yes")&&($lbUSPS2 ==="Yes")) {
            echo "<p class='green-text'>OK to ship via US Mail</p>";
                        $illdelmes++;         

        }
        if (($lbCommCourier1 === "Yes")&&($lbCommCourier2 === "Yes")) {
            echo "<p class='green-text'>OK to ship via Commercial Courier like FedEx or UPS</p>";
                        $illdelmes++;         

        }

        if ($illdelmes<1) {
            echo "<p class='red-text'>Contact the borrowing library to discuss an appropriate delivery method. More delivery information can also be found on the <a target='_blank' href='https://libguides.senylrc.org/SEAL/Delivery'>SEAL libguide</a></p>";
        }
        ?>
      <br><br><h4>Please note the delivery method, tracking info, special handling, etc</h4>
      <form action="/respond" method="post">
      <input type='hidden' name='num' value= '<?php echo $reqnumb ?>' '>
           <input type='hidden' name='fill' value='1'>
                 <input type='hidden' name='nofillreason' value='0'>
      <textarea name='respondnote' rows="4" cols="50"></textarea><br>
            Due Date:
      <input id="datepicker" name="duedate"/><br>
    Ship Method:
    <select name="shipmethod">
        <option value=""></option>
         <option value="usps">US Mail</option>
         <option value="mhls">Mid-Hudson Courier</option>
         <option value="rcls">RCLS Courier</option>
         <option value="empire">Empire Delivery</option>
         <option value="ups">UPS</option>
         <option value="fedex">FedEx</option>
         <option value="other">Other</option></select><br>
      <input type="submit" value="Submit">
      </form>
        <?php
        // The request will not be filled###########################
    }else{
        echo "Please click the submit button to confirm you can not fill the request";
        ?>
        <br><br><h4>Would you like to add a note about the decline?<br> </h4>
      <form action="/respond" method="post">
      <input type='hidden' name='num' value= '<?php echo $reqnumb ?>' '>
        <input type='hidden' name='fill' value='0'>
      <select name="nofillreason">
    <option value="0">Reason</option>
     <option value="20">In Use</option>
     <option value="21">Lost</option>
     <option value="22">Non-Circulating Format</option>
     <option value="23">Not on shelf</option>
     <option value="24">Poor condition</option>
     <option value="25">Too New</option>
   </select><br><br>
      <textarea name='respondnote' rows="4" cols="50"></textarea><br>
      <input type="submit" value="Submit">
      </form>
        <?php
    }// End if statement of yes or no#####
}// End if statement check if form came from the lender history page
mysqli_close($db);