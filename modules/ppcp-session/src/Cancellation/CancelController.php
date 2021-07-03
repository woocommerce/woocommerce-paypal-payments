<?php
/**
 * Controlls the cancel mechanism to step out of the PayPal order session.
 *
 * @package WooCommerce\PayPalCommerce\Session\Cancellation
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Session\Cancellation;

use WooCommerce\PayPalCommerce\Session\SessionHandler;

/**
 * Class CancelController
 */
class CancelController {

	/**
	 * The Session handler.
	 *
	 * @var SessionHandler
	 */
	private $session_handler;

	/**
	 * The view.
	 *
	 * @var CancelView
	 */
	private $view;

	/**
	 * CancelController constructor.
	 *
	 * @param SessionHandler $session_handler The session handler.
	 * @param CancelView     $view The view object.
	 */
	public function __construct(
		SessionHandler $session_handler,
		CancelView $view
	) {

		$this->view            = $view;
		$this->session_handler = $session_handler;
	}

	/**
	 * Runs the controller.
	 */
	public function run() {
		$param_name = 'ppcp-cancel';
		$nonce      = 'ppcp-cancel-' . get_current_user_id();
		if ( isset( $_GET[ $param_name ] ) && // Input var ok.
			wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_GET[ $param_name ] ) ), // Input var ok.
				$nonce
			)
		) { // Input var ok.
			$this->session_handler->destroy_session_data();
		}
		if ( ! $this->session_handler->order() ) {
			return;
		}

		$url = add_query_arg( array( $param_name => wp_create_nonce( $nonce ) ), wc_get_checkout_url() );
		add_action(
			'woocommerce_review_order_after_submit',
			function () use ( $url ) {
				$this->view->render_session_cancellation( $url );
			}
		);
	}
}
