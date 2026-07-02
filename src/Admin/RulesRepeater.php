<?php
/**
 * Pricing Rules repeater meta box on the price_blueprint edit screen.
 *
 * @package PRBP\Admin
 */

namespace PRBP\Admin;

use PRBP\Utils\RulesFormat;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RulesRepeater {

	public static function register(): void {
		add_action( 'add_meta_boxes',        [ self::class, 'addBox' ],         10 );
		add_action( 'add_meta_boxes',        [ self::class, 'addSidebarBox' ],  10 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueueAssets' ],  10 );
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

	public static function addSidebarBox(): void {
		add_meta_box(
			'prbp_blueprint_settings',
			__( 'Blueprint Settings', 'priceblueprint-for-woocommerce' ),
			[ self::class, 'renderSidebarBox' ],
			'price_blueprint',
			'side',
			'default'
		);
	}

	public static function renderSidebarBox( \WP_Post $post ): void {
		$is_informational = 'informational' === ( get_post_meta( $post->ID, 'prbp_blueprint_type', true ) ?: 'pricing' );

		require PRBP_PLUGIN_DIR . 'templates/blueprint-type-box.php';
	}

	public static function render( \WP_Post $post ): void {
		$raw   = get_post_meta( $post->ID, 'prbp_template_rules', true );
		$rules = [];

		if ( $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$rules = RulesFormat::normalize( $decoded );
			}
		}

		// Enrich rows with thumbnail URLs for display in the admin repeater.
		foreach ( $rules as &$section ) {
			foreach ( $section['rows'] as &$row ) {
				$row['_image_thumb_url'] = '';
				if ( ! empty( $row['image_id'] ) ) {
					$thumb                   = wp_get_attachment_image_src( (int) $row['image_id'], [ 32, 32 ] );
					$row['_image_thumb_url'] = $thumb ? $thumb[0] : '';
				}
			}
			unset( $row );
		}
		unset( $section );

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
		// Media library — required for the wp.media image picker.
		// ------------------------------------------------------------------
		wp_enqueue_media();

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
			'wc_terms_url'     => admin_url( 'edit-tags.php?taxonomy=' ),
			'i18n'             => [
				'loading'             => __( 'Loading…', 'priceblueprint-for-woocommerce' ),
				'load_error'          => __( 'Failed to load terms.', 'priceblueprint-for-woocommerce' ),
				'select_term'         => __( 'Select term(s)', 'priceblueprint-for-woocommerce' ),
				'all_terms_selected'  => __( 'All terms selected', 'priceblueprint-for-woocommerce' ),
				'no_results'          => __( 'No results found', 'priceblueprint-for-woocommerce' ),
				/* translators: 1: Attribute label, 2: Term label */
				'duplicate_msg'       => __( 'Duplicate term for %1$s: %2$s. Remove duplicates before saving.', 'priceblueprint-for-woocommerce' ),
				'add_rule'           => __( '+ Add Rule', 'priceblueprint-for-woocommerce' ),
				'delete'             => __( 'Delete', 'priceblueprint-for-woocommerce' ),
				'reset'              => __( 'Reset', 'priceblueprint-for-woocommerce' ),
				/* translators: %s: Attribute label */
				'confirm_delete_section' => __( 'Delete the "%s" section and all its terms? This cannot be undone.', 'priceblueprint-for-woocommerce' ),
				/* translators: %d: Number of terms in an attribute section */
				'section_term_count' => __( '%d term(s)', 'priceblueprint-for-woocommerce' ),
				'filter_placeholder' => __( 'Filter by attribute or term…', 'priceblueprint-for-woocommerce' ),
				/* translators: %d: Number of visible attribute sections */
				'rules_count'        => __( '%d section(s) shown', 'priceblueprint-for-woocommerce' ),
				'save_error_title'   => __( 'Could not save. Please fix the following errors:', 'priceblueprint-for-woocommerce' ),
				'qs_generate_btn'     => __( 'Generate', 'priceblueprint-for-woocommerce' ),
				'qs_loading'          => __( 'Loading…', 'priceblueprint-for-woocommerce' ),
				'qs_no_attrs'         => __( 'This product has no WooCommerce attributes.', 'priceblueprint-for-woocommerce' ),
				'qs_no_attrs_link'    => __( 'Add them in WooCommerce →', 'priceblueprint-for-woocommerce' ),
				'qs_fetch_error'      => __( 'Could not load attributes. Please try again.', 'priceblueprint-for-woocommerce' ),
				'qs_search_prompt'    => __( 'Search for a product…', 'priceblueprint-for-woocommerce' ),
				'qs_add_manually_btn' => __( '+ Add Attribute', 'priceblueprint-for-woocommerce' ),
				'no_terms_msg'          => __( 'No terms for this attribute.', 'priceblueprint-for-woocommerce' ),
				'no_terms_link'         => __( 'Add terms →', 'priceblueprint-for-woocommerce' ),
				'media_title'           => __( 'Choose an image', 'priceblueprint-for-woocommerce' ),
				'media_insert'          => __( 'Use this image', 'priceblueprint-for-woocommerce' ),
				'choose_image'          => __( 'Choose Image', 'priceblueprint-for-woocommerce' ),
				'change_image'          => __( 'Change', 'priceblueprint-for-woocommerce' ),
				'remove_image'          => __( 'Remove image', 'priceblueprint-for-woocommerce' ),
			],
		] );
	}
}
