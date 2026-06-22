<?php
/**
 * Validates a decoded array of attribute sections before saving to post meta.
 *
 * @package PRBP\Utils
 */

namespace PRBP\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RuleValidator {

	/**
	 * @param  array<int, array<string, mixed>> $sections Raw decoded JSON array of section objects.
	 * @return array{valid: bool, errors: string[]}
	 */
	public static function validate( array $sections ): array {
		$errors          = [];
		$seen_attributes = [];

		foreach ( $sections as $i => $section ) {
			$section_num = $i + 1;
			$attribute   = $section['attribute'] ?? '';
			$label       = $section['attribute_label'] ?? $attribute;

			if ( empty( $attribute ) || ! preg_match( '/^pa_[a-z0-9_]+$/', $attribute ) ) {
				/* translators: %d: Section number in the pricing rules editor */
				$errors[] = sprintf( __( 'Section %d: invalid or missing attribute.', 'priceblueprint-for-woocommerce' ), $section_num );
				continue;
			}

			if ( isset( $seen_attributes[ $attribute ] ) ) {
				/* translators: %s: Attribute label */
				$errors[] = sprintf( __( 'Duplicate section for attribute: %s.', 'priceblueprint-for-woocommerce' ), $label );
			}
			$seen_attributes[ $attribute ] = true;

			$rows = $section['rows'] ?? null;
			if ( empty( $rows ) || ! is_array( $rows ) ) {
				/* translators: %s: Attribute label */
				$errors[] = sprintf( __( '%s: at least one value is required.', 'priceblueprint-for-woocommerce' ), $label );
				continue;
			}

			$seen_values = [];

			foreach ( $rows as $j => $row ) {
				$row_num = $j + 1;

				if ( empty( $row['value_slugs'] ) || ! is_array( $row['value_slugs'] ) ) {
					/* translators: 1: Attribute label, 2: Row number within the section */
					$errors[] = sprintf( __( '%1$s, row %2$d: value is required.', 'priceblueprint-for-woocommerce' ), $label, $row_num );
				} else {
					$labels = $row['value_labels'] ?? null;
					if ( ! is_array( $labels ) || count( $labels ) !== count( $row['value_slugs'] ) ) {
						/* translators: 1: Attribute label, 2: Row number within the section */
						$errors[] = sprintf( __( '%1$s, row %2$d: value slugs and labels must have the same number of entries.', 'priceblueprint-for-woocommerce' ), $label, $row_num );
					}

					foreach ( $row['value_slugs'] as $slug ) {
						if ( isset( $seen_values[ $slug ] ) ) {
							/* translators: 1: Attribute label, 2: Row number within the section, 3: Value slug */
							$errors[] = sprintf( __( '%1$s, row %2$d: duplicate value (%3$s).', 'priceblueprint-for-woocommerce' ), $label, $row_num, $slug );
						}
						$seen_values[ $slug ] = true;
					}
				}

				if ( ! isset( $row['price'] ) || ! is_numeric( $row['price'] ) || (float) $row['price'] < 0 ) {
					/* translators: 1: Attribute label, 2: Row number within the section */
					$errors[] = sprintf( __( '%1$s, row %2$d: price must be a non-negative number.', 'priceblueprint-for-woocommerce' ), $label, $row_num );
				}
			}
		}

		return [ 'valid' => empty( $errors ), 'errors' => $errors ];
	}
}
