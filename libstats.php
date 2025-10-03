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
//libstats.php####
require '/var/www/seal_wp_script/seal_function.php';

// Connect to database
require '/var/www/seal_wp_script/seal_db.inc';
$db = mysqli_connect($dbhost, $dbuser, $dbpass);
mysqli_select_db($db, $dbname);


if (($_SERVER['REQUEST_METHOD'] == 'POST')   || (isset($_GET{'page'}))) {
    $startdate = date('Y-m-d', strtotime('-7 days'));
    $enddated = $_REQUEST["enddate"];
    $startdated =   $_REQUEST["startdate"];
    $libname = $_REQUEST["libname"];
    $loc = $field_loc_location_code;
    $libname=$_REQUEST["libname"];

    if (strlen($libname) >2) {
        $loc = $libname;
    }

    $reg = '~(0[1-9]|1[012])[-/](0[1-9]|[12][0-9]|3[01])[-/](19|20)\d\d~';
    //checking if date is in the correct format
    if ((!preg_match($reg, $startdated))||(!preg_match($reg, $enddated))) {
        echo "<h1 style=color:red;>Date is not in the correct format of mm/dd/yyyy </h1>";
    } else {
        $loc = mysqli_real_escape_string($db, $loc);
        $startdate = date('Y-m-d H:i:s', strtotime(str_replace('-', '/', $startdated)));
        $enddate = date('Y-m-d H:i:s', strtotime(str_replace('-', '/', $enddated)));

        // Get total requests
        $GETREQUESTCOUNTSQLL= "SELECT * FROM `$sealSTAT` WHERE `Requester LOC` LIKE '$loc'   and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00' ";
        $retval = mysqli_query($db, $GETREQUESTCOUNTSQLL);
        if (!$retval) {
            // There was an error in the query
            die('Error: ' . mysqli_error($db));
        } else {
            if (mysqli_num_rows($retval) == 0) {
                // No results were found, display an error message
                echo "No results found.";
            } else {
                $row_cnt = mysqli_num_rows($retval);

                // Get total filled requests
                $FINDFILL= "SELECT * FROM `$sealSTAT` WHERE `Requester LOC` LIKE '$loc'     and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'  and  Fill =1 ";
                $retfilled =   mysqli_query($db, $FINDFILL);
                $row_fill = mysqli_num_rows($retfilled);
                // Get percentage fill
                $percentfill = $row_fill/$row_cnt;
                $percent_friendly_fill = number_format($percentfill * 100, 2) . '%';

                // Get total not filled requests
                $FINDNOTFILL= "SELECT * FROM `$sealSTAT` WHERE `Requester LOC` LIKE '$loc'   and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'  and  Fill =0 ";
                $retnotfilled =   mysqli_query($db, $FINDNOTFILL);
                $row_notfill = mysqli_num_rows($retnotfilled);

                // Get percentage fill
                $percentnotfill = $row_notfill/$row_cnt;
                $percent_friendly_notfill = number_format($percentnotfill * 100, 2) . '%';

                // Get total requests expired
                $FINDEXPIRE= "SELECT * FROM `$sealSTAT` WHERE `Requester LOC` LIKE '$loc'   and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'  and  Fill =4 ";
                $retexpire =   mysqli_query($db, $FINDEXPIRE);
                $row_expire = mysqli_num_rows($retexpire);
                // Get percentage fill
                $percentexpire = $row_expire/$row_cnt;
                $percent_friendly_expire = number_format($percentexpire * 100, 2) . '%';

                // Get total requests not answered
                $FINDNOANSW= "SELECT * FROM `$sealSTAT` WHERE `Requester LOC` LIKE '$loc'   and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'  and  Fill =3 ";
                $retnoansw =   mysqli_query($db, $FINDNOANSW);
                $row_noansw = mysqli_num_rows($retnoansw);
                // Get percentage fill
                $percentnoansw = $row_noansw/$row_cnt;
                $percent_friendly_noansw = number_format($percentnoansw * 100, 2) . '%';

                // Get total requests canceled
                $CANANSW= "SELECT * FROM `$sealSTAT` WHERE `Requester LOC` LIKE '$loc'    and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'  and  Fill =6 ";
                $canansw =   mysqli_query($db, $CANANSW);
                $row_cancel = mysqli_num_rows($canansw);
                // Get percentage fill
                $percentcancel = $row_cancel/$row_cnt;
                $percent_friendly_cancel = number_format($percentcancel * 100, 2) . '%';



                // Get the library name
                $libnames= "SELECT Name FROM `$sealLIB` WHERE `LOC` LIKE '$loc'  ";
                $libnameq =   mysqli_query($db, $libnames);
                while ($row = $libnameq->fetch_assoc()) {
                    $libname =  $row["Name"];
                }

                // Stats overall in the time frame chosen
                echo "<h3>From $startdated to $enddated </h3>";
                echo "<h4>Borrower  statistics for ".$libname."  </h4>";
                echo "Total Request Placed  ".$row_cnt." <br>";
                echo "Number of Request Filled: ".$row_fill." (".$percent_friendly_fill.")<br>";
                echo "Number of Request Not Filled: ".$row_notfill." (".$percent_friendly_notfill.")<br>";
                echo "Number of Request Expired: ".$row_expire." (".$percent_friendly_expire.")<br>";
                echo "Number of Request Canceled: ".$row_cancel." (".$percent_friendly_cancel.")<br>";
                echo "Number of Not Answered Yet: ".$row_noansw." (".$percent_friendly_noansw.")<br><br>";




                echo "<hr><h4>Break down of requests</h4>";
                // Find which systems they sent request to
                $destsystem=" SELECT distinct (`DestSystem` )  FROM `$sealSTAT` WHERE `Requester LOC` LIKE '$loc'   and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'  ";
                $destsystemq = mysqli_query($db, $destsystem);
                // loop through the results of destion systems
                while ($row = mysqli_fetch_assoc($destsystemq)) {
                    $dessysvar= $row['DestSystem'];
                    $destsystemcount=" SELECT `itype`  FROM `$sealSTAT` WHERE `Requester LOC` LIKE '$loc'   and `DestSystem`='$dessysvar' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'  ";
                    $destsystemcountq = mysqli_query($db, $destsystemcount);
                    // Count the number of requests to that system
                    $destnum_rows = mysqli_num_rows($destsystemcountq);

                    // Get percentage
                    $percentdestnum_rows = $destnum_rows/$row_cnt;
                    $percent_friendly_destnum = number_format($percentdestnum_rows * 100, 2) . '%';


                    // translate system code to text name
                    if (strcmp($dessysvar, 'MH')==0) {
                        $dessysvartxt = "Mid Hudson Library System";
                    }else if (strcmp($dessysvar, 'RC')==0) {
                        $dessysvartxt = "Ramapo Catskill Library System";
                    }else if (strcmp($dessysvar, 'DU')==0) {
                        $dessysvartxt = "Dutchess BOCES";
                    }else if (strcmp($dessysvar, 'OU')==0) {
                        $dessysvartxt = "Orange Ulster BOCES";
                    }else if (strcmp($dessysvar, 'RB')==0) {
                        $dessysvartxt = "Rockland BOCES";
                    }else if (strcmp($dessysvar, 'SB')==0) {
                        $dessysvartxt = "Sullivan BOCES";
                    }else if (strcmp($dessysvar, 'UB')==0) {
                        $dessysvartxt = "Ulster BOCES";
                    }else{
                        $dessysvartxt = "SENYLRC Group";
                    }
        
        




                    echo " ". $destnum_rows ." (".$percent_friendly_destnum.") overall requests were made  to <strong> ".$dessysvartxt."</strong><br>";
                    // Find which item types were requests
                    $destitype=" SELECT distinct (`itype` )  FROM `$sealSTAT`  WHERE `Requester LOC` LIKE '$loc'    and `DestSystem`='$dessysvar' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00' ";
                    $destitypeq = mysqli_query($db, $destitype);
                    // loop through the results of items from that destination
                    while ($row2 = mysqli_fetch_assoc($destitypeq)) {
                        $dessysitype= $row2['itype'];
                        // Remove any white space

                        $destitemcount=" SELECT `fill`  FROM `$sealSTAT` WHERE `Itype`='$dessysitype' and `Requester LOC` LIKE '$loc'  and `DestSystem`='$dessysvar' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'";
                        $destitemcountq = mysqli_query($db, $destitemcount);
                        // Count the number of reuqests to that system
                        $destnumitype_rows = mysqli_num_rows($destitemcountq);

                        // Get percentage
                        $percenttypesys_rows = $destnumitype_rows/$destnum_rows;
                        $percent_friendly_typesys = number_format($percenttypesys_rows * 100, 2) . '%';


                        echo "&nbsp&nbsp&nbsp".$destnumitype_rows." (".$percent_friendly_typesys.") of the request to ".$dessysvartxt." were <strong>".$dessysitype."</strong><br>";

                        // Find what the fill rate is
                        $destitemcountfill=" SELECT `fill`  FROM `$sealSTAT` WHERE Fill='1' and `Itype`='$dessysitype' and `Requester LOC` LIKE '$loc'   and `DestSystem`='$dessysvar' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'";
                        $destitemcountfillq = mysqli_query($db, $destitemcountfill);
                        // Count the number of fills
                        $destnumfilled_rows = mysqli_num_rows($destitemcountfillq);

                        // Get percentage
                        $percent1_rows =$destnumfilled_rows/ $destnumitype_rows;
                        $percent_friendly_1 = number_format($percent1_rows * 100, 2) . '%';

                        echo " &nbsp&nbsp&nbsp&nbsp&nbsp      $destnumfilled_rows (".$percent_friendly_1.") were filled<br>";

                        // Find what the unfill rate is
                        $destitemcountunfill=" SELECT `fill`  FROM `$sealSTAT` WHERE Fill='0' and `Itype`='$dessysitype' and `Requester LOC` LIKE '$loc'   and `DestSystem`='$dessysvar' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'";
                        $destitemcountunfillq = mysqli_query($db, $destitemcountunfill);
                        // Count the number of unfilled
                        $destnumunfilled_rows = mysqli_num_rows($destitemcountunfillq);

                        // Get percentage
                        $percent2_rows =$destnumunfilled_rows/ $destnumitype_rows;
                        $percent_friendly_2 = number_format($percent2_rows * 100, 2) . '%';

                        echo "&nbsp&nbsp&nbsp&nbsp&nbsp   $destnumunfilled_rows (". $percent_friendly_2.") were not filled<br>";

                        // Find what the expire rate is
                        $destitemcountexfill=" SELECT `fill`  FROM `$sealSTAT` WHERE Fill='4' and `Itype`='$dessysitype' and `Requester LOC` LIKE '$loc'  and `DestSystem`='$dessysvar' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'";
                        $destitemcountexfillq = mysqli_query($db, $destitemcountexfill);
                        // Count the number of expired requests
                        $destnumexfilled_rows = mysqli_num_rows($destitemcountexfillq);

                        // Get percentage
                        $percent3_rows =$destnumexfilled_rows/  $destnumitype_rows;
                        $percent_friendly_3 = number_format($percent3_rows * 100, 2) . '%';

                        echo "&nbsp&nbsp&nbsp&nbsp&nbsp   $destnumexfilled_rows (". $percent_friendly_3.") were expired<br>";

                        // Find what the cancel rate is
                        $destitemcountcanfill=" SELECT `fill`  FROM `$sealSTAT` WHERE Fill='6' and `Itype`='$dessysitype' and `Requester LOC` LIKE '$loc'  and `DestSystem`='$dessysvar' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'";
                        $destitemcountcanfillq = mysqli_query($db, $destitemcountcanfill);
                        // Count the number of canceled requests
                        $destnumcanfilled_rows = mysqli_num_rows($destitemcountcanfillq);

                        // Get percentage
                        $percent4_rows =$destnumcanfilled_rows/ $destnumitype_rows ;
                        $percent_friendly_4 = number_format($percent4_rows * 100, 2) . '%';

                        echo "&nbsp&nbsp&nbsp&nbsp&nbsp   $destnumcanfilled_rows  (". $percent_friendly_4.") were canceled<br>";

                        // Find the numbered not answer yet
                        $destitemcountnoanswfill=" SELECT `fill`  FROM `$sealSTAT` WHERE Fill='3' and `Itype`='$dessysitype' and `Requester LOC` LIKE '$loc'  and `DestSystem`='$dessysvar' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'";

                        $destitemcountnoanswfillq = mysqli_query($db, $destitemcountnoanswfill);
                        // Count the number of requests not answered yet
                        $destnumnoanswfilled_rows = mysqli_num_rows($destitemcountnoanswfillq);

                        // Get percentage
                        $percent5_rows =$destnumnoanswfilled_rows/ $destnumitype_rows ;
                        $percent_friendly_5 = number_format($percent5_rows * 100, 2) . '%';

                        echo "&nbsp&nbsp&nbsp&nbsp&nbsp    $destnumnoanswfilled_rows  (". $percent_friendly_5.") of requests not answered<br>";
                        echo "<br>";
                    }
                    echo "<br><hr><br>";
                }
            } //end check for 0 results
        }// end if (!$retval)
    }//end date format check
} else {
    if (isset($_GET['loc'])) {
        $loc = $_GET['loc'];
    } else {
        $loc='null';
    }

    $libname=$_REQUEST["libname"]; ?>
    <h2>Borrowing Statistics</h2>
     <h2>Enter your desired date range:</h2>


     <form action="/libstats?<?php echo $_SERVER['QUERY_STRING']; ?>" method="post">
     Start Date:
     <input id="datepicker" name="startdate"/>
     End Date:
     <input id="datepicker2" name="enddate"/>
<br><br>

     <input type="submit" value="Submit">
    </form>

    <?php
}

?>