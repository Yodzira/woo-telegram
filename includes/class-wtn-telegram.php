<?php
/**
 * Telegram API client (pure, transport injectable).
 *
 * @package WooToTelegram
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTN_Telegram {

	/** @var callable|null fn(string $url, array $args): array{code:int,body:string} */
	private $transport;

	public function __construct( $transport = null ) {
		$this->transport = $transport;
	}

	/**
	 * Send a message. Returns true on Telegram's ok:true.
	 *
	 * @param string $token Bot token.
	 * @param string $chat  Chat id or @channel.
	 * @param string $text  Message text.
	 * @return bool
	 */
	public function send( $token, $chat, $text ) {
		if ( '' === trim( (string) $token ) || '' === trim( (string) $chat ) || '' === trim( (string) $text ) ) {
			return false;
		}

		$url = 'https://api.telegram.org/bot' . rawurlencode( (string) $token ) . '/sendMessage';
		$ok  = $this->deliver( $url, array( 'chat_id' => (string) $chat, 'text' => (string) $text ) );

		return true === $ok;
	}

	/**
	 * Test-credentials call used by the setup wizard.
	 *
	 * @param string $token Bot token.
	 * @param string $chat  Chat.
	 * @return array {ok: bool, error: string}
	 */
	public function test( $token, $chat ) {
		$url  = 'https://api.telegram.org/bot' . rawurlencode( (string) $token ) . '/sendMessage';
		$result = $this->deliver( $url, array( 'chat_id' => (string) $chat, 'text' => '✅ Health check from WooCommerce to Telegram' ) );

		return array(
			'ok'    => true === $result,
			'error' => true === $result ? '' : (string) $result,
		);
	}

	/**
	 * POST via the injected transport or wp_remote_post.
	 *
	 * @param string $url  Endpoint.
	 * @param array  $body Form fields.
	 * @return bool|string true on ok:true, error string otherwise.
	 */
	private function deliver( $url, array $body ) {
		if ( $this->transport ) {
			$result = call_user_func( $this->transport, $url, array( 'body' => $body ) );
		} else {
			$response = wp_remote_post(
				$url,
				array(
					'timeout' => 3,
					'body'    => $body,
				)
			);
			if ( is_wp_error( $response ) ) {
				return $response->get_error_message();
			}
			$result = array(
				'code' => (int) wp_remote_retrieve_response_code( $response ),
				'body' => (string) wp_remote_retrieve_body( $response ),
			);
		}

		$code = isset( $result['code'] ) ? (int) $result['code'] : 0;
		$payload = json_decode( isset( $result['body'] ) ? (string) $result['body'] : '', true );
		if ( $code >= 200 && $code < 300 && is_array( $payload ) && ! empty( $payload['ok'] ) ) {
			return true;
		}

		return isset( $payload['description'] ) ? (string) $payload['description'] : 'HTTP ' . $code;
	}
}
