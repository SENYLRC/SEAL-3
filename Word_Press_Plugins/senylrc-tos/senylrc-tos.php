<?php
/**
 * Plugin Name: Terms of Service Agreement
 * Plugin URI: https://senylrc.org
 * Description: Adds a Terms of Service agreement checkbox to WordPress registration form.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://senylrc.org
 * License: GPL2
 */

// Add the Terms of Service checkbox to the registration form
function tos_add_registration_field() {
    ?>
    <p>
        <input type="checkbox" name="tos_agree" id="tos_agree" value="1" required>
        <label for="tos_agree">I agree to the <a href="/terms-of-service" target="_blank">Terms of Service</a></label>
    </p>
    <?php
}
add_action('register_form', 'tos_add_registration_field');

// Validate Terms of Service checkbox during registration
function tos_check_registration($errors, $sanitized_user_login, $user_email) {
    if (!isset($_POST['tos_agree']) || $_POST['tos_agree'] != '1') {
        $errors->add('tos_error', '<strong>ERROR</strong>: You must agree to the Terms of Service.');
    }
    return $errors;
}
add_filter('registration_errors', 'tos_check_registration', 10, 3);

// Enqueue styles if needed
function tos_enqueue_styles() {
    echo '<style>p label { display: inline; }</style>';
}
add_action('login_head', 'tos_enqueue_styles');
