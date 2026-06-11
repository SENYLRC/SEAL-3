<?php
/*
Template Name: Staff Emails (CSV Export)
Description: Lists all staff account emails with CSV export
*/

// ==========================================================
// WordPress Role Enforcement — Restrict to Administrator
// ==========================================================
require_once('/var/www/wpSEAL/wp-load.php');

$current_user = wp_get_current_user();
$user_roles   = (array)$current_user->roles;

if ( ! in_array('administrator', $user_roles, true) ) {
    die("<div style='padding:20px;color:red;font-weight:bold;'>
        Access Denied<br>You must have the <b>Administrator</b> role to access this page.
    </div>");
}

// ==========================================================
// CSV Export Logic (Email + Institution)
// ==========================================================
if ( isset($_GET['download']) && $_GET['download'] === 'csv' ) {

    // Turn off any output buffering
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Fetch all users
    $users = get_users();

    // Send file headers
    header('Content-Description: File Transfer');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=staff-emails.csv');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Header row
    fputcsv($output, ['Email', 'Institution']);

    // Data rows
    foreach ($users as $user) {
        $institution = get_user_meta($user->ID, 'institution', true);
        fputcsv($output, [$user->user_email, $institution]);
    }

    fclose($output);
    exit;
}

// ==========================================================
// On-screen Display (Email + Institution)
// ==========================================================
get_header();
?>
<div class="wrap">
    <h1>Staff Email Addresses</h1>
    <p><a href="?download=csv" class="button button-primary">⬇️ Download CSV (Email + Institution)</a></p>

    <ul>
        <?php
        $users = get_users();
        foreach ($users as $user) {
            $institution = get_user_meta($user->ID, 'institution', true);
            echo '<li>' . esc_html($user->user_email);
            if (!empty($institution)) {
                echo ' — ' . esc_html($institution);
            }
            echo '</li>';
        }
        ?>
    </ul>
</div>
<?php get_footer(); ?>