<?php

namespace NewUserApproveZapier;

if (!defined("NUA_ZAPIER_DB_VERSION")) {
    define("NUA_ZAPIER_DB_VERSION", "1.0");
}
if (!defined("NUA_ZAPIER_OPTION_STATUS")) {
    define("NUA_ZAPIER_OPTION_STATUS", true);
}

class Init
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
        $this->require_files();
        register_activation_hook(NUA_FILE, "create_nua_zapier_table");
    }

    /**
     * @version 1.0
     * @since 2.1
     */
    public function require_files()
    {
        require_once plugin_dir_path(__FILE__) .
            "/includes/nua-zapier-functions.php";
        require_once plugin_dir_path(__FILE__) . "/includes/rest-api.php";
        require_once plugin_dir_path(__FILE__) . "/includes/user.php";
    }
}

\NewUserApproveZapier\Init::get_instance();
