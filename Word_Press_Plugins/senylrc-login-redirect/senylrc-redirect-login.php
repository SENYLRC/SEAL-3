<?php
/*
Plugin Name: Redirect Libstaff Users After Login
Plugin URI:  https://senylrc.org
Description: Redirects all users with the libstaff role to /illuser/ after login,
             unless they are trying to visit a specific page.
Version:     1.1
Author:      SENYLRC
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_filter('login_redirect', 'libstaff_login_redirect', 10, 3);

function libstaff_login_redirect($redirect_to, $request, $user) {
    if (isset($user->roles) && is_array($user->roles)) {
        if (in_array('libstaff', $user->roles)) {
            // If no redirect requested (empty) or just the admin dashboard, send to /illuser/
            if (empty($redirect_to) || strpos($redirect_to, 'wp-admin') !== false) {
                return home_url('/illuser/');
            }
        }
    }
    // Keep the normal behavior (user requested another page)
    return $redirect_to;
}
