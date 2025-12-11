<?php
// Make sure this runs in the context of WordPress
$current_user = wp_get_current_user();
$all_meta     = get_user_meta($current_user->ID);

// Use home_system for SYSTEM
$SYSTEM = get_user_meta($current_user->ID, 'home_system', true) ?: '';

// --- Standard WordPress fields
$user_id         = $current_user->ID;
$field_first_name = $current_user->first_name;
$field_last_name  = $current_user->last_name;
$email            = $current_user->user_email;

// --- Custom meta fields
$field_your_institution    = get_user_meta($current_user->ID, 'institution', true);
$field_home_library_system = $SYSTEM;
$field_work_phone          = get_user_meta($current_user->ID, 'phone', true);
$field_backup_email        = get_user_meta($current_user->ID, 'alt_email', true);
$field_loc_location_code   = get_user_meta($current_user->ID, 'address_loc_code', true);

// Mailing address parts
$street_address1 = get_user_meta($current_user->ID, 'delivery_address1', true);
$street_address2 = get_user_meta($current_user->ID, 'delivery_address2', true);
$city            = get_user_meta($current_user->ID, 'delivery_city', true);
$state           = get_user_meta($current_user->ID, 'delivery_state', true);
$zip             = get_user_meta($current_user->ID, 'delivery_zip', true);

// Combined address
$field_street_address  = $street_address1;
$field_street_address2 = $street_address2;
$field_city_state_zip  = "$city, $state $zip";

// Optional field: does user want to filter own system?
$field_filter_own_system = get_user_meta($current_user->ID, 'filter_own_system', true);
$field_filter_own_system = ($field_filter_own_system == '1' || $field_filter_own_system === 1) ? 1 : 0;

$firstname = $field_first_name;
$lastname  = $field_last_name;
$wholename = "$firstname $lastname";

// -----------------------------------------------------------------------------
// Helper functions
// -----------------------------------------------------------------------------

function build_notes($reqnote, $lendnote)
{
    $displaynotes = "";
    if ((strlen($reqnote) > 2) && (strlen($lendnote) > 2)) {
        $displaynotes = $reqnote . "</br>Lender Note: " . $lendnote;
    }
    if ((strlen($reqnote) > 2) && (strlen($lendnote) < 2)) {
        $displaynotes = $reqnote;
    }
    if ((strlen($reqnote) < 2) && (strlen($lendnote) > 2)) {
        $displaynotes = "Lender Note: " . $lendnote;
    }
    return $displaynotes;
}

function build_renewnotes($renewNote, $renewNoteLender)
{
    $displayrenewnotes = "";
    if ((strlen($renewNote) > 2) && (strlen($renewNoteLender) > 2)) {
        $displayrenewnotes = "Renew Note: " . $renewNote . "</br>Lender Note: " . $renewNoteLender;
    }
    if ((strlen($renewNote) > 2) && (strlen($renewNoteLender) < 2)) {
        $displayrenewnotes = "Renew Note: " . $renewNote;
    }
    if ((strlen($renewNote) < 2) && (strlen($renewNoteLender) > 2)) {
        $displayrenewnotes = "Lender Note: " . $renewNoteLender;
    }
    return $displayrenewnotes;
}

function build_return_notes($returnnote, $returnmethodtxt)
{
    $displayreturnnotes = '';
    if ((strlen($returnnote) > 2) || (strlen($returnmethodtxt) > 2)) {
        $displayreturnnotes = "Return Note: " . $returnnote . "  <br>Return Method: " . $returnmethodtxt;
    }
    return $displayreturnnotes;
}

function my_checked($filter_value)
{
    return ($filter_value == "yes") ? "checked" : "";
}

function shipmtotxt($shipmethod)
{
    $map = [
        "usps" => "US Mail",
        "mhls" => "Mid-Hudson Courier",
        "rcls" => "RCLS Courier",
        "empire" => "Empire Delivery",
        "ups" => "UPS",
        "fedex" => "FedEx",
        "OCLC Article Exchange" => "OCLC Article Exchange",
        "other" => "Other",
        "" => ""
    ];
    return $map[$shipmethod] ?? $shipmethod;
}

function itemstatus($fill, $receiveaccount, $returnaccount, $returndate, $receivedate, $checkinaccount, $checkindate, $fillNoFillDate)
{
    if ($fillNoFillDate == '0000-00-00') {
        $fillNoFillDate = '';
    }
    if ($fill == "1") {
        $fill = "Filled<br>" . $fillNoFillDate;
    }
    if ($fill == "0") {
        $fill = "Not Filled<br>" . $fillNoFillDate;
    }
    if ($fill == "3") {
        $fill = "No Answer";
    }
    if ($fill == "4") {
        $fill = "Expired";
    }
    if ($fill == "6") {
        $fill = "Canceled";
    }

    if ((strlen($receiveaccount) > 1) && (strlen($returnaccount) < 1) && (strlen($checkinaccount) < 1)) {
        $fill = "Loan Item Received<br>" . $receivedate;
    }
    if ((strlen($checkinaccount) < 1) && (strlen($receiveaccount) > 1) && (strlen($returnaccount) > 1)) {
        $fill = "Loan Item Returned<br>" . $returndate;
    }
    if (strlen($checkinaccount) > 1) {
        $fill = "Item Checkin by Lender<br>" . $checkindate;
    }
    return $fill;
}

