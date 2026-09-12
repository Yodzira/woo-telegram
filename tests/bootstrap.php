<?php
/**
 * Standalone bootstrap: pure core (message, flood, telegram client).
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/wp/' );
}
if ( ! defined( 'WTN_DIR' ) ) {
	define( 'WTN_DIR', dirname( __DIR__ ) );
}
if ( ! defined( 'WTN_VERSION' ) ) {
	define( 'WTN_VERSION', '0.1.0-test' );
}

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

$GLOBALS['__wtn_options'] = array();

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['__wtn_options'] ) ? $GLOBALS['__wtn_options'][ $key ] : $default;
}

function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['__wtn_options'][ $key ] = $value;

	return true;
}

function delete_option( $key ) {
	unset( $GLOBALS['__wtn_options'][ $key ] );

	return true;
}

function wp_parse_args( $args, $defaults ) {
	return array_merge( $defaults, (array) $args );
}
