<?php
// illuser.php###
// Load WordPress if needed
if (!defined('ABSPATH')) {
    require_once('/var/www/wpSEAL/wp-load.php');
}

// Restrict to Logged-In Administrator or Library Staff
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

$user_id = $current_user->ID;

if (get_current_user_id() !== (int)$user_id && !current_user_can('edit_user', $user_id)) {
    wp_die('You do not have permission to edit this profile.');
}



// Helper for safe output
function h($v)
{
    return esc_attr((string)($v ?? ''));
}

// Load existing core fields
$username     = $current_user->user_login; // immutable
$email        = $current_user->user_email;
$first_name   = get_user_meta($user_id, 'first_name', true);
$last_name    = get_user_meta($user_id, 'last_name', true);
$nickname     = $current_user->nickname;

// Build display name choices
$display_name = $current_user->display_name;
$name_options = array_unique(array_filter([
  $username,
  $nickname,
  trim($first_name),
  trim($last_name),
  trim($first_name . ' ' . $last_name),
  trim($last_name . ' ' . $first_name),
]));

// Load custom meta fields
$institution =        get_user_meta($user_id, 'institution', true);
$home_system       = get_user_meta($user_id, 'home_system', true);
$work_phone        = get_user_meta($user_id, 'work_phone', true);
$alt_email         = get_user_meta($user_id, 'additional_email', true);
$loc_code          = get_user_meta($user_id, 'address_loc_code', true);
$oclc_symbol       = get_user_meta($user_id, 'oclc_symbol', true);
$delivery_address1 = get_user_meta($user_id, 'delivery_address1', true);
$delivery_address2 = get_user_meta($user_id, 'delivery_address2', true);
$city              = get_user_meta($user_id, 'delivery_city', true);
$state             = get_user_meta($user_id, 'delivery_state', true);
$zip               = get_user_meta($user_id, 'delivery_zip', true);

$notice = '';
$notice_class = '';

// Handle POST
if ('POST' === $_SERVER['REQUEST_METHOD'] && isset($_POST['wp_profile_nonce']) && wp_verify_nonce($_POST['wp_profile_nonce'], 'update_wp_profile')) {

    // Sanitize incoming
    $new_first   = sanitize_text_field($_POST['first_name'] ?? '');
    $new_last    = sanitize_text_field($_POST['last_name'] ?? '');
    $new_email   = sanitize_email($_POST['email'] ?? $email);

    // nickname: use email by default if blank
    $new_nick    = !empty($_POST['nickname']) ? sanitize_text_field($_POST['nickname']) : $new_email;
    $new_disp    = sanitize_text_field($_POST['display_name'] ?? $display_name);

    $new_pass    = (string)($_POST['new_password'] ?? '');
    $new_pass2   = (string)($_POST['new_password_confirm'] ?? '');

    $new_institution   = sanitize_text_field($_POST['institution'] ?? '');
    $new_system        = sanitize_text_field($_POST['home_system'] ?? '');
    $new_work_phone    = sanitize_text_field($_POST['work_phone'] ?? '');
    $new_alt_email     = sanitize_email($_POST['additional_email'] ?? '');
    $new_loc_code      = sanitize_text_field($_POST['address_loc_code'] ?? '');
    $new_oclc          = sanitize_text_field($_POST['oclc_symbol'] ?? '');
    $new_address1      = sanitize_text_field($_POST['delivery_address1'] ?? '');
    $new_address2      = sanitize_text_field($_POST['delivery_address2'] ?? '');
    $new_city          = sanitize_text_field($_POST['delivery_city'] ?? '');
    $new_state         = sanitize_text_field($_POST['delivery_state'] ?? '');
    $new_zip           = sanitize_text_field($_POST['delivery_zip'] ?? '');

    // Validate basics
    $errors = new WP_Error();

    if (empty($new_email) || ! is_email($new_email)) {
        $errors->add('email_invalid', 'Please enter a valid email.');
    }
    if (! empty($new_pass) || ! empty($new_pass2)) {
        if ($new_pass !== $new_pass2) {
            $errors->add('pass_mismatch', 'Passwords do not match.');
        } elseif (strlen($new_pass) < 8) {
            $errors->add('pass_short', 'Password must be at least 8 characters.');
        }
    }

    if (empty($errors->errors)) {
        // Update core user fields
        $userdata = [
          'ID'           => $user_id,
          'user_email'   => $new_email,
          'first_name'   => $new_first,
          'last_name'    => $new_last,
          'nickname'     => $new_nick,
          'display_name' => $new_disp,
        ];
        if (! empty($new_pass)) {
            $userdata['user_pass'] = $new_pass;
        }

        $updated = wp_update_user($userdata);

        if (is_wp_error($updated)) {
            $notice = $updated->get_error_message();
            $notice_class = 'error';
        } else {
            // Update meta
            if ($new_institution !== '') {
                update_user_meta($user_id, 'institution', $new_institution);
            }
            if ($new_system      !== '') {
                update_user_meta($user_id, 'home_system', $new_system);
            }
            if ($new_loc_code    !== '') {
                update_user_meta($user_id, 'address_loc_code', $new_loc_code);
            }
            if ($new_oclc        !== '') {
                update_user_meta($user_id, 'oclc_symbol', $new_oclc);
            }
            update_user_meta($user_id, 'work_phone', $new_work_phone);
            update_user_meta($user_id, 'additional_email', $new_alt_email);
            update_user_meta($user_id, 'delivery_address1', $new_address1);
            update_user_meta($user_id, 'delivery_address2', $new_address2);
            update_user_meta($user_id, 'delivery_city', $new_city);
            update_user_meta($user_id, 'delivery_state', $new_state);
            update_user_meta($user_id, 'delivery_zip', $new_zip);

            // Refresh local vars for re-render
            $first_name       = $new_first;
            $last_name        = $new_last;
            $nickname         = $new_nick;
            $display_name     = $new_disp;
            $email            = $new_email;
            $institution      = $new_institution;
            $home_system      = $new_system;
            $work_phone       = $new_work_phone;
            $alt_email        = $new_alt_email;
            $loc_code         = $new_loc_code;
            $oclc_symbol      = $new_oclc;
            $delivery_address1 = $new_address1;
            $delivery_address2 = $new_address2;
            $city             = $new_city;
            $state            = $new_state;
            $zip              = $new_zip;

            $notice = 'Profile updated successfully.';
            $notice_class = 'success';
            // Reload all values from the database to reflect current state
            $institution      = get_user_meta($user_id, 'institution', true);
            $home_system      = get_user_meta($user_id, 'home_system', true);
            $loc_code         = get_user_meta($user_id, 'address_loc_code', true);
            $oclc_symbol      = get_user_meta($user_id, 'oclc_symbol', true);
            $delivery_address1 = get_user_meta($user_id, 'delivery_address1', true);
            $delivery_address2 = get_user_meta($user_id, 'delivery_address2', true);
            $city             = get_user_meta($user_id, 'delivery_city', true);
            $state            = get_user_meta($user_id, 'delivery_state', true);
            $zip              = get_user_meta($user_id, 'delivery_zip', true);
            $work_phone       = get_user_meta($user_id, 'work_phone', true);
            $alt_email        = get_user_meta($user_id, 'additional_email', true);
        }
    } else {
        $notice = implode(' ', $errors->get_error_messages());
        $notice_class = 'error';
    }
}
?>

