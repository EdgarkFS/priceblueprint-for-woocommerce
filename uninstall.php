<?php
/**
 * Uninstall PriceBlueprint for WooCommerce.
 *
 * Runs when the plugin is deleted from the WordPress admin.
 * Removes all plugin data: CPT posts, post meta, and transients.
 *
 * @package PriceBlueprintWC
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete all price_blueprint posts and their meta.
$prbp_template_ids = get_posts( [
	'post_type'      => 'price_blueprint',
	'posts_per_page' => -1,
	'post_status'    => 'any',
	'fields'         => 'ids',
] );

foreach ( $prbp_template_ids as $prbp_template_id ) {
	wp_delete_post( (int) $prbp_template_id, true );
}

// Delete product meta referencing price blueprints.
delete_post_meta_by_key( 'prbp_template_id' );

// Clean up any lingering transients (save error transients use user IDs).
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transientprbp_save_error_%' OR option_name LIKE '_transient_timeoutprbp_save_error_%'"
);
