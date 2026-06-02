<?php
/**
 * Validates a decoded array of rule objects before saving to post meta.
 *
 * @package PRBP\Utils
 */

namespace PRBP\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RuleValidator {

	/**
	 * @param  array<int, array<string, mixed>> $rules Raw decoded JSON array of rule objects.
	 * @return array{valid: bool, errors: string[]}
	 */
	public static function validate( array $rules ): array {
		$errors = [];
		$seen   = [];

		foreach ( $rules as $i => $rule ) {
			$row = $i + 1;

			if ( empty( $rule['attribute'] ) || ! preg_match( '/^pa_[a-z0-9_]+$/', $rule['attribute'] ) ) {
				/* translators: %d: Row number in the pricing rules table */
				$errors[] = sprintf( __( 'Row %d: invalid or missing attribute.', 'priceblueprint-for-woocommerce' ), $row );
			}

			if ( empty( $rule['value_slugs'] ) || ! is_array( $rule['value_slugs'] ) ) {
				/* translators: %d: Row number in the pricing rules table */
				$errors[] = sprintf( __( 'Row %d: value is required.', 'priceblueprint-for-woocommerce' ), $row );
			} else {
				$labels = $rule['value_labels'] ?? null;
				if ( ! is_array( $labels ) || count( $labels ) !== count( $rule['value_slugs'] ) ) {
					/* translators: %d: Row number in the pricing rules table */
					$errors[] = sprintf( __( 'Row %d: value slugs and labels must have the same number of entries.', 'priceblueprint-for-woocommerce' ), $row );
				}

				foreach ( $rule['value_slugs'] as $slug ) {
					$dup = ( $rule['attribute'] ?? '' ) . '|' . $slug;
					if ( isset( $seen[ $dup ] ) ) {
						$errors[] = sprintf(
							/* translators: 1: Row number, 2: Attribute slug, 3: Value slug */
							__( 'Row %1$d: duplicate rule (%2$s / %3$s).', 'priceblueprint-for-woocommerce' ),
							$row,
							$rule['attribute'] ?? '',
							$slug
						);
					}
					$seen[ $dup ] = true;
				}
			}

			if ( ! isset( $rule['price'] ) || ! is_numeric( $rule['price'] ) || (float) $rule['price'] < 0 ) {
				/* translators: %d: Row number in the pricing rules table */
				$errors[] = sprintf( __( 'Row %d: price must be a non-negative number.', 'priceblueprint-for-woocommerce' ), $row );
			}
		}

		return [ 'valid' => empty( $errors ), 'errors' => $errors ];
	}
}
