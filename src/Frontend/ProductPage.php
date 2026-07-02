<?php
/**
 * Renders the configurator on single product pages.
 *
 * @package PRBP\Frontend
 */

namespace PRBP\Frontend;

use PRBP\Utils\BlueprintType;
use PRBP\Utils\RulesCache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProductPage {

	public static function register(): void {
		add_action( 'woocommerce_prbp_configurable_product_add_to_cart', [ self::class, 'renderAddToCart' ],        10 );
		add_action( 'woocommerce_before_add_to_cart_button',              [ self::class, 'renderSelects' ],          10 );
		add_action( 'wp_enqueue_scripts',                                 [ self::class, 'enqueueAssets' ],          10 );
		add_filter( 'woocommerce_get_price_html',                         [ self::class, 'filterPriceHtml' ],        10, 2 );
		add_action( 'woocommerce_product_thumbnails',                     [ self::class, 'renderGallerySlides' ],    20 );
		add_filter( 'woocommerce_single_product_image_thumbnail_html',    [ self::class, 'suppressGalleryPlaceholder' ], 10, 2 );
	}

	/**
	 * Minimum displayable price: base price + cheapest option per attribute.
	 * Used for shop listings, product page, and configurator initial display.
	 *
	 * @param int $product_id
	 * @return float
	 */
	public static function getMinPrice( int $product_id, string $base_meta_key = '_price' ): float {
		$base        = (float) get_post_meta( $product_id, $base_meta_key, true );
		$template_id = (int)   get_post_meta( $product_id, 'prbp_template_id', true );

		if ( ! $template_id ) {
			return $base;
		}

		$rules = RulesCache::get( $template_id );

		// Blueprint base-price override applies to the active price only.
		// Regular-price lookups (_regular_price) always use WC meta for correct strikethrough.
		if ( '_price' === $base_meta_key ) {
			$bp_enabled = get_post_meta( $template_id, 'prbp_base_price_enabled', true );
			if ( 'true' === $bp_enabled ) {
				$base = (float) get_post_meta( $template_id, 'prbp_base_price', true );
			}
		}

		if ( empty( $rules ) ) {
			return $base;
		}

		$min_per_attr = [];
		foreach ( $rules as $rule ) {
			$attr  = $rule['attribute'];
			$price = (float) $rule['price'];
			if ( ! isset( $min_per_attr[ $attr ] ) || $price < $min_per_attr[ $attr ] ) {
				$min_per_attr[ $attr ] = $price;
			}
		}

		return $base + array_sum( $min_per_attr );
	}

	/**
	 * Replace the displayed price HTML for configurable products with the
	 * minimum total price everywhere WooCommerce renders a price.
	 *
	 * @param string      $price_html
	 * @param \WC_Product $product
	 * @return string
	 */
	public static function filterPriceHtml( string $price_html, \WC_Product $product ): string {
		if ( $product->get_type() !== 'prbp_configurable_product' ) {
			return $price_html;
		}

		$product_id  = $product->get_id();
		$template_id = (int) get_post_meta( $product_id, 'prbp_template_id', true );
		if ( ! $template_id || BlueprintType::isInformational( $template_id ) ) {
			return $price_html;
		}

		$active_min = self::getMinPrice( $product_id );

		$price = $product->is_on_sale()
			? wc_format_sale_price(
				self::getMinPrice( $product_id, '_regular_price' ),
				$active_min
			)
			: wc_price( $active_min );

		return sprintf(
			/* translators: %s: Formatted price or sale price comparison, e.g. "$10.00" or "<del>$15.00</del> <ins>$10.00</ins>" */
			__( 'From %s', 'priceblueprint-for-woocommerce' ),
			$price
		);
	}

	/**
	 * Load the WC-standard add-to-cart form shell for configurable products.
	 * Fires on woocommerce_prbp_configurable_product_add_to_cart.
	 */
	public static function renderAddToCart(): void {
		$product = wc_get_product();
		if ( ! $product || $product->get_type() !== 'prbp_configurable_product' ) {
			return;
		}

		$template_id = (int) get_post_meta( $product->get_id(), 'prbp_template_id', true );

		if ( ! $template_id ) {
			echo '<p class="prbp-no-rules">' . esc_html__( 'This product has no pricing options available.', 'priceblueprint-for-woocommerce' ) . '</p>';
			return;
		}

		if ( BlueprintType::isInformational( $template_id ) ) {
			require PRBP_PLUGIN_DIR . 'templates/add-to-cart.php';
			return;
		}

		$rules = RulesCache::get( $template_id );

		if ( empty( $rules ) ) {
			echo '<p class="prbp-no-rules">' . esc_html__( 'This product has no pricing options available.', 'priceblueprint-for-woocommerce' ) . '</p>';
			return;
		}

		require PRBP_PLUGIN_DIR . 'templates/add-to-cart.php';
	}

	/**
	 * Render attribute selects + price display inside WC's form.
	 * Fires on woocommerce_before_add_to_cart_button (guarded to our product type).
	 */
	public static function renderSelects(): void {
		$product = wc_get_product();
		if ( ! $product || $product->get_type() !== 'prbp_configurable_product' ) {
			return;
		}

		$product_id  = $product->get_id();
		$template_id = (int) get_post_meta( $product_id, 'prbp_template_id', true );

		if ( ! $template_id ) {
			return;
		}

		if ( BlueprintType::isInformational( $template_id ) ) {
			return;
		}

		$rules = RulesCache::get( $template_id );

		if ( empty( $rules ) ) {
			return;
		}

		$prbp_slide_map = self::buildImageSlides( $rules, $product );
		$grouped_rules  = [];
		foreach ( $rules as $rule ) {
			$grouped_rules[ $rule['attribute'] ][] = $rule;
		}

		$preselected       = [];
		$precomputed_price = null;
		$is_on_sale        = $product->is_on_sale();
		$regular_min_total = self::getMinPrice( $product_id, '_regular_price' );

		require PRBP_PLUGIN_DIR . 'templates/configurator-selects.php';
	}

	/**
	 * Remove the WC placeholder image when the product has no featured image but has
	 * rule images that will act as the gallery. The placeholder is a non-slide sibling
	 * inside .woocommerce-product-gallery__wrapper; it offsets flexslider's horizontal
	 * slide positions, making the main viewport appear blank.
	 */
	public static function suppressGalleryPlaceholder( string $html, int|string $attachment_id ): string {
		if ( $attachment_id ) {
			return $html;
		}
		if ( ! is_product() ) {
			return $html;
		}
		$product = wc_get_product();
		if ( ! $product || $product->get_type() !== 'prbp_configurable_product' ) {
			return $html;
		}
		$template_id = (int) get_post_meta( $product->get_id(), 'prbp_template_id', true );
		if ( ! $template_id || BlueprintType::isInformational( $template_id ) ) {
			return $html;
		}
		// WC gallery images serve as base slides; keep the placeholder so slide
		// indices match the base_index calculated in buildImageSlides().
		if ( count( $product->get_gallery_image_ids() ) > 0 ) {
			return $html;
		}
		$rules  = RulesCache::get( $template_id );
		$slides = empty( $rules ) ? [] : self::buildImageSlides( $rules, $product );
		if ( empty( $slides['order'] ) ) {
			return $html;
		}
		return '';
	}

	public static function buildGallerySlidesHtml( array $imageIdsInOrder ): string {
		$html = '';
		foreach ( $imageIdsInOrder as $image_id ) {
			$html .= wc_get_gallery_image_html( $image_id );
		}
		return $html;
	}

	public static function renderGallerySlides(): void {
		if ( ! is_product() ) return;
		$product = wc_get_product();
		if ( ! $product || $product->get_type() !== 'prbp_configurable_product' ) return;
		$template_id = (int) get_post_meta( $product->get_id(), 'prbp_template_id', true );
		if ( ! $template_id || BlueprintType::isInformational( $template_id ) ) return;
		$rules = RulesCache::get( $template_id );
		if ( empty( $rules ) ) return;
		$slides = self::buildImageSlides( $rules, $product );
		if ( empty( $slides['order'] ) ) return;
		echo self::buildGallerySlidesHtml( $slides['order'] ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function buildImageSlides( array $rules, \WC_Product $product ): array {
		$base_index = ( $product->get_image_id() ? 1 : 0 ) + count( $product->get_gallery_image_ids() );
		$order = []; $index_by_image = []; $attr_map = [];
		foreach ( $rules as $rule ) {
			$image_id = (int) ( $rule['image_id'] ?? 0 );
			if ( ! $image_id ) continue;
			if ( ! isset( $index_by_image[ $image_id ] ) ) {
				$index_by_image[ $image_id ] = $base_index + count( $order );
				$order[] = $image_id;
			}
			$attr = $rule['attribute'];
			foreach ( (array) ( $rule['value_slugs'] ?? [] ) as $slug ) {
				if ( $slug ) $attr_map[ $attr ][ $slug ] = $index_by_image[ $image_id ];
			}
		}
		return [ 'order' => $order, 'index_by_image' => $index_by_image, 'attr_map' => $attr_map ];
	}

	public static function enqueueAssets(): void {
		if ( ! is_product() ) {
			return;
		}

		$product = wc_get_product();
		if ( ! $product || $product->get_type() !== 'prbp_configurable_product' ) {
			return;
		}

		$template_id = (int) get_post_meta( $product->get_id(), 'prbp_template_id', true );
		if ( BlueprintType::isInformational( $template_id ) ) {
			return;
		}

		wp_enqueue_style(
			'prbp-frontend',
			PRBP_PLUGIN_URL . 'assets/css/frontend.css',
			[],
			PRBP_VERSION
		);
		wp_style_add_data( 'prbp-frontend', 'rtl', 'replace' );

		wp_enqueue_script(
			'prbp-frontend',
			PRBP_PLUGIN_URL . 'assets/js/frontend.js',
			[],
			PRBP_VERSION,
			true
		);

		add_filter( 'script_loader_tag', static function ( $tag, $handle ) {
			if ( 'prbp-frontend' === $handle ) {
				$tag = str_replace( ' src=', ' type="module" src=', $tag );
			}
			return $tag;
		}, 10, 2 );

		$has_base_slide = (bool) ( $product->get_image_id() || count( $product->get_gallery_image_ids() ) > 0 );

		wp_localize_script( 'prbp-frontend', 'prbpFrontend', [
			'ajax_url'       => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'prbp_frontend_nonce' ),
			'product_id'     => get_the_ID(),
			'currency'       => get_woocommerce_currency_symbol(),
			'has_base_slide' => $has_base_slide,
			'i18n'       => [
				/* translators: %s: Attribute label, e.g. "Color" */
				'select_option' => __( '— Select %s —', 'priceblueprint-for-woocommerce' ),
				'select_all'    => __( 'Please select all options before adding to cart.', 'priceblueprint-for-woocommerce' ),
				'loading'       => __( 'Calculating…', 'priceblueprint-for-woocommerce' ),
				'total_label'   => __( 'Total:', 'priceblueprint-for-woocommerce' ),
				/* translators: %s: Formatted price, e.g. "$10.00" */
				'price_from'    => __( 'From %s', 'priceblueprint-for-woocommerce' ),
				'add_to_cart'   => __( 'Add to cart', 'priceblueprint-for-woocommerce' ),
				'no_options'    => __( 'This product has no pricing options available.', 'priceblueprint-for-woocommerce' ),
			],
		] );
	}
}
