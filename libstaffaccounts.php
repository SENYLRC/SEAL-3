<?php
if (!is_user_logged_in()) {
    return '<p>You must be logged in to view this content.</p>';
}

$current_user = wp_get_current_user();
$location_code = get_user_meta($current_user->ID, 'address_loc_code', true);

if (empty($location_code)) {
    return '<p>No location code found for your account.</p>';
}

echo '<h2>Library Staff for: ' . esc_html($location_code) . '</h2>';

$users = get_users([
    'meta_key'   => 'address_loc_code',
    'meta_value' => $location_code,
]);

if (!empty($users)) {
    echo '<table border="1" cellpadding="5" cellspacing="0">';
    echo '<tr><th>First Name</th><th>Last Name</th><th>Username</th><th>Email</th><th>Last Login</th><th>Account Created</th></tr>';

    foreach ($users as $user) {
        $first_name = get_user_meta($user->ID, 'first_name', true);
        $last_name = get_user_meta($user->ID, 'last_name', true);
        $username = $user->user_login;
        $email = $user->user_email;
        $registered = date('Y-m-d', strtotime($user->user_registered));

        // Get last login timestamp
  $last_login_ts = get_user_meta($user->ID, 'wp-last-login', true);
$last_login = $last_login_ts ? date('Y-m-d H:i:s', $last_login_ts) : 'Unknown';

        echo '<tr>';
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
    echo '<p>No other users found for this location.</p>';
}
?>