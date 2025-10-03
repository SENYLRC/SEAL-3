<?php
// manage-libraries.php###
//start session that is used for export feature
session_id('YOUR_SESSION_ID');
session_start();

require '/var/www/seal_wp_script/seal_function.php';

$firstpass = (isset($_REQUEST['firstpass']) ? "no" : "yes");

if ($firstpass == "no") {
    $filter_library = (isset($_REQUEST['library']) ? $filter_library = $_REQUEST['library'] : "");
    $filter_loc = (isset($_REQUEST['loc']) ? $filter_loc = $_REQUEST['loc'] : "");
    $filter_alias = (isset($_REQUEST['filter_alias']) ? $filter_alias = $_REQUEST['filter_alias'] : "");
    $filter_illemail = (isset($_REQUEST['filter_illemail']) ? $filter_illemail = $_REQUEST['filter_illemail'] : "");
    $filter_numresults = (isset($_REQUEST['filter_numresults']) ? $filter_numresults = $_REQUEST['filter_numresults'] : "");
    $filter_offset = (isset($_REQUEST['filter_offset']) ? $filter_offset = $_REQUEST['filter_offset'] : "0");
    if (($filter_library != "") || ($filter_loc != "") || ($filter_alias != "") || ($filter_illemail != "")) {
        $filter_aliasblank = "";
        $filter_illemailblank = "";
        $filter_illpart = (isset($_REQUEST['filter_illpart']) ? $filter_illpart = $_REQUEST['filter_illpart'] : "");
        $filter_suspend = (isset($_REQUEST['filter_suspend']) ? $filter_suspend = $_REQUEST['filter_suspend'] : "");
        $filter_all_libs = (isset($_REQUEST['filter_all_libs']) ? $filter_all_libs = $_REQUEST['filter_all_libs'] : "");
        $filter_system = (isset($_REQUEST['filter_system']) ? $filter_system = $_REQUEST['filter_system'] : "");
    } else {
        $filter_illemailblank = (isset($_REQUEST['filter_illemailblank']) ? $filter_illemailblank = $_REQUEST['filter_illemailblank'] : "");
        $filter_illpart = (isset($_REQUEST['filter_illpart']) ? $filter_illpart = $_REQUEST['filter_illpart'] : "");
        $filter_suspend = (isset($_REQUEST['filter_suspend']) ? $filter_suspend = $_REQUEST['filter_suspend'] : "");
        $filter_all_libs = (isset($_REQUEST['filter_all_libs']) ? $filter_all_libs = $_REQUEST['filter_all_libs'] : "");
        $filter_system = (isset($_REQUEST['filter_system']) ? $filter_system = $_REQUEST['filter_system'] : "");
        $filter_aliasblank = (isset($_REQUEST['filter_aliasblank']) ? $filter_aliasblank = $_REQUEST['filter_aliasblank'] : "");
    }
} else {
    $filter_library = (isset($_REQUEST['library']) ? $filter_library = $_REQUEST['library'] : "");
    $filter_loc = (isset($_REQUEST['loc']) ? $filter_loc = $_REQUEST['loc'] : "");
    $filter_offset = 0;
    $filter_alias = "";
    $filter_aliasblank = "";
    $filter_illemail = "";
    $filter_illemailblank = "";
    $filter_suspend = "";
    $filter_all_libs = "";
    $filter_system = "";
    $filter_numresults = "25";
    if (($filter_library != "") || ($filter_loc != "")) {
        $filter_illpart = "";
    } else {
        //$filter_illpart = "yes";
        $filter_all_libs = "yes";
    }
}



// Connect to database
require '/var/www/seal_wp_script/seal_db.inc';
$db = mysqli_connect($dbhost, $dbuser, $dbpass);
mysqli_select_db($db, $dbname);

if (isset($_REQUEST['library'])) {
    $LibraryName = $_REQUEST['library'];
} else {
    $LibraryName = "";
}

// check if an action has been requested
if (isset($_REQUEST['action'])) {
    // set the pageaction to what has been requested
    $pageaction = $_REQUEST['action'];
    // Chec if the librecnumb variable has been sent with action and set a variable to be used for edit and delete
    if (isset($_REQUEST['librecnumb'])) {
        $librecnumb = $_REQUEST['librecnumb'];
    }
} else {
    $pageaction = '0';
}
if (isset($_REQUEST['libname'])) {
    $libname = $_REQUEST['libname'];
}
if (isset($_REQUEST['libalias'])) {
    $libalias = $_REQUEST['libalias'];
}
if (isset($_REQUEST['libemail'])) {
    $libemail = $_REQUEST['libemail'];
}
if (isset($_REQUEST['libilliad'])) {
    $libilliad = $_REQUEST['libilliad'];
}
if (isset($_REQUEST['$libilliadkey'])) {
    $libilliadkey = $_REQUEST['$libilliadkey'];
}