function my_selected($days, $filter_value)
{
    return ($days == $filter_value) ? "selected" : "";
}

function elementHunt($startdated, $hunting)
{
    switch ($hunting) {
        case "D": return substr($startdated, 3, 2);
        case "M": return substr($startdated, 0, 2);
        case "Y": return substr($startdated, 6, 4);
    }
    return '';
}

function convertDate($InputDate)
{
    $Y = elementHunt($InputDate, "Y");
    $M = elementHunt($InputDate, "M");
    $D = elementHunt($InputDate, "D");
    return $Y . "-" . $M . "-" . $D;
}

function returnLimits($Offset, $filter_numresults)
{
    if (($Offset == "") || ($Offset = 0)) {
        $startint = 0;
    } else {
        $startint = $Offset * $filter_numresults;
    }
    $endint = $startint + $filter_numresults;
}

// -----------------------------------------------------------------------------
// Availability helpers
// -----------------------------------------------------------------------------

function normalize_availability($itemavail)
{
    $itemavail = str_replace([" ", "\n"], "", $itemavail);
    switch ($itemavail) {
        case "-":
        case "AVAILABLE":
        case "Available":
        case "CheckedIn":
        case "CHECKEDIN":
            return 1;
        default:
            return 0;
    }
}

function set_availability($itemavail)
{
    if ($itemavail == 1) {
        return "Available";
    }
    if ($itemavail == 0) {
        return "Unavailable";
    }
    if ($itemavail == 2) {
        return "UNKNOWN";
    }
}

function set_koha_availability($itemavail)
{
    if ($itemavail == 0 || stripos($itemavail, "available") !== false) {
        return ['status' => 'Available', 'code' => 0];
    }
    if ($itemavail == 1) {
        return ['status' => 'Unavailable', 'code' => 2];
    }
    if ($itemavail == 2) {
        return ['status' => 'UNKNOWN', 'code' => 3];
    }
    if (stripos($itemavail, "checked out") !== false) {
        return ['status' => 'Checked out', 'code' => 1];
    }
    if (stripos($itemavail, "on hold") !== false) {
        return ['status' => 'On hold', 'code' => 1];
    }
    if (stripos($itemavail, "lost") !== false) {
        return ['status' => 'Lost', 'code' => 1];
    }
    if (stripos($itemavail, "in transit") !== false) {
        return ['status' => 'In transit', 'code' => 1];
    }
    return ['status' => $itemavail, 'code' => null];
}

// -----------------------------------------------------------------------------
// Catalog/location checks (unchanged from your version)
// -----------------------------------------------------------------------------

function find_catalog($location)
{
    switch ($location) {
        case "SENYLRC Special Library Catalog": return "Koha";
        case "Adelphi University - Hudson Valley Center": return "Alma";
        case "Astor Services For Children & Families": return "Koha";
        case "Cary Institute": return "Koha";
        case "Columbia-Greene Community College": return "Alma";
        case "Dominican College": return "Alma";
        case "Dutchess BOCES School Library System": return "OPALS";
        case "Dutchess Community College": return "Alma";
        case "Mid-Hudson Library System": return "InnovativeMHLS";
        case "Mount St. Mary College": return "Innovative";
        case "St. Thomas Aquinas College": return "Innovative";
        case "Nathan Kline Institute": return "Koha";
        case "New York State Library": return "SirsiDynix";
        case "Orange County Community College": return "Alma";
        case "Orange-Ulster School Library System": return "OPALS";
        case "Ramapo-Catskill Library System": return "Koha";
        case "Rockland Community College": return "Alma";
        case "Rockland School Library System": return "OPALS";
        case "SUNY New Paltz ": return "Alma";
        case "Sullivan County Community College": return "Alma";
        case "Sullivan School Library System": return "TLC";
        case "Ulster County Community College": return "Alma";
        case "Vassar College": return "Alma";
    }
    return '';
}

