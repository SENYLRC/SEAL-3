<?php

// Exit if accessed directly
if (!defined("ABSPATH")) {
    exit();
}

if (!class_exists("PW_New_User_Approve")) {
    class PW_New_User_Approve
    {
        /**
         * The only instance of PW_New_User_Approve.
         *
         * @var PW_New_User_Approve
         */
        private static $instance;

        public $email_tags;

        /**
         * Returns the main instance.
         *
         * @return PW_New_User_Approve
         */
        public static function instance()
        {
            if (!isset(self::$instance)) {
                self::$instance = new PW_New_User_Approve();
                self::$instance->includes();
                self::$instance->email_tags = new NUA_Email_Template_Tags();
            }

            return self::$instance;
        }

        private function __construct()
        {
            // Load up the localization file if we're using WordPress in a different language
            // Just drop it in this plugin's "localization" folder and name it "new-user-approve-[value in wp-config].mo"
            // load_plugin_textdomain( 'new-user-approve', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
            register_activation_hook(__FILE__, [$this, "activation"]);
            register_deactivation_hook(__FILE__, [$this, "deactivation"]);
            // Actions
            add_action("admin_head", [$this, "change_background_color"]);
            add_action("wp_loaded", [$this, "admin_loaded"]);
            add_action("rightnow_end", [$this, "dashboard_stats"]);
            add_action(
                "user_register",
                [$this, "delete_new_user_approve_transient"],
                11
            );
            add_action(
                "new_user_approve_approve_user",
                [$this, "delete_new_user_approve_transient"],
                11
            );
            add_action(
                "new_user_approve_deny_user",
                [$this, "delete_new_user_approve_transient"],
                11
            );
            add_action("deleted_user", [
                $this,
                "delete_new_user_approve_transient",
            ]);
            add_action("admin_enqueue_scripts", [$this, "nua_admin_scripts"]);
            //add_action( 'register_post', array( $this, 'request_admin_approval_email' ), 10, 3 );
            add_action("register_post", [$this, "create_new_user"], 10, 3);
            add_action("woocommerce_created_customer", [
                $this,
                "nua_welcome_email_woo_new_user",
            ]);

            add_action("lostpassword_post", [$this, "lost_password"], 99, 2);
            add_action("user_register", [$this, "add_user_status"]);
            add_action("user_register", [
                $this,
                "request_admin_approval_email_2",
            ]);
            add_action("new_user_approve_approve_user", [
                $this,
                "approve_user",
            ]);
            add_action("new_user_approve_deny_user", [$this, "deny_user"]);
            add_action("new_user_approve_deny_user", [
                $this,
                "update_deny_status",
            ]);
            add_action("admin_init", [$this, "nua_init_admin_functions"]);

            add_action("wp_login", [$this, "login_user"], 10, 2);
            // Filters
            add_filter("wp_authenticate_user", [$this, "authenticate_user"]);
            add_filter(
                "registration_errors",
                [$this, "show_user_pending_message"],
                99
            );
            add_filter("login_message", [$this, "welcome_user"]);
            add_filter(
                "new_user_approve_validate_status_update",
                [$this, "validate_status_update"],
                10,
                3
            );
            add_filter("shake_error_codes", [$this, "failure_shake"]);
            add_filter("woocommerce_registration_auth_new_customer", [
                $this,
                "disable_woo_auto_login",
            ]);
            add_action(
                "woocommerce_checkout_order_processed",
                [$this, "disable_woo_auto_login_on_checkout"],
                10,
                0
            );
            add_action("init", [$this, "update_admin_notice"]);
            add_action("admin_menu", [$this, "admin_menu_link"], 5);
            add_action("plugins_loaded", [$this, "wpdocs_load_textdomain"]);
            //compatibility with membership pro plugin.
            add_filter(
                "pmpro_setup_new_user",
                [$this, "allow_pmpro_setup_user"],
                20,
                4
            );
            add_action(
                "pmpro_after_checkout",
                [$this, "logout_after_pmpro_registration"],
                20
            );
            add_action(
                "the_content",
                [$this, "nua_show_pending_user_message"],
                20
            );
        }

        public function allow_pmpro_setup_user(
            $proceed,
            $user_id,
            $user_data,
            $level
        ) {
            return true;
        }

        public function logout_after_pmpro_registration($user_id)
        {
            if (get_current_user_id() === (int) $user_id) {
                wp_logout();
                wp_redirect(
                    add_query_arg("nua_status", "pending", wp_get_referer())
                );
                exit();
            }
        }

        public function nua_show_pending_user_message($content)
        {
            if (
                isset($_GET["nua_status"]) &&
                $_GET["nua_status"] === "pending"
            ) {
                $message =
                    '<div class="nua-pending-message" style="background:#00a6361f;padding:15px;border-left:4px solid #00a636;margin-bottom:20px;">' .
                    __(
                        "Thank you for registering. Your account is currently pending approval. Please wait for admin approval before attempting to log in.",
                        "new-user-approve"
                    ) .
                    "</div>";

                return $message . $content;
            }

            return $content;
        }

        public function wpdocs_load_textdomain()
        {
            load_plugin_textdomain(
                "new-user-approve",
                false,
                dirname(plugin_basename(__FILE__)) . "/languages"
            );
        }

        public function nua_init_admin_functions()
        {
            $this->verify_settings();
            $this->nua_register_caps();
        }

        private function get_caps()
        {
            return [
                "nua_main_menu",
                "nua_users_cap",
                "nua_view_invitation_tab",
                "nua_auto_approve_cap",
                "nua_integration_cap",
                "nua_settings_cap",
            ];
        }

        public function nua_register_caps()
        {
            // List of all plugin-specific capabilities
            $role = get_role("administrator");
            if ($role) {
                foreach ($this->get_caps() as $cap) {
                    if (!$role->has_cap($cap)) {
                        $role->add_cap($cap);
                    }
                }
            }
        }

        public function admin_menu_link()
        {
            $hook = add_menu_page(
                __("New User Approve", "new-user-approve"),
                __("New User Approve", "new-user-approve"),
                "nua_main_menu",
                "new-user-approve-admin",
                [$this, "empty_admin_page"],
                "dashicons-nua-main"
            );

            add_action("admin_head-" . $hook, [
                $this,
                "admin_custom_icon_style",
            ]);
        }
        public function empty_admin_page()
        {
            echo '<div class="wrap"></div>';
        }

        public function admin_custom_icon_style()
        {
            echo '<style>
				.dashicons-nua-main:before {
					content: "\e900";
					font-family: "dashicons";
				}
			</style>';
        }
        public function get_plugin_url()
        {
            return plugin_dir_url(__FILE__);
        }

        public function get_plugin_dir()
        {
            return plugin_dir_path(__FILE__);
        }

        /**
         * Include required files
         *
         * @access private
         * @since 1.4
         * @since 2.1 `required` zapier.php
         * @return void
         */
        private function includes()
        {
            require_once $this->get_plugin_dir() . "includes/email-tags.php";
            require_once $this->get_plugin_dir() . "includes/messages.php";
            require_once $this->get_plugin_dir() .
                "includes/invitation-code.php";
            require_once $this->get_plugin_dir() . "includes/zapier/zapier.php";
        }

        /**
         * Require a minimum version of WordPress on activation
         *
         * @uses register_activation_hook
         */
        public function activation()
        {
            global $wp_version;
            $min_wp_version = "3.5.1";
            // translators: %s is for wordpress version
            $exit_msg = sprintf(
                __(
                    "New User Approve requires WordPress %s or newer.",
                    "new-user-approve"
                ),
                $min_wp_version
            );
            if (version_compare($wp_version, $min_wp_version, "<")) {
                exit(esc_html($exit_msg));
            }
            // since the right version of WordPress is being used, run a hook
            do_action("new_user_approve_activate");
        }

        /**
         * @uses register_deactivation_hook
         */
        public function deactivation()
        {
            do_action("new_user_approve_deactivate");
        }

        /**
         * Verify settings upon activation
         *
         * @uses admin_init
         */
        public function verify_settings()
        {
            // make sure the membership setting is turned on
            if (get_option("users_can_register") != 1) {
                add_action("admin_notices", [$this, "admin_notices"]);
            }
        }

        public function change_background_color()
        {
            ?>
		<style> 
			#wpwrap { background-color:#F4F4F4; }
		</style>

			<?php
        }

        /**
         * Update admin notice settings if clicks on hide.
         */
        public function update_admin_notice()
        {
            if (
                isset($_GET["notice-nounce"]) &&
                wp_verify_nonce($_GET["notice-nounce"], "nua_notice_nounce")
            ) {
                // if the user isn't an admin, definitely don't show the notice
                if (!current_user_can("manage_options")) {
                    return;
                }
                // update the setting for the current user
                if (
                    isset($_GET["new-user-approve-settings-notice"]) &&
                    "1" == $_GET["new-user-approve-settings-notice"]
                ) {
                    $user_id = get_current_user_id();
                    add_user_meta(
                        $user_id,
                        "pw_new_user_approve_settings_notice",
                        "1",
                        true
                    );
                }
            }
        }

        /**
         * Show admin notice if the membership setting is turned off.
         */
        public function admin_notices()
        {
            $user_id = get_current_user_id();
            $show_notice = get_user_meta(
                $user_id,
                "pw_new_user_approve_settings_notice"
            );
            // one last chance to show the update
            $show_notice = apply_filters(
                "new_user_approve_show_membership_notice",
                $show_notice,
                $user_id
            );
            // Check that the user hasn't already clicked to ignore the message
            if (!$show_notice) {
                $notice_nounce = wp_create_nonce("nua_notice_nounce");
                echo '<div class="error"><p>';
                printf(
                    wp_kses_post(
                        'The Membership setting must be turned on in order for the New User Approve to work correctly. <a href="%1$s">Update in settings</a>. | <a href="%2$s">Hide Notice</a>'
                    ),
                    esc_url(admin_url("options-general.php")),
                    esc_url(
                        add_query_arg([
                            "new-user-approve-settings-notice" => 1,
                            "notice-nounce" => $notice_nounce,
                        ])
                    )
                );
                echo "</p></div>";
            }
        }

        /**
         * Makes it possible to disable the user admin integration. Must happen after
         * WordPress is loaded.
         *
         * @uses wp_loaded
         */
        public function admin_loaded()
        {
            $user_admin_integration = apply_filters(
                "new_user_approve_user_admin_integration",
                true
            );
            if ($user_admin_integration) {
                require_once __DIR__ . "/includes/user-list.php";
                require_once __DIR__ . "/includes/end-points/users-api.php";
                require_once __DIR__ .
                    "/includes/end-points/invitation-code-api.php";
                require_once __DIR__ . "/includes/end-points/settings-api.php";
                require_once __DIR__ . "/includes/help.php";
            }
            $legacy_panel = apply_filters(
                "new_user_approve_user_admin_legacy",
                true
            );
            if ($legacy_panel) {
                require_once __DIR__ . "/includes/admin-approve.php";
            }
        }

        /**
         * Get the status of a user.
         *
         * @param int $user_id
         * @return string the status of the user
         */
        public function get_user_status($user_id)
        {
            $user_status = get_user_meta($user_id, "pw_user_status", true);
            if (empty($user_status)) {
                $user_status = "approved";
            }
            return $user_status;
        }

        /**
         * Update the status of a user. The new status must be either 'approve' or 'deny'.
         *
         * @param int $user
         * @param string $status
         *
         * @return boolean
         */
        public function update_user_status($user, $status)
        {
            $user_id = absint($user);
            if (!$user_id) {
                return false;
            }
            if (!in_array($status, ["approve", "deny"])) {
                return false;
            }
            $do_update = apply_filters(
                "new_user_approve_validate_status_update",
                true,
                $user_id,
                $status
            );
            if (!$do_update) {
                return false;
            }
            // where it all happens
            do_action("new_user_approve_" . $status . "_user", $user_id);
            do_action("new_user_approve_user_status_update", $user_id, $status);
            //update user count
            $user_statuses = $this->_get_user_statuses();
            update_option("nua_users_count", $user_statuses);

            return true;
        }

        /**
         * Get the valid statuses. Anything outside of the returned array is an invalid status.
         *
         * @return array
         */
        public function get_valid_statuses()
        {
            return ["pending", "approved", "denied"];
        }

        /**
         * Only validate the update if the status has been updated to prevent unnecessary update
         * and especially emails.
         *
         * @param bool $do_update
         * @param int $user_id
         * @param string $status either 'approve' or 'deny'
         */
        public function validate_status_update($do_update, $user_id, $status)
        {
            $current_status = pw_new_user_approve()->get_user_status($user_id);

            if ($status == "approve") {
                $new_status = "approved";
            } else {
                $new_status = "denied";
            }

            if ($current_status == $new_status) {
                $do_update = false;
            }
            return $do_update;
        }

        /**
         * The default message that is shown to a user depending on their status
         * when trying to sign in.
         *
         * @return string
         */
        public function default_authentication_message($status)
        {
            $message = "";

            if ($status == "pending") {
                $message = __(
                    "<strong>ERROR</strong>: Your account is still pending approval.",
                    "new-user-approve"
                );
                $message = apply_filters(
                    "new_user_approve_pending_error",
                    $message
                );
            } elseif ($status == "denied") {
                $message = __(
                    "<strong>ERROR</strong>: Your account has been denied access to this site.",
                    "new-user-approve"
                );
                $message = apply_filters(
                    "new_user_approve_denied_error",
                    $message
                );
            }

            $message = apply_filters(
                "new_user_approve_default_authentication_message",
                $message,
                $status
            );
            return $message;
        }

        /**
         * Determine if the user is good to sign in based on their status.
         *
         * @uses wp_authenticate_user
         * @param array $userdata
         */
        public function authenticate_user($userdata)
        {
            $status = $this->get_user_status($userdata->ID);
            if (empty($status)) {
                // the user does not have a status so let's assume the user is good to go
                return $userdata;
            }
            $message = false;
            switch ($status) {
                case "pending":
                    $pending_message = $this->default_authentication_message(
                        "pending"
                    );
                    $message = new WP_Error(
                        "pending_approval",
                        $pending_message
                    );
                    break;
                case "denied":
                    $denied_message = $this->default_authentication_message(
                        "denied"
                    );
                    $message = new WP_Error("denied_access", $denied_message);
                    break;
                case "approved":
                    $message = $userdata;
                    break;
            }
            return $message;
        }
        //updated function for user count fix
        public function _get_user_statuses($count = true)
        {
            global $wpdb;

            $statuses = [];

            foreach ($this->get_valid_statuses() as $status) {
                $total = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->users} u
                LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
                WHERE um.meta_key = %s AND um.meta_value = %s",
                        "pw_user_status",
                        $status
                    )
                );

                if ($count === true) {
                    $statuses[$status] = $total;
                } else {
                    // If count is false, return the list of users
                    $user_query = new WP_User_Query([
                        "meta_key" => "pw_user_status",
                        "meta_value" => $status,
                    ]);

                    $statuses[$status] = $user_query->get_results();
                }
            }
            return $statuses;
        }
        /**
         * Get a list of statuses with a count of users using WPQuery not transient
         */
        public function _get_users_by_status($count = true, $status = "")
        {
            $paged =
                isset($_REQUEST["paged"]) && !empty($_REQUEST["paged"])
                    ? absint($_REQUEST["paged"])
                    : 1;

            $statuses = [];
            foreach ($this->get_valid_statuses() as $status) {
                // Query the users table
                if ($status != "approved") {
                    // Query the users table
                    $query = [
                        "meta_key" => "pw_user_status",
                        "meta_value" => $status,
                        "count_total" => true,
                        "number" => 15,
                        "paged" => $paged,
                    ];
                } else {
                    // get all approved users and any user without a status
                    $query = [
                        "meta_query" => [
                            "relation" => "OR",
                            [
                                "key" => "pw_user_status",
                                "value" => "approved",
                                "compare" => "=",
                            ],
                            [
                                "key" => "pw_user_status",
                                "value" => "",
                                "compare" => "NOT EXISTS",
                            ],
                        ],
                        "count_total" => true,
                        "number" => 15,
                        "paged" => $paged,
                    ];
                }

                // if ( $count === false ) {
                //     unset( $query['count_total'] );
                //     unset( $query['number'] );
                // }

                $wp_user_search = new WP_User_Query($query);

                if ($count === true) {
                    $statuses[$status] = $wp_user_search->get_total();
                } else {
                    $statuses[$status] = $wp_user_search->get_results();
                }
            }
            return $statuses;
        }

        /**
         * Get a list of statuses with a count of users with that status
         */
        public function get_count_of_user_statuses()
        {
            $user_statuses = get_option(
                "new_user_approve_user_statuses_count",
                []
            );
            if (empty($user_statuses)) {
                $user_statuses = $this->_get_user_statuses();
                update_option(
                    "new_user_approve_user_statuses_count",
                    $user_statuses
                );
            }

            return $user_statuses;
        }

        public function get_user_statuses($status)
        {
            $nua_users_transient = apply_filters("nua_users_transient", true);
            $user_statuses = [];

            if (!$nua_users_transient) {
                $user_statuses = $this->_get_users_by_status(false, $status);
            } else {
                $user_statuses = get_transient(
                    "new_user_approve_user_statuses"
                );
                if (false === $user_statuses) {
                    $user_statuses = $this->_get_user_statuses(false);
                    set_transient(
                        "new_user_approve_user_statuses",
                        $user_statuses
                    );
                }
            }

            foreach ($this->get_valid_statuses() as $status) {
                $user_statuses[$status] = apply_filters(
                    "new_user_approve_user_status",
                    $user_statuses[$status],
                    $status
                );
            }
            return $user_statuses;
        }

        /**
         * Delete the transient storing all of the user statuses.
         *
         * @uses user_register
         * @uses deleted_user
         * @uses new_user_approve_approve_user
         * @uses new_user_approve_deny_user
         */
        public function delete_new_user_approve_transient()
        {
            delete_transient("new_user_approve_user_statuses");
        }

        /**
         * Display the stats on the WP dashboard. Will show 1 line with a count
         * of users and their status.
         *
         * @uses rightnow_end
         */

        /**
         * Add scripts and styles for New User Approve admin pages
         * @since 2.0 `nua-admin` enqueued for zapier
         */
        public function nua_admin_scripts()
        {
            $pages = [
                "new-user-approve-auto-approve",
                "nua-invitation-code",
                "new-user-approve",
                "new-user-approve-admin",
            ];

            wp_enqueue_style(
                "nua-fonts",
                plugins_url("/assets/css/nua-fonts.css", __FILE__),
                [],
                NUA_VERSION
            );
            if (isset($_GET["page"]) && in_array($_GET["page"], $pages)) {
                wp_enqueue_script("jquery");
                wp_enqueue_editor();
                wp_enqueue_media();
                wp_enqueue_script(
                    "new-user-approve-buildjs",
                    plugins_url("/build/index.js", __FILE__),
                    [
                        "wp-blocks",
                        "wp-block-library",
                        "wp-element",
                        "wp-i18n",
                        "wp-components",
                        "wp-editor",
                        "wp-data",
                    ],
                    NUA_VERSION,
                    true
                );
                wp_enqueue_style(
                    "new-user-approve-buildcss",
                    plugins_url("/build/style-index.css", __FILE__)
                );
                wp_enqueue_style(
                    "nua-admin-style",
                    plugins_url("/assets/css/nua-admin-style.css", __FILE__),
                    ["nua-fonts"],
                    NUA_VERSION
                );
                wp_localize_script("new-user-approve-buildjs", "siteDetail", [
                    "siteUrl" => get_site_url(),
                ]);
                wp_set_script_translations(
                    "new-user-approve-buildjs",
                    "new-user-approve",
                    plugin_dir_path(__FILE__) . "languages"
                );

                wp_localize_script("new-user-approve-buildjs", "NUARestAPI", [
                    "permalink_delimeter" => str_contains(
                        get_rest_url(),
                        "wp-json/"
                    )
                        ? "?"
                        : "&",
                    "request_to" => get_rest_url(null, "nua-request/v1"),
                    "save_invitation_codes" => get_rest_url(
                        null,
                        "nua-request/v1/save-invitation-codes"
                    ),
                    "get_invitation_code" => get_rest_url(
                        null,
                        "nua-request/v1/get-invitation-settings"
                    ),
                    "get_nua_invite_codes" => get_rest_url(
                        null,
                        "nua-request/v1/get-nua-codes"
                    ),
                    "get_remaining_uses" => get_rest_url(
                        null,
                        "nua-request/v1/get-remaining-uses"
                    ),
                    "get_total_uses" => get_rest_url(
                        null,
                        "nua-request/v1/get-total-uses"
                    ),
                    "get_expiry" => get_rest_url(
                        null,
                        "nua-request/v1/get-expiry"
                    ),
                    "get_status" => get_rest_url(
                        null,
                        "nua-request/v1/get-status"
                    ),
                    "get_invited_users" => get_rest_url(
                        null,
                        "nua-request/v1/get-invited-users"
                    ),
                    "update_invitation_code" => get_rest_url(
                        null,
                        "nua-request/v1/update-invitation-code"
                    ),
                    "status_update_invCode" => get_rest_url(
                        null,
                        "nua-request/v1/status-update-invCode"
                    ),
                    "delete_invCode" => get_rest_url(
                        null,
                        "nua-request/v1/delete-invCode"
                    ),
                    "general_settings" => get_rest_url(
                        null,
                        "nua-request/v1/general-settings"
                    ),
                    "help_settings" => get_rest_url(
                        null,
                        "nua-request/v1/help-settings"
                    ),
                    "recent_user" => get_rest_url(
                        null,
                        "nua-request/v1/recent-users"
                    ),
                    "update_users" => get_rest_url(
                        null,
                        "nua-request/v1/update-user"
                    ),
                    "get_all_users" => get_rest_url(
                        null,
                        "nua-request/v1/get-all-users"
                    ),
                    "get_approved_users" => get_rest_url(
                        null,
                        "nua-request/v1/get-approved-users"
                    ),
                    "get_pending_users" => get_rest_url(
                        null,
                        "nua-request/v1/get-pending-users"
                    ),
                    "get_denied_users" => get_rest_url(
                        null,
                        "nua-request/v1/get-denied-users"
                    ),
                    "get_approved_user_roles" => get_rest_url(
                        null,
                        "/nua-request/v1/get-approved-user-roles"
                    ),
                    "get_user_roles" => get_rest_url(
                        null,
                        "nua-request/v1/get-user-roles"
                    ),
                    "update_user_role" => get_rest_url(
                        null,
                        "nua-request/v1/update-user-role"
                    ),
                    "get_activity_log" => get_rest_url(
                        null,
                        "nua-request/v1/get-activity-log"
                    ),
                    "get_api_key" => get_rest_url(
                        null,
                        "nua-request/v1/get-api-key"
                    ),
                    "update_api_key" => get_rest_url(
                        null,
                        "nua-request/v1/update-api-key"
                    ),
                    "all_statuses_users" => get_rest_url(
                        null,
                        "nua-request/v1/get-all-statuses-users"
                    ),
                ]);

                $current_user = wp_get_current_user();
                wp_enqueue_script(
                    "nua-admin",
                    plugins_url("/assets/js/admin.js", __FILE__),
                    ["jquery"],
                    NUA_VERSION
                );
                wp_localize_script("nua-admin", "nuaAdmin", [
                    "info" => $current_user->user_nicename,
                    "nua_view_invitation_tab" => current_user_can(
                        "nua_view_invitation_tab"
                    ),
                    "nua_auto_approve_cap" => current_user_can(
                        "nua_auto_approve_cap"
                    ),
                    "nua_integration_cap" => current_user_can(
                        "nua_integration_cap"
                    ),
                    "nua_settings_cap" => current_user_can("nua_settings_cap"),
                    "nua_users_cap" => current_user_can("nua_users_cap"),
                ]);
            }
        }
        public function dashboard_stats()
        {
            $user_status = $this->get_count_of_user_statuses(); ?>
		<div>
			<p>
				<span style="font-weight:bold;">
					<a href="
					<?php echo wp_kses_post(
         apply_filters("new_user_approve_dashboard_link", "users.php")
     ); ?>
		">
			<?php esc_html_e("Users", "new-user-approve"); ?>
		</a>
				</span>:
				<?php foreach ($user_status as $status => $count) {
        print esc_html_e(ucwords($status), "new-user-approve") .
            "(" .
            esc_attr($count) .
            ")&nbsp;&nbsp;&nbsp;";
    } ?>
			</p>
		</div>
		<?php
        }

        /**
         * Send email to admin requesting approval.
         *
         * @param $user_login username
         * @param $user_email email address of the user
         */
        public function admin_approval_email($user_login, $user_email)
        {
            $default_admin_url = admin_url(
                "admin.php?page=new-user-approve-admin#/action=users/tab=pending-users"
            );
            $admin_url = apply_filters(
                "new_user_approve_admin_link",
                $default_admin_url
            );
            /* send email to admin for approval */
            $message = apply_filters(
                "new_user_approve_request_approval_message_default",
                nua_default_notification_message()
            );
            $message = nua_do_email_tags($message, [
                "context" => "request_admin_approval_email",
                "user_login" => $user_login,
                "user_email" => $user_email,
                "admin_url" => $admin_url,
            ]);
            $message = apply_filters(
                "new_user_approve_request_approval_message",
                $message,
                $user_login,
                $user_email
            );
            // translators: %s is for blogname of admin notify email subject
            $subject = sprintf(
                __("[%s] User Approval", "new-user-approve"),
                wp_specialchars_decode(get_option("blogname"), ENT_QUOTES)
            );
            $subject = apply_filters(
                "new_user_approve_request_approval_subject",
                $subject
            );
            $to = apply_filters("new_user_approve_email_admins", [
                get_option("admin_email"),
            ]);
            $to = array_unique($to);
            // send the mail
            wp_mail($to, $subject, $message, $this->email_message_headers());
        }

        /**
         * Send an email to the admin to request approval. If there are already errors,
         * just go back and let core do it's thing.
         *
         * @uses register_post
         * @param string $user_login
         * @param string $user_email
         * @param object $errors
         */
        public function request_admin_approval_email(
            $user_login,
            $user_email,
            $errors
        ) {
            if ($errors->get_error_code()) {
                return $errors;
            }
            $this->admin_approval_email($user_login, $user_email);
        }

        /**
         * Send an email to the admin to request approval.
         *
         * @uses user_register
         * @param int $user_id
         */
        public function request_admin_approval_email_2($user_id)
        {
            $user = new WP_User($user_id);
            $user_login = stripslashes($user->data->user_login);
            $user_email = stripslashes($user->data->user_email);
            $this->admin_approval_email($user_login, $user_email);
        }

        /**
         * Create a new user after the registration has been validated. Normally,
         * when a user registers, an email is sent to the user containing their
         * username and password. The email does not get sent to the user until
         * the user is approved when using the default behavior of this plugin.
         *
         * @uses register_post
         * @param string $user_login
         * @param string $user_email
         * @param object $errors
         */
        public function create_new_user($user_login, $user_email, $errors)
        {
            if ($errors->get_error_code()) {
                return;
            }
            // create the user
            $user_pass = wp_generate_password(12, false);
            $user_pass = apply_filters("nua_pass_create_new_user", $user_pass);
            $user_id = wp_create_user($user_login, $user_pass, $user_email);

            if (!$user_id) {
                // translators: %s is for admin email
                $errors->add(
                    "registerfail",
                    sprintf(
                        __(
                            '<strong>ERROR</strong>: Couldn&#8217;t register you... please contact the <a href="mailto:%s">webmaster</a> !'
                        ),
                        get_option("admin_email")
                    )
                );
            } else {
                // User Registeration welcome email
                $disable = apply_filters(
                    "nua_disable_welcome_email",
                    false,
                    $user_id
                );
                if (false === $disable) {
                    $message = nua_default_registeration_welcome_email();

                    $message = apply_filters(
                        "new_user_approve_welcome_user_message",
                        $message,
                        $user_email
                    );
                    // translators: %s is for blogname of wp  welcome email subject
                    $subject = sprintf(
                        __(
                            "Your registration is pending for approval - [%s]",
                            "new-user-approve"
                        ),
                        get_option("blogname")
                    );
                    $subject = apply_filters(
                        "new_user_approve_welcome_user_subject",
                        $subject
                    );

                    wp_mail(
                        $user_email,
                        $subject,
                        $message,
                        $this->email_message_headers()
                    );
                }
            }
        }

        public function nua_welcome_email_woo_new_user($customer_id)
        {
            $customer = new WC_Customer($customer_id);
            // pw_new_user_approve()->nua_invitation_status_code_field_validation();
            // nua_invitation_code()->nua_invitation_status_code_field_validation($user_login, $user_email, $errors);
            $user_email = $customer->get_email();

            $message = nua_default_registeration_welcome_email();

            $message = apply_filters(
                "new_user_approve_welcome_user_message",
                $message,
                $user_email
            );
            // translators: %s is for blogname of woo welcome email subject
            $subject = sprintf(
                __(
                    "Your registration is pending for approval - [%s]",
                    "new-user-approve"
                ),
                get_option("blogname")
            );
            $subject = apply_filters(
                "new_user_approve_welcome_user_subject",
                $subject
            );
            // disable new account email.
            add_filter(
                "woocommerce_email_enabled_customer_new_account",
                "__return_false"
            );
            $disable_welcome_email = apply_filters(
                "disable_welcome_email_woo_new_user",
                [$this, false]
            );
            if ($disable_welcome_email === true) {
                return;
            }

            wp_mail(
                $user_email,
                $subject,
                $message,
                $this->email_message_headers()
            );
        }

        /**
         * Determine whether a password needs to be reset.
         *
         * password should only be reset for users that:
         * * have never logged in
         * * are just approved for the first time
         *
         * @return boolean
         */
        public function do_password_reset($user_id)
        {
            // Default behavior is to reset password
            $do_password_reset = true;
            // Get the current user status. By default each user is given a pending
            // status when the user is created (with this plugin activated). If the
            // user was created while this plugin was not active, the user will not
            // have a status set.
            $user_status = get_user_meta($user_id, "pw_user_status");
            // if no status is set, don't reset password
            if (empty($user_status)) {
                $do_password_reset = false;
            }
            // if user has signed in, don't reset password
            $user_has_signed_in = get_user_meta(
                $user_id,
                "pw_new_user_approve_has_signed_in"
            );
            if ($user_has_signed_in) {
                $do_password_reset = false;
            }
            // for backward compatability
            $bypass_password_reset = apply_filters(
                "new_user_approve_bypass_password_reset",
                !$do_password_reset
            );
            return apply_filters(
                "new_user_approve_do_password_reset",
                !$bypass_password_reset
            );
        }

        /**
         * Admin approval of user
         *
         * @uses new_user_approve_approve_user
         */
        public function approve_user($user_id)
        {
            $user = new WP_User($user_id);
            wp_cache_delete($user->ID, "users");
            wp_cache_delete($user->data->user_login, "userlogins");
            // send email to user telling of approval
            $user_login = stripslashes($user->data->user_login);
            $user_email = stripslashes($user->data->user_email);
            // format the message
            $message = nua_default_approve_user_message();
            $message = nua_do_email_tags($message, [
                "context" => "approve_user",
                "user" => $user,
                "user_login" => $user_login,
                "user_email" => $user_email,
            ]);
            $message = apply_filters(
                "new_user_approve_approve_user_message",
                $message,
                $user
            );
            // translators: %s is for blogname of approved email subject
            $subject = sprintf(
                __("[%s] Registration Approved", "new-user-approve"),
                get_option("blogname")
            );
            $subject = apply_filters(
                "new_user_approve_approve_user_subject",
                $subject
            );
            // send the mail
            wp_mail(
                $user_email,
                $subject,
                $message,
                $this->email_message_headers()
            );
            //to update statuses count
            $this->update_users_statuses_count("approved", $user_id);

            // change usermeta tag in database to approved
            update_user_meta($user->ID, "pw_user_status", "approved");
            // update the status time.
            update_user_meta(
                $user->ID,
                "pw_user_status_time",
                gmdate("Y-m-d h:i:s")
            );
            do_action("new_user_approve_user_approved", $user);
        }

        /**
         * Send email to notify user of denial.
         *
         * @uses new_user_approve_deny_user
         */
        public function deny_user($user_id)
        {
            $user = new WP_User($user_id);

            $disable = apply_filters(
                "new_user_approve_denied_email",
                false,
                $user_id
            );
            if ($disable === false) {
                $user_id = $user->ID;

                $sessions = WP_Session_Tokens::get_instance($user_id);
                $sessions->destroy_all();

                // send email to user telling of denial
                $user_email = stripslashes($user->data->user_email);
                // format the message
                $message = nua_default_deny_user_message();
                $message = nua_do_email_tags($message, [
                    "context" => "deny_user",
                ]);
                $message = apply_filters(
                    "new_user_approve_deny_user_message",
                    $message,
                    $user
                );
                // translators: %s is for blogname of deined email subject
                $subject = sprintf(
                    __("[%s] Registration Denied", "new-user-approve"),
                    get_option("blogname")
                );
                $subject = apply_filters(
                    "new_user_approve_deny_user_subject",
                    $subject
                );
                // send the mail
                wp_mail(
                    $user_email,
                    $subject,
                    $message,
                    $this->email_message_headers()
                );
            }
        }

        /**
         * Update user status when denying user.
         *
         * @uses new_user_approve_deny_user
         */
        public function update_deny_status($user_id)
        {
            $user = new WP_User($user_id);
            //to update statuses count
            $this->update_users_statuses_count("denied", $user_id);
            // change usermeta tag in database to denied
            update_user_meta($user->ID, "pw_user_status", "denied");
            update_user_meta(
                $user->ID,
                "pw_user_status_time",
                gmdate("Y-m-d h:i:s")
            );

            do_action("new_user_approve_user_denied", $user);
        }

        public function email_message_headers()
        {
            $admin_email = get_option("admin_email");
            if (isset($_SERVER["SERVER_NAME"]) && empty($admin_email)) {
                $admin_email =
                    "support@" .
                    sanitize_text_field(wp_unslash($_SERVER["SERVER_NAME"]));
            }
            $from_name = get_option("blogname");
            $headers = ["From: \"{$from_name}\" <{$admin_email}>\n"];
            $headers = apply_filters("new_user_approve_email_header", $headers);
            return $headers;
        }

        /**
         * Display a message to the user after they have registered
         *
         * @uses registration_errors
         */
        public function show_user_pending_message($errors)
        {
            $nonce = "";
            if (wp_verify_nonce($nonce)) {
                return;
            }
            $disable_redirect = apply_filters(
                "nua_disable_redirect_to_field",
                false
            );
            if (!empty($_POST["redirect_to"]) && false === $disable_redirect) {
                // if a redirect_to is set, honor it
                wp_safe_redirect(wp_unslash($_POST["redirect_to"]));
                exit();
            }

            // if there is an error already, let it do it's thing
            if (
                !empty($errors) &&
                is_wp_error($errors) &&
                $errors->get_error_code()
            ) {
                return $errors;
            }
            $message = nua_default_registration_complete_message();
            $message = nua_do_email_tags($message, [
                "context" => "pending_message",
            ]);
            $message = apply_filters(
                "new_user_approve_pending_message",
                $message
            );
            $errors->add("registration_required", $message, "message");
            $success_message = __(
                "Registration successful.",
                "new-user-approve"
            );
            $success_message = apply_filters(
                "new_user_approve_registration_message",
                $success_message
            );

            if (function_exists("login_header")) {
                login_header(
                    __("Pending Approval", "new-user-approve"),
                    '<p class="message register">' . $success_message . "</p>",
                    $errors
                );
            }
            if (function_exists("login_footer")) {
                login_footer();
            }

            do_action(
                "new_user_approve_after_registration",
                $errors,
                $success_message
            );

            // an exit is necessary here so the normal process for user registration doesn't happen
            exit();
        }

        /**
         * Only give a user their password if they have been approved
         *
         * @uses lostpassword_post
         */
        public function lost_password($errors, $user_data)
        {
            $user_login = $user_data->user_login;
            if (empty($user_login)) {
                return;
            }

            $is_email = strpos(
                sanitize_text_field(wp_unslash($user_login)),
                "@"
            );

            if ($is_email === false) {
                $username = sanitize_user(wp_unslash($user_login));
                $user_data = get_user_by("login", trim($username));
            } else {
                $email = is_email(wp_unslash($user_login));
                $user_data = get_user_by("email", $email);
            }

            if (
                isset($user_data) &&
                is_object($user_data) &&
                $user_data->pw_user_status &&
                $user_data->pw_user_status != "approved"
            ) {
                $errors->add(
                    "unapproved_user",
                    __(
                        "<strong>ERROR</strong>: User has not been approved.",
                        "new-user-approve"
                    )
                );
            }
            return $errors;
        }

        /**
         * Add message to login page saying registration is required.
         *
         * @uses login_message
         * @param string $message
         * @return string
         */
        public function welcome_user($message)
        {
            if (!isset($_GET["action"])) {
                $welcome = nua_default_welcome_message();
                $welcome = nua_do_email_tags($welcome, [
                    "context" => "welcome_message",
                ]);
                $welcome = apply_filters(
                    "new_user_approve_welcome_message",
                    $welcome
                );
                if (!empty($welcome)) {
                    $message .=
                        '<p class="message register">' . $welcome . "</p>";
                }
            }

            $nonce = "";
            if (wp_verify_nonce($nonce)) {
                return;
            }
            if (
                isset($_GET["action"]) &&
                $_GET["action"] == "register" &&
                !$_POST
            ) {
                $instructions = nua_default_registration_message();
                $instructions = nua_do_email_tags($instructions, [
                    "context" => "registration_message",
                ]);
                $instructions = apply_filters(
                    "new_user_approve_register_instructions",
                    $instructions
                );
                if (!empty($instructions)) {
                    $message .=
                        '<p class="message register">' . $instructions . "</p>";
                }
            }

            return $message;
        }

        /**
         * Give the user a status
         *
         * @uses user_register
         * @param int $user_id
         */
        public function add_user_status($user_id)
        {
            $status = "pending";
            // This check needs to happen when a user is created in the admin
            if (
                isset($_REQUEST["action"]) &&
                "createuser" == $_REQUEST["action"]
            ) {
                $status = "approved";
            }
            $status = apply_filters(
                "new_user_approve_default_status",
                $status,
                $user_id
            );
            //update user count
            $this->update_users_statuses_count($status, $user_id);
            update_user_meta($user_id, "pw_user_status", $status);
            update_user_meta(
                $user_id,
                "pw_user_status_time",
                gmdate("Y-m-d h:i:s")
            );
        }

        /**
         * Add error codes to shake the login form on failure
         *
         * @uses shake_error_codes
         * @param $error_codes
         * @return array
         */
        public function failure_shake($error_codes)
        {
            $error_codes[] = "pending_approval";
            $error_codes[] = "denied_access";
            return $error_codes;
        }

        /**
         * After a user successfully logs in, record in user meta. This will only be recorded
         * one time. The password will not be reset after a successful login.
         *
         * @uses wp_login
         * @param $user_login
         * @param $user
         */
        public function login_user($user_login, $user = null)
        {
            if ($user != null && is_object($user)) {
                if (
                    !get_user_meta(
                        $user->ID,
                        "pw_new_user_approve_has_signed_in"
                    )
                ) {
                    add_user_meta(
                        $user->ID,
                        "pw_new_user_approve_has_signed_in",
                        time()
                    );
                }
            }
        }

        /**
         * Disable auto login for WooCommerce
         *
         * @return boolean
         */
        public function disable_woo_auto_login($new_customer)
        {
            return false;
        }
        /**
         * Disable auto login on WooCommerce checkout
         *
         */
        public function disable_woo_auto_login_on_checkout()
        {
            // destroying session when pending user trying to checkout
            $boolean = false;
            $boolean = apply_filters(
                "new_user_approve_woo_checkout_process_logout",
                $boolean
            );
            if ($boolean) {
                if (is_user_logged_in()) {
                    $user_id = get_current_user_id();
                    $user_status = get_user_meta(
                        $user_id,
                        "pw_user_status",
                        true
                    );
                    if ($user_status == "denied" || $user_status == "pending") {
                        wp_destroy_current_session();
                        wp_clear_auth_cookie();
                        wp_set_current_user(0);
                    }
                }
            }
        }

        /**
         * Update users statuses count
         *
         */
        public function update_users_statuses_count($new_status, $user_id)
        {
            $old_status = get_user_meta($user_id, "pw_user_status", true);

            if ($old_status == $new_status) {
                return;
            }

            $user_statuses = get_option(
                "new_user_approve_user_statuses_count",
                []
            );

            if (empty($user_statuses)) {
                $user_statuses = $this->_get_user_statuses();
            }

            foreach ($this->get_valid_statuses() as $status) {
                if (isset($user_statuses[$status]) && $old_status == $status) {
                    $count = $user_statuses[$status];
                    $user_statuses[$status] = $count - 1;
                } elseif (
                    isset($user_statuses[$status]) &&
                    $new_status == $status
                ) {
                    $count = $user_statuses[$status];
                    $user_statuses[$status] = $count + 1;
                }
            }
            update_option(
                "new_user_approve_user_statuses_count",
                $user_statuses
            );
        }
    }
}
