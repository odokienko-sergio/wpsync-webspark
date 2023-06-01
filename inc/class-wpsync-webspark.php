<?php

class WpSync_WebSpark {
	// Constructor
	public function __construct() {
		// Event handler for scheduling the synchronization on plugin activation
		register_activation_hook( __FILE__, array( $this, 'schedule_sync_event' ) );

		// Event handler for cleaning and re-queuing the synchronization on settings change
		add_action( 'admin_init', array( $this, 'clean_and_reschedule_sync_event' ) );
	}

	// Method for scheduling the synchronization event
	public function schedule_sync_event() {
		if ( ! wp_next_scheduled( 'wpsync_webspark_sync_event' ) ) {
			wp_schedule_event( time(), 'hourly', 'wpsync_webspark_sync_event' );
		}
	}

	// Method for cleaning and re-queuing the synchronization event
	public function clean_and_reschedule_sync_event() {
		// Clear the scheduled event
		wp_clear_scheduled_hook( 'wpsync_webspark_sync_event' );

		// Get the new synchronization interval from the settings
		$sync_frequency = get_option( 'wpsync_webspark_sync_frequency', 'hourly' );

		// Re-queue the synchronization event with the new interval
		wp_schedule_event( time(), $sync_frequency, 'wpsync_webspark_sync_event' );
	}

	// Method that performs the product database synchronization
	public function sync_products() {
		error_log( esc_attr( 'Sync products executed' ) );
		$api_url = esc_url( 'https://wp.webspark.dev/wp-api/products' );

		// Make the API request
		$response = wp_remote_get( $api_url );

		// Check for a successful response
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			// Handle API request error
			error_log( esc_attr( 'API request error: ' ) . $response->get_error_message() );
			return;
		}

		// Retrieve the response body
		$json_data = wp_remote_retrieve_body( $response );

		// Convert the JSON string to an array
		$products = json_decode( $json_data, true );

		if ( ! is_array( $products ) ) {
			// Handling incorrect JSON format
			error_log( esc_attr( 'Incorrect JSON format' ) );
			return;
		}

		// Get all existing product SKUs in the WordPress database
		$existing_product_skus = get_posts(
			array(
				'post_type'      => 'product',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $products as $product ) {
			// Validate required fields
			if (
				! isset( $product['sku'] ) ||
				! isset( $product['name'] ) ||
				! isset( $product['description'] ) ||
				! isset( $product['price'] ) ||
				! isset( $product['picture'] ) ||
				! isset( $product['in_stock'] )
			) {
				// Log an error and skip processing the product
				error_log( esc_attr( 'Missing required fields in product data' ) );
				continue;
			}

			$sku         = esc_attr( $product['sku'] );
			$name        = esc_html( $product['name'] );
			$description = esc_html( $product['description'] );
			$price       = esc_attr( $product['price'] );
			$picture     = esc_url( $product['picture'] );
			$in_stock    = esc_attr( $product['in_stock'] );

			// Check if a product with the specified SKU exists in the WordPress database
			$existing_product = wc_get_product_by_sku( $sku );

			if ( $existing_product ) {
				// The product already exists, update the fields
				$existing_product->set_name( $name );
				$existing_product->set_description( $description );
				$existing_product->set_price( $price );

				// Save the updated product
				$existing_product->save();

				// Remove SKU from the existing product SKUs array
				$existing_product_skus = array_diff( $existing_product_skus, array( $existing_product->get_id() ) );
			} else {
				// The product does not exist, create a new product
				$new_product = new WC_Product();
				$new_product->set_sku( $sku );
				$new_product->set_name( $name );
				$new_product->set_description( $description );
				$new_product->set_price( $price );

				// Save the new product
				$new_product->save();
			}
		}

		// Remove any remaining products that are not present in the API response
		foreach ( $existing_product_skus as $existing_product_sku ) {
			$existing_product = wc_get_product( $existing_product_sku );

			if ( $existing_product ) {
				// Remove the product from the site
				wp_trash_post( $existing_product->get_id() );
			}
		}

		// After retrieving the response body and decoding the JSON
		if ( ! is_array( $products ) || empty( $products ) ) {
			error_log( esc_attr( 'Empty response or lack of connection' ) );
			return;
		}
	}
}

// Instantiate the class
$wp_sync_webspark = new WpSync_WebSpark();

// Register the synchronization event
add_action( 'wpsync_webspark_sync_event', array(
	$wp_sync_webspark,
	'sync_products',
) );