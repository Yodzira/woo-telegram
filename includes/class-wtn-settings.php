<?php
/**
 * Settings (option-backed, sanitized).
 *
 * @package WooToTelegram
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTN_Settings {

	const OPTION = 'wtn_settings';

	public static function defaults() {
		return array(
			'token'         => '',
			'chat'          => '',
			'enabled'       => true,
			'statuses'      => array( 'new_order', 'processing', 'completed', 'cancelled', 'refunded' ),
			'mask_contacts' => true,
			'status_change' => true,
		);
	}

	public static function get() {
		$stored = get_option( self::OPTION, array() );
		$merged = array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
		$merged['statuses'] = array_values( array_intersect( self::defaults()['statuses'], (array) $merged['statuses'] ) );

		return $merged;
	}

	public static function sanitize( $in ) {
		$in = is_array( $in ) ? $in : array();
		$defaults = self::defaults();

		$statuses = array();
		foreach ( (array) ( isset( $in['statuses'] ) ? $in['statuses'] : array() ) as $s ) {
			if ( in_array( (string) $s, $defaults['statuses'], true ) ) {
				$statuses[] = (string) $s;
			}
		}

		return array(
			'token'         => substr( preg_replace( '/[^0-9:A-Za-z_\-]/', '', (string) ( isset( $in['token'] ) ? $in['token'] : '' ) ), 0, 64 ),
			'chat'          => substr( preg_replace( '/[^0-9@A-Za-z_\-]/', '', (string) ( isset( $in['chat'] ) ? $in['chat'] : '' ) ), 0, 64 ),
			'enabled'       => ! empty( $in['enabled'] ),
			'statuses'      => $statuses,
			'mask_contacts' => ! empty( $in['mask_contacts'] ),
			'status_change' => ! empty( $in['status_change'] ),
		);
	}

	public static function save( $in ) {
		$clean = self::sanitize( $in );
		update_option( self::OPTION, $clean );

		return $clean;
	}
}
