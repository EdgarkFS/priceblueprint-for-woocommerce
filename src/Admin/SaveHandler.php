<?php
/**
 * Validates and saves price blueprint rules.
 *
 * @package PRBP\Admin
 */

namespace PRBP\Admin;

use PRBP\Utils\RuleValidator;
use PRBP\Utils\RulesCache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SaveHandler {

	public static function register(): void {
		add_action( 'save_post_price_blueprint', [ self::class, 'handle' ],         10, 1 );
		add_action( 'admin_notices',              [ self::class, 'showErrorNotice' ], 10 );
	}

	public static function handle( int $post_id ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['prbp_rules_nonce_field'] )
			|| ! check_admin_referer( 'prbp_rules_nonce', 'prbp_rules_nonce_field' )
		) {
			return;
		}

		// JSON must not be run through a string sanitizer — content is validated by RuleValidator after decode.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw_json = isset( $_POST['prbp_rules_json'] ) ? wp_unslash( $_POST['prbp_rules_json'] ) : '';

		$rules = [];
		if ( ! empty( $raw_json ) ) {
			$decoded = json_decode( $raw_json, true );
			if ( is_array( $decoded ) ) {
				$rules = $decoded;
			}
		}

		$result = RuleValidator::validate( $rules );

		if ( ! $result['valid'] ) {
			$user_id = get_current_user_id();
			set_transient( 'prbp_save_error_' . $user_id, $result['errors'], 60 );

			$redirect = add_query_arg(
				[ 'prbp_error' => 1, 'post' => $post_id, 'action' => 'edit' ],
				admin_url( 'post.php' )
			);
			wp_safe_redirect( $redirect );
			exit;
		}

		update_post_meta( $post_id, 'prbp_template_rules', wp_json_encode( self::sanitizeRules( $rules ) ) );
		RulesCache::flush( $post_id );
	}

	/**
	 * Whitelist and sanitize each rule field before storage.
	 *
	 * @param  array<int, array<string, mixed>> $rules
	 * @return array<int, array<string, mixed>>
	 */
	private static function sanitizeRules( array $rules ): array {
		$clean = [];

		foreach ( $rules as $rule ) {
			$clean[] = [
				'attribute'       => sanitize_key( $rule['attribute'] ?? '' ),
				'attribute_label' => sanitize_text_field( $rule['attribute_label'] ?? '' ),
				'value_ids'       => array_map( 'absint',              (array) ( $rule['value_ids']    ?? [] ) ),
				'value_slugs'     => array_map( 'sanitize_key',        (array) ( $rule['value_slugs']  ?? [] ) ),
				'value_labels'    => array_map( 'sanitize_text_field', (array) ( $rule['value_labels'] ?? [] ) ),
				'price'           => (string) max( 0.0, (float) ( $rule['price'] ?? 0 ) ),
				'operator'        => '+',
			];
		}

		return $clean;
	}

	public static function showErrorNotice(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'price_blueprint' !== $screen->post_type ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['prbp_error'] ) ) {
			return;
		}

		$user_id = get_current_user_id();
		$errors  = get_transient( 'prbp_save_error_' . $user_id );

		if ( ! $errors ) {
			return;
		}

		delete_transient( 'prbp_save_error_' . $user_id );

		echo '<div class="notice notice-error is-dismissible"><p>';
		echo '<strong>' . esc_html__( 'Could not save. Please fix the following errors:', 'priceblueprint-for-woocommerce' ) . '</strong>';
		echo '<ul>';
		foreach ( (array) $errors as $error ) {
			echo '<li>' . esc_html( $error ) . '</li>';
		}
		echo '</ul></p></div>';
	}
}
