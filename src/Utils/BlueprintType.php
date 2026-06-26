<?php
/**
 * @package PRBP\Utils
 */

namespace PRBP\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BlueprintType {

	public static function get( int $blueprint_id ): string {
		$type = (string) get_post_meta( $blueprint_id, 'prbp_blueprint_type', true );
		return ( 'informational' === $type ) ? 'informational' : 'pricing';
	}

	public static function isInformational( int $blueprint_id ): bool {
		return 'informational' === self::get( $blueprint_id );
	}
}
