<?php

namespace Premium_NewUserApproveZapier;

// Exit if accessed directly
if (!defined("ABSPATH")) {
    exit();
}

use WP_REST_Server;
if (!class_exists("RestRoutes")) {
    class RestRoutes
    {
        private static $_instance;

        /**
         * @version 1.0
         * @since 2.1
         */
        public static function get_instance()
        {
            if (self::$_instance == null) {
                self::$_instance = new self();
            }

            return self::$_instance;
        }

        /**
         * @version 1.0
         * @since 2.1
         */
        public function __construct()
        {
            add_action("rest_api_init", [$this, "register_routes"]);
        }

        /**
         * @version 1.0
         * @since 2.1
         */
        public function register_routes()
        {
            register_rest_route("nua-zapier", "/v1/auth", [
                "methods" => WP_REST_Server::EDITABLE,
                "callback" => [$this, "authenticate"],
                // 'permission_callback' => array($this, 'nua_zapier_permission_callback')
                "permission_callback" => "__return_true",
            ]);

            register_rest_route("nua-zapier", "/v1/user-approved", [
                "methods" => WP_REST_Server::EDITABLE,
                "callback" => [$this, "user_approved"],
                "permission_callback" => "__return_true",
            ]);

            register_rest_route("nua-zapier", "/v1/user-denied", [
                "methods" => WP_REST_Server::EDITABLE,
                "callback" => [$this, "user_denied"],
                "permission_callback" => "__return_true",
            ]);

            register_rest_route("nua-zapier", "/v1/user-invcode", [
                "methods" => WP_REST_Server::EDITABLE,
                "callback" => [$this, "user_invcode"],
                "permission_callback" => "__return_true",
            ]);

            register_rest_route("nua-zapier", "/v1/user-pending", [
                "methods" => WP_REST_Server::EDITABLE,
                "callback" => [$this, "user_pending"],
                "permission_callback" => "__return_true",
            ]);

            register_rest_route("nua-zapier", "/v1/user-whitelisted", [
                "methods" => WP_REST_Server::EDITABLE,
                "callback" => [$this, "user_whitelisted"],
                "permission_callback" => "__return_true",
            ]);

            register_rest_route("nua-zapier", "/v1/user-approved-via-role", [
                "methods" => WP_REST_Server::EDITABLE,
                "callback" => [$this, "user_approved_via_role"],
                "permission_callback" => "__return_true",
            ]);
        }

        /**
         * @version 1.0
         * @since 2.1
         */
        public static function api_key()
        {
            return get_option("nua_api_key");
        }

        /**
         * @version 1.0
         * @since 2.1
         */
        public function authenticate($request)
        {
            $api_key = $request->get_param("api_key");

            if ($api_key == $this->api_key()) {
                return new \WP_REST_Response(true, 200);
            }

            if ($api_key == null) {
                return new \WP_Error(
                    400,
                    __("Required Parameter Missing", "new-user-approve"),
                    "api_key required"
                );
            }

            if ($api_key != $this->api_key()) {
                return new \WP_Error(
                    400,
                    __("Invalid API Key", "new-user-approve"),
                    "invalid api_key"
                );
            }
        }

        public function user_whitelisted($request)
        {
            $api_key = $request->get_param("api_key");

            if ($api_key == null) {
                return new \WP_Error(
                    400,
                    __("Required Parameter Missing", "new-user-approve"),
                    "api_key required"
                );
            }

            if ($api_key != $this->api_key()) {
                return new \WP_Error(
                    400,
                    __("Invalid API Key", "new-user-approve"),
                    "invalid api_key"
                );
            }

            if ($api_key == $this->api_key()) {
                return $this->user_data("nua_user_whitelisted");
            }
        }

        public function user_pending($request)
        {
            $api_key = $request->get_param("api_key");

            if ($api_key == null) {
                return new \WP_Error(
                    400,
                    __("Required Parameter Missing", "new-user-approve"),
                    "api_key required"
                );
            }

            if ($api_key != $this->api_key()) {
                return new \WP_Error(
                    400,
                    __("Invalid API Key", "new-user-approve"),
                    "invalid api_key"
                );
            }

            if ($api_key == $this->api_key()) {
                return $this->user_data("nua_user_pending");
            }
        }

        public function user_invcode($request)
        {
            $api_key = $request->get_param("api_key");

            if ($api_key == null) {
                return new \WP_Error(
                    400,
                    __("Required Parameter Missing", "new-user-approve"),
                    "api_key required"
                );
            }

            if ($api_key != $this->api_key()) {
                return new \WP_Error(
                    400,
                    __("Invalid API Key", "new-user-approve"),
                    "invalid api_key"
                );
            }

            if ($api_key == $this->api_key()) {
                return $this->user_data("nua_user_invcode");
            }
        }

        public function user_approved($request)
        {
            $api_key = $request->get_param("api_key");

            if ($api_key == null) {
                return new \WP_Error(
                    400,
                    __("Required Parameter Missing", "new-user-approve"),
                    "api_key required"
                );
            }

            if ($api_key != $this->api_key()) {
                return new \WP_Error(
                    400,
                    __("Invalid API Key", "new-user-approve"),
                    "invalid api_key"
                );
            }

            if ($api_key == $this->api_key()) {
                return $this->user_data("nua_user_approved");
            }
        }

        public function user_approved_via_role($request)
        {
            $api_key = $request->get_param("api_key");

            if ($api_key == null) {
                return new \WP_Error(
                    400,
                    __("Required Parameter Missing", "new-user-approve"),
                    "api_key required"
                );
            }

            if ($api_key != $this->api_key()) {
                return new \WP_Error(
                    400,
                    __("Invalid API Key", "new-user-approve"),
                    "invalid api_key"
                );
            }

            if ($api_key == $this->api_key()) {
                return $this->user_data("nua_user_approved_via_role");
            }
        }

        public function user_denied($request)
        {
            $api_key = $request->get_param("api_key");

            if ($api_key == null) {
                return new \WP_Error(
                    400,
                    __("Required Parameter Missing", "new-user-approve"),
                    "api_key required"
                );
            }

            if ($api_key != $this->api_key()) {
                return new \WP_Error(
                    400,
                    __("Invalid API Key", "new-user-approve"),
                    "invalid api_key"
                );
            }

            if ($api_key == $this->api_key()) {
                return $this->user_data("nua_user_denied");
            }
        }

        public function user_data($option_name)
        {
            // data migrating,  to make compatible with previous NUA version
            if (!get_option("nua_zapier_option_status")) {
                \Premium_NewUserApproveZapier\User::get_instance()->nua_zap_compatible_legacy_options();
                update_option(
                    "nua_zapier_option_status",
                    NUA_ZAPIER_OPTION_STATUS
                );
            }

            $user_data = premium_get_users_by_nua_zap($option_name);

            if ($user_data) {
                $data = [];

                $time_key = "nua_user_pending";

                if ($option_name == "nua_user_approved") {
                    $time_key = "approval_time";
                } elseif ($option_name == "nua_user_denied") {
                    $time_key = "denial_time";
                } elseif ($option_name == "nua_user_invcode") {
                    $time_key = "invitation_code";
                } elseif ($option_name == "nua_user_whitelisted") {
                    $time_key = "whitelisted_domain";
                } elseif ($option_name == "nua_user_approved_via_role") {
                    $time_key = "user_role";
                }

                foreach ($user_data as $key => $value) {
                    $user_id = $value["user_id"];

                    $user = get_userdata($user_id);
                    $time_val = gmdate(DATE_ISO8601, $value["time"]);

                    if ("nua_user_invcode" == $option_name) {
                        $inv_code_id = get_user_meta(
                            $user->ID,
                            "nua_invcode_used",
                            true
                        );
                        $time_val = get_the_title($inv_code_id);
                    } elseif ("nua_user_whitelisted" == $option_name) {
                        $time_val = get_user_meta(
                            $user->ID,
                            "nua_wl_domain_used",
                            true
                        );
                    }
                    // else if ( 'nua_user_approved' == $option_name && ( !empty(get_user_meta( $user->ID, 'nua_invcode_used', true )) || !empty(get_user_meta( $user->ID, 'nua_wl_domain_used', true )) || !empty(get_user_meta($user_id, 'nua_user_role_based_approved', true)) ) ) {
                    // 		// user is auto approved through invitation code or whitelist
                    // 	   continue;
                    // }
                    elseif ("nua_user_approved_via_role" == $option_name) {
                        $time_val = get_user_meta(
                            $user->ID,
                            "nua_user_role_based_approved",
                            true
                        );
                    }

                    $data[] = [
                        "id" => $value["id"],
                        "user_login" => $user->user_login,
                        "user_nicename" => $user->user_nicename,
                        "user_email" => $user->user_email,
                        "user_registered" => gmdate(
                            DATE_ISO8601,
                            strtotime($user->user_registered)
                        ),
                        $time_key => $time_val,
                    ];
                    $data = apply_filters(
                        "nua_zapier_data_fields",
                        $data,
                        $user
                    );
                }

                return apply_filters("{$option_name}_zapier", $data);
            }
        }
        public function nua_zapier_permission_callback($request)
        {
            $api_key = $request->get_param("api_key");

            if ($api_key === null || $api_key !== $this->api_key()) {
                return false;
            }

            return apply_filters("zapier_api_permission", true, $request);
        }
    }
}

\Premium_NewUserApproveZapier\RestRoutes::get_instance();
