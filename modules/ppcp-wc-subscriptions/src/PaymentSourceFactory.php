<?php
/**
 * Payment source factory
 *
 * @package WooCommerce\PayPalCommerce\WcSubscriptions
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcSubscriptions;

use Exception;
use WC_Order;
use WC_Payment_Tokens;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenApplePay;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenVenmo;
use WooCommerce\PayPalCommerce\Vaulting\WooCommercePaymentTokens;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;

class PaymentSourceFactory {

	private WooCommercePaymentTokens $wc_payment_tokens;
	private SubscriptionHelper $subscription_helper;

	public function __construct(
		WooCommercePaymentTokens $wc_payment_tokens,
		SubscriptionHelper $subscription_helper
	) {
		$this->wc_payment_tokens   = $wc_payment_tokens;
		$this->subscription_helper = $subscription_helper;
	}

	/**
	 * @throws Exception
	 */
	public function payment_source( string $payment_method, int $user_id, WC_Order $wc_order ): PaymentSource {
		switch ( $payment_method ) {
			case PayPalGateway::ID:
				return $this->create_paypal_payment_source( $user_id );
			case CreditCardGateway::ID:
				return $this->create_card_payment_source( $user_id, $wc_order );
			default:
				throw new Exception( 'Could not create payment source' );
		}
	}

	/**
	 * @throws Exception
	 */
	private function create_paypal_payment_source( int $user_id ): PaymentSource {
		$wc_tokens     = WC_Payment_Tokens::get_customer_tokens( $user_id, PayPalGateway::ID );
		$paypal_tokens = $this->wc_payment_tokens->customer_tokens( $user_id );
		foreach ( $wc_tokens as $wc_token ) {
			foreach ( $paypal_tokens as $paypal_token ) {
				if ( $paypal_token['id'] === $wc_token ) {
					$name       = 'paypal';
					$properties = array(
						'vault_id' => $wc_token->get_token(),
					);

					if ( $wc_token instanceof PaymentTokenVenmo ) {
						$name = 'venmo';
					}

					if ( $wc_token instanceof PaymentTokenApplePay ) {
						$name                            = 'apple_pay';
						$properties['stored_credential'] = array(
							'payment_initiator' => 'MERCHANT',
							'payment_type'      => 'RECURRING',
							'usage'             => 'SUBSEQUENT',
						);
					}

					return new PaymentSource(
						$name,
						(object) $properties
					);
				}
			}
		}

		throw new Exception( 'Could not create PayPal payment source.' );
	}

	/**
	 * @throws Exception
	 */
	private function create_card_payment_source( int $user_id, WC_Order $wc_order ): PaymentSource {
		$wc_tokens     = WC_Payment_Tokens::get_customer_tokens( $user_id, CreditCardGateway::ID );
		$paypal_tokens = $this->wc_payment_tokens->customer_tokens( $user_id );
		foreach ( $wc_tokens as $wc_token ) {
			foreach ( $paypal_tokens as $paypal_token ) {
				if ( $paypal_token['id'] === $wc_token ) {
					$properties = array(
						'vault_id' => $wc_token->get_token(),
					);

					$properties = $this->add_previous_transaction( $wc_order, $properties );

					return new PaymentSource(
						'card',
						(object) $properties
					);
				}
			}
		}

		throw new Exception( 'Could not create card payment source.' );
	}

	/**
	 * @param WC_Order $wc_order
	 * @param array    $properties
	 * @return array
	 */
	private function add_previous_transaction( WC_Order $wc_order, array $properties ): array {
		$subscriptions = wcs_get_subscriptions_for_renewal_order( $wc_order );
		$subscription  = end( $subscriptions );
		if ( $subscription ) {
			$transaction = $this->subscription_helper->previous_transaction( $subscription );
			if ( $transaction ) {
				$properties['stored_credential'] = array(
					'payment_initiator'              => 'MERCHANT',
					'payment_type'                   => 'RECURRING',
					'usage'                          => 'SUBSEQUENT',
					'previous_transaction_reference' => $transaction,
				);
			}
		}

		return $properties;
	}
}
