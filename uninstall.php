<?php
// If uninstall not called from WordPress, then exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete options
delete_option( 'wpsync_webspark_api_url' );
delete_option( 'wpsync_webspark_sync_frequency' );

// Remove scheduled event
wp_clear_scheduled_hook( 'wpsync_webspark_sync_event' );

// Delete products created by the plugin
$all_products = wc_get_products();
foreach ( $all_products as $product ) {
	$product_sku = $product->get_sku();
	if ( strpos( $product_sku, 'wpsync_' ) === 0 ) {
		$product->delete();
	}
}