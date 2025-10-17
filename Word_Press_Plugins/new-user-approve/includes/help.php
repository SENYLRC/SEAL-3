<?php

// Exit if accessed directly
if (!defined("ABSPATH")) {
    exit();
}

function nua_opt_diagnostics()
{
    $theme_data = wp_get_theme();
    $theme = $theme_data->Name . " " . $theme_data->Version;
    $nua_version = null;
    $nua_options_version = null;

    foreach (get_plugins() as $plugin) {
        if ("New User Approve" == $plugin["Name"]) {
            $nua_version = $plugin["Version"];
        }

        if ("New User Approve Options" == $plugin["Name"]) {
            $nua_options_version = $plugin["Version"];
        }

        if (!empty($nua_version) && !empty($nua_options_version)) {
            break;
        }
    }

    $dignostic_info = [
        "site_url" => site_url(),
        "home_url" => home_url(),
        "multisite" => is_multisite() ? "Yes" : "No",
        "nua_version" => $nua_version,
        "nua_option_version" => $nua_options_version,
        "wordpress_version" => get_bloginfo("version"),
        "active_theme" => $theme,
        "web_server_info" => $_SERVER["SERVER_SOFTWARE"],
        "php_safe_mode" => ini_get("safe_mode") ? "Yes" : "No",
        "php_memory_limit" => ini_get("memory_limit"),
        "php_upload_max_size" => ini_get("upload_max_filesize"),
        "php_post_max_size" => ini_get("post_max_size"),
        "php_upload_max_filesize" => ini_get("upload_max_filesize"),
        "php_time_limit" => ini_get("max_execution_time"),
        "php_max_input_vars" => ini_get("max_input_vars"),
        "php_arg_separator" => ini_get("arg_separator.output"),
        "php_allow_file_url_oprn" => ini_get("allow_url_fopen") ? "Yes" : "No",
        "wp_debug" => defined("WP_DEBUG")
            ? (WP_DEBUG
                ? "Enabled"
                : "Disabled")
            : "Not set",
    ];

    // ACTIVE PLUGINS:
    $plugins = get_plugins();
    $active_plugins = get_option("active_plugins", []);

    foreach ($plugins as $plugin_path => $plugin) {
        // If the plugin isn't active, don't show it.
        if (!in_array($plugin_path, $active_plugins)) {
            continue;
        }

        $plugin_info[] = $plugin["Name"] . ":" . $plugin["Version"] . "\n";
    }

    if (is_multisite()):
        // NETWORK ACTIVE PLUGINS:

        $plugins = wp_get_active_network_plugins();
        $active_plugins = get_site_option("active_sitewide_plugins", []);

        foreach ($plugins as $plugin_path) {
            $plugin_base = plugin_basename($plugin_path);

            // If the plugin isn't active, don't show it.
            if (!array_key_exists($plugin_base, $active_plugins)) {
                continue;
            }

            $plugin = get_plugin_data($plugin_path);

            $plugin_info[] = $plugin["Name"] . ":" . $plugin["Version"] . "\n";
        }
    endif;

    return ["dignostic_info" => array_merge($plugin_info, $dignostic_info)];
}
