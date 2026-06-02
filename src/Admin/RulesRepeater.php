<?php
/**
 * Pricing Rules repeater meta box on the price_blueprint edit screen.
 *
 * @package PRBP\Admin
 */

namespace PRBP\Admin;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RulesRepeater {

	public static function register(): void {
		add_action( 'add_meta_boxes',        [ self::class, 'addBox' ],        10 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueueAssets' ], 10 );
	}

	public static function addBox(): void {
		add_meta_box(
			'prbp_pricing_rules',
			__( 'Pricing Rules', 'priceblueprint-for-woocommerce' ),
			[ self::class, 'render' ],
			'price_blueprint',
			'normal',
			'high'
		);
	}

	public static function render( \WP_Post $post ): void {
		$raw   = get_post_meta( $post->ID, 'prbp_template_rules', true );
		$rules = [];

		if ( $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$rules = $decoded;
			}
		}

		// Fetch all WC global attributes (pa_* taxonomies).
		$attribute_taxonomies = wc_get_attribute_taxonomies();

		require PRBP_PLUGIN_DIR . 'templates/admin-repeater.php';
	}

	public static function enqueueAssets( string $hook ): void {
		global $post;

		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		if ( ! $post || 'price_blueprint' !== $post->post_type ) {
			return;
		}

		// ------------------------------------------------------------------
		// Tom Select — loaded first so TomSelect global is available when
		// our component script runs (prbp-admin depends on it).
		// ------------------------------------------------------------------
		wp_enqueue_script(
			'tom-select',
			PRBP_PLUGIN_URL . 'assets/js/vendor/tom-select.min.js',
			[],
			'2.4.3',
			false   // <head> + defer
		);

		wp_enqueue_style(
			'tom-select',
			PRBP_PLUGIN_URL . 'assets/css/tom-select.css',
			[],
			'2.4.3'
		);

		// ------------------------------------------------------------------
		// Our Alpine component script — must load BEFORE Alpine so that the
		// alpine:init listener is registered when Alpine fires the event.
		// Depends on tom-select so TomSelect is available at component init.
		// ------------------------------------------------------------------
		wp_enqueue_script(
			'prbp-admin',
			PRBP_PLUGIN_URL . 'assets/js/admin/index.js',
			[ 'tom-select' ],
			PRBP_VERSION,
			false   // <head> + defer
		);

		// ------------------------------------------------------------------
		// Alpine.js — depends on prbp-admin, guaranteeing load order:
		//   1. tom-select  2. prbp-admin  3. alpinejs
		// Alpine fires alpine:init after prbp-admin has registered the
		// component factory, then processes the DOM.
		// ------------------------------------------------------------------
		wp_enqueue_script(
			'alpinejs',
			PRBP_PLUGIN_URL . 'assets/js/vendor/alpine.min.js',
			[ 'prbp-admin' ],
			'3.14.8',
			false   // <head> + defer
		);

		// prbp-admin is an ES module — type="module" implies defer, so the
		// browser fetches index.js and its imports in parallel, then executes
		// them (in dependency order) after DOM parsing.
		// tom-select and alpinejs are plain scripts; they get explicit defer.
		add_filter( 'script_loader_tag', static function ( $tag, $handle ) {
			if ( 'prbp-admin' === $handle ) {
				$tag = str_replace( ' src=', ' type="module" src=', $tag );
			} elseif ( in_array( $handle, [ 'tom-select', 'alpinejs' ], true ) ) {
				if ( false === strpos( $tag, ' defer' ) ) {
					$tag = str_replace( ' src=', ' defer src=', $tag );
				}
			}
			return $tag;
		}, 10, 2 );

		wp_enqueue_style(
			'prbp-admin',
			PRBP_PLUGIN_URL . 'assets/css/admin.css',
			[ 'tom-select' ],
			PRBP_VERSION
		);
		wp_style_add_data( 'prbp-admin', 'rtl', 'replace' );

		wp_localize_script( 'prbp-admin', 'prbpAdmin', [
			'ajax_url'         => admin_url( 'admin-ajax.php' ),
			'nonce'            => wp_create_nonce( 'prbp_admin_nonce' ),
			'product_edit_url' => admin_url( 'post.php?post=' ),
			'i18n'             => [
				'loading'             => __( 'Loading…', 'priceblueprint-for-woocommerce' ),
				'load_error'          => __( 'Failed to load values.', 'priceblueprint-for-woocommerce' ),
				'select_value'        => __( 'Select value(s)', 'priceblueprint-for-woocommerce' ),
				'all_values_selected' => __( 'All values selected', 'priceblueprint-for-woocommerce' ),
				'no_results'          => __( 'No results found', 'priceblueprint-for-woocommerce' ),
				/* translators: 1: Attribute label, 2: Value label */
				'duplicate_msg'       => __( 'Duplicate rule: %1$s → %2$s. Remove duplicates before saving.', 'priceblueprint-for-woocommerce' ),
				'add_rule'           => __( '+ Add Rule', 'priceblueprint-for-woocommerce' ),
				'delete'             => __( 'Delete', 'priceblueprint-for-woocommerce' ),
				'restore'            => __( 'Restore', 'priceblueprint-for-woocommerce' ),
				'filter_placeholder' => __( 'Filter by attribute or value…', 'priceblueprint-for-woocommerce' ),
				/* translators: %d: Number of visible rules */
				'rules_count'        => __( '%d rule(s) shown', 'priceblueprint-for-woocommerce' ),
				'save_error_title'   => __( 'Could not save. Please fix the following errors:', 'priceblueprint-for-woocommerce' ),
				'qs_generate_btn'     => __( 'Generate', 'priceblueprint-for-woocommerce' ),
				'qs_loading'          => __( 'Loading…', 'priceblueprint-for-woocommerce' ),
				'qs_no_attrs'         => __( 'This product has no WooCommerce attributes.', 'priceblueprint-for-woocommerce' ),
				'qs_no_attrs_link'    => __( 'Add them in WooCommerce →', 'priceblueprint-for-woocommerce' ),
				'qs_fetch_error'      => __( 'Could not load attributes. Please try again.', 'priceblueprint-for-woocommerce' ),
				'qs_search_prompt'    => __( 'Search for a product…', 'priceblueprint-for-woocommerce' ),
				'qs_add_manually_btn' => __( '+ Add Attribute', 'priceblueprint-for-woocommerce' ),
			],
		] );
	}
}
