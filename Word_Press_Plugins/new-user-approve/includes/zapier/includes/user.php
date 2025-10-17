<?php

namespace Premium_NewUserApproveZapier;
// Exit if accessed directly
if (!defined("ABSPATH")) {
    exit();
}

class User
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
        add_action("new_user_approve_user_approved", [$this, "user_approved"]);
        add_action("new_user_approve_user_denied", [$this, "user_denied"]);
        add_filter(
            "new_user_approve_default_status",
            [$this, "user_pending"],
            999,
            2
        );
        add_action(
            "nua_invited_user",
            [$this, "user_auto_approved_via_inv_code"],
            15,
            2
        );
        add_action(
            "nua_whitelisted_users",
            [$this, "user_auto_approved_via_whitelist"],
            15,
            3
        );
        add_action(
            "role_base_approval_completed",
            [$this, "user_auto_approved_via_user_role"],
            15,
            4
        );
    }

    /**
     * @since 2.6
     * @method void : update user to zapier table.
     *
     */

    public function user_auto_approved_via_user_role(
        $user_id,
        $user_roles,
        $auto_approve_role_list,
        $status
    ) {
        update_user_meta($user_id, "nua_user_role_based_approved", $user_roles);
        $this->update_user("nua_user_approved_via_role", $user_id);
    }

    /**
     * @version 1.0
     * @since 2.5
     */
    public function user_auto_approved_via_whitelist($user_id, $email, $domain)
    {
        update_user_meta($user_id, "nua_wl_domain_used", $domain);
        $this->update_user("nua_user_whitelisted", $user_id);
    }

    /**
     * @version 1.0
     * @since 2.5
     */
    public function user_auto_approved_via_inv_code($user_id, $code_inv)
    {
        update_user_meta($user_id, "nua_invcode_used", $code_inv);
        $this->update_user("nua_user_invcode", $user_id);
    }

    /**
     * @version 1.0
     * @since 2.5
     */
    public function user_pending($status, $user_id)
    {
        if ("pending" == $status) {
            $this->update_user("nua_user_pending", $user_id);
        }
        return $status;
    }

    /**
     * @version 1.0
     * @since 2.1
     */
    public function user_approved($user)
    {
        if (
            !empty(get_user_meta($user->ID, "nua_invcode_used", true)) ||
            !empty(get_user_meta($user->ID, "nua_wl_domain_used", true))
        ) {
            // user is auto approved through invitation code or whitelist
            return;
        }
        $this->update_user("nua_user_approved", $user->ID);
    }

    /**
     * @version 1.0
     * @since 2.1
     */
    public function user_denied($user)
    {
        $this->update_user("nua_user_denied", $user->ID);
    }

    public function update_user($option_name, $user_id)
    {
        // inserting the data into nua-zapier table
        nua_zapier_insert_log($option_name, $user_id);
    }
    /**
     * Making compatibility with NUA previous version
     * Inserting the nua-zapier options users data to nua-zapier table
     */
    public function nua_zap_compatible_legacy_options()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . "nua_zapier";
        $options_statuses = [
            "nua_user_pending",
            "nua_user_approved",
            "nua_user_denied",
            "nua_user_invcode",
        ];
        foreach ($options_statuses as $status) {
            $user_data = get_option($status);
            $option = ["users_status" => $status];
            if (!empty($user_data)) {
                foreach ($user_data as $key => $value) {
                    $time = $value["time"];
                    unset($value["time"]);
                    unset($value["id"]);
                    $created_time = ["created_time" => $time];
                    $value = array_merge($value, $created_time);
                    $value = array_merge($value, $option);
                    $wpdb->insert($table_name, $value);
                }
            }
        }
    }
}

\Premium_NewUserApproveZapier\User::get_instance();
