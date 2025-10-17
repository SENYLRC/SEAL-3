<?php
/**
* Plugin Name: New User Approve
* Plugin URI: http://newuserapprove.com/
* Description: Allow administrators to approve users once they register. Only approved users will be allowed to access the site. For support, please go to the <a href="http://wordpress.org/support/plugin/new-user-approve">support forums</a> on wordpress.org.
* Author: New User Approve
* Version: 3.0.9
* Author URI: https://newuserapprove.com/
* Text Domain: new-user-approve
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( !defined( 'NUA_VERSION' ) ) {
	define( 'NUA_VERSION', '3.0.9' );
}

if ( !defined('NUA_FILE') ) {
	define('NUA_FILE', __FILE__);
}

function nua_init_fs() {

	global $nua_fs;

	if ( ! isset( $nua_fs ) && file_exists( __DIR__ . '/freemius/start.php' ) ) {
		
		// Include Freemius SDK.
		require_once __DIR__ . '/freemius/start.php';

		$nua_fs = fs_dynamic_init( array(
			'id'             => '5930',
			'slug'           => 'new-user-approve',
			'type'           => 'plugin',
			'public_key'     => 'pk_ee61e9ff1f383893927fd96595470',
			'is_premium'     => false,
			'premium_suffix' => 'Premium',
			'has_addons'     => false,
			'has_paid_plans' => false,
			'has_premium_version' => true,
			'has_affiliation'     => 'selected',
			'menu'           => array(
				'slug'    => 'new-user-approve-admin',
				'contact' => false,
				'support' => false,
				'account' => false,
				'pricing' => false,
			),
			'is_live'        => true,
		) );

		// Signal that SDK was initiated.
		do_action( 'nua_fs_loaded' );

	}

	return $nua_fs;
}


function pw_new_user_approve() {

	// Init Freemius.
	nua_init_fs();

	// requiring the New User Approve Main file.
	require_once __DIR__ . '/pw-new-user-approve.php';
	return PW_New_User_Approve::instance();
}
pw_new_user_approve();
