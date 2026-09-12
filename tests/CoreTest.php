<?php

use PHPUnit\Framework\TestCase;

/**
 * Message templates.
 */
class MessageTest extends TestCase {

	public function test_order_message_basics() {
		$text = WTN_Message::order(
			array(
				'number'   => '1042',
				'total'    => '12400.00',
				'currency' => '₽',
				'status'   => 'new',
				'items'    => array( array( 'iPhone-чехол', 2 ), array( 'Кабель', 1 ) ),
				'payment'  => 'ЮKassa',
				'city'     => 'Москва',
			)
		);

		$this->assertStringContainsString( '🛒 Заказ #1042 — 12400.00 ₽ (new)', $text );
		$this->assertStringContainsString( '• iPhone-чехол ×2', $text );
		$this->assertStringContainsString( '• Кабель', $text );
		$this->assertStringContainsString( '💳 ЮKassa', $text );
		$this->assertStringContainsString( '📍 Москва', $text );
	}

	public function test_masking_hides_city() {
		$text = WTN_Message::order( array( 'number' => '1', 'city' => 'Москва', 'mask' => true ) );
		$this->assertStringNotContainsString( 'Москва', $text );
	}

	public function test_truncation_to_telegram_limit() {
		$items = array();
		for ( $i = 0; $i < 400; $i++ ) {
			$items[] = array( str_repeat( 'товар', 20 ), 1 );
		}
		$text = WTN_Message::order( array( 'number' => '1', 'items' => $items ) );
		$this->assertLessThanOrEqual( WTN_Message::TELEGRAM_LIMIT, strlen( $text ) );
	}

	public function test_digest_collapses_burst() {
		$buffer = array();
		for ( $i = 1; $i <= 12; $i++ ) {
			$buffer[] = array( 'number' => (string) $i, 'total' => '100.00', 'total_value' => 100.0 );
		}
		$text = WTN_Message::digest( $buffer, 'shop.ru' );

		$this->assertStringContainsString( '12 новых заказов', $text );
		$this->assertStringContainsString( 'Σ 1200', $text );
		$this->assertStringContainsString( '#12', $text );
	}

	public function test_status_change() {
		$this->assertSame( '🔁 Заказ #7: pending → completed', WTN_Message::status_change( '7', 'pending', 'completed' ) );
	}
}

/**
 * Flood gate windows.
 */
class FloodTest extends TestCase {

	public function test_first_orders_pass() {
		$flood = new WTN_Flood();
		$this->assertSame( 'pass', $flood->register( 1000 ) );
		$this->assertSame( 'pass', $flood->register( 1001 ) );
	}

	public function test_burst_gets_buffered() {
		$flood = new WTN_Flood();
		$mode  = 'pass';
		for ( $t = 0; $t < 15; $t++ ) {
			$mode = $flood->register( 1000 + $t );
		}
		$this->assertSame( 'buffered', $mode );
	}

	public function test_window_slides() {
		$flood = new WTN_Flood();
		for ( $t = 0; $t < 10; $t++ ) {
			$flood->register( 1000 + $t );
		}
		// Window fully expired: fresh start.
		$this->assertSame( 'pass', $flood->register( 2000 ) );
		$this->assertSame( array( 2000 ), $flood->export() );
	}
}

/**
 * Telegram client with a fake transport.
 */
class TelegramTest extends TestCase {

	public function test_send_true_on_ok() {
		$transport = static function () {
			return array( 'code' => 200, 'body' => '{"ok":true,"result":{"message_id":1}}' );
		};
		$this->assertTrue( ( new WTN_Telegram( $transport ) )->send( '123:ABC', '42', 'hello' ) );
	}

	public function test_send_false_on_error_description() {
		$transport = static function () {
			return array( 'code' => 400, 'body' => '{"ok":false,"description":"chat not found"}' );
		};
		$this->assertFalse( ( new WTN_Telegram( $transport ) )->send( '123:ABC', '42', 'hello' ) );
	}

	public function test_send_false_on_empty_args() {
		$transport = static function () {
			throw new Exception( 'must not be called' );
		};
		$this->assertFalse( ( new WTN_Telegram( $transport ) )->send( '', '42', 'hello' ) );
		$this->assertFalse( ( new WTN_Telegram( $transport ) )->send( '1:2', '', 'hello' ) );
		$this->assertFalse( ( new WTN_Telegram( $transport ) )->send( '1:2', '42', '  ' ) );
	}

	public function test_transport_receives_encoded_url_and_body() {
		$seen = null;
		$transport = static function ( $url, $args ) use ( &$seen ) {
			$seen = array( $url, $args );

			return array( 'code' => 200, 'body' => '{"ok":true}' );
		};
		( new WTN_Telegram( $transport ) )->send( '123:ABC_x-', '42', 'текст' );

		$this->assertStringStartsWith( 'https://api.telegram.org/bot123%3AABC_x-/sendMessage', $seen[0] );
		$this->assertSame( '42', $seen[1]['body']['chat_id'] );
		$this->assertSame( 'текст', $seen[1]['body']['text'] );
	}
}

/**
 * Settings sanitization.
 */
class SettingsTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['__wtn_options'] = array();
	}

	public function test_sanitize_stricts() {
		$clean = WTN_Settings::sanitize(
			array(
				'token'     => "123:abc!@#DEF-_\n",
				'chat'      => '@My_Chat',
				'enabled'   => '1',
				'statuses'  => array( 'new_order', 'bogus' ),
				'mask_contacts' => 'on',
			)
		);
		$this->assertSame( '123:abcDEF-_', $clean['token'] ); // Stray chars stripped.
		$this->assertSame( '@My_Chat', $clean['chat'] );
		$this->assertTrue( $clean['enabled'] );
		$this->assertSame( array( 'new_order' ), $clean['statuses'] ); // Unknown dropped.
		$this->assertTrue( $clean['mask_contacts'] );
		$this->assertFalse( $clean['status_change'] ); // Absent = unchecked.
	}
}
