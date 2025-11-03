<?php
/*
Plugin Name: SENYLC Custom User Fields
Description: Adds and manages custom fields for user profiles, removes default fields, shows in add-new-user, and adds Institution, Home System, and LOC Code
to Users list. Adds full custom registration form.
Version: 1.5
Author: SENYLRC
*/

// -------------------------------
// Add custom fields (Profile + Add User)
// -------------------------------
function custom_user_profile_fields($user)
{
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
    if (empty($home_system)) {
        $home_system = 'SE';
    }
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
    if (empty($delivery_state)) {
        $delivery_state = 'NY';
    }
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
add_action('user_new_form', 'custom_user_profile_fields');

// -------------------------------
// Save custom fields
// -------------------------------
function save_custom_user_profile_fields($user_id)
{
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }

    $fields = [
        'institution', 'home_system', 'phone', 'alt_email', 'address_loc_code',
        'delivery_address1', 'delivery_address2', 'delivery_city', 'delivery_state',
        'delivery_zip', 'oclc_symbol'
    ];
    foreach ($fields as $field) {
        $value = isset($_POST[$field]) ? sanitize_text_field($_POST[$field]) : '';
        if ($field === 'alt_email') {
            $value = sanitize_email($value);
        }
        update_user_meta($user_id, $field, $value);
    }
}
add_action('personal_options_update', 'save_custom_user_profile_fields');
add_action('edit_user_profile_update', 'save_custom_user_profile_fields');
add_action('user_register', 'save_custom_user_profile_fields');

// -------------------------------
// Add fields to registration form
// -------------------------------
function senylc_registration_form_fields()
{
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
    <p><label for="institution">Library Name (required)<br/>
        <input type="text" name="institution" id="institution" class="input"
            value="<?php echo esc_attr($_POST['institution'] ?? ''); ?>" required></label></p>

    <p><label for="first_name">First Name (required)<br/>
        <input type="text" name="first_name" id="first_name" class="input"
            value="<?php echo esc_attr($_POST['first_name'] ?? ''); ?>" required></label></p>

    <p><label for="last_name">Last Name (required)<br/>
        <input type="text" name="last_name" id="last_name" class="input"
            value="<?php echo esc_attr($_POST['last_name'] ?? ''); ?>" required></label></p>

    <p><label for="home_system">Home Library System<br/>
        <select name="home_system" id="home_system">
            <?php foreach ($options as $code => $label): ?>
                <option value="<?php echo esc_attr($code); ?>" <?php selected($_POST['home_system'] ?? 'SE', $code); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select></label></p>

    <p><label for="phone">Work Phone<br/>
        <input type="text" name="phone" id="phone" class="input"
            value="<?php echo esc_attr($_POST['phone'] ?? ''); ?>"></label></p>

    <p><label for="alt_email">Additional Email<br/>
        <input type="email" name="alt_email" id="alt_email" class="input"
            value="<?php echo esc_attr($_POST['alt_email'] ?? ''); ?>"></label></p>

    <p><label for="address_loc_code">LOC Location Code<br/>
        <input type="text" name="address_loc_code" id="address_loc_code" class="input"
            value="<?php echo esc_attr($_POST['address_loc_code'] ?? ''); ?>"></label></p>

    <p><label for="oclc_symbol">OCLC Symbol<br/>
        <input type="text" name="oclc_symbol" id="oclc_symbol" class="input"
            value="<?php echo esc_attr($_POST['oclc_symbol'] ?? ''); ?>"></label></p>

    <p><label for="delivery_address1">Delivery Street Address Line 1 (required)<br/>
        <input type="text" name="delivery_address1" id="delivery_address1" class="input"
            value="<?php echo esc_attr($_POST['delivery_address1'] ?? ''); ?>" required></label></p>

    <p><label for="delivery_address2">Delivery Street Address Line 2<br/>
        <input type="text" name="delivery_address2" id="delivery_address2" class="input"
            value="<?php echo esc_attr($_POST['delivery_address2'] ?? ''); ?>"></label></p>

    <p><label for="delivery_city">City (required)<br/>
        <input type="text" name="delivery_city" id="delivery_city" class="input"
            value="<?php echo esc_attr($_POST['delivery_city'] ?? ''); ?>" required></label></p>

    <p><label for="delivery_state">State (required)<br/>
        <input type="text" name="delivery_state" id="delivery_state" class="input"
            value="<?php echo esc_attr($_POST['delivery_state'] ?? 'NY'); ?>" required></label></p>

    <p><label for="delivery_zip">Zip Code (required)<br/>
        <input type="text" name="delivery_zip" id="delivery_zip" class="input"
            value="<?php echo esc_attr($_POST['delivery_zip'] ?? ''); ?>" required></label></p>
    <?php
}
add_action('register_form', 'senylc_registration_form_fields');

