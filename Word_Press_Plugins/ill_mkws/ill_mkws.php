<?php
/*
Plugin Name: IndexData MKWS Script Loader
Plugin URI: https://seal.senylrc.org/
Description: Loads the mkws-complete.js file for use in the theme or plugin context.
Version: 1.0
Author: SENYLRC
*/

function mkws_enqueue_scripts() {
    // Enqueue mkws-complete.js
    wp_enqueue_script(
        'mkws-complete',
        plugins_url('mkws-complete.js', __FILE__),
        array(),
        '1.0',
        true
    );

    // Enqueue ill.js
    wp_enqueue_script(
        'ill-script',
        plugins_url('ill.js', __FILE__),
        array('mkws-complete'), // Load after mkws-complete.js if needed
        '1.0',
        true
    );
}
add_action('wp_enqueue_scripts', 'mkws_enqueue_scripts');
