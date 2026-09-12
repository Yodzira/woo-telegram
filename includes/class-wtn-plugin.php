<?php
/**
 * Plugin boot + admin settings page with the 60-second wizard.
 *
 * @package WooToTelegram
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WTN_Plugin {

	public static function boot() {
		if ( is_admin() ) {
			add_action( 'admin_menu', array( 'WTN_Admin', 'menu' ) );
			add_action( 'admin_post_wtn_save', array( 'WTN_Admin', 'handle_save' ) );
			add_action( 'wp_ajax_wtn_test', array( 'WTN_Admin', 'handle_test' ) );
		}
		if ( function_exists( 'WC' ) || class_exists( 'WooCommerce' ) ) {
			WTN_Orders::boot();
		}
		add_action( 'wtn_send_single', array( 'WTN_Orders', 'send_single' ), 10, 1 );
		add_action( 'wtn_process_queue', array( 'WTN_Orders', 'process_queue' ) );
	}
}

class WTN_Admin {

	public static function menu() {
		add_menu_page( 'Woo to Telegram', 'Woo to Telegram', 'manage_options', 'woo-telegram', array( __CLASS__, 'render' ), 'dashicons-format-chat' );
	}

	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'woo-telegram' ) );
		}
		check_admin_referer( 'wtn_save' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized in Settings::sanitize().
		$payload = isset( $_POST['wtn'] ) ? (array) $_POST['wtn'] : array();
		WTN_Settings::save( $payload );
		wp_safe_redirect( admin_url( 'admin.php?page=woo-telegram&saved=1' ) );
		exit;
	}

	/**
	 * AJAX: test the saved (or posted) credentials.
	 *
	 * @return void
	 */
	public static function handle_test() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'error' => 'Not allowed.' ), 403 );
		}
		check_ajax_referer( 'wtn_test', 'nonce' );

		$token = isset( $_POST['token'] ) ? preg_replace( '/[^0-9:A-Za-z_\-]/', '', (string) wp_unslash( $_POST['token'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- regex-sanitized.
		$chat  = isset( $_POST['chat'] ) ? preg_replace( '/[^0-9@A-Za-z_\-]/', '', (string) wp_unslash( $_POST['chat'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- regex-sanitized.

		$telegram = new WTN_Telegram();
		$result   = $telegram->test( $token, $chat );
		if ( $result['ok'] ) {
			wp_send_json_success( array( 'message' => __( 'Delivered — check your Telegram!', 'woo-telegram' ) ) );
		}
		wp_send_json_error( array( 'error' => $result['error'] ) );
	}

	public static function render() {
		$settings  = WTN_Settings::get();
		$just_saved = isset( $_GET['saved'] ) ? sanitize_key( wp_unslash( $_GET['saved'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display flag.
		?>
		<div class="wrap">
			<h1>Woo to Telegram</h1>

			<?php if ( '1' === $just_saved ) : ?>
				<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wtn_save">
				<?php wp_nonce_field( 'wtn_save' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th>Bot token</th>
						<td>
							<input type="text" name="wtn[token]" value="<?php echo esc_attr( $settings['token'] ); ?>" class="regular-text" id="wtn-token">
							<p class="description">Create a bot with @BotFather, paste its token here.</p>
						</td>
					</tr>
					<tr>
						<th>Chat ID</th>
						<td>
							<input type="text" name="wtn[chat]" value="<?php echo esc_attr( $settings['chat'] ); ?>" class="regular-text" id="wtn-chat">
							<p class="description">Send any message to your bot, then get the chat id from @userinfobot (numbers) or use @channelname.</p>
						</td>
					</tr>
					<tr>
						<th>Enabled</th>
						<td><label><input type="checkbox" name="wtn[enabled]" value="1" <?php checked( $settings['enabled'] ); ?>> Send new-order notifications</label></td>
					</tr>
					<tr>
						<th>Status changes</th>
						<td><label><input type="checkbox" name="wtn[status_change]" value="1" <?php checked( $settings['status_change'] ); ?>> Notify about order status changes</label></td>
					</tr>
					<tr>
						<th>Notify statuses</th>
						<td>
							<?php foreach ( array( 'new_order', 'processing', 'completed', 'cancelled', 'refunded' ) as $status ) : ?>
								<label style="margin-right:14px"><input type="checkbox" name="wtn[statuses][]" value="<?php echo esc_attr( $status ); ?>" <?php checked( in_array( $status, $settings['statuses'], true ) ); ?>> <?php echo esc_html( $status ); ?></label>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th>Privacy</th>
						<td><label><input type="checkbox" name="wtn[mask_contacts]" value="1" <?php checked( $settings['mask_contacts'] ); ?>> Hide customer city/address details</label></td>
					</tr>
				</table>

				<p>
					<button type="submit" class="button button-primary">Save settings</button>
					<button type="button" class="button" id="wtn-test-btn">Send test</button>
					<span id="wtn-test-result" style="margin-left:10px"></span>
				</p>
			</form>
		</div>

		<script>
		(function () {
			var btn = document.getElementById('wtn-test-btn');
			btn && btn.addEventListener('click', function () {
				var out = document.getElementById('wtn-test-result');
				out.textContent = '…';
				var body = new FormData();
				body.append('action', 'wtn_test');
				body.append('nonce', '<?php echo esc_js( wp_create_nonce( 'wtn_test' ) ); ?>');
				body.append('token', document.getElementById('wtn-token').value);
				body.append('chat', document.getElementById('wtn-chat').value);
				fetch(window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
					.then(function (r) { return r.json(); })
					.then(function (json) {
						out.textContent = json.success ? '✅ ' + json.data.message : '❌ ' + (json.data && json.data.error ? json.data.error : 'Failed');
					});
			});
		})();
		</script>
		<?php
	}
}
