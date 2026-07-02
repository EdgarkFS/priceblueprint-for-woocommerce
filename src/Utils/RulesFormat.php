<?php
/**
 * Converts between the legacy flat rule-array format and the current
 * attribute-sections format used by prbp_template_rules.
 *
 * @package PRBP\Utils
 */

namespace PRBP\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RulesFormat {

	/**
	 * Normalize a decoded prbp_template_rules value into the sections shape.
	 *
	 * Entries that already have a 'rows' array are assumed to be in the
	 * current format and are returned unchanged. Otherwise, entries are
	 * treated as old-format flat rules (each carrying its own 'attribute'
	 * and 'value_slugs') and grouped into one section per attribute,
	 * preserving the order attributes first appear in.
	 *
	 * @param  array<int, array<string, mixed>> $decoded
	 * @return array<int, array{attribute: string, attribute_label: string, rows: array<int, array<string, mixed>>}>
	 */
	public static function normalize( array $decoded ): array {
		$is_sections = true;
		foreach ( $decoded as $entry ) {
			if ( ! isset( $entry['rows'] ) || ! is_array( $entry['rows'] ) ) {
				$is_sections = false;
				break;
			}
		}

		if ( $is_sections ) {
			return $decoded;
		}

		$sections = [];

		foreach ( $decoded as $rule ) {
			$attribute = $rule['attribute'] ?? '';

			if ( ! isset( $sections[ $attribute ] ) ) {
				$sections[ $attribute ] = [
					'attribute'       => $attribute,
					'attribute_label' => $rule['attribute_label'] ?? '',
					'rows'            => [],
				];
			}

			$row = [
				'value_ids'    => $rule['value_ids']    ?? [],
				'value_slugs'  => $rule['value_slugs']  ?? [],
				'value_labels' => $rule['value_labels'] ?? [],
				'price'        => $rule['price']        ?? '0',
			];
			if ( isset( $rule['status'] ) ) {
				$row['status'] = $rule['status'];
			}

			$sections[ $attribute ]['rows'][] = $row;
		}

		return array_values( $sections );
	}

	/**
	 * Flatten the sections shape back into one entry per row, matching the
	 * legacy flat-rule shape that runtime consumers (PriceRecalculator,
	 * CalculatePrice, AttributeSync, ProductPage, CartItemMeta) expect.
	 *
	 * @param  array<int, array{attribute: string, attribute_label: string, rows: array<int, array<string, mixed>>}> $sections
	 * @return array<int, array<string, mixed>>
	 */
	public static function flatten( array $sections ): array {
		$flat = [];

		foreach ( $sections as $section ) {
			$attribute = $section['attribute']       ?? '';
			$label     = $section['attribute_label'] ?? '';

			foreach ( (array) ( $section['rows'] ?? [] ) as $row ) {
				$entry = [
					'attribute'       => $attribute,
					'attribute_label' => $label,
					'value_ids'       => $row['value_ids']    ?? [],
					'value_slugs'     => $row['value_slugs']  ?? [],
					'value_labels'    => $row['value_labels'] ?? [],
					'price'           => $row['price']        ?? '0',
					'operator'        => '+',
				];
				if ( isset( $row['status'] ) ) {
					$entry['status'] = $row['status'];
				}
				if ( ! empty( $row['image_id'] ) ) {
					$entry['image_id'] = (int) $row['image_id'];
				}
				$flat[] = $entry;
			}
		}

		return $flat;
	}
}
