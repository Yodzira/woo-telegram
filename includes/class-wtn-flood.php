<?php
/**
 * Flood gate: collapse order bursts into a single digest (pure).
 *
 * @package WooToTelegram
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTN_Flood {

	const WINDOW_SECONDS = 60;
	const MAX_IMMEDIATE  = 10;

	/** @var array Timestamps of orders inside the current window. */
	private $window;

	/**
	 * Constructor.
	 *
	 * @param array $window Pre-populated window (for persistence).
	 */
	public function __construct( array $window = array() ) {
		$this->window = $window;
	}

	/**
	 * Register one order.
	 *
	 * @param int $now Current timestamp.
	 * @return string 'pass' (send immediately) | 'buffered' (goes into digest).
	 */
	public function register( $now ) {
		$now = (int) $now;
		$this->window = array_values(
			array_filter(
				$this->window,
				static function ( $t ) use ( $now ) {
					return $now - (int) $t <= self::WINDOW_SECONDS;
				}
			)
		);
		$this->window[] = $now;

		return count( $this->window ) > self::MAX_IMMEDIATE ? 'buffered' : 'pass';
	}

	/**
	 * Export the window for persistence.
	 *
	 * @return array
	 */
	public function export() {
		return $this->window;
	}
}
