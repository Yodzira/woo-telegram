<?php
/**
 * Message templates (pure): order text, digest text, truncation.
 *
 * @package WooToTelegram
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTN_Message {

	const TELEGRAM_LIMIT = 4096;

	/**
	 * Build the order message from a normalized payload.
	 *
	 * @param array $payload {number, total, currency, status, items:[[name,qty]], payment, city, mask}
	 * @return string
	 */
	public static function order( array $payload ) {
		$number   = isset( $payload['number'] ) ? (string) $payload['number'] : '?';
		$total    = isset( $payload['total'] ) ? (string) $payload['total'] : '';
		$currency = isset( $payload['currency'] ) ? (string) $payload['currency'] : '';
		$status   = isset( $payload['status'] ) ? (string) $payload['status'] : '';

		$head = '🛒 Заказ #' . $number;
		if ( '' !== $total ) {
			$head .= ' — ' . $total . ( '' !== $currency ? ' ' . $currency : '' );
		}
		if ( '' !== $status ) {
			$head .= ' (' . $status . ')';
		}

		$lines = array( $head );
		foreach ( (array) ( isset( $payload['items'] ) ? $payload['items'] : array() ) as $item ) {
			$name = isset( $item[0] ) ? (string) $item[0] : '';
			$qty  = isset( $item[1] ) ? (int) $item[1] : 1;
			$lines[] = '• ' . $name . ( $qty > 1 ? ' ×' . $qty : '' );
		}
		if ( isset( $payload['payment'] ) && '' !== (string) $payload['payment'] ) {
			$lines[] = '💳 ' . $payload['payment'];
		}
		if ( empty( $payload['mask'] ) && isset( $payload['city'] ) && '' !== (string) $payload['city'] ) {
			$lines[] = '📍 ' . $payload['city'];
		}

		$text = implode( "\n", $lines );
		if ( strlen( $text ) > self::TELEGRAM_LIMIT ) {
			// Telegram counts UTF-16 units; we cap conservatively by bytes.
			$limit = self::TELEGRAM_LIMIT;
			if ( function_exists( 'mb_substr' ) ) {
				$text = mb_substr( $text, 0, $limit, 'UTF-8' );
				while ( strlen( $text ) > self::TELEGRAM_LIMIT && $limit > 1 ) {
					$limit = max( 1, (int) ( $limit * self::TELEGRAM_LIMIT / strlen( $text ) ) - 1 );
					$text  = mb_substr( $text, 0, $limit, 'UTF-8' );
				}
			} else {
				$text = substr( $text, 0, self::TELEGRAM_LIMIT );
			}
		}

		return $text;
	}

	/**
	 * Flood digest: many orders collapsed into one message.
	 *
	 * @param array  $buffer Payloads buffered during the flood window.
	 * @param string $site   Site name.
	 * @return string
	 */
	public static function digest( array $buffer, $site ) {
		$count = count( $buffer );
		$lines = array( '🛒 ' . $site . ': ' . $count . ' новых заказов за минуту', '' );
		$sum   = 0.0;
		foreach ( $buffer as $payload ) {
			$lines[] = '• #' . ( isset( $payload['number'] ) ? $payload['number'] : '?' ) . ' — ' . ( isset( $payload['total'] ) ? $payload['total'] : '?' );
			if ( isset( $payload['total_value'] ) && is_numeric( $payload['total_value'] ) ) {
				$sum += (float) $payload['total_value'];
			}
		}
		if ( $sum > 0 ) {
			$lines[] = '';
			$lines[] = 'Σ ' . round( $sum, 2 );
		}

		return implode( "\n", $lines );
	}

	/**
	 * Status-change message.
	 *
	 * @param string $number    Order number.
	 * @param string $from      Old status.
	 * @param string $to        New status.
	 * @return string
	 */
	public static function status_change( $number, $from, $to ) {
		return '🔁 Заказ #' . (string) $number . ': ' . (string) $from . ' → ' . (string) $to;
	}
}
