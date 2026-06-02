<?php
/**
 * Contract for price calculation implementations.
 *
 * @package PRBP\Core
 */

namespace PRBP\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface PricingEngineInterface {

	/**
	 * Calculate the total price for a product given a set of attribute selections.
	 *
	 * @param int   $product_id The WC product post ID.
	 * @param array $selections Associative array: ['pa_color' => 'red', 'pa_size' => 'xl']
	 * @return float Computed total price (base + additions).
	 */
	public function calculate( int $product_id, array $selections ): float;
}
