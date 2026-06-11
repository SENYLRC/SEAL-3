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

if (!array_intersect(['administrator', 'libstaff'], $user_roles)) {
    die("<div style='padding:20px;color:red;font-weight:bold;'>
        Access Denied<br>You must have the <b>Administrator</b> or <b>Library Staff</b> role to access this page.
    </div>");
}

// ==========================================================
// Library scope (primary + extra LOCs) + optional ALL
// ==========================================================
$primary_loc = strtoupper(trim((string)get_user_meta($current_user->ID, 'address_loc_code', true)));

if ($primary_loc === '') {
    die("<div style='padding:20px;color:red;font-weight:bold;'>
        No location code found for your account.
    </div>");
}

// extra LOCs from user meta: "NHIGS,NWATTJ"
$extra_locs_raw = get_user_meta($current_user->ID, 'seal_extra_locs', true);
$extra_locs_raw = is_string($extra_locs_raw) ? trim($extra_locs_raw) : '';

$extra_locs = [];
if ($extra_locs_raw !== '') {
    foreach (explode(',', $extra_locs_raw) as $c) {
        $c = strtoupper(trim($c));
        if ($c !== '') {
            $extra_locs[] = $c;
        }
    }
}

$allowed_locs = array_values(array_unique(array_filter(array_merge([$primary_loc], $extra_locs))));

// ==========================================================
// DB connect (needed for LOC → Name translation)
// ==========================================================
require '/var/www/seal_wp_script/seal_db.inc';
$db = mysqli_connect($dbhost, $dbuser, $dbpass);
mysqli_select_db($db, $dbname);

// -------------------------------
// Resolve LOC codes to Library Names
// -------------------------------
$loc_name_map = [];

if (!empty($allowed_locs)) {
    $in = "'" . implode("','", array_map(
        fn ($l) => mysqli_real_escape_string($db, $l),
        $allowed_locs
    )) . "'";

    $sql = "SELECT loc, Name FROM `$sealLIB` WHERE loc IN ($in)";
    $res = mysqli_query($db, $sql);

    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $loc_name_map[strtoupper($r['loc'])] = $r['Name'];
        }
    }
}


// Active selection: ?loc=XXXX or ?loc=all
$active_sel = isset($_GET['loc']) ? strtolower(trim((string)$_GET['loc'])) : strtolower($primary_loc);
if ($active_sel === '') {
    $active_sel = strtolower($primary_loc);
}

// Validate selection
$is_all = ($active_sel === 'all');

if (!$is_all) {
    $active_loc = strtoupper($active_sel);
    if (!in_array($active_loc, $allowed_locs, true)) {
        $active_loc = $primary_loc; // prevent tampering
    }
} else {
    $active_loc = null; // not used in ALL mode
}

// ==========================================================
// Switcher UI (only if multiple LOCs)
// ==========================================================
if (count($allowed_locs) > 1) {
    echo '<div style="margin:12px 0; padding:12px; border:1px solid #ddd; border-radius:8px; background:#fafafa;">';
    echo '<label for="locSwitch" style="font-weight:bold; margin-right:8px;">View staff for:</label>';

    echo '<select id="locSwitch" onchange="if(this.value){ window.location.href=this.value; }">';

    // ALL option
    $all_url = esc_url(add_query_arg('loc', 'all'));
    echo '<option value="'.$all_url.'" '.($is_all ? 'selected' : '').'>All my libraries</option>';

    // Individual LOCs
    foreach ($allowed_locs as $l) {
        $url = esc_url(add_query_arg('loc', $l));
        $sel = (!$is_all && $l === $active_loc) ? 'selected' : '';

        $label = ($loc_name_map[$l] ?? $l) . ' (' . $l . ')';

        echo '<option value="'.$url.'" '.$sel.'>'.esc_html($label).'</option>';
    }


    echo '</select>';
    echo '</div>';
}

// Title
if ($is_all) {

    $names = [];
    foreach ($allowed_locs as $code) {
        $names[] = ($loc_name_map[$code] ?? $code) . ' (' . $code . ')';
    }

    echo '<h2>Library Staff for: All My Libraries</h2>';
    echo '<div class="pill">' . esc_html(implode(', ', $names)) . '</div>';

} else {

    $name = $loc_name_map[$active_loc] ?? 'Unknown Library';
    echo '<h2>Library Staff for: ' . esc_html($name) . ' (' . esc_html($active_loc) . ')</h2>';

}


// ==========================================================
// Query users
// ==========================================================
if ($is_all) {
    // Pull staff for ALL allowed LOCs
    $users = get_users([
        'meta_query' => [
            [
                'key'     => 'address_loc_code',
                'value'   => $allowed_locs,
                'compare' => 'IN',
            ]
        ],
        'orderby' => 'display_name',
        'order'   => 'ASC',
        'number'  => 9999,
    ]);
} else {
    // Pull staff for ONE loc
    $users = get_users([
        'meta_key'   => 'address_loc_code',
        'meta_value' => $active_loc,
        'orderby'    => 'display_name',
        'order'      => 'ASC',
        'number'     => 9999,
    ]);
}

// ==========================================================
// Output table
// ==========================================================
if (!empty($users)) {
    echo '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse;width:100%;">';
    echo '<tr style="background-color:#f4f4f4;">
            <th>LOC</th>
            <th>First Name</th>
            <th>Last Name</th>
            <th>Username</th>
            <th>Email</th>
            <th>Last Login</th>
            <th>Account Created</th>
          </tr>';

    foreach ($users as $user) {
        $user_loc    = strtoupper((string)get_user_meta($user->ID, 'address_loc_code', true));
        $first_name  = get_user_meta($user->ID, 'first_name', true);
        $last_name   = get_user_meta($user->ID, 'last_name', true);
        $username    = $user->user_login;
        $email       = $user->user_email;
        $registered  = date('Y-m-d', strtotime($user->user_registered));

        $last_login_ts = get_user_meta($user->ID, 'wp-last-login', true);
        $last_login = $last_login_ts ? date('Y-m-d H:i:s', (int)$last_login_ts) : 'Unknown';

        echo '<tr>';
        echo '<td>' . esc_html($user_loc ?: '—') . '</td>';
        echo '<td>' . esc_html($first_name) . '</td>';
        echo '<td>' . esc_html($last_name) . '</td>';
        echo '<td>' . esc_html($username) . '</td>';
        echo '<td>' . esc_html($email) . '</td>';
        echo '<td>' . esc_html($last_login) . '</td>';
        echo '<td>' . esc_html($registered) . '</td>';
        echo '</tr>';
    }

    echo '</table>';
} else {
    echo '<p>No users found for this selection.</p>';
}
