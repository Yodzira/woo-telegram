<?php
/**
 * Woo to Telegram integration — inside QA container (no WooCommerce here):
 * queue mechanics, flood gate, retry path with an intentionally fake token.
 *
 *   docker exec infra-wordpress-1 wp eval-file /tmp/wtn-integration.php --allow-root
 */

defined( 'ABSPATH' ) || exit;

$GLOBALS['pass'] = 0;
$GLOBALS['fail'] = 0;

function check( $label, $cond ) {
	if ( $cond ) {
		$GLOBALS['pass']++;
		echo "  ok   {$label}\n";
	} else {
		$GLOBALS['fail']++;
		echo "  FAIL {$label}\n";
	}
}

echo "== Woo to Telegram integration ==\n";

check( 'plugin active', is_plugin_active( 'woo-telegram/woo-telegram.php' ) );
check( 'classes loaded', class_exists( 'WTN_Orders' ) && class_exists( 'WTN_Telegram' ) );
check( 'cron scheduled on activation', (bool) wp_next_scheduled( 'wtn_process_queue' ) );

// Settings round-trip.
$clean = WTN_Settings::save(
	array(
		'token'    => '123456:FakeTokenForRetryPath_Abc',
		'chat'     => '424242',
		'enabled'  => '1',
		'statuses' => array( 'new_order' ),
	)
);
check( 'settings saved and sanitized', '123456:FakeTokenForRetryPath_Abc' === $clean['token'] && array( 'new_order' ) === $clean['statuses'] );
check( 'settings reload from DB', WTN_Settings::get()['chat'] === '424242' );

// Enqueue a real payload through the flood gate.
WTN_Orders::enqueue(
	array(
		'number'   => '1042',
		'total'    => '12400.00',
		'currency' => '₽',
		'status'   => 'new',
		'items'    => array( array( 'iPhone-чехол', 2 ) ),
		'mask'     => true,
	)
);
$queue = (array) get_option( WTN_Orders::QUEUE_OPTION, array() );
check( 'payload enqueued', 1 === count( $queue ) && '1042' === $queue[0]['number'] );
check( 'delivery event scheduled', (bool) wp_next_scheduled( 'wtn_send_single', array( 0 ) ) );

// Message built from the queued payload.
$text = WTN_Message::order( $queue[0] );
check( 'message contains order data', false !== strpos( $text, 'Заказ #1042' ) && false !== strpos( $text, 'iPhone-чехол' ) );

// Retry path: fake token => Telegram answers 401, one retry must be scheduled.
WTN_Orders::send_single( 0 );
$queue = (array) get_option( WTN_Orders::QUEUE_OPTION, array() );
$retries = (array) get_option( WTN_Orders::RETRY_OPTION, array() );
check( 'failed send stays in queue', isset( $queue[0] ) );
check( 'retry attempt registered', isset( $retries[0] ) && 1 === (int) $retries[0] );
check( 'retry event scheduled', (bool) wp_next_scheduled( 'wtn_send_single', array( 0 ) ) );

// Flood gate: a burst collapses (queue keeps growing, no per-order events).
for ( $i = 0; $i < 14; $i++ ) {
	WTN_Orders::enqueue( array( 'number' => 'burst-' . $i, 'total' => '1.00' ) );
}
$queue = (array) get_option( WTN_Orders::QUEUE_OPTION, array() );
check( 'burst accumulated in queue', count( $queue ) >= 14 );
check( 'flood window tracked', count( (array) get_option( WTN_Orders::FLOOD_OPTION, array() ) ) > 0 );

// Cleanup everything.
delete_option( 'wtn_settings' );
delete_option( WTN_Orders::QUEUE_OPTION );
delete_option( WTN_Orders::FLOOD_OPTION );
delete_option( WTN_Orders::RETRY_OPTION );
wp_clear_scheduled_hook( 'wtn_send_single' );
wp_clear_scheduled_hook( 'wtn_process_queue' );
check( 'cleanup done', ! get_option( 'wtn_settings' ) && ! get_option( WTN_Orders::QUEUE_OPTION ) );

printf( "\n== Woo to Telegram integration: %d pass, %d fail ==\n", $GLOBALS['pass'], $GLOBALS['fail'] );
exit( $GLOBALS['fail'] > 0 ? 1 : 0 );
