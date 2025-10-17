<?php
if (!defined("ABSPATH")) {
    exit();
}

class Users_API
{
    public static $instance;

    public static function instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new Users_API();
        }
        return self::$instance;
    }

    public function __construct()
    {
        add_action("rest_api_init", [$this, "register_user_routes"]);
        // add_action('admin_init', array($this, 'get_activity_log'));
    }

    public function register_user_routes()
    {
        register_rest_route("nua-request", "/v1/recent-users", [
            "methods" => "GET",
            "callback" => [$this, "recent_users"],
            "permission_callback" => [
                $this,
                "nua_users_api_permission_callback",
            ],
        ]);

        register_rest_route("nua-request", "/v1/update-user", [
            "methods" => "POST",
            "callback" => [$this, "update_user"],
            "permission_callback" => [
                $this,
                "nua_users_api_permission_callback",
            ],
        ]);

        register_rest_route("nua-request", "/v1/get-all-users", [
            "methods" => "GET",
            "callback" => [$this, "get_all_users"],
            "permission_callback" => [
                $this,
                "nua_users_api_permission_callback",
            ],
        ]);

        register_rest_route("nua-request", "/v1/get-approved-users", [
            "methods" => "GET",
            "callback" => [$this, "get_approved_users"],
            "permission_callback" => [
                $this,
                "nua_users_api_permission_callback",
            ],
        ]);

        register_rest_route("nua-request", "/v1/get-pending-users", [
            "methods" => "GET",
            "callback" => [$this, "get_pending_users"],
            "permission_callback" => [
                $this,
                "nua_users_api_permission_callback",
            ],
        ]);

        register_rest_route("nua-request", "/v1/get-denied-users", [
            "methods" => "GET",
            "callback" => [$this, "get_denied_users"],
            "permission_callback" => [
                $this,
                "nua_users_api_permission_callback",
            ],
        ]);

        register_rest_route("nua-request", "/v1/get-approved-user-roles", [
            "methods" => "GET",
            "callback" => [$this, "get_approved_user_roles"],
            "permission_callback" => [
                $this,
                "nua_users_api_permission_callback",
            ],
        ]);

        register_rest_route("nua-request", "/v1/get-user-roles", [
            "methods" => "GET",
            "callback" => [$this, "get_user_roles"],
            "permission_callback" => [
                $this,
                "nua_users_api_permission_callback",
            ],
        ]);

        register_rest_route("nua-request", "/v1/get-activity-log", [
            "methods" => "GET",
            "callback" => [$this, "get_activity_log"],
            "permission_callback" => [
                $this,
                "nua_users_api_permission_callback",
            ],
        ]);

        register_rest_route("nua-request", "/v1/update-user-role", [
            "methods" => "POST",
            "callback" => [$this, "update_user_role"],
            "permission_callback" => [
                $this,
                "nua_users_api_permission_callback",
            ],
        ]);

        register_rest_route("nua-request", "/v1/get-api-key", [
            "methods" => "GET",
            "callback" => [$this, "get_api_key"],
            "permission_callback" => [
                $this,
                "nua_users_api_permission_callback",
            ],
        ]);

        register_rest_route("nua-request", "/v1/update-api-key", [
            "methods" => "POST",
            "callback" => [$this, "update_api_key"],
            "permission_callback" => [
                $this,
                "nua_users_api_permission_callback",
            ],
        ]);

        register_rest_route("nua-request", "/v1/get-all-statuses-users", [
            "methods" => "GET",
            "callback" => [$this, "get_all_statuses_users"],
            "permission_callback" => [
                $this,
                "nua_users_api_permission_callback",
            ],
        ]);
    }

    public function recent_users($request)
    {
        $nonce = $request->get_header("X-WP-Nonce");
        if (!wp_verify_nonce($nonce, "wp_rest")) {
            return new WP_Error(
                "rest_forbidden",
                __("Invalid nonce.", "new-user-approve"),
                ["status" => 403]
            );
        }

        $filter_by = sanitize_text_field(
            $request->get_param("filter_by") ?: "30 days ago"
        );
        $limit = (int) apply_filters("recent_users_limit", 5);
        $new_results = $this->nua_users_filter($filter_by, $limit);

        $users = [];
        $default_cols = [
            "user_login",
            "user_email",
            "user_registered",
            "nua_status",
            "actions",
        ];

        foreach ($new_results as $user) {
            $data = [
                "ID" => $user->ID,
                "user_login" => $user->user_login,
                "user_email" => $user->user_email,
                "user_registered" => get_date_from_gmt(
                    $user->user_registered,
                    "Y-m-d H:i:s"
                ),
                "nua_status" => pw_new_user_approve()->get_user_status(
                    $user->ID
                ),
            ];
            $users[] = (object) apply_filters(
                "nua_recent_user_data",
                $data,
                $user
            );
        }

        $extra_cols = !empty($users[0]) ? array_keys((array) $users[0]) : [];
        $extra_cols = array_filter($extra_cols, function ($col) {
            return $col !== "ID";
        });

        $columns = apply_filters(
            "nua_user_columns",
            array_merge($default_cols, $extra_cols)
        );

        return [
            "users" => $users,
            "totals" => count($users),
            "columns_order" => array_values(array_unique($columns)),
        ];
    }

    // updating user manually.
    public function update_user($request)
    {
        // Nonce Verification
        $nonce = $request->get_header("X-WP-Nonce");
        if (!wp_verify_nonce($nonce, "wp_rest")) {
            return new WP_Error(
                "rest_forbidden",
                __("Invalid nonce.", "new-user-approve"),
                ["status" => 403]
            );
        }

        if (
            isset($_SERVER["REQUEST_METHOD"]) &&
            $_SERVER["REQUEST_METHOD"] === "POST"
        ) {
            $postData = file_get_contents("php://input");
            $data = json_decode($postData, true);

            if ($data) {
                // Handle Bulk Users
                $user_ids = [];

                if (
                    isset($data["userIDs"]) &&
                    is_array($data["userIDs"]) &&
                    !empty($data["userIDs"])
                ) {
                    $user_ids = array_map("absint", $data["userIDs"]);
                } elseif (isset($data["userID"]) && !empty($data["userID"])) {
                    $user_ids[] = absint($data["userID"]); // fallback for single user
                } else {
                    return new \WP_Error(
                        400,
                        __("Incomplete Request", "new-user-approve"),
                        "Incomplete Request"
                    );
                }

                if (
                    !isset($data["status_label"]) ||
                    empty($data["status_label"])
                ) {
                    return new \WP_Error(
                        400,
                        __("Incomplete Request", "new-user-approve"),
                        "Incomplete Request"
                    );
                }

                $statuses = [
                    "approve" => "approved",
                    "deny" => "denied",
                ];

                $label = sanitize_text_field($data["status_label"]);
                $user_status = $statuses[$label];

                foreach ($user_ids as $user_id) {
                    if ($user_status === "approved") {
                        pw_new_user_approve()->approve_user($user_id);
                    } elseif ($user_status === "denied") {
                        pw_new_user_approve()->update_deny_status($user_id);
                        pw_new_user_approve()->deny_user($user_id);
                    }
                }

                return new WP_REST_Response(["message" => "Success"], 200);
            } else {
                return new \WP_Error(
                    400,
                    __("Request has been Failed", "new-user-approve"),
                    "Request has been Failed"
                );
            }
        }
    }
    public function get_all_users($request)
    {
        $nonce = $request->get_header("X-WP-Nonce");
        if (!wp_verify_nonce($nonce, "wp_rest")) {
            return new WP_Error(
                "rest_forbidden",
                __("Invalid nonce.", "new-user-approve"),
                ["status" => 403]
            );
        }

        $page = (int) $request->get_param("page") ?: 1;
        $limit = (int) $request->get_param("limit") ?: 10;
        $offset = ($page - 1) * $limit;
        $search = sanitize_text_field($request->get_param("search") ?: "");

        $args = [
            "meta_query" => [
                ["key" => "pw_user_status", "value" => "", "compare" => "!="],
            ],
            "orderby" => "user_registered",
            "order" => "DESC",
            "number" => $limit,
            "offset" => $offset,
            "search" => "*" . $search . "*",
            "search_columns" => ["user_login", "user_nicename", "user_email"],
        ];

        $results = new WP_User_Query(
            apply_filters("get_all_users_query", $args)
        );
        $users = [];
        $default_cols = [
            "user_login",
            "user_email",
            "user_registered",
            "nua_status",
            "actions",
        ];

        foreach ($results->get_results() as $user) {
            $data = [
                "ID" => $user->ID,
                "user_login" => $user->user_login,
                "user_email" => $user->user_email,
                "user_registered" => get_date_from_gmt(
                    $user->user_registered,
                    "Y-m-d H:i:s"
                ),
                "nua_status" => pw_new_user_approve()->get_user_status(
                    $user->ID
                ),
            ];
            $users[] = (object) apply_filters("nua_user_data", $data, $user);
        }

        $extra_cols = !empty($users[0]) ? array_keys((array) $users[0]) : [];
        $extra_cols = array_filter($extra_cols, function ($col) {
            return $col !== "ID";
        });

        $columns = apply_filters(
            "nua_user_columns",
            array_merge($default_cols, $extra_cols)
        );

        return [
            "users" => $users,
            "totals" => $results->get_total(),
            "columns_order" => array_values(array_unique($columns)),
        ];
    }

    public function get_approved_users($request)
    {
        $nonce = $request->get_header("X-WP-Nonce");
        if (!wp_verify_nonce($nonce, "wp_rest")) {
            return new WP_Error(
                "rest_forbidden",
                __("Invalid nonce.", "new-user-approve"),
                ["status" => 403]
            );
        }

        $page = (int) $request->get_param("page") ?: 1;
        $limit = (int) $request->get_param("limit") ?: 5;
        $offset = ($page - 1) * $limit;
        $search = sanitize_text_field($request->get_param("search") ?: "");

        $args = [
            "meta_query" => [
                ["key" => "pw_user_status", "value" => "approved"],
            ],
            "orderby" => "user_registered",
            "order" => "DESC",
            "number" => $limit,
            "offset" => $offset,
            "search" => "*" . $search . "*",
            "search_columns" => ["user_login", "user_nicename", "user_email"],
        ];

        $results = new WP_User_Query(
            apply_filters("get_approved_users_query", $args)
        );
        $users = [];
        $default_cols = [
            "user_login",
            "user_email",
            "user_registered",
            "nua_status",
            "actions",
        ];

        foreach ($results->get_results() as $user) {
            $data = [
                "ID" => $user->ID,
                "user_login" => $user->user_login,
                "user_email" => $user->user_email,
                "user_registered" => get_date_from_gmt(
                    $user->user_registered,
                    "Y-m-d H:i:s"
                ),
                "nua_status" => pw_new_user_approve()->get_user_status(
                    $user->ID
                ),
            ];
            $users[] = (object) apply_filters("nua_user_data", $data, $user);
        }

        $extra_cols = !empty($users[0]) ? array_keys((array) $users[0]) : [];
        $extra_cols = array_filter($extra_cols, function ($col) {
            return $col !== "ID";
        });

        $columns = apply_filters(
            "nua_user_columns",
            array_merge($default_cols, $extra_cols)
        );

        return [
            "users" => $users,
            "totals" => $results->get_total(),
            "columns_order" => array_values(array_unique($columns)),
        ];
    }

    public function get_pending_users($request)
    {
        // Nonce Verification
        $nonce = $request->get_header("X-WP-Nonce");
        if (!wp_verify_nonce($nonce, "wp_rest")) {
            return new WP_Error(
                "rest_forbidden",
                __("Invalid nonce.", "new-user-approve"),
                ["status" => 403]
            );
        }

        $page = $request->get_param("page")
            ? intval($request->get_param("page"))
            : 1;
        $limit = $request->get_param("limit")
            ? intval($request->get_param("limit"))
            : 5;
        $offset = ($page - 1) * $limit;
        $search = $request->get_param("search")
            ? sanitize_text_field($request->get_param("search"))
            : "";

        $args = [
            "meta_query" => [
                [
                    "key" => "pw_user_status",
                    "value" => "pending",
                    "compare" => "=",
                ],
            ],
            "orderby" => "user_registered",
            "order" => "DESC",
            "number" => $limit,
            "offset" => $offset,
            "search" => "*" . $search . "*",
            "search_columns" => ["user_login", "user_nicename", "user_email"],
        ];

        $query = apply_filters("get_pending_users_query", $args);
        $results = new WP_User_Query($query);
        $new_results = $results->get_results();
        $total_users = $results->get_total();

        $users = [];
        foreach ($new_results as $user) {
            $status = pw_new_user_approve()->get_user_status($user->ID);
            $user_registered = get_date_from_gmt(
                $user->user_registered,
                "Y-m-d H:i:s"
            );

            $data = [
                "ID" => $user->ID,
                "user_login" => $user->user_login,
                "user_email" => $user->user_email,
                "user_registered" => $user_registered,
                "nua_status" => $status,
            ];

            $users[] = (object) apply_filters("nua_user_data", $data, $user);
        }

        $default_cols = [
            "user_login",
            "user_email",
            "user_registered",
            "nua_status",
            "actions",
        ];
        $extra_cols = !empty($users[0]) ? array_keys((array) $users[0]) : [];
        $extra_cols = array_filter($extra_cols, function ($col) {
            return $col !== "ID";
        });

        $columns = array_merge($default_cols, $extra_cols);
        $columns = apply_filters("nua_user_columns", $columns);
        $columns = array_values(array_unique($columns));

        return [
            "users" => $users,
            "totals" => $total_users,
            "columns_order" => $columns,
        ];
    }

    public function get_denied_users($request)
    {
        // Nonce Verification
        $nonce = $request->get_header("X-WP-Nonce");
        if (!wp_verify_nonce($nonce, "wp_rest")) {
            return new WP_Error(
                "rest_forbidden",
                __("Invalid nonce.", "new-user-approve"),
                ["status" => 403]
            );
        }

        $page = $request->get_param("page")
            ? intval($request->get_param("page"))
            : 1;
        $limit = $request->get_param("limit")
            ? intval($request->get_param("limit"))
            : 5;
        $offset = ($page - 1) * $limit;
        $search = $request->get_param("search")
            ? sanitize_text_field($request->get_param("search"))
            : "";

        $args = [
            "meta_query" => [
                [
                    "key" => "pw_user_status",
                    "value" => "denied",
                    "compare" => "=",
                ],
            ],
            "orderby" => "user_registered",
            "order" => "DESC",
            "number" => $limit,
            "offset" => $offset,
            "search" => "*" . $search . "*",
            "search_columns" => ["user_login", "user_nicename", "user_email"],
        ];

        $query = apply_filters("get_denied_users_query", $args);
        $results = new WP_User_Query($query);
        $new_results = $results->get_results();
        $total_users = $results->get_total();

        $users = [];
        foreach ($new_results as $user) {
            $status = pw_new_user_approve()->get_user_status($user->ID);
            $user_registered = get_date_from_gmt(
                $user->user_registered,
                "Y-m-d H:i:s"
            );

            $data = [
                "ID" => $user->ID,
                "user_login" => $user->user_login,
                "user_email" => $user->user_email,
                "user_registered" => $user_registered,
                "nua_status" => $status,
            ];

            $users[] = (object) apply_filters("nua_user_data", $data, $user);
        }

        $default_cols = [
            "user_login",
            "user_email",
            "user_registered",
            "nua_status",
            "actions",
        ];
        $extra_cols = !empty($users[0]) ? array_keys((array) $users[0]) : [];
        $extra_cols = array_filter($extra_cols, function ($col) {
            return $col !== "ID";
        });

        $columns = array_merge($default_cols, $extra_cols);
        $columns = apply_filters("nua_user_columns", $columns);
        $columns = array_values(array_unique($columns));

        return [
            "users" => $users,
            "totals" => $total_users,
            "columns_order" => $columns,
        ];
    }

    public function get_approved_user_roles($request)
    {
        // Nonce Verification
        $nonce = $request->get_header("X-WP-Nonce");
        if (!wp_verify_nonce($nonce, "wp_rest")) {
            return new WP_Error(
                "rest_forbidden",
                __("Invalid nonce.", "new-user-approve"),
                ["status" => 403]
            );
        }

        $args = [
            "meta_query" => [
                [
                    "key" => "pw_user_status",
                    "value" => "approved",
                    "compare" => "=",
                ],
            ],
            "orderby" => "date",
            "order" => "DESC",
            "number" => 5,
        ];
        $query = apply_filters("get_approved_user_roles_query", $args);

        $results = new WP_User_Query($query);
        $new_results = $results->get_results();
        $users = [];
        foreach ($new_results as $user) {
            $user_current_role =
                isset($user->roles[0]) && !empty($user->roles[0])
                    ? sanitize_text_field($user->roles[0])
                    : "";
            $user_current_role = apply_filters(
                "new_user_approve_user_roles",
                $user_current_role,
                $user->roles
            );

            $user_requseted_role = get_user_meta(
                $user->ID,
                "nua_request_new_role",
                true
            );
            $user_roles = [
                "user_current_role" => $user_current_role,
                "user_requested_role" => $user_requseted_role,
            ];

            $usersData = (object) array_merge(
                (array) $user->data,
                (array) $user_roles
            );
            $users[] = $usersData;
        }

        return $users;
    }

    public function get_user_roles($request)
    {
        // Nonce Verification
        $nonce = $request->get_header("X-WP-Nonce");
        if (!wp_verify_nonce($nonce, "wp_rest")) {
            return new WP_Error(
                "rest_forbidden",
                __("Invalid nonce.", "new-user-approve"),
                ["status" => 403]
            );
        }

        global $wp_roles;

        if (!isset($wp_roles)) {
            $all_roles = new WP_Roles();
        }

        $all_roles = $wp_roles->get_names();

        return apply_filters("user_roles_edit", $all_roles);
    }

    public function get_activity_log($request)
    {
        // Nonce Verification
        $nonce = $request->get_header("X-WP-Nonce");
        if (!wp_verify_nonce($nonce, "wp_rest")) {
            return new WP_Error(
                "rest_forbidden",
                __("Invalid nonce.", "new-user-approve"),
                ["status" => 403]
            );
        }

        $user_query = new WP_User_Query([
            "meta_key" => "pw_user_status_time",
            "orderby" => "meta_value",
            "order" => "DESC",
            "number" => 3,
        ]);
        $new_results = $user_query->get_results();
        $activity_log = [];
        foreach ($new_results as $user) {
            if (isset($user->ID)) {
                $status = get_user_meta($user->ID, "pw_user_status", true);
                $time = get_user_meta($user->ID, "pw_user_status_time", true);
                $activity_log[][$status] = [
                    "ID" => $user->ID,
                    "display_name" => $user->display_name,
                    "status_time" => $this->timeAgo($time),
                ];
            }
        }

        return [
            "status" => "success",
            "data" => $activity_log,
        ];
    }

    public function update_user_role($request)
    {
        // Nonce Verification
        $nonce = $request->get_header("X-WP-Nonce");
        if (!wp_verify_nonce($nonce, "wp_rest")) {
            return new WP_Error(
                "rest_forbidden",
                __("Invalid nonce.", "new-user-approve"),
                ["status" => 403]
            );
        }

        if (
            isset($_SERVER["REQUEST_METHOD"]) &&
            $_SERVER["REQUEST_METHOD"] === "POST"
        ) {
            $params = $request->get_json_params();
            $user_id = isset($params["user_id"])
                ? intval($params["user_id"])
                : 0;
            $new_role = isset($params["new_role"])
                ? sanitize_text_field($params["new_role"])
                : "";
            if ($user_id && $new_role) {
                $user = get_user_by("id", $user_id);

                if ($user) {
                    $user->set_role($new_role);
                    return new WP_REST_Response(
                        [
                            "status" => "success",
                            "message" => "User role updated successfully.",
                        ],
                        200
                    );
                } else {
                    return new WP_REST_Response(
                        [
                            "status" => "error",
                            "message" => "User not found.",
                        ],
                        404
                    );
                }
            } else {
                return new WP_REST_Response(
                    [
                        "status" => "error",
                        "message" => "Invalid user ID or role.",
                    ],
                    400
                );
            }
        }

        return new \WP_Error(
            400,
            __("Incomplete Request", "new-user-approve"),
            "Incomplete Request"
        );
    }

    public function get_api_key($request)
    {
        // Nonce Verification
        $nonce = $request->get_header("X-WP-Nonce");
        if (!wp_verify_nonce($nonce, "wp_rest")) {
            return new WP_Error(
                "rest_forbidden",
                __("Invalid nonce.", "new-user-approve"),
                ["status" => 403]
            );
        }

        $api_key = get_option("nua_api_key");
        return ["api_key" => $api_key];
    }

    public function update_api_key($request)
    {
        // Nonce Verification
        $nonce = $request->get_header("X-WP-Nonce");
        if (!wp_verify_nonce($nonce, "wp_rest")) {
            return new WP_Error(
                "rest_forbidden",
                __("Invalid nonce.", "new-user-approve"),
                ["status" => 403]
            );
        }

        $api_key = get_option("nua_api_key");
        $params = $request->get_json_params();
        $api_key = isset($params["api_key"])
            ? sanitize_text_field($params["api_key"])
            : "";
        update_option("nua_api_key", $api_key);

        return new WP_REST_Response(
            [
                "status" => "success",
                "message" => "Zapier API has been updated successfully.",
            ],
            200
        );
    }

    public function get_all_statuses_users($request)
    {
        // Nonce Verification
        $nonce = $request->get_header("X-WP-Nonce");
        if (!wp_verify_nonce($nonce, "wp_rest")) {
            return new WP_Error(
                "rest_forbidden",
                __("Invalid nonce.", "new-user-approve"),
                ["status" => 403]
            );
        }

        $filter_by = $request->get_param("filter_by");
        $filter_by = !empty($filter_by) ? $filter_by : "30 days ago";
        $number_limit = apply_filters("analytics_users_limit", -1);
        $results = $this->nua_users_filter($filter_by, $number_limit);
        $pending = 0;
        $approved = 0;
        $denied = 0;
        if (!empty($results)) {
            foreach ($results as $user) {
                $user_status = pw_new_user_approve()->get_user_status(
                    $user->ID
                );
                switch ($user_status) {
                    case "pending":
                        ++$pending;
                        break;
                    case "approved":
                        ++$approved;
                        break;
                    case "denied":
                        ++$denied;
                        break;
                    default:
                        break;
                }
            }
            $total = absint($pending) + absint($approved) + absint($denied);
            $users = [
                "total" => $total,
                "pending" => $pending,
                "approved" => $approved,
                "denied" => $denied,
            ];
            return new WP_REST_Response($users, 200);
        } else {
            return [
                "total" => 0,
                "pending" => 0,
                "approved" => 0,
                "denied" => 0,
            ];
        }
    }

    public function nua_users_filter($filter_by = "", $number_limit = "")
    {
        $date_query = [];
        switch ($filter_by) {
            case "today":
                $date_query[] = [
                    "after" => "today",
                    "inclusive" => true,
                    "column" => "user_registered",
                ];
                break;

            case "yesterday":
                $date_query[] = [
                    "after" => "yesterday",
                    "before" => "today",
                    "inclusive" => true,
                    "column" => "user_registered",
                ];
                break;

            case "1 week ago":
                $date_query[] = [
                    "after" => "last Sunday",
                    "inclusive" => true,
                    "column" => "user_registered",
                ];
                break;

            case "30 days ago":
                $date_query[] = [
                    "after" => "30 days ago",
                    "inclusive" => true,
                    "column" => "user_registered",
                ];
                break;

            default:
                return null;
        }

        $args = [
            "meta_query" => [
                [
                    "key" => "pw_user_status",
                    "value" => "",
                    "compare" => "!=",
                ],
            ],
            "date_query" => $date_query,
            "number" => $number_limit,
            "orderby" => "user_registered",
            "order" => "DESC",
        ];
        $results = new WP_User_Query($args);

        if (!empty($results->get_results())) {
            return $results->get_results();
        } else {
            return [];
        }
    }

    public function timeAgo($datetime)
    {
        $now = new DateTime();
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        // Compute weeks separately
        $weeks = floor($diff->d / 7);
        $diff->d -= $weeks * 7;

        $string = [
            "y" => "year",
            "m" => "month",
            "w" => "week", // Handle weeks manually
            "d" => "day",
            "h" => "hour",
            "i" => "minute",
            "s" => "second",
        ];

        $result = [];

        foreach ($string as $k => $v) {
            if ($k === "w" && $weeks) {
                $result[$k] = $weeks . " " . $v . ($weeks > 1 ? "s" : "");
            } elseif ($k !== "w" && $diff->$k) {
                $result[$k] = $diff->$k . " " . $v . ($diff->$k > 1 ? "s" : "");
            }
        }

        $result = array_slice($result, 0, 1);
        return $result ? implode(", ", $result) . " ago" : "just now";
    }

    // users api permission callback
    public function nua_users_api_permission_callback($request)
    {
        $nonce = $request->get_header("X-WP-Nonce");

        $current_user = wp_get_current_user();
        $cap = apply_filters(
            "new_user_approve_min_users_api_cap",
            "edit_users"
        );

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

        $permission = apply_filters("users_api_permission", true, $request);
        return $permission;
    }
}
// phpcs:ignore
function users_api()
{
    return Users_API::instance();
}

users_api();
