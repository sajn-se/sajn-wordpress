<?php
/**
 * Removes the plugin's options when the plugin is deleted.
 *
 * @package sajn
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'sajn_settings' );
delete_option( 'sajn_connections' );
delete_option( 'sajn_log' );
