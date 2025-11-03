<?php

/*
Plugin Name: SENYLC Export Users
Description: Allows administrators to download a CSV list of users including custom fields and login info.
Version: 1.2
Author: SENYLRC
*/

if (!defined('ABSPATH')) {
    exit;
}

// ------------------------------------------------------------------
// Add Admin Menu
// ------------------------------------------------------------------
add_action('admin_menu', function () {
    add_users_page(
        'Export User List',
        'Export User List (CSV)',
        'manage_options',
        'senylrc-export-users',
        'senylrc_export_users_page'
    );
});

// ------------------------------------------------------------------
// Admin Page HTML
// ------------------------------------------------------------------
function senylrc_export_users_page()
{
    if (!current_user_can('manage_options')) {
        wp_die('Access denied.');
    }

    $export_url = admin_url('admin-post.php?action=senylrc_export_users');

    echo '<div class="wrap">';
    echo '<h1>Export User List</h1>';
    echo '<p>Click below to download a CSV file containing all users with custom fields.</p>';
    echo '<a href="' . esc_url($export_url) . '" class="button button-primary">Download CSV</a>';
    echo '</div>';
}

// ------------------------------------------------------------------
// Handle Export Request
// ------------------------------------------------------------------
add_action('admin_post_senylrc_export_users', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Access denied.');
    }

    // Clean output buffer and disable caching
    while (ob_get_level()) {
        ob_end_clean();
    }

    $filename = 'user_export_' . date('Y-m-d_His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // CSV Header Row
    fputcsv($output, [
        'Username',
        'Email',
        'First Name',
        'Last Name',
        'Library Name',
        'Home System',
        'LOC Code'
    ]);

    // Get all users
    $users = get_users(['fields' => ['ID', 'user_login', 'user_email']]);

    foreach ($users as $user) {
        $uid = $user->ID;

        $first_name   = get_user_meta($uid, 'first_name', true);
        $last_name    = get_user_meta($uid, 'last_name', true);
        $library_name = get_user_meta($uid, 'institution', true);
        $home_system  = get_user_meta($uid, 'home_system', true);
        $loc_code     = get_user_meta($uid, 'address_loc_code', true);

        fputcsv($output, [
            $user->user_login,
            $user->user_email,
            $first_name,
            $last_name,
            $library_name,
            $home_system,
            $loc_code
        ]);
    }

    fclose($output);
    exit;
});
