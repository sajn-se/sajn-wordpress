<?php
/**
 * The last submissions the plugin handled, shown under sajn → Log.
 *
 * @package sajn
 */

defined( 'ABSPATH' ) || exit;

final class Sajn_Log {

	const OPTION      = 'sajn_log';
	const MAX_ENTRIES = 50;

	public static function add( $connection_name, $success, $message, $document_id = '' ) {
		$entries = self::all();
		array_unshift(
			$entries,
			array(
				'time'        => time(),
				'connection'  => (string) $connection_name,
				'success'     => (bool) $success,
				'message'     => (string) $message,
				'document_id' => (string) $document_id,
			)
		);
		update_option( self::OPTION, array_slice( $entries, 0, self::MAX_ENTRIES ), false );
	}

	public static function all() {
		$entries = get_option( self::OPTION, array() );
		return is_array( $entries ) ? $entries : array();
	}
}
