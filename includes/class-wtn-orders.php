<?php
/**
 * Woo hooks → payload → queue. Queue processing with retries.
 *
 * @package WooToTelegram
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTN_Orders {

	const QUEUE_OPTION = 'wtn_queue';
	const FLOOD_OPTION = 'wtn_flood_window';
	const RETRY_OPTION = 'wtn_retries';

	public static function boot() {
		add_action( 'woocommerce_new_order', array( __CLASS__, 'on_new_order' ), 10, 2 );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'on_status_changed' ), 10, 4 );
		add_action( 'wtn_send_single', array( __CLASS__, 'send_single' ), 10, 1 );
		add_action( 'wtn_process_queue', array( __CLASS__, 'process_queue' ) );
	}

	/**
	 * Normalize a WC_Order (or a plain array in tests) into the payload.
	 *
	 * @param mixed $order WC_Order or payload array.
	 * @return array
	 */
	public static function order_payload( $order ) {
		if ( is_array( $order ) ) {
			return $order;
		}
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_order_number' ) ) {
			return array();
		}

		$items = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = array( $item->get_name(), (int) $item->get_quantity() );
		}

		$settings = WTN_Settings::get();
		$payload  = array(
			'number'   => (string) $order->get_order_number(),
			'total'    => (string) $order->get_total(),
			'currency' => (string) $order->get_currency(),
			'status'   => 'new',
			'items'    => $items,
			'payment'  => (string) $order->get_payment_method_title(),
			'city'     => (string) ( $order->get_billing_city() ?? '' ),
			'mask'     => ! empty( $settings['mask_contacts'] ),
		);

		return $payload;
	}

	/**
	 * New order hook.
	 *
	 * @param int   $order_id Order id.
	 * @param mixed $order    WC_Order.
	 * @return void
	 */
	public static function on_new_order( $order_id, $order = null ) {
		$settings = WTN_Settings::get();
		if ( empty( $settings['enabled'] ) || ! in_array( 'new_order', $settings['statuses'], true ) ) {
			return;
		}
		self::enqueue( self::order_payload( $order ? $order : self::payload_from_id( $order_id ) ) );
	}

	/**
	 * Status change hook.
	 *
	 * @param int    $order_id   Order id.
	 * @param string $from       Old status.
	 * @param string $to         New status.
	 * @param mixed  $order      WC_Order.
	 * @return void
	 */
	public static function on_status_changed( $order_id, $from, $to, $order = null ) {
		$settings = WTN_Settings::get();
		if ( empty( $settings['enabled'] ) || empty( $settings['status_change'] ) ) {
			return;
		}
		if ( ! in_array( $to, $settings['statuses'], true ) ) {
			return;
		}
		self::enqueue(
			array(
				'number'   => (string) $order_id,
				'status'   => '',
				'__status' => WTN_Message::status_change( $order_id, $from, $to ),
			)
		);
	}

	/**
	 * Add a payload to the queue respecting the flood gate, schedule delivery.
	 *
	 * @param array $payload Normalized payload.
	 * @return void
	 */
	public static function enqueue( array $payload ) {
		if ( ! $payload ) {
			return;
		}

		$flood = new WTN_Flood( (array) get_option( self::FLOOD_OPTION, array() ) );
		$mode  = $flood->register( time() );
		update_option( self::FLOOD_OPTION, $flood->export(), false );

		$queue = (array) get_option( self::QUEUE_OPTION, array() );
		$queue[] = $payload;
		update_option( self::QUEUE_OPTION, $queue, false );

		if ( 'buffered' === $mode && count( $queue ) > 1 ) {
			// A burst: everything collapses into the next digest pass.
			return;
		}

		if ( ! wp_next_scheduled( 'wtn_send_single', array( count( $queue ) - 1 ) ) ) {
			wp_schedule_single_event( time() + 2, 'wtn_send_single', array( count( $queue ) - 1 ) );
		}
	}

	/**
	 * Send one queued payload (cron callback).
	 *
	 * @param int $index Queue index.
	 * @return void
	 */
	public static function send_single( $index = 0 ) {
		$settings = WTN_Settings::get();
		$queue    = (array) get_option( self::QUEUE_OPTION, array() );
		if ( ! isset( $queue[ $index ] ) || '' === $settings['token'] || '' === $settings['chat'] ) {
			self::drop( $index );

			return;
		}

		$payload = $queue[ $index ];
		$text    = isset( $payload['__status'] ) ? $payload['__status'] : WTN_Message::order( $payload );
		$telegram = new WTN_Telegram();
		$ok       = $telegram->send( $settings['token'], $settings['chat'], $text );

		if ( $ok ) {
			self::drop( $index );

			return;
		}

		// Retry up to twice (1 min, 5 min), then give up quietly.
		$retries = (array) get_option( self::RETRY_OPTION, array() );
		$attempts = isset( $retries[ $index ] ) ? (int) $retries[ $index ] : 0;
		if ( $attempts < 2 ) {
			$retries[ $index ] = $attempts + 1;
			update_option( self::RETRY_OPTION, $retries, false );
			wp_schedule_single_event( time() + ( 0 === $attempts ? 60 : 300 ), 'wtn_send_single', array( $index ) );
		} else {
			unset( $retries[ $index ] );
			update_option( self::RETRY_OPTION, $retries, false );
			self::drop( $index );
		}
	}

	/**
	 * Hourly sweep for stuck entries.
	 *
	 * @return void
	 */
	public static function process_queue() {
		$queue = (array) get_option( self::QUEUE_OPTION, array() );
		if ( $queue ) {
			self::send_single( 0 );
		}
	}

	/**
	 * Drop index from the queue.
	 *
	 * @param int $index Index.
	 * @return void
	 */
	private static function drop( $index ) {
		$queue = (array) get_option( self::QUEUE_OPTION, array() );
		if ( isset( $queue[ $index ] ) ) {
			unset( $queue[ $index ] );
			update_option( self::QUEUE_OPTION, array_values( $queue ), false );
		}
	}

	/**
	 * Fallback payload loader when Woo passes only the id.
	 *
	 * @param int $order_id Order id.
	 * @return array
	 */
	private static function payload_from_id( $order_id ) {
		if ( function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				return self::order_payload( $order );
			}
		}

		return array( 'number' => (string) $order_id );
	}
}