function find_locationinfo($locationalias, $locationname)
{
    $locationalias = trim($locationalias);
    $locationname  = trim($locationname);
    $libparticipant = '';

    include '/var/www/seal_wp_script/seal_db.inc';
    $db = mysqli_connect($dbhost, $dbuser, $dbpass);
    mysqli_select_db($db, $dbname);

    if ($locationname == "Mid-Hudson Library System") {
        $GETLISTSQL = "SELECT `loc`,`participant`,`ill_email`,`suspend`,`system`,`Name`,`alias`
                     FROM `$sealLIB`
                     WHERE alias LIKE '%".$locationalias."%' AND `system`='MH'";
    } elseif ($locationname == "Sullivan School Library System") {
        $GETLISTSQL = "SELECT `loc`,`participant`,`ill_email`,`suspend`,`system`,`Name`,`alias`
                     FROM `$sealLIB`
                     WHERE alias LIKE '%".$locationalias."%' AND `system`='SB'";
    } else {
        $GETLISTSQL = "SELECT `loc`,`participant`,`ill_email`,`suspend`,`system`,`Name`,`alias`
                     FROM `$sealLIB`
                     WHERE alias = '$locationalias'";
    }

    $result = mysqli_query($db, $GETLISTSQL);
    $row    = mysqli_fetch_row($result);
    $libparticipant = $row;

    return $libparticipant;
}

function check_itemtype($destill, $itemtype, $destlibsystem)
{
    include '/var/www/seal_wp_script/seal_db.inc';
    $db = mysqli_connect($dbhost, $dbuser, $dbpass);
    mysqli_select_db($db, $dbname);

    $GETLISTSQL = "SELECT `Name`,book_loan,av_loan,ejournal_request,theses_loan,ebook_request
                 FROM `$sealLIB`
                 WHERE loc = '$destill'";
    $result = mysqli_query($db, $GETLISTSQL);

    while ($row = $result->fetch_assoc()) {
        $libname = $row['Name'];
        if ($libname == 'New York State Library') {
            return 1; // allow all
        }
        if ($itemtype == "other") {
            if ($destlibsystem == 'RC') {
                return 0;
            } else {
                return 1;
            }
        }
        if (($itemtype == "book") || ($itemtype == "book (large print)")) {
            if ($row['book_loan'] == "Yes") {
                return 1;
            }
        }
        if (($itemtype == 'journal') || ($itemtype == 'journal (electronic)')) {
            if ($row['ejournal_request'] == "Yes") {
                return 1;
            }
        }
        if (($itemtype == 'book (electronic)') || ($itemtype == 'web')) {
            if ($row['ebook_request'] == "Yes") {
                return 1;
            }
        }
        if (($itemtype == 'recording') || ($itemtype == 'video') || ($itemtype == 'audio') || ($itemtype == 'video-dvd')) {
            if ($row['av_loan'] == "Yes") {
                return 1;
            }
        }
        if (($itemtype == 'other') || ($itemtype == 'music-score') || ($itemtype == 'map') || ($itemtype == 'other (electronic)')) {
            if ($row['theses_loan'] == "Yes") {
                return 1;
            }
        }
    }
    return 0;
}

// -----------------------------------------------------------------------------
// Datepicker enqueue
// -----------------------------------------------------------------------------

function liblenderstat_enqueue_datepicker()
{
    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_style(
        'jquery-ui-css',
        'https://code.jquery.com/ui/1.13.3/themes/base/jquery-ui.css',
        [],
        '1.13.3'
    );
}
add_action('wp_enqueue_scripts', 'liblenderstat_enqueue_datepicker');

// -----------------------------------------------------------------------------
// Attribute helpers (safe fallback if WP context not available)
// -----------------------------------------------------------------------------

if (!function_exists('attr_checked')) {
    function attr_checked($val)
    {
        if (function_exists('checked')) {
            return checked($val, 'yes', false);
        }
        return ((string)$val === 'yes' || $val === true || $val === 1 || $val === '1') ? 'checked="checked"' : '';
    }
}

if (!function_exists('attr_selected')) {
    function attr_selected($value, $current)
    {
        if (function_exists('selected')) {
            return selected((string)$value, (string)$current, false);
        }
        return ((string)$value === (string)$current) ? 'selected="selected"' : '';
    }
}

