<?php

// Exit if accessed directly
if (!defined("ABSPATH")) {
    exit();
}

class Nua_Settings_API
{
    public static $instance;

    private $option_group = "new_user_approve_options_group";

    private $option_page = "new_user_approve";

    private $screen_name = "new-user-approve-admin";

    public $option_key = "new_user_approve_options";

    public static function instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new Nua_Settings_API();
        }
        return self::$instance;
    }

    public function __construct()
    {
        // add_action('init', array($this, 'maybe_migrate_legacy_invitation_setting'));
        add_action("rest_api_init", [$this, "register_user_routes"]);
    }

    public function register_user_routes()
    {
        register_rest_route("nua-request", "/v1/general-settings", [
            "methods" => WP_REST_Server::EDITABLE,
            "callback" => [$this, "general_settings"],
            "permission_callback" => [
                $this,
                "nua_settings_api_permission_callback",
            ],
            "args" => [
                "method" => [
                    "required" => true,
                    "validate_callback" => function ($param, $request, $key) {
                        // Validate filter_by parameter
                        return is_string($param) && !empty($param);
                    },
                ],
            ],
        ]);

        // help settings
        register_rest_route("nua-request", "/v1/help-settings", [
            "methods" => "GET",
            "callback" => [$this, "help_settings"],
            "permission_callback" => [
                $this,
                "nua_settings_api_permission_callback",
            ],
        ]);
    }

    public function general_settings($request)
    {
        // Nonce verification
        $nonce = $request->get_header("X-WP-Nonce");
        if (!wp_verify_nonce($nonce, "wp_rest")) {
            return new WP_Error(
                "rest_forbidden",
                __("Invalid nonce.", "new-user-approve"),
                ["status" => 403]
            );
        }
        // $method = isset($_GET['method']) && !empty($_GET['method']) ? sanitize_text_field($_GET['method']) : '';
        $method = $request->get_param("method");
        if ($method === "get") {
            $general_settings = $this->get_general_settings();

            return [
                "status" => "success",
                "data" => $general_settings,
            ];
        }

        if ($method === "update") {
            $general_settings = $request->get_json_params();

            $sanitized_settings = $this->sanitize($general_settings);

            update_option($this->option_key, $sanitized_settings);
            return [
                "status" => "success",
                "method" => $sanitized_settings,
            ];
        }
    }

    public function get_general_settings()
    {
        $invitation_code = $this->option_invitation_code();
        return [
            "nua_free_invitation" =>
                $invitation_code === "enable" ? "enable" : false,
        ];
    }

    public function help_settings($request)
    {
        // Nonce verification
        $nonce = $request->get_header("X-WP-Nonce");
        if (!wp_verify_nonce($nonce, "wp_rest")) {
            return new WP_Error(
                "rest_forbidden",
                __("Invalid nonce.", "new-user-approve"),
                ["status" => 403]
            );
        }

        $diagnostics_options = nua_opt_diagnostics();
        return [
            "status" => "success",
            "data" => $diagnostics_options,
        ];
    }

    /**
     * callback of invitation code api for Validation and Permission
     * @param $request (object)
     * @return ( bool)
     *
     */
    public function nua_settings_api_permission_callback($request)
    {
        $current_user = wp_get_current_user();
        $cap = apply_filters("new_user_approve_settings_api_cap", "edit_users");

        if (!is_user_logged_in()) {
            return new WP_Error(
                "rest_forbidden",
                __(
                    "Non-logged-in users do not have permission to access this endpoint.",
                    "new-user-approve"
                ),
                ["status" => 403]
            );
        }

        if (
            !in_array("administrator", $current_user->roles) &&
            !current_user_can($cap)
        ) {
            return new WP_Error(
                "rest_forbidden",
                __(
                    "You do not have permission to access this endpoint.",
                    "new-user-approve"
                ),
                ["status" => 403]
            );
        }

        $permission = apply_filters("settings_api_permission", true, $request);
        return $permission;
    }

    public function option_key()
    {
        $options = get_option($this->option_key);
        return $options;
    }

    public function sanitize($input)
    {
        $current = get_option($this->option_key, []);

        if (
            isset($input["nua_settings_tab"]) &&
            "general" === $input["nua_settings_tab"]
        ) {
            if (
                isset($input["nua_free_invitation"]) &&
                in_array(
                    (string) $input["nua_free_invitation"],
                    ["enable", "1"],
                    true
                )
            ) {
                $current["nua_free_invitation"] = "enable";

                if (get_option("nua_free_invitation") !== false) {
                    delete_option("nua_free_invitation");
                }
            } else {
                unset($current["nua_free_invitation"]);
            }
        }

        $current = apply_filters("nua_input_sanitize_hook", $current, $input);
        return $current;
    }

    // public function option_invitation_code() {
    // 	$options = get_option( $this->option_key );

    // 	$invitation_code_invite = ( isset( $options['nua_invitation_code'] ) ) ? $options['nua_invitation_code'] : false;
    // 	return $invitation_code_invite;
    // }

    public function option_invitation_code()
    {
        $options = get_option($this->option_key);

        // If setting already exists in the new version, just return it
        if (isset($options["nua_free_invitation"])) {
            return $options["nua_free_invitation"];
        }
        // Check for the legacy option
        $legacy_option = get_option("nua_free_invitation");

        // If legacy option is enabled, migrate it to new version
        if ($legacy_option === "enable") {
            if (!is_array($options)) {
                $options = [];
            }

            $options["nua_free_invitation"] = "enable";
            //  Save to new DB structure
            update_option($this->option_key, $options);
            // delete the old option to clean up
            delete_option("nua_free_invitation");
            return "enable";
        }

        return false;
    }
}
// phpcs:ignore
function nua_settings_API()
{
    return Nua_Settings_API::instance();
}

nua_settings_API();
