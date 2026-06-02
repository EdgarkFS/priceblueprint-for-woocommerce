<?php
/**
 * Contract for rule data retrieval implementations.
 *
 * @package PRBP\Core
 */

namespace PRBP\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface RuleRepositoryInterface {

	/**
	 * Return active rules only (status === 'active').
	 *
	 * @param int $template_id Post ID of the price_blueprint CPT.
	 * @return array<int, array<string, mixed>>
	 */
	public function getRules( int $template_id ): array;

	/**
	 * Return all rules including soft-deleted ones.
	 *
	 * @param int $template_id Post ID of the price_blueprint CPT.
	 * @return array<int, array<string, mixed>>
	 */
	public function getAllRules( int $template_id ): array;
}