function build_actions($row)
{
    $illNUB    = $row["illNUB"];
    $fill      = $row["Fill"];
    $receive   = $row["receiveAccount"];
    $return    = $row["returnAccount"];
    $checkin   = $row["checkinAccount"];
    $renewReq  = $row["renewAccountRequester"];
    $daysdiff  = round(abs(strtotime(date("Y-m-d")) - strtotime($row["Timestamp"])) / 86400);

    ob_start(); // start output buffer

    if ($fill == 0) {
        echo "&nbsp;";
    } elseif (($fill == 3) || (strlen($receive) < 1 && $daysdiff < 30 && $fill != 6)) {
        ?>
        <form method="post" action="/respond">
          <input type="hidden" name="FromLender" value="1">
          <input type="hidden" name="num" value="<?php echo htmlspecialchars($illNUB, ENT_QUOTES); ?>">
          <input type="hidden" name="fill" value="1">
          <button type="submit">Yes, Will Fill</button>
        </form>
        <hr>
        <form method="post" action="/respond">
          <input type="hidden" name="FromLender" value="1">
          <input type="hidden" name="num" value="<?php echo htmlspecialchars($illNUB, ENT_QUOTES); ?>">
          <input type="hidden" name="fill" value="0">
          <button type="submit">No, Can’t Fill</button>
        </form>
        <?php
    } elseif ((strlen($return) < 2) && ($fill == 1) && (strlen($renewReq) > 1) && (strlen($checkin) < 2)) {
        ?>
        <form method="post" action="/renew">
          <input type="hidden" name="num" value="<?php echo htmlspecialchars($illNUB, ENT_QUOTES); ?>">
          <input type="hidden" name="a" value="1">
          <button type="submit">Approve Renewal</button>
        </form>
        <form method="post" action="/renew">
          <input type="hidden" name="num" value="<?php echo htmlspecialchars($illNUB, ENT_QUOTES); ?>">
          <input type="hidden" name="a" value="2">
          <button type="submit">Deny Renewal</button>
        </form>
        <hr>
        <form method="post" action="/status">
          <input type="hidden" name="num" value="<?php echo htmlspecialchars($illNUB, ENT_QUOTES); ?>">
          <input type="hidden" name="a" value="3">
          <button type="submit">Check Item Back In</button>
        </form>
        <?php
    } elseif (($daysdiff > 14) && (strlen($checkin) < 2) && ($fill != 4) && ($fill != 6)) {
        ?>
        <form method="post" action="/status">
          <input type="hidden" name="num" value="<?php echo htmlspecialchars($illNUB, ENT_QUOTES); ?>">
          <input type="hidden" name="a" value="3">
          <button type="submit">Check Item Back In</button>
        </form>
        <?php
    } else {
        echo "&nbsp;";
    }

    return ob_get_clean();
}

// -----------------------------------------------------------------------------
// Build action buttons for lender-history and similar pages
// -----------------------------------------------------------------------------
function build_lender_actions($illNUB, $fill, $receive, $return, $checkin, $renewReq, $daysdiff)
{
    ob_start();

    if ($fill == 0 || $fill == 6) {
        echo "&nbsp;";
    }
    // Unanswered / recent requests
    elseif (($fill == 3) || (strlen($receive) < 1 && $daysdiff < 30 && $fill != 6)) {
        ?>
        <form method="post" action="/respond">
          <input type="hidden" name="FromLender" value="1">
          <input type="hidden" name="num" value="<?php echo htmlspecialchars($illNUB, ENT_QUOTES); ?>">
          <input type="hidden" name="fill" value="1">
          <button type="submit">Yes, Will Fill</button>
        </form>
        <hr>
        <form method="post" action="/respond">
          <input type="hidden" name="FromLender" value="1">
          <input type="hidden" name="num" value="<?php echo htmlspecialchars($illNUB, ENT_QUOTES); ?>">
          <input type="hidden" name="fill" value="0">
          <button type="submit">No, Can’t Fill</button>
        </form>
        <?php
    }
    // Renewal requests waiting
    elseif ((strlen($return) < 2) && ($fill == 1) && (strlen($renewReq) > 1) && (strlen($checkin) < 2)) {
        ?>
        <form method="post" action="/renew">
          <input type="hidden" name="num" value="<?php echo htmlspecialchars($illNUB, ENT_QUOTES); ?>">
          <input type="hidden" name="a" value="1">
          <button type="submit">Approve Renewal</button>
        </form>
        <form method="post" action="/renew">
          <input type="hidden" name="num" value="<?php echo htmlspecialchars($illNUB, ENT_QUOTES); ?>">
          <input type="hidden" name="a" value="2">
          <button type="submit">Deny Renewal</button>
        </form>
        <hr>
        <form method="post" action="/status">
          <input type="hidden" name="num" value="<?php echo htmlspecialchars($illNUB, ENT_QUOTES); ?>">
          <input type="hidden" name="a" value="3">
          <button type="submit">Check Item Back In</button>
        </form>
        <?php
    }
    // Overdue items
    elseif (($daysdiff > 14) && (strlen($checkin) < 2) && ($fill != 4) && ($fill != 6)) {
        ?>
        <form method="post" action="/status">
          <input type="hidden" name="num" value="<?php echo htmlspecialchars($illNUB, ENT_QUOTES); ?>">
          <input type="hidden" name="a" value="3">
          <button type="submit">Check Item Back In</button>
        </form>
        <?php
    } else {
        echo "&nbsp;";
    }

    return ob_get_clean();
}
function self_url()
{
    if (function_exists('get_permalink')) {
        return get_permalink(); // current WP page URL
    }
    // fallback if outside WordPress
    return $_SERVER['REQUEST_URI'];
}



?>
