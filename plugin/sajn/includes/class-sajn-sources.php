<?php
/**
 * Form plugins a connection can listen to.
 *
 * @package sajn
 */

defined( 'ABSPATH' ) || exit;

abstract class Sajn_Source {

	/** Stable id stored on connections, such as `cf7`. */
	abstract public function id();

	abstract public function label();

	/** True when the form plugin is installed and active. */
	abstract public function is_available();

	/** Hooks into the form plugin's submission flow. */
	abstract public function boot();

	/** @return array<int, string> Form id => form title. */
	abstract public function forms();

	/** @return array<string, string> Tag name => field label. A submission resolves `{tag}` to the field's value. */
	abstract public function fields( $form_id );
}

final class Sajn_Sources {

	/** @var Sajn_Source[] */
	private static $sources = array();

	public static function register( Sajn_Source $source ) {
		if ( ! $source->is_available() ) {
			return;
		}
		self::$sources[ $source->id() ] = $source;
		$source->boot();
	}

	/** @return Sajn_Source[] */
	public static function all() {
		return self::$sources;
	}

	/** @return Sajn_Source|null */
	public static function get( $id ) {
		return isset( self::$sources[ $id ] ) ? self::$sources[ $id ] : null;
	}
}
