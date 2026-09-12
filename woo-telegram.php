<?php
/**
 * Plugin Name:       WooCommerce to Telegram
 * Plugin URI:        https://github.com/Yodzira/woo-telegram
 * Description:       Instant WooCommerce order notifications in Telegram — queued delivery with retries, flood protection, 60-second setup wizard. Order emails get lost; your Telegram doesn't.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Yodzira
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       woo-telegram
 * Woo:               0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WTN_VERSION', '0.1.0' );
define( 'WTN_FILE', __FILE__ );
define( 'WTN_DIR', __DIR__ );

spl_autoload_register(
	static function ( $class ) {
		if ( 0 !== strpos( $class, 'WTN_' ) ) {
			return;
		}
		$snake = strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', substr( $class, 4 ) ) );
		$snake = str_replace( '_', '-', $snake );
		$file  = WTN_DIR . '/includes/class-wtn-' . $snake . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

add_action( 'plugins_loaded', array( 'WTN_Plugin', 'boot' ), 20 );

register_activation_hook(
	__FILE__,
	static function () {
		if ( ! wp_next_scheduled( 'wtn_process_queue' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'wtn_process_queue' );
		}
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		wp_clear_scheduled_hook( 'wtn_process_queue' );
		wp_clear_scheduled_hook( 'wtn_send_single' );
	}
);
