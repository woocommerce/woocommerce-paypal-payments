<?php
/**
 * The process_payment functionality for the both gateways.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway;

use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;

/**
 * Trait ProcessPaymentTrait
 */
trait ProcessPaymentTrait {
	/**
	 * Process a payment for an WooCommerce order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {

		$failure_data = array(
			'result'   => 'failure',
			'redirect' => wc_get_checkout_url(),
		);

		$wc_order = wc_get_order( $order_id );
		if ( ! is_a( $wc_order, \WC_Order::class ) ) {
			wc_add_notice(
				__( 'Couldn\'t find order to process', 'woocommerce-paypal-payments' ),
				'error'
			);

			return $failure_data;
		}

		/**
		 * If customer has chosen a saved credit card payment.
		 */
		$saved_credit_card = filter_input( INPUT_POST, 'saved_credit_card', FILTER_SANITIZE_STRING );
		$pay_for_order     = filter_input( INPUT_GET, 'pay_for_order', FILTER_SANITIZE_STRING );
		if ( $saved_credit_card && ! isset( $pay_for_order ) ) {

			$user_id  = (int) $wc_order->get_customer_id();
			$customer = new \WC_Customer( $user_id );
			$tokens   = $this->payment_token_repository->all_for_user_id( (int) $customer->get_id() );

			$selected_token = null;
			foreach ( $tokens as $token ) {
				if ( $token->id() === $saved_credit_card ) {
					$selected_token = $token;
					break;
				}
			}

			if ( ! $selected_token ) {
				return null;
			}

			$purchase_unit = $this->purchase_unit_factory->from_wc_order( $wc_order );
			$payer         = $this->payer_factory->from_customer( $customer );
			try {
				$order = $this->order_endpoint->create(
					array( $purchase_unit ),
					$payer,
					$selected_token
				);

				if ( $order->status()->is( OrderStatus::COMPLETED ) && $order->intent() === 'CAPTURE' ) {
					$wc_order->update_status(
						'processing',
						__( 'Payment received.', 'woocommerce-paypal-payments' )
					);

					$this->session_handler->destroy_session_data();
					return array(
						'result'   => 'success',
						'redirect' => $this->get_return_url( $wc_order ),
					);
				}
			} catch ( RuntimeException $error ) {
				$this->session_handler->destroy_session_data();
				wc_add_notice( $error->getMessage(), 'error' );
				return null;
			}
		}

		/**
		 * If customer has chosen change Subscription payment.
		 */
		if ( $this->subscription_helper->has_subscription( $order_id ) && $this->subscription_helper->is_subscription_change_payment() ) {
			if ( 'ppcp-credit-card-gateway' === $this->id && $saved_credit_card ) {
				update_post_meta( $order_id, 'payment_token_id', $saved_credit_card );

				$this->session_handler->destroy_session_data();
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $wc_order ),
				);
			}

			$saved_paypal_payment = filter_input( INPUT_POST, 'saved_paypal_payment', FILTER_SANITIZE_STRING );
			if ( 'ppcp-gateway' === $this->id && $saved_paypal_payment ) {
				update_post_meta( $order_id, 'payment_token_id', $saved_paypal_payment );

				$this->session_handler->destroy_session_data();
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $wc_order ),
				);
			}
		}

		/**
		 * If the WC_Order is payed through the approved webhook.
		 */
		//phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_REQUEST['ppcp-resume-order'] ) && $wc_order->has_status( 'processing' ) ) {
			$this->session_handler->destroy_session_data();
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $wc_order ),
			);
		}
		//phpcs:enable WordPress.Security.NonceVerification.Recommended

		try {
			if ( $this->order_processor->process( $wc_order ) ) {
				$this->session_handler->destroy_session_data();
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $wc_order ),
				);
			}
		} catch ( PayPalApiException $error ) {
			if ( $error->has_detail( 'INSTRUMENT_DECLINED' ) ) {
				$wc_order->update_status(
					'failed',
					__( 'Instrument declined.', 'woocommerce-paypal-payments' )
				);

				$this->session_handler->increment_insufficient_funding_tries();
				$host = $this->config->has( 'sandbox_on' ) && $this->config->get( 'sandbox_on' ) ?
					'https://www.sandbox.paypal.com/' : 'https://www.paypal.com/';
				$url  = $host . 'checkoutnow?token=' . $this->session_handler->order()->id();
				if ( $this->session_handler->insufficient_funding_tries() >= 3 ) {
					$this->session_handler->destroy_session_data();
					wc_add_notice(
						__( 'Please use a different payment method.', 'woocommerce-paypal-payments' ),
						'error'
					);
					return $failure_data;
				}
				return array(
					'result'   => 'success',
					'redirect' => $url,
				);
			}

			$this->session_handler->destroy_session_data();
		} catch ( RuntimeException $error ) {
			$wc_order->update_status(
				'failed',
				__( 'Could not process order.', 'woocommerce-paypal-payments' )
			);
			$this->session_handler->destroy_session_data();
			wc_add_notice( $error->getMessage(), 'error' );
			return $failure_data;
		}

		wc_add_notice(
			$this->order_processor->last_error(),
			'error'
		);
		$wc_order->update_status(
			'failed',
			__( 'Could not process order.', 'woocommerce-paypal-payments' )
		);

		return $failure_data;
	}

	/**
	 * Checks if PayPal or Credit Card gateways are enabled.
	 *
	 * @return bool Whether any of the gateways is enabled.
	 */
	protected function gateways_enabled(): bool {
		if ( $this->config->has( 'enabled' ) && $this->config->get( 'enabled' ) ) {
			return true;
		}
		if ( $this->config->has( 'dcc_enabled' ) && $this->config->get( 'dcc_enabled' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Checks if vault setting is enabled.
	 *
	 * @return bool Whether vault settings are enabled or not.
	 * @throws \WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException When a setting hasn't been found.
	 */
	protected function vault_setting_enabled(): bool {
		if ( $this->config->has( 'vault_enabled' ) && $this->config->get( 'vault_enabled' ) ) {
			return true;
		}
		return false;
	}
}
