<?php
/**
 * Adds contextual Help tabs to the Price Blueprint edit screen.
 *
 * @package PRBP\Admin
 */

namespace PRBP\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HelpTab {

	public static function register(): void {
		add_action( 'current_screen', [ self::class, 'addTabs' ] );
	}

	public static function addTabs(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'price_blueprint' !== $screen->post_type ) {
			return;
		}

		$screen->add_help_tab( [
			'id'      => 'prbp_overview',
			'title'   => __( 'Overview', 'priceblueprint-for-woocommerce' ),
			'content' => self::overview(),
		] );

		$screen->add_help_tab( [
			'id'      => 'prbp_rules',
			'title'   => __( 'Adding Rules', 'priceblueprint-for-woocommerce' ),
			'content' => self::rules(),
		] );

		$screen->add_help_tab( [
			'id'      => 'prbp_price',
			'title'   => __( 'How the Price is Calculated', 'priceblueprint-for-woocommerce' ),
			'content' => self::price(),
		] );

		$screen->add_help_tab( [
			'id'      => 'prbp_assign',
			'title'   => __( 'Assigning to a Product', 'priceblueprint-for-woocommerce' ),
			'content' => self::assign(),
		] );

		$screen->set_help_sidebar( self::sidebar() );
	}

	private static function overview(): string {
		return '<h2>' . esc_html__( 'What is a Price Blueprint?', 'priceblueprint-for-woocommerce' ) . '</h2>'
			. '<p>' . esc_html__( 'A Price Blueprint is a set of rules that controls how the price of a product is built. Instead of setting a fixed price on each product, you define the rules once in a template and then attach that template to as many products as you like.', 'priceblueprint-for-woocommerce' ) . '</p>'
			. '<p>' . esc_html__( 'When you update a rule in the template, every product that uses it will immediately reflect the new price — no need to edit products one by one.', 'priceblueprint-for-woocommerce' ) . '</p>'
			. '<p>' . esc_html__( 'Each product still has its own base price. The template only controls the add-ons that get added on top of that base.', 'priceblueprint-for-woocommerce' ) . '</p>';
	}

	private static function rules(): string {
		return '<h2>' . esc_html__( 'What is a Rule?', 'priceblueprint-for-woocommerce' ) . '</h2>'
			. '<p>' . esc_html__( 'Each rule answers the question: "If the customer picks this option, how much extra should be added to the price?"', 'priceblueprint-for-woocommerce' ) . '</p>'
			. '<p>' . esc_html__( 'A rule has three parts:', 'priceblueprint-for-woocommerce' ) . '</p>'
			. '<ul>'
			. '<li><strong>' . esc_html__( 'Attribute', 'priceblueprint-for-woocommerce' ) . '</strong> — ' . esc_html__( 'The characteristic being chosen, for example Color or Size. These come from your WooCommerce global attributes.', 'priceblueprint-for-woocommerce' ) . '</li>'
			. '<li><strong>' . esc_html__( 'Value', 'priceblueprint-for-woocommerce' ) . '</strong> — ' . esc_html__( 'The specific option the customer picks, for example Red or Large.', 'priceblueprint-for-woocommerce' ) . '</li>'
			. '<li><strong>' . esc_html__( 'Price add-on', 'priceblueprint-for-woocommerce' ) . '</strong> — ' . esc_html__( 'The amount added to the base price when this option is selected. Set it to 0 if the option should cost nothing extra.', 'priceblueprint-for-woocommerce' ) . '</li>'
			. '</ul>'
			. '<p>' . esc_html__( 'You can add as many rules as you need. Use the filter box to quickly find a rule when the list gets long.', 'priceblueprint-for-woocommerce' ) . '</p>';
	}

	private static function price(): string {
		return '<h2>' . esc_html__( 'How the Final Price is Calculated', 'priceblueprint-for-woocommerce' ) . '</h2>'
			. '<p>' . esc_html__( 'The price the customer pays is always:', 'priceblueprint-for-woocommerce' ) . '</p>'
			. '<p><strong>' . esc_html__( 'Base price + add-ons for each selected option = Total', 'priceblueprint-for-woocommerce' ) . '</strong></p>'
			. '<p>' . esc_html__( 'Example: a product has a base price of $20. The customer picks Color → Red (+$5) and Size → Large (+$3). The total is $20 + $5 + $3 = $28.', 'priceblueprint-for-woocommerce' ) . '</p>'
			. '<p>' . esc_html__( 'On the shop and product page, the displayed price shows the lowest possible total ("From $X"), calculated using the cheapest option for each attribute.', 'priceblueprint-for-woocommerce' ) . '</p>'
			. '<p>' . esc_html__( 'The price shown to the customer updates instantly as they make selections — no page reload needed.', 'priceblueprint-for-woocommerce' ) . '</p>'
			. '<p>' . esc_html__( 'If you update a rule after a customer has already added the product to their cart, the new price will apply the next time they visit the cart.', 'priceblueprint-for-woocommerce' ) . '</p>';
	}

	private static function assign(): string {
		return '<h2>' . esc_html__( 'Attaching a Template to a Product', 'priceblueprint-for-woocommerce' ) . '</h2>'
			. '<ol>'
			. '<li>' . esc_html__( 'Save your Price Blueprint first.', 'priceblueprint-for-woocommerce' ) . '</li>'
			. '<li>' . esc_html__( 'Go to Products and open the product you want to configure (or create a new one).', 'priceblueprint-for-woocommerce' ) . '</li>'
			. '<li>' . esc_html__( 'In the Product Data section, change the product type to Configurable Product.', 'priceblueprint-for-woocommerce' ) . '</li>'
			. '<li>' . esc_html__( 'Open the General tab and set a base price — this is the starting price before any options are added.', 'priceblueprint-for-woocommerce' ) . '</li>'
			. '<li>' . esc_html__( 'Open the PriceBlueprint tab and select the template from the dropdown.', 'priceblueprint-for-woocommerce' ) . '</li>'
			. '<li>' . esc_html__( 'Save the product.', 'priceblueprint-for-woocommerce' ) . '</li>'
			. '</ol>'
			. '<p>' . esc_html__( 'The same template can be assigned to multiple products. Each product keeps its own base price.', 'priceblueprint-for-woocommerce' ) . '</p>';
	}

	private static function sidebar(): string {
		return '<p><strong>' . esc_html__( 'Quick tips', 'priceblueprint-for-woocommerce' ) . '</strong></p>'
			. '<ul>'
			. '<li>' . esc_html__( 'Set a price add-on of 0 to offer a free option.', 'priceblueprint-for-woocommerce' ) . '</li>'
			. '<li>' . esc_html__( 'One template can be shared across many products.', 'priceblueprint-for-woocommerce' ) . '</li>'
			. '<li>' . esc_html__( 'Updating a rule takes effect immediately — no need to re-save products.', 'priceblueprint-for-woocommerce' ) . '</li>'
			. '<li>' . esc_html__( 'Disable a rule to hide it from customers without losing it.', 'priceblueprint-for-woocommerce' ) . '</li>'
			. '</ul>';
	}
}
