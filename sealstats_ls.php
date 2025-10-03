<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/themes/smoothness/jquery-ui.css">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js"></script>

  <script>
  $(document).ready(function() {
     $("#datepicker").datepicker();
     $("#datepicker2").datepicker();
     $("#datepickerl").datepicker();
     $("#datepickerl2").datepicker();
     $("#expdatepicker").datepicker();
     $("#expdatepicker2").datepicker();
     $("#top10datepicker").datepicker();
     $("#top10datepicker2").datepicker();
     $("#top10fdatepicker").datepicker();
     $("#top10fdatepicker2").datepicker();
  });
  </script>
<?php
//sealstats_ls.php
// Connect to database
require '/var/www/seal_wp_script/seal_function.php';
require '/var/www/seal_wp_script/seal_db.inc';

$db = mysqli_connect($dbhost, $dbuser, $dbpass);
mysqli_select_db($db, $dbname);

if (($_SERVER['REQUEST_METHOD'] == 'POST')   || (isset($_GET{'page'}))) {
    if ($_REQUEST['stattype'] == 'wholesystem') {
        // A request to generate stats has been posted
        $startdate = date('Y-m-d', strtotime('-7 days'));
        $enddated = $_REQUEST["enddate"];
        $startdated =   $_REQUEST["startdate"];
        $libsystem = $_REQUEST["system"];
        $startdate = date('Y-m-d H:i:s', strtotime(str_replace('-', '/', $startdated)));
        $enddate = date('Y-m-d H:i:s', strtotime(str_replace('-', '/', $enddated)));

        //if a system is not selected dispaly this error message
        if ($libsystem=='none') {
            echo "<h1 style=color:red;>Please chose a system first to run stats</h1><br><br>";
        } else {
            // Get total requests
            $GETREQUESTCOUNTSQLL= "SELECT * FROM `$sealSTAT` WHERE  `ReqSystem` LIKE '%$libsystem%'  and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00' ";


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
                    $FINDFILL= "SELECT * FROM `$sealSTAT` WHERE  `ReqSystem` LIKE '%$libsystem%'  and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'  and  Fill =1 ";
                    $retfilled =   mysqli_query($db, $FINDFILL);
                    $row_fill = mysqli_num_rows($retfilled);
                    // Get percentage fill
                    $percentfill = $row_fill/$row_cnt;
                    $percent_friendly_fill = number_format($percentfill * 100, 2) . '%';

                    // Get total not filled requests
                    $FINDNOTFILL= "SELECT * FROM `$sealSTAT` WHERE  `ReqSystem` LIKE '%$libsystem%'  and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'  and  Fill =0 ";
                    $retnotfilled =   mysqli_query($db, $FINDNOTFILL);
                    $row_notfill = mysqli_num_rows($retnotfilled);
                    // Get percentage fill
                    $percentnotfill = $row_notfill/$row_cnt;
                    $percent_friendly_notfill = number_format($percentnotfill * 100, 2) . '%';

                    // Get total requests expired
                    $FINDEXPIRE= "SELECT * FROM `$sealSTAT` WHERE  `ReqSystem` LIKE '%$libsystem%'  and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'  and  Fill =4  ";
                    $retexpire =   mysqli_query($db, $FINDEXPIRE);
                    $row_expire = mysqli_num_rows($retexpire);
                    // Get percentage fill
                    $percentexpire = $row_expire/$row_cnt;
                    $percent_friendly_expire = number_format($percentexpire * 100, 2) . '%';

                    // Get total requests not answered
                    $FINDNOANSW= "SELECT * FROM `$sealSTAT` WHERE  `ReqSystem` LIKE '%$libsystem%'  and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'  and  Fill =3 ";
                    $retnoansw =   mysqli_query($db, $FINDNOANSW);
                    $row_noansw = mysqli_num_rows($retnoansw);
                    // Get percentage fill
                    $percentnoansw = $row_noansw/$row_cnt;
                    $percent_friendly_noansw = number_format($percentnoansw * 100, 2) . '%';

                    // Get total requests canceled
                    $CANANSW= "SELECT * FROM `$sealSTAT` WHERE  `ReqSystem` LIKE '%$libsystem%'  and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'  and  Fill =6 ";
                    $canansw =   mysqli_query($db, $CANANSW);
                    $row_cancel = mysqli_num_rows($canansw);
                    // Get percentage fill
                    $percentcancel = $row_cancel/$row_cnt;
                    $percent_friendly_cancel = number_format($percentcancel * 100, 2) . '%';

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


                    // Stats overall in the time frame chosen
                    echo "<h1><center>SEAL Borrowing Stats from $startdated to $enddated </h1></center>";
                    echo "<h1>Library System ".$libsystemtxt." </h1>";
                    echo "Total Request ".$row_cnt." <br>";
                    echo "Number of Request Filled: ".$row_fill." (".$percent_friendly_fill.")<br>";
                    echo "Number of Request Not Filled: ".$row_notfill." (".$percent_friendly_notfill.")<br>";
                    echo "Number of Request Expired: ".$row_expire." (".$percent_friendly_expire.")<br>";
                    echo "Number of Request Canceled: ".$row_cancel." (".$percent_friendly_cancel.")<br>";
                    echo "Number of Not Answered Yet: ".$row_noansw." (".$percent_friendly_noansw.")<br><br>";

                    // Calulcate fill from other systems
                    if (strlen($libsystem)>1) {
                        echo "<h1>Break down of requests</h1>";
                        // Find which systems they sent request to
                        $destsystem=" SELECT distinct (`DestSystem` )  FROM `$sealSTAT` WHERE `ReqSystem`='$libsystem' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'  ";
                        $destsystemq = mysqli_query($db, $destsystem);
                        // loop through the results of destion systems
                        while ($row = mysqli_fetch_assoc($destsystemq)) {
                            $dessysvar= $row['DestSystem'];
                            $destsystemcount=" SELECT `itype`  FROM `$sealSTAT` WHERE `ReqSystem`='$libsystem'  and `DestSystem`='$dessysvar' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'  ";
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

                            echo " ".$destnum_rows." (".$percent_friendly_destnum.") overall requests were made  to <strong> ".$dessysvartxt."</strong><br>";
                            // Find which item types were requests
                            $destitype=" SELECT distinct (`itype` )  FROM `$sealSTAT`  WHERE `ReqSystem`='$libsystem'  and `DestSystem`='$dessysvar' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00' ";
                            $destitypeq = mysqli_query($db, $destitype);
                            // loop through the results of items from that destination
                            while ($row2 = mysqli_fetch_assoc($destitypeq)) {
                                $dessysitype= $row2['itype'];
                                // Remove any white space
                                $destitemcount=" SELECT `fill`  FROM `$sealSTAT` WHERE `Itype`='$dessysitype' and `ReqSystem`='$libsystem'  and `DestSystem`='$dessysvar' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'";
                                $destitemcountq = mysqli_query($db, $destitemcount);
                                // Count the number of reuqests to that system
                                $destnumitype_rows = mysqli_num_rows($destitemcountq);
                                // Get percentage
                                $percenttypesys_rows = $destnumitype_rows/$row_cnt;
                                $percent_friendly_typesys = number_format($percenttypesys_rows * 100, 2) . '%';
                                echo "&nbsp&nbsp&nbsp".$destnumitype_rows." (".$percent_friendly_typesys.") of the request to ".$dessysvartxt." were <strong>".$dessysitype."</strong><br>";
                                // Find what the fill rate is
                                $destitemcountfill=" SELECT `fill`  FROM `$sealSTAT` WHERE Fill='1' and `Itype`='$dessysitype' and `ReqSystem`='$libsystem'  and `DestSystem`='$dessysvar' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'";
                                $destitemcountfillq = mysqli_query($db, $destitemcountfill);
                                // Count the number of fills
                                $destnumfilled_rows = mysqli_num_rows($destitemcountfillq);
                                // Get percentage
                                $percent1_rows =$destnumfilled_rows/ $destnumitype_rows;
                                $percent_friendly_1 = number_format($percent1_rows * 100, 2) . '%';
                                echo " &nbsp&nbsp&nbsp&nbsp&nbsp      $destnumfilled_rows (".$percent_friendly_1.") were filled<br>";
                                // Find what the unfill rate is
                                $destitemcountunfill=" SELECT `fill`  FROM `$sealSTAT` WHERE Fill='0' and `Itype`='$dessysitype' and `ReqSystem`='$libsystem'  and `DestSystem`='$dessysvar' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'";
                                $destitemcountunfillq = mysqli_query($db, $destitemcountunfill);
                                // Count the number of unfilled
                                $destnumunfilled_rows = mysqli_num_rows($destitemcountunfillq);
                                // Get percentage
                                $percent2_rows =$destnumunfilled_rows/ $destnumitype_rows;
                                $percent_friendly_2 = number_format($percent2_rows * 100, 2) . '%';
                                echo "&nbsp&nbsp&nbsp&nbsp&nbsp   $destnumunfilled_rows (". $percent_friendly_2.") were not filled<br>";
                                // Find what the expire rate is
                                $destitemcountexfill=" SELECT `fill`  FROM `$sealSTAT` WHERE Fill='4' and `Itype`='$dessysitype' and `ReqSystem`='$libsystem'  and `DestSystem`='$dessysvar' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'";
                                $destitemcountexfillq = mysqli_query($db, $destitemcountexfill);
                                // Count the number of expired requests
                                $destnumexfilled_rows = mysqli_num_rows($destitemcountexfillq);
                                // Get percentage
                                $percent3_rows = $destnumexfilled_rows/$destnumitype_rows;
                                ;
                                $percent_friendly_3 = number_format($percent3_rows * 100, 2) . '%';
                                echo "&nbsp&nbsp&nbsp&nbsp&nbsp   $destnumexfilled_rows (". $percent_friendly_3.") were expired<br>";
                                // Find what the cancel rate is
                                $destitemcountcanfill=" SELECT `fill`  FROM `$sealSTAT` WHERE Fill='6' and `Itype`='$dessysitype' and `ReqSystem`='$libsystem'  and `DestSystem`='$dessysvar' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'";
                                $destitemcountcanfillq = mysqli_query($db, $destitemcountcanfill);
                                // Count the number of canceled requests
                                $destnumcanfilled_rows = mysqli_num_rows($destitemcountcanfillq);
                                // Get percentage
                                $percent4_rows =$destnumcanfilled_rows/$destnumitype_rows;
                                $percent_friendly_4 = number_format($percent4_rows * 100, 2) . '%';
                                echo "&nbsp&nbsp&nbsp&nbsp&nbsp   $destnumcanfilled_rows  (". $percent_friendly_4.") were canceled<br>";
                                // Find the numbered not answer yet
                                $destitemcountnoanswfill=" SELECT `fill`  FROM `$sealSTAT` WHERE Fill='3' and `Itype`='$dessysitype' and `ReqSystem`='$libsystem'  and `DestSystem`='$dessysvar' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'";
                                $destitemcountnoanswfillq = mysqli_query($db, $destitemcountnoanswfill);
                                // Count the number of requests not answered yet
                                $destnumnoanswfilled_rows = mysqli_num_rows($destitemcountnoanswfillq);
                                // Get percentage
                                $percent5_rows =$destnumnoanswfilled_rows/ $destnumitype_rows;
                                $percent_friendly_5 = number_format($percent5_rows * 100, 2) . '%';
                                echo "&nbsp&nbsp&nbsp&nbsp&nbsp   $destnumnoanswfilled_rows  (". $percent_friendly_5.") of requests not answered yet<br>";
                                echo "<br>";
                            }
                            echo "<hr>";
                        }
                    }//end check if libsystem is larger than 1 character
                }//end check if system is none
            } //end check if 0 results
        }// end  if (!$retval)
        // End of whole system borrowing stats
    } elseif ($_REQUEST['stattype'] == 'wholesystemlending') {


        // A request to generate stats has been posted
        $startdate = date('Y-m-d', strtotime('-7 days'));
        $enddated = $_REQUEST["enddate"];
        $startdated =   $_REQUEST["startdate"];
        $libsystem = $_REQUEST["system"];
        //if a system is not selected dispaly this error message
        if ($libsystem=='none') {
            echo "<h1 style=color:red;>Please chose a system first to run stats</h1><br><br>";
        } else {
            $startdate = date('Y-m-d H:i:s', strtotime(str_replace('-', '/', $startdated)));
            $enddate = date('Y-m-d H:i:s', strtotime(str_replace('-', '/', $enddated)));


            // Get total requests received


            $GETREQUESTCOUNTSQLL= "SELECT * FROM `$sealSTAT` WHERE `DestSystem` LIKE '%$libsystem%'  and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00' ";
            //  echo $GETREQUESTCOUNTSQLL;
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
                    $FINDFILL= "SELECT * FROM `$sealSTAT` WHERE `DestSystem` LIKE '%$libsystem%' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'  and  Fill =1 ";
                    $retfilled =   mysqli_query($db, $FINDFILL);
                    $row_fill = mysqli_num_rows($retfilled);
                    // Get percentage fill
                    $percentfill = $row_fill/$row_cnt;
                    $percent_friendly_fill = number_format($percentfill * 100, 2) . '%';

                    // Get total not filled requests
                    $FINDNOTFILL= "SELECT * FROM `$sealSTAT` WHERE `DestSystem` LIKE '%$libsystem%' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'  and  Fill =0 ";
                    $retnotfilled =   mysqli_query($db, $FINDNOTFILL);
                    $row_notfill = mysqli_num_rows($retnotfilled);

                    // Get percentage fill
                    $percentnotfill = $row_notfill/$row_cnt;
                    $percent_friendly_notfill = number_format($percentnotfill * 100, 2) . '%';

                    // Get total requests expired
                    $FINDEXPIRE= "SELECT * FROM `$sealSTAT` WHERE `DestSystem` LIKE '%$libsystem%' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'  and  Fill =4 ";
                    $retexpire =   mysqli_query($db, $FINDEXPIRE);
                    $row_expire = mysqli_num_rows($retexpire);
                    // Get percentage fill
                    $percentexpire = $row_expire/$row_cnt;
                    $percent_friendly_expire = number_format($percentexpire * 100, 2) . '%';

                    // Get total requests not answered
                    $FINDNOANSW= "SELECT * FROM `$sealSTAT` WHERE `DestSystem` LIKE '%$libsystem%' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'  and  Fill =3 ";
                    $retnoansw =   mysqli_query($db, $FINDNOANSW);
                    $row_noansw = mysqli_num_rows($retnoansw);
                    // Get percentage fill
                    $percentnoansw = $row_noansw/$row_cnt;
                    $percent_friendly_noansw = number_format($percentnoansw * 100, 2) . '%';

                    // Get total requests canceled
                    $CANANSW= "SELECT * FROM `$sealSTAT` WHERE `DestSystem` LIKE '%$libsystem%' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'  and  Fill =6 ";
                    $canansw =   mysqli_query($db, $CANANSW);
                    $row_cancel = mysqli_num_rows($canansw);
                    // Get percentage fill
                    $percentcancel = $row_cancel/$row_cnt;
                    $percent_friendly_cancel = number_format($percentcancel * 100, 2) . '%';

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


                    // Stats overall in the time frame chosen
                    echo "<h1><center>SEAL Lending Stats from $startdated to $enddated </h1></center>";
                    echo "<h1>Lender request statistics for ".$libsystemtxt."  </h1>";
                    echo "Total Requests received ".$row_cnt." <br>";
                    echo "Number of Requests Filled: ".$row_fill." (".$percent_friendly_fill.")<br>";
                    echo "Number of Requests Not Filled: ".$row_notfill." (".$percent_friendly_notfill.")<br>";
                    echo "Number of Requests Expired: ".$row_expire." (".$percent_friendly_expire.")<br>";
                    echo "Number of Requests Canceled: ".$row_cancel." (".$percent_friendly_cancel.")<br>";
                    echo "Number of Requests Not Answered Yet: ".$row_noansw." (".$percent_friendly_noansw.")<br><br>";

                    // Calulcate fill to other systems
                    if (strlen($libsystem)>1) {
                        echo "<h1>Break down of lending requests</h1>";
                        // Find which systems they sent request to
                        $destsystem=" SELECT distinct (`ReqSystem` )  FROM `$sealSTAT` WHERE `DestSystem`='$libsystem' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'";

                        $destsystemq = mysqli_query($db, $destsystem);
                        // loop through the results of destion systems
                        while ($row = mysqli_fetch_assoc($destsystemq)) {
                            $dessysvar= $row['ReqSystem'];
                            $destsystemcount="SELECT `itype` FROM `$sealSTAT` WHERE `DestSystem`='$libsystem' and `ReqSystem`='$dessysvar' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'  ";

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

                            echo " ".$destnum_rows." (".$percent_friendly_destnum.") overall lendinng requests were made  from <strong> ".$dessysvartxt."</strong><br>";
                            // Find which item types were requests
                            $destitype=" SELECT distinct (`itype` )  FROM `$sealSTAT`  WHERE `DestSystem`='$libsystem'  and `ReqSystem`='$dessysvar' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00' ";
                            $destitypeq = mysqli_query($db, $destitype);
                            // loop through the results of items from that destination
                            while ($row2 = mysqli_fetch_assoc($destitypeq)) {
                                $dessysitype= $row2['itype'];
                                // Remove any white space
                                $destitemcount=" SELECT `fill`  FROM `$sealSTAT` WHERE `Itype`='$dessysitype' and `DestSystem`='$libsystem'  and `ReqSystem`='$dessysvar' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'";
                                $destitemcountq = mysqli_query($db, $destitemcount);
                                // Count the number of reuqests from that system
                                $destnumitype_rows = mysqli_num_rows($destitemcountq);
                                // Get percentage
                                $percenttypesys_rows = $destnumitype_rows/$row_cnt;
                                $percent_friendly_typesys = number_format($percenttypesys_rows * 100, 2) . '%';
                                echo "&nbsp&nbsp&nbsp".$destnumitype_rows." (".$percent_friendly_typesys.") of the request from ".$dessysvartxt." were <strong>".$dessysitype."</strong><br>";
                                // Find what the fill rate is
                                $destitemcountfill=" SELECT `fill`  FROM `$sealSTAT` WHERE Fill='1' and `Itype`='$dessysitype' and `DestSystem`='$libsystem'  and `ReqSystem`='$dessysvar' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'";
                                $destitemcountfillq = mysqli_query($db, $destitemcountfill);
                                // Count the number of fills
                                $destnumfilled_rows = mysqli_num_rows($destitemcountfillq);
                                // Get percentage
                                $percent1_rows =$destnumfilled_rows/ $destnumitype_rows;
                                $percent_friendly_1 = number_format($percent1_rows * 100, 2) . '%';
                                echo " &nbsp&nbsp&nbsp&nbsp&nbsp      $destnumfilled_rows (".$percent_friendly_1.") were filled<br>";
                                // Find what the unfill rate is
                                $destitemcountunfill=" SELECT `fill`  FROM `$sealSTAT` WHERE Fill='0' and `Itype`='$dessysitype' and `DestSystem`='$libsystem'  and `ReqSystem`='$dessysvar' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'";
                                $destitemcountunfillq = mysqli_query($db, $destitemcountunfill);
                                // Count the number of unfilled
                                $destnumunfilled_rows = mysqli_num_rows($destitemcountunfillq);
                                // Get percentage
                                $percent2_rows =$destnumunfilled_rows/ $destnumitype_rows;
                                $percent_friendly_2 = number_format($percent2_rows * 100, 2) . '%';
                                echo "&nbsp&nbsp&nbsp&nbsp&nbsp   $destnumunfilled_rows (". $percent_friendly_2.") were not filled<br>";
                                // Find what the expire rate is
                                $destitemcountexfill=" SELECT `fill`  FROM `$sealSTAT` WHERE Fill='4' and `Itype`='$dessysitype' and `DestSystem`='$libsystem'  and `ReqSystem`='$dessysvar' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'";
                                $destitemcountexfillq = mysqli_query($db, $destitemcountexfill);
                                // Count the number of expired requests
                                $destnumexfilled_rows = mysqli_num_rows($destitemcountexfillq);
                                // Get percentage
                                $percent3_rows = $destnumexfilled_rows/$destnumitype_rows;
                                ;
                                $percent_friendly_3 = number_format($percent3_rows * 100, 2) . '%';
                                echo "&nbsp&nbsp&nbsp&nbsp&nbsp   $destnumexfilled_rows (". $percent_friendly_3.") were expired<br>";
                                // Find what the cancel rate is
                                $destitemcountcanfill=" SELECT `fill`  FROM `$sealSTAT` WHERE Fill='6' and `Itype`='$dessysitype' and `DestSystem`='$libsystem'  and `ReqSystem`='$dessysvar' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'";
                                $destitemcountcanfillq = mysqli_query($db, $destitemcountcanfill);
                                // Count the number of canceled requests
                                $destnumcanfilled_rows = mysqli_num_rows($destitemcountcanfillq);
                                // Get percentage
                                $percent4_rows =$destnumcanfilled_rows/$destnumitype_rows;
                                $percent_friendly_4 = number_format($percent4_rows * 100, 2) . '%';
                                echo "&nbsp&nbsp&nbsp&nbsp&nbsp   $destnumcanfilled_rows  (". $percent_friendly_4.") were canceled<br>";
                                // Find the numbered not answer yet
                                $destitemcountnoanswfill=" SELECT `fill`  FROM `$sealSTAT` WHERE Fill='3' and `Itype`='$dessysitype' and `DestSystem`='$libsystem'  and `ReqSystem`='$dessysvar' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'";
                                $destitemcountnoanswfillq = mysqli_query($db, $destitemcountnoanswfill);
                                // Count the number of requests not answered yet
                                $destnumnoanswfilled_rows = mysqli_num_rows($destitemcountnoanswfillq);
                                // Get percentage
                                $percent5_rows =$destnumnoanswfilled_rows/ $destnumitype_rows;
                                $percent_friendly_5 = number_format($percent5_rows * 100, 2) . '%';
                                echo "&nbsp&nbsp&nbsp&nbsp&nbsp   $destnumnoanswfilled_rows  (". $percent_friendly_5.") of requests not answered yet<br>";
                                echo "<br>";
                            }
                            echo "<hr>";
                        }
                    }
                }//end check if system is set to none
            }// end check if 0 results
        }// end   if (!$retval) 
        // End of whole system lending stats
    } elseif ($_REQUEST['stattype'] == 'top10fstats') {
        // Generate the top 10 filling requests
        $startdate = date('Y-m-d', strtotime('-7 days'));
        $enddated = $_REQUEST["enddate"];
        $startdated =   $_REQUEST["startdate"];

        $startdate = date('Y-m-d H:i:s', strtotime(str_replace('-', '/', $startdated)));
        $enddate = date('Y-m-d H:i:s', strtotime(str_replace('-', '/', $enddated)));
        echo "<h1><center>Top 10 Libraries Filling Requests from $startdated to $enddated </h1></center>";
        $GETREQUESTCOUNTSQLL= "SELECT  `Destination`, count(*)  FROM `$sealSTAT` WHERE  	DestSystem  = '".$field_home_library_system."' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00' and Fill=1  GROUP BY `Destination` ORDER BY count(*) DESC LIMIT 10 ";
        $retval = mysqli_query($db, $GETREQUESTCOUNTSQLL);
        $row_cnt = mysqli_num_rows($retval);
        echo "<table><tr><th>Library Name</th><th>Number of Fills</th></tr>";
        while ($row2 = mysqli_fetch_assoc($retval)) {
            $reqestid = $row2['Destination'];
            $countnumb =  $row2["count(*)"];
            $libnames= "SELECT loc,Name FROM `$sealLIB` WHERE `loc`='$reqestid' ";
            $result = mysqli_query($db, $libnames);
            while ($row =  $result->fetch_assoc()) {
                $reqestid =  $row["Name"];
            }
            echo "<tr><td>$reqestid</td><td>$countnumb</td></tr>";
        }
        echo "</table>";
    } elseif ($_REQUEST['stattype'] == 'top10stats') {
        $startdate = date('Y-m-d', strtotime('-7 days'));
        $enddated = $_REQUEST["enddate"];
        $startdated =   $_REQUEST["startdate"];
        $startdate = date('Y-m-d H:i:s', strtotime(str_replace('-', '/', $startdated)));
        $enddate = date('Y-m-d H:i:s', strtotime(str_replace('-', '/', $enddated)));
        echo "<h1><center>Top 10 Libraries Making Requests from $startdated to $enddated </h1></center>";
        $GETREQUESTCOUNTSQLL= "SELECT  `Requester LOC`, count(*)  FROM `$sealSTAT`  WHERE  	ReqSystem  = '".$field_home_library_system."' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'   GROUP BY `Requester LOC` ORDER BY count(*) DESC LIMIT 10 ";
        $retval = mysqli_query($db, $GETREQUESTCOUNTSQLL);
        $row_cnt = mysqli_num_rows($retval);
        echo "<table><tr><th>Library Name</th><th>Number of Requests</th></tr>";
        while ($row2 = mysqli_fetch_assoc($retval)) {
            $reqestid = $row2['Requester LOC'];
            $countnumb =  $row2["count(*)"];
            $libnames= "SELECT loc,Name FROM `$sealLIB` WHERE `loc`='$reqestid' ";
            $result = mysqli_query($db, $libnames);
            while ($row =  $result->fetch_assoc()) {
                $reqestid =  $row["Name"];
            }
            echo "<tr><td>$reqestid</td><td>$countnumb</td></tr>";
        }
        echo "</table>";
    } elseif ($_REQUEST['stattype'] == 'expirestats') {
        $startdate = date('Y-m-d', strtotime('-7 days'));
        $enddated = $_REQUEST["enddate"];
        $startdated =   $_REQUEST["startdate"];
        $startdate = date('Y-m-d H:i:s', strtotime(str_replace('-', '/', $startdated)));
        $enddate = date('Y-m-d H:i:s', strtotime(str_replace('-', '/', $enddated)));
        // Get total requests that expired
        $GETREQUESTCOUNTSQLL= "SELECT * FROM `$sealSTAT`   WHERE  	DestSystem  = '".$field_home_library_system."' and `Timestamp` >= '$startdate 00:00:00' and `Timestamp` <= '$enddate 00:00:00'  and  Fill =4 ORDER BY Destination";
        
        $retval = mysqli_query($db, $GETREQUESTCOUNTSQLL);
        $row_cnt = mysqli_num_rows($retval);

        // loop through the results of items from that destination
        echo "<h1><center>SEAL Requests that expired from $startdated to $enddated </h1></center>";
        echo  "$row_cnt  results"; ?>
     <table><tr><th>ILL #</th><th>LOC</th><th>Destination Library</th><th>Requesting Library</th><th>Note</th><th>Title</th><th>Item Type</th><th>Date</th></tr>
        <?php
        while ($row2 = mysqli_fetch_assoc($retval)) {
            $dessid= $row2['Destination'];
            $illid= $row2['illNUB'];
            $note= $row2['responderNOTE'];
            $title= $row2['Title'];
            $itype= $row2['Itype'];
            $date= $row2['Timestamp'];
            $reqestid = $row2['Requester LOC'];
            $libnames= "SELECT loc,Name FROM `$sealLIB` WHERE `loc`='$dessid' ";
            $result = mysqli_query($db, $libnames);
            while ($row =  $result->fetch_assoc()) {
                $dessidtxt =  $row["Name"];
            }
            $libnames= "SELECT loc,Name FROM `$sealLIB` WHERE `loc`='$reqestid' ";
            $result = mysqli_query($db, $libnames);
            while ($row =  $result->fetch_assoc()) {
                $reqestid =  $row["Name"];
            }
            echo "<tr><td>".$illid."</td><td><a target='_blank' href='/senthistory?loc=".$dessid."'> ".$dessid."</a></td><td>".$dessidtxt."</td><td>".$reqestid."</td><td>".$note."</td><td>".$title."</td><td>".$itype."</td><td>".$date."</td></tr>";
        }
        echo "</table>";
    }
} else {
    // SEAL Borrowing Stats

    ?>
     <h3>Borrowing statistics for <?php echo $field_home_library_system;?>:</h3>
     <form action="/sealstats_ls?<?php echo $_SERVER['QUERY_STRING']; ?>" method="post">
     Start Date:
     <input id="datepicker" name="startdate"/>
     End Date:
     <input id="datepicker2" name="enddate"/>
     <br><br>
     
                    <input type="hidden" name="system" value="<?php echo $field_home_library_system;?>">
                    <input type="hidden" name="stattype" value="wholesystem">
     <input type="submit" value="Submit">
    </form>
    <br><hr>
    <!--#SEAL Lending Stats-->
     <h3>Lending statistics for <?php echo $field_home_library_system;?>:</h3>
     <form action="/sealstats_ls?<?php echo $_SERVER['QUERY_STRING']; ?>" method="post">
     Start Date:
     <input id="datepickerl" name="startdate"/>
     End Date:
     <input id="datepickerl2" name="enddate"/>
     <br><br>
   
     <input type="hidden" name="system" value="<?php echo $field_home_library_system;?>">
     <input type="hidden" name="stattype" value="wholesystemlending">
     <input type="submit" value="Submit">
    </form>
    <br><hr>

    <?php
    // Generate the drop down for borrower stats
    $libnames= "SELECT loc,Name FROM `$sealLIB` WHERE `participant`=1 and `System` = '".$field_home_library_system."' order by `Name` ";
    $libnameq =   mysqli_query($db, $libnames);
    echo "<form action='/libstats'   method='post2' >";
    echo "<h3>Borrowing statistics for a specific library:</h3><br>";
    echo "<select name=libname>";
    while ($row = $libnameq->fetch_assoc()) {
        $libname =  $row["Name"];
        $loccode = $row["loc"];
        echo "<option value=".$loccode.">".$libname."</option><br>";
    }
    echo "<input type='submit' value='Submit'>";
    echo "</select></form>";
    echo "<hr>";
    // Generating the links for borrowing stats
    $libnames= "SELECT loc,Name FROM `$sealLIB` WHERE `participant`=1  and `System` = '".$field_home_library_system."' order by `Name` ";
    $libnameq =   mysqli_query($db, $libnames);
    echo "<form action='/liblenderstat'   method='post2' >";
    echo "<h3>Lending statistics for a specific library:</h3><br>";
    echo "<select name=libname>";
    while ($row = $libnameq->fetch_assoc()) {
        $libname =  $row["Name"];
        $loccode = $row["loc"];
        echo "<option value=".$loccode.">".$libname."</option><br>";
    }
    echo "<input type='submit' value='Submit'>";
    echo "</select></form>"; ?>
    <hr>
    <form action="/sealstats_ls?<?php echo $_SERVER['QUERY_STRING']; ?>" method="post">
    <h3>List of expired requests:</h3><br>
     Start Date:
     <input id="expdatepicker" name="startdate"/>
     End Date:
     <input id="expdatepicker2" name="enddate"/>
     <input type="hidden" name="stattype" value="expirestats">
     <input type="submit" value="Submit">
    </form>
    <hr>
    <form action="/sealstats_ls?<?php echo $_SERVER['QUERY_STRING']; ?>" method="post">
    <h3>List of top 10 borrowing libraries:</h3><br>
    Start Date:
    <input id="top10datepicker" name="startdate"/>
    End Date:
    <input id="top10datepicker2" name="enddate"/>
    <input type="hidden" name="stattype" value="top10stats">
    <input type="submit" value="Submit">
    </form>
      <hr>
    <form action="/sealstats_ls?<?php echo $_SERVER['QUERY_STRING']; ?>" method="post">
    <h3>List of top 10 lending libraries:</h3><br>
    Start Date:
    <input id="top10fdatepicker" name="startdate"/>
    End Date:
    <input id="top10fdatepicker2" name="enddate"/>
    <input type="hidden" name="stattype" value="top10fstats">
    <input type="submit" value="Submit">
  </form>
    <?php
}
?>