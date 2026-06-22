<?php
/**
 * Static per-request cache for price blueprint rules.
 *
 * Avoids repeated get_post_meta() calls within one page load.
 *
 * @package PRBP\Utils
 */

namespace PRBP\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RulesCache {

	/** @var array<string, array<int, array<string, mixed>>> */
	private static array $cache = [];

	/**
	 * Get rules for a template, optionally filtering to active-only.
	 *
	 * @param int  $template_id Post ID of the price_blueprint CPT.
	 * @param bool $active_only When true (default), returns only status==="active" rules.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get( int $template_id, bool $active_only = true ): array {
		$key = $template_id . '_' . ( $active_only ? 'active' : 'all' );

		if ( ! isset( self::$cache[ $key ] ) ) {
			$raw     = get_post_meta( $template_id, 'prbp_template_rules', true );
			$decoded = $raw ? json_decode( $raw, true ) : [];

			if ( ! is_array( $decoded ) ) {
				$decoded = [];
			}

			$sections = RulesFormat::normalize( $decoded );
			$all      = RulesFormat::flatten( $sections );

			self::$cache[ $template_id . '_all' ]    = $all;
			self::$cache[ $template_id . '_active' ] = array_values(
				array_filter( $all, fn( array $r ): bool => ( $r['status'] ?? 'active' ) === 'active' )
			);
		}

		return self::$cache[ $key ];
	}

	/**
	 * Invalidate cached rules for a given template.
	 *
	 * Call this after saving new rules so the next request re-reads from DB.
	 *
	 * @param int $template_id
	 */
	public static function flush( int $template_id ): void {
		unset(
			self::$cache[ $template_id . '_active' ],
			self::$cache[ $template_id . '_all' ]
		);
	}
}
