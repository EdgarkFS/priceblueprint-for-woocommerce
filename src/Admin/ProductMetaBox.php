<?php
/**
 * Adds a "PriceBlueprint" tab inside WooCommerce's product data metabox.
 *
 * The tab and its panel are hidden for all product types except
 * prbp_configurable_product — WooCommerce JS handles show/hide automatically
 * via the show_if_prbp_prbp_configurable_product CSS class.
 *
 * @package PRBP\Admin
 */

namespace PRBP\Admin;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProductMetaBox {

	public static function register(): void {
		add_filter( 'woocommerce_product_data_tabs',    [ self::class, 'addTab' ],        10, 1 );
		add_action( 'woocommerce_product_data_panels',  [ self::class, 'renderPanel' ] );
		add_action( 'woocommerce_process_product_meta', [ self::class, 'save' ],          10, 1 );
		add_action( 'admin_notices',                    [ self::class, 'showPriceError' ] );
	}

	/**
	 * Add a PriceBlueprint tab to the WooCommerce product data tab bar.
	 *
	 * @param array<string, array<string, mixed>> $tabs
	 * @return array<string, array<string, mixed>>
	 */
	public static function addTab( array $tabs ): array {
		$tabs['priceblueprint'] = [
			'label'    => __( 'PriceBlueprint', 'priceblueprint-for-woocommerce' ),
			'target'   => 'priceblueprint_product_data',
			'class'    => [ 'show_if_prbp_configurable_product' ],
			'priority' => 80,
		];
		return $tabs;
	}

	/**
	 * Render the panel content (shown when the PriceBlueprint tab is active).
	 */
	public static function renderPanel(): void {
		global $post;
		if ( ! $post ) {
			return;
		}

		$templates = get_posts( [
			'post_type'      => 'price_blueprint',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		$current = (int) get_post_meta( $post->ID, 'prbp_template_id', true );
		if ( ! $current ) {
			$current = (int) get_post_meta( $post->ID, '_prbp_template_id', true );
		}

		wp_nonce_field( 'prbp_product_meta_nonce', 'prbp_product_meta_nonce_field' );
		?>
		<div id="priceblueprint_product_data" class="panel woocommerce_options_panel show_if_prbp_configurable_product">
			<div class="options_group">
				<?php
				woocommerce_wp_select( [
					'id'          => 'prbp_template_id',
					'label'       => __( 'Price Blueprint', 'priceblueprint-for-woocommerce' ),
					'description' => __( 'Select the template that defines how this product\'s price is calculated.', 'priceblueprint-for-woocommerce' ),
					'desc_tip'    => true,
					'value'       => $current,
					'options'     => self::buildOptions( $templates ),
				] );
				?>
				<?php if ( empty( $templates ) ) : ?>
				<p class="form-field">
					<span></span>
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=price_blueprint' ) ); ?>">
						<?php esc_html_e( '+ Create your first Price Blueprint', 'priceblueprint-for-woocommerce' ); ?>
					</a>
				</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Build the <select> options array.
	 *
	 * @param \WP_Post[] $templates
	 * @return array<string, string>
	 */
	private static function buildOptions( array $templates ): array {
		$options = [ '' => __( '— Select a Price Blueprint —', 'priceblueprint-for-woocommerce' ) ];
		foreach ( $templates as $template ) {
			$options[ $template->ID ] = sprintf( '%s (#%d)', $template->post_title, $template->ID );
		}
		return $options;
	}

	public static function showPriceError(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['prbp_price_error'] ) ) {
			return;
		}
		echo '<div class="notice notice-error is-dismissible"><p>'
			. esc_html__( 'Regular price is required for Configurable Products.', 'priceblueprint-for-woocommerce' )
			. '</p></div>';
	}

	public static function save( int $post_id ): void {
		if ( ! isset( $_POST['prbp_product_meta_nonce_field'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['prbp_product_meta_nonce_field'] ) ),
				'prbp_product_meta_nonce'
			)
		) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$product_type = isset( $_POST['product-type'] ) ? sanitize_key( wp_unslash( $_POST['product-type'] ) ) : '';
		if ( 'prbp_configurable_product' === $product_type ) {
			$regular_price = isset( $_POST['_regular_price'] ) ? sanitize_text_field( wp_unslash( $_POST['_regular_price'] ) ) : '';
			if ( '' === $regular_price ) {
				WC()->session?->set( 'prbp_price_error_' . $post_id, true );
				add_filter( 'redirect_post_location', static function ( string $location ) use ( $post_id ): string {
					return add_query_arg( 'prbp_price_error', 1, $location );
				} );
			}
		}

		$template_id = isset( $_POST['prbp_template_id'] ) ? absint( $_POST['prbp_template_id'] ) : 0;
		update_post_meta( $post_id, 'prbp_template_id', $template_id );
		delete_post_meta( $post_id, '_prbp_template_id' );
	}
}
