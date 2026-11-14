<?php
/**
 * Plugin Name:       sajn E-Signature
 * Plugin URI:        https://sajn.se
 * Description:       Send a sajn document for signing when a visitor submits a form. Works with Contact Form 7, WPForms, and Gravity Forms.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            sajn
 * Author URI:        https://sajn.se
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sajn
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'SAJN_VERSION', '0.1.0' );
define( 'SAJN_PLUGIN_FILE', __FILE__ );
define( 'SAJN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once SAJN_PLUGIN_DIR . 'includes/class-sajn-api.php';
require_once SAJN_PLUGIN_DIR . 'includes/class-sajn-log.php';
require_once SAJN_PLUGIN_DIR . 'includes/class-sajn-connections.php';
require_once SAJN_PLUGIN_DIR . 'includes/class-sajn-sources.php';
require_once SAJN_PLUGIN_DIR . 'includes/sources/class-sajn-source-cf7.php';
require_once SAJN_PLUGIN_DIR . 'includes/sources/class-sajn-source-wpforms.php';
require_once SAJN_PLUGIN_DIR . 'includes/sources/class-sajn-source-gravity-forms.php';
require_once SAJN_PLUGIN_DIR . 'includes/class-sajn-admin.php';

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'sajn', false, dirname( plugin_basename( SAJN_PLUGIN_FILE ) ) . '/languages' );

		Sajn_Sources::register( new Sajn_Source_CF7() );
		Sajn_Sources::register( new Sajn_Source_WPForms() );
		Sajn_Sources::register( new Sajn_Source_Gravity_Forms() );

		if ( is_admin() ) {
			Sajn_Admin::init();
		}
	}
);
