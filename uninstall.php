<?php
/**
 * Uninstall cleanup.
 *
 * @package WooToTelegram
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	return;
}

delete_option( 'wtn_settings' );
delete_option( 'wtn_queue' );
delete_option( 'wtn_flood_window' );
delete_option( 'wtn_retries' );
wp_clear_scheduled_hook( 'wtn_process_queue' );
wp_clear_scheduled_hook( 'wtn_send_single' );
