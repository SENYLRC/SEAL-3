<?php
/*
Plugin Name: SENYLC Custom User Fields
Description: Adds and manages custom fields for user profiles, removes default fields, shows in add-new-user, and adds Institution, Home System, and LOC Code to Users list.
Version: 1.3
Author: Your Name
*/

// -------------------------------
// Add custom fields (Profile + Add User)
// -------------------------------
function custom_user_profile_fields($user) {
    ?>
    <h3>Additional User Information</h3>
    <table class="form-table">
        <tr><th><label for="institution">Institution</label></th>
            <td><input type="text" name="institution" id="institution"
                value="<?php echo esc_attr(get_the_author_meta('institution', $user->ID)); ?>"
                class="regular-text" /></td></tr>

        <tr><th><label for="home_system">Home Library System</label></th>
            <td>
                <?php
                $home_system = get_the_author_meta('home_system', $user->ID);
                if (empty($home_system)) $home_system = 'SE'; // Default

                $options = [
                    'DU' => 'Dutchess BOCES',
                    'MH' => 'Mid-Hudson Library System',
                    'OU' => 'Orange-Ulster BOCES',
                    'RC' => 'Ramapo Catskill Library System',
                    'RB' => 'Rockland BOCES',
                    'SB' => 'Sullivan BOCES',
                    'UB' => 'Ulster BOCES',
                    'SE' => 'SENYLRC'
                ];
                ?>
                <select name="home_system" id="home_system">
                    <?php foreach ($options as $code => $label): ?>
                        <option value="<?php echo esc_attr($code); ?>" <?php selected($home_system, $code); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>

        <tr><th><label for="phone">Work Phone</label></th>
            <td><input type="text" name="phone" id="phone"
                value="<?php echo esc_attr(get_the_author_meta('phone', $user->ID)); ?>"
                class="regular-text" /></td></tr>

        <tr><th><label for="alt_email">Additional Email</label></th>
            <td><input type="email" name="alt_email" id="alt_email"
                value="<?php echo esc_attr(get_the_author_meta('alt_email', $user->ID)); ?>"
                class="regular-text" /></td></tr>

        <tr><th><label for="address_loc_code">LOC Location Code</label></th>
            <td><input type="text" name="address_loc_code" id="address_loc_code"
                value="<?php echo esc_attr(get_the_author_meta('address_loc_code', $user->ID)); ?>"
                class="regular-text" /></td></tr>

        <tr><th><label for="oclc_symbol">OCLC Symbol</label></th>
            <td><input type="text" name="oclc_symbol" id="oclc_symbol"
                value="<?php echo esc_attr(get_the_author_meta('oclc_symbol', $user->ID)); ?>"
                class="regular-text" /></td></tr>

        <tr><th><label for="delivery_address1">Delivery Street Address Line 1</label></th>
            <td><input type="text" name="delivery_address1" id="delivery_address1"
                value="<?php echo esc_attr(get_the_author_meta('delivery_address1', $user->ID)); ?>"
                class="regular-text" /></td></tr>

        <tr><th><label for="delivery_address2">Delivery Street Address Line 2</label></th>
            <td><input type="text" name="delivery_address2" id="delivery_address2"
                value="<?php echo esc_attr(get_the_author_meta('delivery_address2', $user->ID)); ?>"
                class="regular-text" /></td></tr>

        <tr><th><label for="delivery_city">City</label></th>
            <td><input type="text" name="delivery_city" id="delivery_city"
                value="<?php echo esc_attr(get_the_author_meta('delivery_city', $user->ID)); ?>"
                class="regular-text" /></td></tr>

        <tr><th><label for="delivery_state">State</label></th>
            <?php
            $delivery_state = get_the_author_meta('delivery_state', $user->ID);
            if (empty($delivery_state)) $delivery_state = 'NY';
            ?>
            <td><input type="text" name="delivery_state" id="delivery_state"
                value="<?php echo esc_attr($delivery_state); ?>"
                class="regular-text" /></td></tr>

        <tr><th><label for="delivery_zip">Zip Code</label></th>
            <td><input type="text" name="delivery_zip" id="delivery_zip"
                value="<?php echo esc_attr(get_the_author_meta('delivery_zip', $user->ID)); ?>"
                class="regular-text" /></td></tr>
    </table>
    <?php
}
add_action('show_user_profile', 'custom_user_profile_fields');
add_action('edit_user_profile', 'custom_user_profile_fields');
// Also show on Add New User
add_action('user_new_form', 'custom_user_profile_fields');

// -------------------------------
// Save custom fields
// -------------------------------
function save_custom_user_profile_fields($user_id) {
    if (!current_user_can('edit_user', $user_id)) return false;

    $fields = [
        'institution', 'home_system', 'phone', 'alt_email', 'address_loc_code',
        'delivery_address1', 'delivery_address2', 'delivery_city', 'delivery_state',
        'delivery_zip', 'oclc_symbol'
    ];
    foreach ($fields as $field) {
        $value = isset($_POST[$field]) ? sanitize_text_field($_POST[$field]) : '';
        if ($field === 'alt_email') $value = sanitize_email($value);
        update_user_meta($user_id, $field, $value);
    }
}
add_action('personal_options_update', 'save_custom_user_profile_fields');
add_action('edit_user_profile_update', 'save_custom_user_profile_fields');
add_action('user_register', 'save_custom_user_profile_fields');

// -------------------------------
// Add columns to Users list
// -------------------------------
function add_custom_user_columns($columns) {
    // Remove Posts column
    unset($columns['posts']);
    // Add our fields
    $columns['institution'] = 'Institution';
    $columns['home_system'] = 'Home System';
    $columns['address_loc_code'] = 'LOC Code';
    return $columns;
}
add_filter('manage_users_columns', 'add_custom_user_columns');

function show_custom_user_columns($value, $column_name, $user_id) {
    switch ($column_name) {
        case 'institution':
        case 'home_system':
        case 'address_loc_code':
            return esc_html(get_the_author_meta($column_name, $user_id));
    }
    return $value;
}
add_filter('manage_users_custom_column', 'show_custom_user_columns', 10, 3);

// -------------------------------
// Hide unwanted default profile fields
// -------------------------------
function hide_default_user_profile_fields_css() {
    echo '<style>
        .user-admin-color-wrap,
        .user-url-wrap,
        .user-description-wrap,
        tr.user-profile-picture,
        #application-passwords-section,
        .xyz_admin_notice,
        .notice-info,
        .notice-success,
        .notice-warning,
        .notice-error {
            display: none !important;
        }
    </style>';
}
add_action('admin_head-user-edit.php', 'hide_default_user_profile_fields_css');
add_action('admin_head-profile.php', 'hide_default_user_profile_fields_css');
add_action('admin_head', 'hide_default_user_profile_fields_css');

// -------------------------------
// Suppress Insert PHP Code Snippet nag
// -------------------------------
function suppress_xyz_ips_nag_via_js() {
    echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            var nag = document.getElementById("ips_notice_td");
            if (nag) nag.remove();
        });
    </script>';
}
add_action('admin_footer-profile.php', 'suppress_xyz_ips_nag_via_js');
add_action('admin_footer-user-edit.php', 'suppress_xyz_ips_nag_via_js');