if (isset($_REQUEST['libilliadurl'])) {
    $libilliadurl = $_REQUEST['libilliadurl'];
}
if (isset($_REQUEST['libilliaddate'])) {
    $libilliaddate = $_REQUEST['libilliaddate'];
}
if (isset($_REQUEST['$libemailalert'])) {
    $libemailalert = $_REQUEST['$libemailalert'];
}
if (isset($_REQUEST['participant'])) {
    $participant = $_REQUEST['participant'];
}
if (isset($_REQUEST['suspend'])) {
    $suspend = $_REQUEST['suspend'];
}
if (isset($_REQUEST['enddate'])) {
    $enddate = $_REQUEST['enddate'];
}
if (isset($_REQUEST['system'])) {
    $system = $_REQUEST['system'];
}
if (isset($_REQUEST['phone'])) {
    $phone = $_REQUEST['phone'];
}
if (isset($_REQUEST['address1'])) {
    $address1 = $_REQUEST['address1'];
}
if (isset($_REQUEST['address2'])) {
    $address2 = $_REQUEST['address2'];
}
if (isset($_REQUEST['address3'])) {
    $address3 = $_REQUEST['address3'];
}
if (isset($_REQUEST['oclc'])) {
    $oclc = $_REQUEST['oclc'];
}
if (isset($_REQUEST['loc'])) {
    $loc = $_REQUEST['loc'];
}
if (isset($_REQUEST['book'])) {
    $book = $_REQUEST['book'];
}
if (isset($_REQUEST['journal'])) {
    $journal = $_REQUEST['journal'];
}
if (isset($_REQUEST['ebook'])) {
    $ebook = $_REQUEST['ebook'];
}
if (isset($_REQUEST['ejournal'])) {
    $ejournal = $_REQUEST['ejournal'];
}
if (isset($_REQUEST['reference'])) {
    $reference = $_REQUEST['reference'];
}
if (isset($_REQUEST['av'])) {
    $av = $_REQUEST['av'];
}
if ($pageaction ==3) {
    // Delete a library
    if (($_SERVER['REQUEST_METHOD'] == 'POST')   || (isset($_GET{'page'}))) {
        $librecnumb = mysqli_real_escape_string($db, $librecnumb);
        $sqldel = "DELETE FROM `$sealLIB` WHERE recnum='$librecnumb'";
        mysqli_query($db, $sqldel);
        echo "Library has been deleted<br><br>";
        echo "<a href='".$_SERVER['REDIRECT_URL']."'>Return to main list</a>";
    } else {
        ?><form action="".$_SERVER['REDIRECT_URL']."?<?php echo $_SERVER['QUERY_STRING']; ?>" method="post"><?php
    echo "<input type='hidden' name='action' value='$pageaction''>";
        echo "<input type='hidden' name='librecnumb' value='$librecnumb''>";
        echo  "Confirm you want to delete?";
        echo  "<input type='submit' value='Confirm'>";
        echo "</form>";
    }
} elseif ($pageaction ==5) {
    // Suspend and Unsuspend en masse
    ?>
   <p>Please select the action you wish to take and the library system to act upon.</p>
   <form action="/status-confirmation" method="post">
   <input type="radio" name="task" value="suspend">Suspend lending<br>
   <input type="radio" name="task" value="activate" checked="checked">Activate lending<br><br>
   <b>Library System </b><select name="system">
   <option value = "none">Select a system</option>
   <option value="DU">Dutchess BOCES</option>
   <option value="MH">Mid-Hudson Library System</option>
   <option value="OU">Orange Ulster BOCES</option>
   <option value="RC">Ramapo Catskill Library System</option>
   <option value="RB">Rockland BOCES</option>
   <option value="SE">SENYLRC</option>
   <option value="SB">Sullivan BOCES</option>
   <option value="UB">Ulster BOCES</option>
    </select><br>

      <b>Suspension End Date:</b><input id="datepicker" name="enddate"/><br>
   <br><br>
   <input type="submit" value="Submit">
   </form>
    <?php
} elseif ($pageaction ==1) {
    // Adding a library
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Generate Time Stamp
        $timestamp = date("Y-m-d H:i:s");
        $libname = mysqli_real_escape_string($db, $libname);
        $libemail = mysqli_real_escape_string($db, $libemail);
        $address1 = mysqli_real_escape_string($db, $address1);
        $address2 = mysqli_real_escape_string($db, $address2);
        $address3 = mysqli_real_escape_string($db, $address3);
        $phone = mysqli_real_escape_string($db, $phone);
        $loc = mysqli_real_escape_string($db, $loc);
        $book = mysqli_real_escape_string($db, $book);
        $journal = mysqli_real_escape_string($db, $journal);
        $av = mysqli_real_escape_string($db, $av);
        $ebook = mysqli_real_escape_string($db, $ebook);
        $ejournal = mysqli_real_escape_string($db, $ejournal);
        $reference = mysqli_real_escape_string($db, $reference);
        $oclc = mysqli_real_escape_string($db, $oclc);
        //remove whitespace
        $libname=trim($libname);
        $libalias=trim($libalias);
        $address1=trim($address1);
        $address2=trim($address2);
        $address3=trim($address3);
        $phone=trim($phone);
        $oclc=trim($oclc);
        $loc=trim($loc);
        $libemail=trim($libemail);
        $insertsql  = "
    INSERT INTO `$sealLIB` (`recnum`, `Name`, `ill_email`, `alias`, `participant`, `suspend`, `system`, `phone`, `address1`, `address2`, `address3`,  `loc`, `oclc`, `book_loan`,`periodical_loan`,`av_loan`, `ebook_request`, `ejournal_request`,`theses_loan`,`ModifyDate`)
      VALUES (NULL,'$libname','$libemail','$libalias','$participant','$suspend','$system','$phone','$address1','$address2','$address3','$loc','$oclc','$book','$journal','$av','$ebook','$ejournal','$reference','$timestamp')";
        // Show interset on debug
        //echo $insertsql;
        $result = mysqli_query($db, $insertsql);
        echo  "Library Had Been Added";
        echo "<br><a href='".$_SERVER['REDIRECT_URL']."'>Return to main list</a><br>";
        echo "<br><a href='/adminlib?action=1'>Add Another Library</a>";
    } else {
        ?>
    <form action="".$_SERVER['REDIRECT_URL']."?<?php echo $_SERVER['QUERY_STRING']; ?>" method="post">
    <B>Library Name:</b> <input type="text" SIZE=60 MAXLENGTH=255  name="libname"><br>
    <B>Library Alias:</b> <input type="text" SIZE=60 MAXLENGTH=255  name="libalias"><br>
    <B>Library ILL Email:</b> <input type="text" SIZE=60 MAXLENGTH=255  name="libemail"><br>
    <B>Library Phone:</b> <input type="text" SIZE=60 MAXLENGTH=255  name="phone"><br>
    <B>ILL Dept:</b> <input type="text" SIZE=60 MAXLENGTH=255  name="address1"><br>
    <B>Street Address:</b> <input type="text" SIZE=60 MAXLENGTH=255  name="address2"><br>
    <B>City State Zip:</b> <input type="text" SIZE=60 MAXLENGTH=255  name="address3"><br>
    <B>OCLC Symbol:</b> <input type="text" SIZE=60 MAXLENGTH=255  name="oclc"><br>
    <B>LOC Location:</b> <input type="text" SIZE=60 MAXLENGTH=255  name="loc"><a target='_blank' href='https://www.loc.gov/marc/organizations/'>(REQUIRED FOR ILL)</a><br>
    <B>Library ILL participant</b><select name="participant">  <option value="1">Yes</option><option value="0">No</option></select><br>
    <B>Suspend ILL</b><select name="suspend">  <option value="0">No</option><option value="1">Yes</option></select><br>
    <B>Library System</b><select name="system">
      <option value="DU">Dutchess BOCES</option>
      <option value="MH">Mid-Hudson Library System</option>
      <option value="OU">Orange Ulster BOCES</option>
      <option value="RC">Ramapo Catskill Library System</option>
      <option value="RB">Rockland BOCES</option>
      <option value="SE">SENYLRC</option>
      <option value="SB">Sullivan BOCES</option>
      <option value="UB">Ulster BOCES</option></select><br><br>

      <br><br>
<h6>Delivery Option:</h6>

<table>
        <tr>
        <td><b>Empire Delivery</b><td>
          <input type="radio" name="lbEmpire" value="Yes" <?php if ($lbEmpire=="Yes") {
                echo "checked";
} ?>> Yes
          <input type="radio" name="lbEmpire" value="No" <?php if ($lbEmpire=="No") {
                echo "checked";
} ?>> No <br>
        </td></tr>
        <tr>
          <td><b>Public Library System Courier  (MHLS or RCLS)</b><td>
            <input type="radio" name="lbsyscourier" value="Yes" <?php if ($lbsyscourier=="Yes") {
                echo "checked";
} ?>> Yes
            <input type="radio" name="lbsyscourier" value="No" <?php if ($lbsyscourier=="No") {
                echo "checked";
} ?>> No <br>
          </td></tr>
        <tr>
          <td><b>US Mail</b><td>
            <input type="radio" name="lbUSPS" value="Yes" <?php if ($lbUSPS=="Yes") {
                echo "checked";
} ?>> Yes
            <input type="radio" name="lbUSPS" value="No" <?php if ($lbUSPS=="No") {
                echo "checked";
} ?>> No <br>
          </td></tr>
        <tr>
          <td><b>Commercial Courier (UPS or FEDEX)</b><td>
            <input type="radio" name="lbCommCourier" value="Yes" <?php if ($lbCommCourier=="Yes") {
                echo "checked";
} ?>> Yes
            <input type="radio" name="lbCommCourier" value="No" <?php if ($lbCommCourier=="No") {
                echo "checked";
} ?>>No <br>
          </td></tr>
       
      </table>

      <h6>Items willing to loan:</h6>
        <table>
        <tr>
        <td><b>Print
        </b><td>
          <input type="radio" name="book" value="Yes" <?php if ($book=="Yes") {
                echo "checked";
} ?>> Yes
          <input type="radio" name="book" value="No" <?php if ($book=="No") {
                echo "checked";
} ?>> No <br>
        </td></tr>
        <tr>
        <td><b>Print Journal or Article</b><td>
          <input type="radio" name="journal" value="Yes" <?php if ($journal=="Yes") {
                echo "checked";
} ?>> Yes
          <input type="radio" name="journal" value="No" <?php if ($journal=="No") {
                echo "checked";
} ?>> No <br>
        </td></tr>
        <tr>
        <td><b>Audio Video Materials</b><td>
          <input type="radio" name="av" value="Yes" <?php if ($av=="Yes") {
                echo "checked";
} ?>> Yes
          <input type="radio" name="av" value="No" <?php if ($av=="No") {
                echo "checked";
} ?>> No <br>
        </td></tr>
        <tr>
        <td><b>Reference/Microfilm</b><td>
          <input type="radio" name="reference" value="Yes" <?php if ($reference=="Yes") {
                echo "checked";
} ?>> Yes
          <input type="radio" name="reference" value="No" <?php if ($reference=="No") {
                echo "checked";
} ?>>No <br>
        </td></tr>
        <tr>
        <td><b>Electronic Book</b><td>
          <input type="radio" name="ebook" value="Yes" <?php if ($ebook=="Yes") {
                echo "checked";
} ?>> Yes
          <input type="radio" name="ebook" value="No" <?php if ($ebook=="No") {
                echo "checked";
} ?>> No <br>
        </td></tr>
        <tr>
        <td><b>Electronic Journal</b><td>
          <input type="radio" name="ejournal" value="Yes" <?php if ($ejournal=="Yes") {
                echo "checked";
} ?>> Yes
          <input type="radio" name="ejournal" value="No" <?php if ($ejournal=="No") {
                echo "checked";
} ?>> No <br>
        </td></tr>
      </table>
      <br>
      <input type="submit" value="Submit">
      </form>
        <?php
    }
} elseif ($pageaction ==2) {
    // Edit a Library
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // if the edit form was posted update the database with the data posted
        // Generate Time Stamp

        $timestamp = date("Y-m-d H:i:s");
        $libname = mysqli_real_escape_string($db, $libname);
        $libalias = mysqli_real_escape_string($db, $libalias);
        $libemail = mysqli_real_escape_string($db, $libemail);
        $address1 = mysqli_real_escape_string($db, $address1);
        $address2 = mysqli_real_escape_string($db, $address2);
        $address3 = mysqli_real_escape_string($db, $address3);
        $phone = mysqli_real_escape_string($db, $phone);
        $book = mysqli_real_escape_string($db, $book);
        $journal = mysqli_real_escape_string($db, $journal);
        $av = mysqli_real_escape_string($db, $av);
        $ebook = mysqli_real_escape_string($db, $ebook);
        $ejournal = mysqli_real_escape_string($db, $ejournal);
        $reference = mysqli_real_escape_string($db, $reference);
        $oclc = mysqli_real_escape_string($db, $oclc);
        $libname=trim($libname);
        $libalias=trim($libalias);
        $address1=trim($address1);
        $address2=trim($address2);
        $address3=trim($address3);
        $phone=trim($phone);
        $oclc=trim($oclc);
        $libilliadurl = mysqli_real_escape_string($db, $libilliadurl);
        $libilliaddate = mysqli_real_escape_string($db, $libilliaddate);
        $loc=trim($loc);
        $lbsyscourier = $_REQUEST["lbsyscourier"];
        $lbUSPS = $_REQUEST["lbUSPS"];
        $lbEmpire  = $_REQUEST["lbEmpire"];
        $lbCommCourier = $_REQUEST["lbCommCourier"];
        $lastmodemail = $email;
        $libemail=trim($libemail);
        // If suspenson is set with no end date, a default one of 7 days is calulated
        if (($suspend==1)&&(strlen($enddate)<2)) {
            $enddate = strtotime("+7 day");
            $enddate = date('Y-m-d', $enddate);
        } else {
            $enddate = date('Y-m-d', strtotime(str_replace('-', '/', $enddate)));
        }

        //if participant is being set to 0 also set suspend to 1
        if ($participant=='0') {
            $suspend='1';
        }
        $sqlupdate = "UPDATE `$sealLIB` SET Name = '$libname', alias='$libalias', `ill_email` ='$libemail',participant=$participant,suspend=$suspend,SuspendDateEnd='$enddate',`system`='$system',phone='$phone',address1='$address1',address2='$address2',address3='$address3',oclc='$oclc',loc='$loc',book_loan='$book',periodical_loan='$journal',av_loan='$av',ebook_request='$ebook',ejournal_request='$ejournal',theses_loan='$reference',ModifyDate='$timestamp',Illiad='$libilliad',IlliadURL='$libilliadurl',IlliadDATE='$libilliaddate',APIkey='$libilliadkey',ModEmail ='seal ADMIN',LibEmailAlert='$libemailalert' WHERE `recnum` = '$librecnumb' ";
        //for debug
        //echo $sqlupdate;

        $result = mysqli_query($db, $sqlupdate);

        echo  "Library has been edited<br><br>";
        echo "<a href='".$_SERVER['REDIRECT_URL']."'>Return to main list</a>";
    } else {
        $GETEDITLISTSQL="SELECT * FROM  `$sealLIB` WHERE `recnum` ='$librecnumb'";
        // for testing
        // echo $GETEDITLISTSQL;
        $GETLIST = mysqli_query($db, $GETEDITLISTSQL);
        $GETLISTCOUNT = '1';
        $row = mysqli_fetch_assoc($GETLIST);
        $libname = $row["Name"];
        $libalias = $row["alias"];
        $libemail = $row["ill_email"];
        $phone = $row["phone"];
        $libparticipant = $row["participant"];
        $libemailalert = $row["LibEmailAlert"];
        $libilliad = $row["Illiad"];
        $libilliadkey = $row["APIkey"];
        $oclc = $row["oclc"];
        $loc = $row["loc"];
        $lastmodemail = $row["ModEmail"];
        $libilliadurl = $row["IlliadURL"];
        $libilliaddate = $row["IlliadDATE"];
        $libsuspend = $row["suspend"];
        $system = $row["system"];
        $address1 = $row["address1"];
        $address2 = $row["address2"];
        $address3 = $row["address3"];
        $book = $row["book_loan"];
        $reference = $row["theses_loan"];
        $av = $row["av_loan"];
        $ebook = $row["ebook_request"];
        $ejournal = $row["ejournal_request"];
        $journal = $row["periodical_loan"];
        $timestamp = $row["ModifyDate"];
        $lbsyscourier = $row["lbsyscourier"];
        $lbUSPS = $row["lbUSPS"];
        $lbEmpire = $row["lbEmpire"];
        $lbCommCourier = $row["lbCommCourier"];
        $enddateshow = $row["SuspendDateEnd"]; ?>
    <form action="/adminlib?<?php echo $_SERVER['QUERY_STRING']; ?>" method="post">
    <B>Library Name:</b> <input type="text" SIZE=60 MAXLENGTH=255  name="libname" value="<?php echo $libname?>"><br>
    <B>Library Alias:</b> <input type="text" SIZE=60 MAXLENGTH=255  name="libalias" value="<?php echo $libalias?>"><br>
    <B>Library ILL Email:</b> <input type="text" SIZE=60 MAXLENGTH=255  name="libemail" value="<?php echo $libemail?>"><br>
    <B>Library Phone:</b> <input type="text" SIZE=60 MAXLENGTH=255  name="phone" value="<?php echo $phone?>"><br>
    <B>ILL Dept:</b> <input type="text" SIZE=60 MAXLENGTH=255  name="address1" value="<?php echo $address1?>"><br>
    <B>Street Address:</b> <input type="text" SIZE=60 MAXLENGTH=255  name="address2" value="<?php echo $address2?>"><br>
    <B>City State Zip:</b> <input type="text" SIZE=60 MAXLENGTH=255  name="address3" value="<?php echo $address3?>"><br>
    <B>OCLC Symbol:</b> <input type="text" SIZE=60 MAXLENGTH=255  name="oclc" value="<?php echo $oclc?>"><br>
    <B>LOC Location:</b> <input type="text" SIZE=60 MAXLENGTH=255  name="loc" value="<?php echo $loc?>"><a target='_blank' href='https://www.loc.gov/marc/organizations/'>(REQUIRED FOR ILL)</a><br>
      <B>Library Email Alert</b><select name="$libemailalert">  <option value="1" <?php if ($libemailalert=="1") {
            echo "selected=\"selected\"";
} ?>>Yes</option><option value="0" <?php if ($libemailalert=="0") {
          echo "selected=\"selected\"";
                                                                                  } ?>>No</option></select><br>
      <B>Library ILLiad</b><select name="libilliad">  <option value="1" <?php if ($libilliad=="1") {
            echo "selected=\"selected\"";
} ?>>Yes</option><option value="0" <?php if ($libilliad=="0") {
          echo "selected=\"selected\"";
                                                                        } ?>>No</option></select><br>
    <B>ILLiad URL:</b> <input type="text" SIZE=60 MAXLENGTH=255  name="libilliadurl" value="<?php echo $libilliadurl?>"><br>
    <B>ILLiad Due Date Days:</b> <input type="text" SIZE=10 MAXLENGTH=10  name="libilliaddate" value="<?php echo $libilliaddate?>"><br>
    <B>ILLiad API <keygen name="name" challenge="string" keytype="RSA" keyparams="medium">:</b> <input type="text" SIZE=60 MAXLENGTH=255  name="$libilliadkey" value="<?php echo $libilliadkey?>"><br>
    <B>Library ILL participant</b><select name="participant">  <option value="1" <?php if ($libparticipant=="1") {
        echo "selected=\"selected\"";
} ?>>Yes</option><option value="0" <?php if ($libparticipant=="0") {
            echo "selected=\"selected\"";
                                                                                 } ?>>No</option></select><br>
    <B>Suspend ILL</b><select name="suspend">  <option value="0" <?php if ($libsuspend=="0") {
        echo "selected=\"selected\"";
} ?>>No</option><option value="1" <?php if ($libsuspend=="1") {
            echo "selected=\"selected\"";
                                                                 } ?>>Yes</option></select><br>
    <b>Suspension End Date:</b><input id="datepicker" name="enddate"/><br>
        <?php
        if ($libsuspend=="1") {
            echo "This  Library has <strong>enable suspension</strong> until $enddateshow <br>";
        } else {
            echo "This Library has suspension disabled<br>";
        } ?>
       <B>Library System</b><select name="system">
                     <option value="DU" <?php if ($system=="DU") {
                            echo "selected=\"selected\"";
} ?>>Dutchess BOCES</option>
                     <option value="MH" <?php if ($system=="MH") {
                            echo "selected=\"selected\"";
} ?>>Mid-Hudson Library System</option>
                     <option value="OU" <?php if ($system=="OU") {
                            echo "selected=\"selected\"";
} ?>>Orange Ulster BOCES</option>
                     <option value="RC" <?php if ($system=="RC") {
                            echo "selected=\"selected\"";
} ?>>Ramapo Catskill Library System</option>
                    <option value="RB" <?php if ($system=="RB") {
                        echo "selected=\"selected\"";
} ?>>Rockland BOCES</option>
                    <option value="SE" <?php if ($system=="SE") {
                        echo "selected=\"selected\"";
} ?>>SENYLRC</option>
                    <option value="SB" <?php if ($system=="SB") {
                        echo "selected=\"selected\"";
} ?>>Sullivan BOCES</option>
                    <option value="UB" <?php if ($system=="UB") {
                        echo "selected=\"selected\"";
} ?>>Ulster BOCES</option>
    </select><br><br>
    <br><br>
<h6>Delivery Option:</h6>

<table>
        <tr>
        <td><b>Empire Delivery</b><td>
          <input type="radio" name="lbEmpire" value="Yes" <?php if ($lbEmpire=="Yes") {
                echo "checked";
} ?>> Yes
          <input type="radio" name="lbEmpire" value="No" <?php if ($lbEmpire=="No") {
                echo "checked";
} ?>> No <br>
        </td></tr>
        <tr>
          <td><b>Public Library System Courier  (MHLS or RCLS)</b><td>
            <input type="radio" name="lbsyscourier" value="Yes" <?php if ($lbsyscourier=="Yes") {
                echo "checked";
} ?>> Yes
            <input type="radio" name="lbsyscourier" value="No" <?php if ($lbsyscourier=="No") {
                echo "checked";
} ?>> No <br>
          </td></tr>
        <tr>
          <td><b>US Mail</b><td>
            <input type="radio" name="lbUSPS" value="Yes" <?php if ($lbUSPS=="Yes") {
                echo "checked";
} ?>> Yes
            <input type="radio" name="lbUSPS" value="No" <?php if ($lbUSPS=="No") {
                echo "checked";
} ?>> No <br>
          </td></tr>
        <tr>
          <td><b>Commercial Courier (UPS or FEDEX)</b><td>
            <input type="radio" name="lbCommCourier" value="Yes" <?php if ($lbCommCourier=="Yes") {
                echo "checked";
} ?>> Yes
            <input type="radio" name="lbCommCourier" value="No" <?php if ($lbCommCourier=="No") {
                echo "checked";
} ?>>No <br>
          </td></tr>
       
      </table>

      <h6>Items willing to loan:</h6>
      <table>
      <tr>
      <td><b>Print Book</b><td>
        <input type="radio" name="book" value="Yes" <?php if ($book=="Yes") {
            echo "checked";
} ?>> Yes
        <input type="radio" name="book" value="No" <?php if ($book=="No") {
            echo "checked";
} ?>> No <br>
      </td></tr>
      <tr>
      <td><b>Print Journal or Article</b><td>
        <input type="radio" name="journal" value="Yes" <?php if ($journal=="Yes") {
            echo "checked";
} ?>> Yes
        <input type="radio" name="journal" value="No" <?php if ($journal=="No") {
            echo "checked";
} ?>> No <br>
      </td></tr>
      <tr>
      <td><b>Audio Video Materials</b><td>
        <input type="radio" name="av" value="Yes" <?php if ($av=="Yes") {
            echo "checked";
} ?>> Yes
        <input type="radio" name="av" value="No" <?php if ($av=="No") {
            echo "checked";
} ?>> No <br>
      </td></tr>
      <tr>
      <td><b>Theses</b><td>
        <input type="radio" name="reference" value="Yes" <?php if ($reference=="Yes") {
            echo "checked";
} ?>> Yes
        <input type="radio" name="reference" value="No" <?php if ($reference=="No") {
            echo "checked";
} ?>>No <br>
      </td></tr>
      <tr>
      <td><b>Electronic Book</b><td>
        <input type="radio" name="ebook" value="Yes" <?php if ($ebook=="Yes") {
            echo "checked";
} ?>> Yes
        <input type="radio" name="ebook" value="No" <?php if ($ebook=="No") {
            echo "checked";
} ?>> No <br>
      </td></tr>
      <tr>
      <td><b>Electronic Journal</b><td>
        <input type="radio" name="ejournal" value="Yes" <?php if ($ejournal=="Yes") {
            echo "checked";
} ?>> Yes
        <input type="radio" name="ejournal" value="No" <?php if ($ejournal=="No") {
            echo "checked";
} ?>> No <br>
      </td></tr>
    </table>

    <input type="submit" value="Submit">
    </form>
      <Br><Br>
      Last Modified: <?php echo "$timestamp;" ?> by <?php echo "$lastmodemail;" ?>
        <?php
    }
} else {
    // echo "<p>Diagnostic Block";
    // echo "<br>firstpass: " . $firstpass;
    // echo "<br>filter_library: " . $filter_library;
    // echo "<br>filter_loc: " . $filter_loc;
    // echo "<br>filter_alias: " . $filter_alias;
    // echo "<br>filter_aliasblank: " . $filter_aliasblank;
    // echo "<br>filter_illemail: " . $filter_illemail;
    // echo "<br>filter_illemailblank: " . $filter_illemailblank;
    // echo "<br>filter_illpart: " . $filter_illpart;
    // echo "<br>filter_suspend: " . $filter_suspend;
    // echo "<br>filter_all_libs: " . $filter_all_libs;
    // echo "<br>filter_system: " . $filter_system;
    // echo "<br>filter_numresults: " . $filter_numresults;
    // echo "<br></p>";



    // Sanitize data
    $loc = mysqli_real_escape_string($db, $loc);

    $SQLBASE="SELECT * FROM `$sealLIB` WHERE ";
    $SQLEND=" ORDER BY `Name` ASC ";

    if ($filter_numresults != "all") {
        $sqllimiter = $filter_numresults * $filter_offset;
        $SQLLIMIT = " LIMIT " . $sqllimiter . ", " . $filter_numresults;
    } else {
        $SQLLIMIT = "";
    }

    $SQLMIDDLE =''; // This builds the display options for the SQL
    //this filter will over ride suspend and partipant 
    if ($filter_all_libs == "yes") {
        //don't set particpant or suspend
        //but nothing really has to happen
        $SQLMIDDLE=$SQLMIDDLE = "`participant` < 5 ";
    }else{
        $SQLMIDDLE= ($filter_illpart == "yes" ? $SQLMIDDLE = "`participant` = 1 " : $SQLMIDDLE = "`participant` = 0 ");
        $SQLMIDDLE= ($filter_suspend == "yes" ? $SQLMIDDLE = $SQLMIDDLE . "AND `suspend` = 1 " : $SQLMIDDLE = $SQLMIDDLE . " AND `suspend` = 0 ");
    }
    $SQLMIDDLE= ($filter_aliasblank == "yes" ? $SQLMIDDLE = $SQLMIDDLE .  "AND alias = ''  " : $SQLMIDDLE = $SQLMIDDLE . "  ");
    $SQLMIDDLE= ($filter_illemailblank == "yes" ? $SQLMIDDLE = $SQLMIDDLE . "AND `ill_email` = '' " : $SQLMIDDLE = $SQLMIDDLE . "  ");
    $SQLMIDDLE= (strlen($filter_loc) >= 1 ? $SQLMIDDLE = $SQLMIDDLE . "AND `loc` like  '%" . $filter_loc . "%' " : $SQLMIDDLE = $SQLMIDDLE);
    $SQLMIDDLE= (strlen($filter_illemail) >= 1 ? $SQLMIDDLE = $SQLMIDDLE . "AND `ill_email` like  '%" . $filter_illemail . "%' " : $SQLMIDDLE = $SQLMIDDLE);
    $SQLMIDDLE= (strlen($filter_alias) >= 1 ? $SQLMIDDLE = $SQLMIDDLE . "AND `Alias` like  '%" . $filter_alias . "%' " : $SQLMIDDLE = $SQLMIDDLE);
    $SQLMIDDLE= (strlen($filter_library) >= 2 ? $SQLMIDDLE = $SQLMIDDLE . "AND `Name` like  '%" . $filter_library . "%' " : $SQLMIDDLE = $SQLMIDDLE);
    $SQLMIDDLE= (strlen($filter_system) >= 1 ? $SQLMIDDLE = $SQLMIDDLE . "AND `System` like  '%" . $filter_system . "%' " : $SQLMIDDLE = $SQLMIDDLE);
    $GETFULLSQL = $SQLBASE . $SQLMIDDLE . $SQLEND;
    $GETLISTSQL = $SQLBASE . $SQLMIDDLE . $SQLEND . $SQLLIMIT;
    $GETLIST = mysqli_query($db, $GETLISTSQL);
    $GETCOUNT = mysqli_query($db, $GETFULLSQL);
    $GETLISTCOUNTwhole = mysqli_num_rows($GETCOUNT);

    // Foo debugig
    //echo $GETLISTSQL . "</br>";


    echo "<form action=".$_SERVER['REDIRECT_URL']." method='post'>";
    echo "<input type='hidden' name='firstpass' value= 'no'>";
    echo "<p>Display filters:";
    echo "<input type='checkbox' name='filter_aliasblank' value='yes' " . checked($filter_aliasblank) . ">Missing alias ";
    echo "<input type='checkbox' name='filter_illemailblank' value='yes' " . checked($filter_illemailblank) . ">Missing ILL Email ";
    echo "<input type='checkbox' name='filter_illpart' value='yes' " . checked($filter_illpart) . ">ILL Participant ";
    echo "<input type='checkbox' name='filter_suspend' value='yes' " . checked($filter_suspend) . ">ILL Suspended ";
    echo "<input type='checkbox' name='filter_all_libs' value='yes' " . checked($filter_all_libs) . ">All Libraries";
    echo "<br>Library System: <select name='filter_system'>";
    echo "<option value='' " . selected("", $filter_system) . ">All</option>";
    echo "<option value = 'DU' " . selected("DU", $filter_system) . ">Dutchess BOCES</option>";
    echo "<option value = 'MH' " . selected("MH", $filter_system) . ">Mid-Hudson Library System</option>";
    echo "<option value = 'OU' " . selected("OU", $filter_system) . ">Orange Ulster BOCES</option>";
    echo "<option value = 'RC' " . selected("RC", $filter_system) . ">Ramapo Catskill Library System</option>";
    echo "<option value = 'RB' " . selected("RB", $filter_system) . ">Rockland BOCES</option>";
    echo "<option value = 'SE' " . selected("SE", $filter_system) . ">SENYLRC</option>";
    echo "<option value = 'SB' " . selected("SB", $filter_system) . ">Sullivan BOCES</option>";
    echo "<option value = 'UB' " . selected("UB", $filter_system) . ">Ulster BOCES</option>";
    echo "</select>";

    echo "<br>Search:";
    echo "<br>Library Name: <input name='library' type='text' value='$filter_library'> ";
    echo "<br>Library Alias: <input name='filter_alias' type='text' value='$filter_alias'> ";
    echo "<br>ILL Code: <input name='loc' type='text' value='$filter_loc'> ";
    echo "<br>ILL Email: <input name='filter_illemail' type='text' value='$filter_illemail'> ";
    echo "<br><select name='filter_numresults'></br>";
    echo "<option " . selected("25", $filter_numresults) . " value = '25'>25</option>";
    echo "<option " . selected("50", $filter_numresults) . " value = '50'>50</option>";
    echo "<option " . selected("100", $filter_numresults) . " value = '100'>100</option>";
    echo "<option " . selected("all", $filter_numresults) . " value = 'all'>All</option>";
    echo "</select> results per page. ";
    echo "</select> results per page. ";


    $resultpages = ceil($GETLISTCOUNTwhole / $filter_numresults);
    $display_page = $filter_offset + 1;
    if ($filter_numresults != "all") {
        echo "Currently on page <select name='filter_offset'>";
        for ($x = 1; $x <= $resultpages; $x++) {
            $localoffset = $x - 1;
            echo "<option " . selected($localoffset, $filter_offset) . " value = '" . $localoffset . "'>" . $x . "</option>";
        }
        echo "</select> of " . $resultpages . ".";
    }
    echo "<br>Total Results: ".$GETLISTCOUNTwhole."<br>";
    ;
    echo "<br><a href='adminlib'>Clear</a> <input type=Submit value=Update><br>";
    echo "</form>";
    echo "<a href='".$_SERVER['REDIRECT_URL']."?action=1'>Would you like to add a library?</a><br>";
    echo "<a href='".$_SERVER['REDIRECT_URL']."?action=5'>Mass suspend or activate library lending (hint: for updating system)</a><br>";
    echo "<a target='_blank' href=/export>Export to CSV</a><br>";
    echo "<table><tr><th>Library</th><th>Alias</th><th>Participant</th><th>Suspend</th><th>System</th><th>OCLC</th><th>LOC</th><th>Action</th></tr>";
  
    while ($row = mysqli_fetch_assoc($GETLIST)) {
        $librecnumb = $row["recnum"];
        $libname = $row["Name"];
        $libalias = $row["alias"];
        $libparticipant = $row["participant"];
        $oclc = $row["oclc"];
        $loc = $row["loc"];
        $libsuspend = $row["suspend"];
        $system = $row["system"];
        if ($libsuspend=="1") {
            $libsuspend="Yes";
        }
        if ($libsuspend=="0") {
            $libsuspend="No";
        }
        if ($libparticipant =="1") {
            $libparticipant ="Yes";
        }
        if ($libparticipant =="0") {
            $libparticipant ="No";
        }
        echo " <Tr><Td>$libname</td><td>$libalias</td><td>$libparticipant</td><td>$libsuspend</td><td>$system</td><td>$oclc</td><td>$loc</td> ";
        echo "<td><a href='".$_SERVER['REDIRECT_URL']."?action=2&librecnumb=$librecnumb'>Edit</a>  <a href='".$_SERVER['REDIRECT_URL']."?action=3&librecnumb=$librecnumb''>Delete</a> </td></tr>";
    }//end while looping through sql results
    //store array of all results  in session for export function
    $_SESSION['query2'] = $GETFULLSQL;
    echo "</table>";
}
?>