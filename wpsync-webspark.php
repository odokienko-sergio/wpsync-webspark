<?php
/*
Plugin Name: WpSync WebSpark
Description: Plugin for synchronizing product database with stock quantities
Version: 1.0
Text Domain: wpsync-webspark
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
	die;
}
// Constants
define( 'WPSYNC_WEBSPARK_VERSION', '1.0' );
define( 'WPSYNC_WEBSPARK_PATH', plugin_dir_path( __FILE__ ) );

// Include the class file
require WPSYNC_WEBSPARK_PATH . 'inc/class-wpsync-webspark.php';

// Plugin activation hook
function wpsync_webspark_activate() {
	if ( class_exists( 'WpSync_WebSpark' ) && class_exists( 'WooCommerce' ) ) {
		$wp_sync_webspark = new WpSync_WebSpark();

		wp_schedule_event( time(), 'hourly', 'wpsync_webspark_sync_event' );
	} else {
		// WooCommerce is not active, show an error message or handle it as desired
		add_action( 'admin_notices', 'wpsync_webspark_woocomerce_missing_notice' );
	}
}

// Plugin deactivation hook
function wpsync_webspark_deactivate() {
	if ( class_exists( 'WpSync_WebSpark' ) ) {
		wp_clear_scheduled_hook( 'wpsync_webspark_sync_event' );
	}
}

/**
 * Load the plugin's text domain for translation.
 */
function wpsync_webspark_load_textdomain() {
	load_plugin_textdomain( 'wpsync-webspark', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

add_action( 'plugins_loaded', 'wpsync_webspark_load_textdomain' );

/**
 * Display an admin notice for missing WooCommerce dependency.
 */
function wpsync_webspark_woocomerce_missing_notice() {
	$class   = 'notice notice-error';
	$message = __( 'WpSync WebSpark requires WooCommerce to be installed and activated.', 'wpsync-webspark' );

	printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
}