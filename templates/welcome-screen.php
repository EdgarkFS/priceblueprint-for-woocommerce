<?php
/**
 * Welcome screen template.
 *
 * @package PRBP\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="prbp-welcome">

	<div class="prbp-welcome-hero">
		<div class="prbp-welcome-hero-inner">
			<div class="prbp-welcome-chip"><?php esc_html_e( 'Getting started', 'priceblueprint-for-woocommerce' ); ?></div>
			<h1><?php esc_html_e( 'Welcome to PriceBlueprint', 'priceblueprint-for-woocommerce' ); ?></h1>
			<p><?php esc_html_e( 'Set up pricing rules once. Apply them across your entire catalog — no variations needed.', 'priceblueprint-for-woocommerce' ); ?></p>
		</div>
	</div>

	<div class="prbp-welcome-steps">
		<p class="prbp-welcome-steps-title"><?php esc_html_e( 'Here\'s how it works', 'priceblueprint-for-woocommerce' ); ?></p>
		<ul class="prbp-welcome-step-list">
			<li class="prbp-welcome-step">
				<div class="prbp-welcome-step-num">1</div>
				<div class="prbp-welcome-step-body">
					<strong><?php esc_html_e( 'Create a Blueprint', 'priceblueprint-for-woocommerce' ); ?></strong>
					<span><?php esc_html_e( 'Define your pricing rules in one place — one blueprint can cover your whole catalog.', 'priceblueprint-for-woocommerce' ); ?></span>
				</div>
			</li>
			<li class="prbp-welcome-step">
				<div class="prbp-welcome-step-num">2</div>
				<div class="prbp-welcome-step-body">
					<strong><?php esc_html_e( 'Add rules', 'priceblueprint-for-woocommerce' ); ?></strong>
					<span><?php esc_html_e( 'Set what each attribute value adds to the base price. Size XL → +$10, Material Oak → +$25.', 'priceblueprint-for-woocommerce' ); ?></span>
				</div>
			</li>
			<li class="prbp-welcome-step">
				<div class="prbp-welcome-step-num">3</div>
				<div class="prbp-welcome-step-body">
					<strong><?php esc_html_e( 'Assign to a product', 'priceblueprint-for-woocommerce' ); ?></strong>
					<span><?php esc_html_e( 'Link the blueprint to any configurable product. Update a rule once — every linked product updates instantly.', 'priceblueprint-for-woocommerce' ); ?></span>
				</div>
			</li>
		</ul>
	</div>

	<div class="prbp-welcome-actions">
		<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=price_blueprint' ) ); ?>"
		   class="prbp-welcome-cta">
			<?php esc_html_e( 'Create your first Blueprint', 'priceblueprint-for-woocommerce' ); ?> &rarr;
		</a>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=price_blueprint' ) ); ?>"
		   class="prbp-welcome-skip">
			<?php esc_html_e( 'Skip for now', 'priceblueprint-for-woocommerce' ); ?>
		</a>
	</div>

</div>