// Validate required fields
function senylc_validate_registration_fields($errors, $sanitized_user_login, $user_email)
{
    $required = ['institution', 'first_name', 'last_name', 'delivery_address1', 'delivery_city', 'delivery_state', 'delivery_zip'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $label = ucwords(str_replace('_', ' ', str_replace('delivery_', '', $field)));
            $errors->add($field . '_error', __("<strong>Error:</strong> {$label} is required."));
        }
    }
    return $errors;
}
add_filter('registration_errors', 'senylc_validate_registration_fields', 10, 3);

// Save registration fields
function senylc_save_registration_fields($user_id)
{
    $fields = [
        'institution','first_name','last_name','home_system','phone','alt_email','address_loc_code',
        'oclc_symbol','delivery_address1','delivery_address2','delivery_city','delivery_state','delivery_zip'
    ];
    foreach ($fields as $f) {
        if (isset($_POST[$f])) {
            $val = $f === 'alt_email' ? sanitize_email($_POST[$f]) : sanitize_text_field($_POST[$f]);
            update_user_meta($user_id, $f, $val);
        }
    }
}
add_action('user_register', 'senylc_save_registration_fields');

// -------------------------------
// Fix registration page layout spacing
// -------------------------------
function senylc_register_form_spacing_fix()
{
    if (isset($_GET['action']) && $_GET['action'] === 'register') {
        echo '<style>
            body.login {
                padding-top: 40px !important;
                overflow-y: auto !important;
            }
            body.login #login {
                margin-top: 40px !important;
            }
            body.login form {
                padding-bottom: 50px !important;
            }
        </style>';
    }
}
add_action('login_head', 'senylc_register_form_spacing_fix');

// -------------------------------
// Custom Columns: Library Name + Home System between Role and Email (sortable)
// -------------------------------
function senylc_add_user_columns($columns)
{
    unset($columns['posts']); // remove Posts column

    $new_columns = [];
    foreach ($columns as $key => $label) {
        $new_columns[$key] = $label;

        if ($key === 'role') {
            $new_columns['institution'] = 'Library Name';
            $new_columns['home_system'] = 'Home Library System';
            $new_columns['user_status'] = 'Status';
        }
    }
    return $new_columns;
}
add_filter('manage_users_columns', 'senylc_add_user_columns', 20);

// Display column values
function senylc_show_user_column_data($value, $column_name, $user_id)
{
    switch ($column_name) {
        case 'institution':
            return esc_html(get_user_meta($user_id, 'institution', true));
        case 'home_system':
            return esc_html(get_user_meta($user_id, 'home_system', true));
        case 'user_status':
            $status = get_userdata($user_id)->user_status;
            return $status == 0 ? 'Active' : 'Inactive';
    }
    return $value;
}
add_filter('manage_users_custom_column', 'senylc_show_user_column_data', 10, 3);

// Make columns sortable
function senylc_sortable_user_columns($columns)
{
    $columns['institution'] = 'institution';
    $columns['home_system'] = 'home_system';
    $columns['user_status'] = 'user_status';
    return $columns;
}
add_filter('manage_users_sortable_columns', 'senylc_sortable_user_columns');

// Sorting logic
function senylc_sort_users_by_meta($query)
{
    global $pagenow;

    if (is_admin() && 'users.php' === $pagenow) {
        $orderby = $query->get('orderby');

        // Sort by meta fields
        if ($orderby === 'institution' || $orderby === 'home_system') {
            $query->set('meta_key', $orderby);
            $query->set('orderby', 'meta_value');
        }

        // Sort by built-in user_status
        if ($orderby === 'user_status') {
            $query->set('orderby', 'user_status');
        }
    }
}
add_action('pre_get_users', 'senylc_sort_users_by_meta');
