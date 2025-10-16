<?php

// ==========================================================
// WordPress Role Enforcement — Restrict to Administrator
// ==========================================================
require_once('/var/www/wpSEAL/wp-load.php');
$current_user = wp_get_current_user();
$user_roles = (array)$current_user->roles;

if (!in_array('administrator', $user_roles, true)) {
    die("<div style='padding:20px;color:red;font-weight:bold;'>
        Access Denied<br>You must have the <b>Administrator</b> role to access this page.
    </div>");
}

/*
Template Name: Staff Emails (CSV Export)
Description: Lists all staff account emails with CSV export
*/
//email-harvest.php
// Only allow logged-in admins
if ( ! current_user_can('list_users') ) {
    wp_die('You do not have permission to view this page.');
}

// Handle CSV download cleanly
if ( isset($_GET['download']) && $_GET['download'] === 'csv' ) {
    // Kill WordPress output buffering and disable theme
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Fetch users (restrict to staff roles if needed)
    $users = get_users();

    // Raw headers
    header('Content-Description: File Transfer');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=staff-emails.csv');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    foreach ($users as $user) {
        // Just email, one per line
        fwrite($output, $user->user_email . "\n");
    }
    fclose($output);
    exit; // 🚨 absolutely stop WordPress here
}

get_header();
?>
<div class="wrap">
    <h1>Staff Email Addresses</h1>
    <p><a href="?download=csv" class="button button-primary">⬇️ Download CSV (emails only)</a></p>

    <ul>
        <?php
        $users = get_users();
        foreach ($users as $user):
            echo '<li>' . esc_html($user->user_email) . '</li>';
        endforeach;
        ?>
    </ul>
</div>
<?php get_footer(); ?>