<div class="LibProfile_Form">
  <?php if (! empty($notice)) : ?>
    <div class="notice <?php echo esc_attr($notice_class); ?>"><?php echo esc_html($notice); ?></div>
  <?php endif; ?>

  <form method="post">
    <?php wp_nonce_field('update_wp_profile', 'wp_profile_nonce'); ?>

    <div class="section-card">
      <h4>Account</h4>
      <div class="form-section">
        <div class="form-group">
          <label>Username</label>
          <div class="pill"><?php echo h($username); ?></div>
          <div class="helper">Usernames cannot be changed.</div>
        </div>

        <div class="form-group">
          <label for="email">Email (required)</label>
          <input type="email" id="email" name="email" value="<?php echo h($email); ?>">
        </div>

        <div class="form-group">
          <label for="first_name">First Name</label>
          <input type="text" id="first_name" name="first_name" value="<?php echo h($first_name); ?>">
        </div>

        <div class="form-group">
          <label for="last_name">Last Name</label>
          <input type="text" id="last_name" name="last_name" value="<?php echo h($last_name); ?>">
        </div>

        <div class="form-group">
          <label for="new_password">New Password</label>
          <input type="password" id="new_password" name="new_password" autocomplete="new-password" placeholder="Set New Password">
          <input type="password" id="new_password_confirm" name="new_password_confirm" autocomplete="new-password" placeholder="Confirm New Password">
          <div class="helper">Leave blank to keep your current password.</div>
        </div>
      </div>
    </div>

    <div class="section-card">
      <h4>Contact Info</h4>
      <div class="form-section">
        <div class="form-group">
          <label for="work_phone">Work Phone</label>
          <input type="tel" id="work_phone" name="work_phone" value="<?php echo h($work_phone); ?>">
        </div>

        <div class="form-group">
          <label for="additional_email">Additional Email</label>
          <input type="email" id="additional_email" name="additional_email" value="<?php echo h($alt_email); ?>">
        </div>
      </div>
    </div>

<div class="section-card">
  <h4>Organization</h4>
  <div class="form-section">
    <div class="form-group">
      <label>Institution</label>
      <div class="form-control-static"><?php echo h($institution); ?></div>
    </div>
    <div class="form-group">
      <label>Home Library System</label>
      <div class="form-control-static"><?php echo h($home_system); ?></div>
    </div>
    <div class="form-group">
      <label>LOC Location Code</label>
      <div class="form-control-static"><?php echo h($loc_code); ?></div>
    </div>
    <div class="form-group">
      <label>OCLC Symbol</label>
      <div class="form-control-static"><?php echo h($oclc_symbol); ?></div>
    </div>
  </div>
</div>



    <div class="section-card">
      <h4>Delivery Address</h4>
      <div class="form-section">
        <div class="form-group">
          <label for="delivery_address1">Delivery Street Address Line 1</label>
          <input type="text" id="delivery_address1" name="delivery_address1" value="<?php echo h($delivery_address1); ?>">
        </div>

        <div class="form-group">
          <label for="delivery_address2">Delivery Street Address Line 2</label>
          <input type="text" id="delivery_address2" name="delivery_address2" value="<?php echo h($delivery_address2); ?>">
        </div>

        <div class="form-group">
          <label for="delivery_city">City</label>
          <input type="text" id="delivery_city" name="delivery_city" value="<?php echo h($city); ?>">
        </div>

        <div class="form-group">
          <label for="delivery_state">State</label>
          <input type="text" id="delivery_state" name="delivery_state" value="<?php echo h($state); ?>">
        </div>

        <div class="form-group">
          <label for="delivery_zip">Zip Code</label>
          <input type="text" id="delivery_zip" name="delivery_zip" value="<?php echo h($zip); ?>">
        </div>
      </div>
    </div>

    <div class="actions">
      <input class="btn-primary" type="submit" value="Save Profile">
    </div>
  </form>
</div>