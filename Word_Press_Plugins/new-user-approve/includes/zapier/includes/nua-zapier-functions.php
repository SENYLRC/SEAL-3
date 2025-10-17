<?php

// Exit if accessed directly
if (!defined("ABSPATH")) {
    exit();
}

if (!function_exists("create_nua_zapier_table")):
    function create_nua_zapier_table()
    {
        require_once ABSPATH . "wp-admin/includes/upgrade.php";
        global $wpdb;
        $nua_zapier_table = $wpdb->prefix . "nua_zapier";

        $tbl_sql = "CREATE TABLE $nua_zapier_table (
            id            INT(11) NOT NULL AUTO_INCREMENT, 
            user_id       INT(11) NOT NULL, 
            created_time  BIGINT(20) DEFAULT NULL,
            users_status   VARCHAR(225) NOT NULL DEFAULT '',
            PRIMARY KEY   (id), 
            UNIQUE KEY id (id),
            INDEX 		  user_id (user_id),
            INDEX 		  created_time (created_time)
        ) ";

        if (maybe_create_table($nua_zapier_table, $tbl_sql)) {
            update_option("nua_zapier_db_version", NUA_ZAPIER_DB_VERSION);
        }
    }
endif;
// avoiding redeclaring error
if (!function_exists("nua_zapier_insert_log")):
    function nua_zapier_insert_log($option_name, $user_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . "nua_zapier";
        $nua_zapier_db = get_option("nua_zapier_db_version", false);
        if ($nua_zapier_db != NUA_ZAPIER_DB_VERSION) {
            // creating the nua_zapier table if does not exist
            create_nua_zapier_table();
        }

        $data = [
            "user_id" => $user_id,
            "users_status" => $option_name,
            "created_time" => time(),
        ];

        premium_nua_delete_previous_same_id_user($user_id);

        $wpdb->insert($table_name, $data);

        return $wpdb->insert_id;
    }
endif;
/**
 * @param int user_id
 * delete the user while updating user status
 */
function premium_nua_delete_previous_same_id_user($user_id)
{
    global $wpdb;
    $table_name = esc_sql($wpdb->prefix . "nua_zapier");
    // translators: %s is for user id

    $result = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT `user_id` FROM $table_name WHERE user_id = %s",
            $user_id
        )
    );
    // avoiding the errors
    if ($result) {
        $wpdb->delete($table_name, ["user_id" => $user_id]);
    }
    return $result;
}

function premium_get_users_by_nua_zap($option_name)
{
    global $wpdb;
    $table_name = $wpdb->prefix . "nua_zapier";

    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, user_id, created_time AS time FROM $table_name WHERE users_status = %s ORDER BY created_time DESC",
            $option_name
        ),
        ARRAY_A
    );

    return apply_filters("nua_zapier_users", $results);
}

?>
