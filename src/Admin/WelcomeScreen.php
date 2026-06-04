<?php
/**
 * One-time welcome screen shown after first activation.
 *
 * @package PRBP\Admin
 */

namespace PRBP\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WelcomeScreen {

	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'addPage' ] );
		add_action( 'admin_init', [ self::class, 'maybeRedirect' ] );
	}

	public static function addPage(): void {
		add_dashboard_page(
			__( 'Welcome to PriceBlueprint', 'priceblueprint-for-woocommerce' ),
			'',
			'manage_options',
			'prbp-welcome',
			[ self::class, 'render' ]
		);
	}

	public static function maybeRedirect(): void {
		if ( ! get_option( 'prbp_show_welcome' ) ) {
			return;
		}

		// Skip on bulk activation or network activation.
		if ( isset( $_GET['activate-multi'] ) || is_network_admin() ) {
			return;
		}

		// Let Freemius finish its opt-in flow first.
		if ( function_exists( 'pfwp_fs' ) && pfwp_fs()->is_activation_mode() ) {
			return;
		}

		// Already on the welcome page — don't redirect again.
		if ( isset( $_GET['page'] ) && 'prbp-welcome' === $_GET['page'] ) {
			return;
		}

		wp_safe_redirect( admin_url( 'index.php?page=prbp-welcome' ) );
		exit;
	}

	public static function render(): void {
		delete_option( 'prbp_show_welcome' );
		?>
		<style>
			body.index_page_prbp-welcome #wpcontent {
				padding-left: 0;
			}
			.prbp-welcome {
				max-width: 700px;
				margin: 40px auto;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
			}

			/* ── Hero ── */
			.prbp-welcome-hero {
				position: relative;
				overflow: hidden;
				background: #0F172A;
				border-radius: 12px;
				padding: 48px 52px;
				color: #F1F5F9;
				margin-bottom: 12px;
			}
			.prbp-welcome-hero::before {
				content: '';
				position: absolute;
				top: -60px; right: -60px;
				width: 220px; height: 220px;
				border-radius: 50%;
				background: #0D9488;
				opacity: .07;
				pointer-events: none;
			}
			.prbp-welcome-hero::after {
				content: '';
				position: absolute;
				bottom: -80px; right: 40px;
				width: 260px; height: 260px;
				border-radius: 50%;
				background: #0D9488;
				opacity: .04;
				pointer-events: none;
			}
			.prbp-welcome-hero-inner {
				position: relative;
				z-index: 1;
			}
			.prbp-welcome-chip {
				display: inline-flex;
				align-items: center;
				gap: 5px;
				background: rgba(13,148,136,.18);
				border: 1px solid rgba(13,148,136,.35);
				color: #5EEAD4;
				border-radius: 20px;
				padding: 3px 12px;
				font-size: 10px;
				font-weight: 700;
				letter-spacing: .09em;
				text-transform: uppercase;
				margin-bottom: 20px;
			}
			.prbp-welcome-hero h1 {
				font-size: 30px;
				font-weight: 700;
				color: #F1F5F9;
				margin: 0 0 12px;
				line-height: 1.2;
			}
			.prbp-welcome-hero p {
				font-size: 15px;
				color: #94A3B8;
				margin: 0;
				line-height: 1.6;
				max-width: 480px;
			}

			/* ── Steps ── */
			.prbp-welcome-steps {
				background: #fff;
				border: 1px solid #e0e0e0;
				border-radius: 12px;
				padding: 32px 40px;
				margin-bottom: 12px;
			}
			.prbp-welcome-steps-title {
				font-size: 11px;
				font-weight: 700;
				text-transform: uppercase;
				letter-spacing: .08em;
				color: #94A3B8;
				margin: 0 0 24px;
			}
			.prbp-welcome-step-list {
				display: flex;
				flex-direction: column;
				gap: 20px;
				list-style: none;
				margin: 0;
				padding: 0;
			}
			.prbp-welcome-step {
				display: flex;
				align-items: flex-start;
				gap: 16px;
			}
			.prbp-welcome-step-num {
				flex-shrink: 0;
				width: 32px;
				height: 32px;
				border-radius: 50%;
				background: rgba(13,148,136,.1);
				border: 1px solid rgba(13,148,136,.25);
				color: #0D9488;
				font-size: 13px;
				font-weight: 700;
				display: flex;
				align-items: center;
				justify-content: center;
				margin-top: 1px;
			}
			.prbp-welcome-step-body strong {
				display: block;
				font-size: 14px;
				font-weight: 600;
				color: #1d2327;
				margin-bottom: 2px;
			}
			.prbp-welcome-step-body span {
				font-size: 13px;
				color: #646970;
				line-height: 1.5;
			}

			/* ── Actions ── */
			.prbp-welcome-actions {
				display: flex;
				align-items: center;
				gap: 16px;
				padding: 0 4px;
			}
			.prbp-welcome-cta {
				display: inline-flex;
				align-items: center;
				gap: 6px;
				background: #0D9488;
				color: #fff !important;
				border: none;
				border-radius: 6px;
				padding: 10px 22px;
				font-size: 14px;
				font-weight: 600;
				text-decoration: none !important;
				cursor: pointer;
				transition: background .15s;
				line-height: 1.4;
			}
			.prbp-welcome-cta:hover {
				background: #0a7a70;
				color: #fff !important;
			}
			.prbp-welcome-skip {
				font-size: 13px;
				color: #646970 !important;
				text-decoration: none !important;
				transition: color .12s;
			}
			.prbp-welcome-skip:hover {
				color: #1d2327 !important;
			}
		</style>

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
		<?php
	}
}
