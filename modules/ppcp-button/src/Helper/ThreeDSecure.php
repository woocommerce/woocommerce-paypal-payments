<?php
/**
 * Helper class to determine how to proceed with an order depending on the 3d secure feedback.
 *
 * @package WooCommerce\PayPalCommerce\Button\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Helper;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Entity\CardAuthenticationResult as AuthResult;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Factory\CardAuthenticationResultFactory;

/**
 * Class ThreeDSecure
 */
class ThreeDSecure {

	const NO_DECISION = 0;
	const PROCCEED    = 1;
	const REJECT      = 2;
	const RETRY       = 3;

	/**
	 * Card authentication result factory.
	 *
	 * @var CardAuthenticationResultFactory
	 */
	private $card_authentication_result_factory;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * ThreeDSecure constructor.
	 *
	 * @param CardAuthenticationResultFactory $card_authentication_result_factory Card authentication result factory.
	 * @param LoggerInterface                 $logger The logger.
	 */
	public function __construct(
		CardAuthenticationResultFactory $card_authentication_result_factory,
		LoggerInterface $logger
	) {
		$this->logger                             = $logger;
		$this->card_authentication_result_factory = $card_authentication_result_factory;
	}

	/**
	 * Determine, how we proceed with a given order.
	 *
	 * @link https://developer.paypal.com/docs/business/checkout/add-capabilities/3d-secure/#authenticationresult
	 *
	 * @param Order $order The order for which the decision is needed.
	 *
	 * @return int
	 */
	public function proceed_with_order( Order $order ): int {

		do_action( 'woocommerce_paypal_payments_three_d_secure_before_check', $order );

		$payment_source = $order->payment_source();
		if ( ! $payment_source ) {
			return $this->return_decision( self::NO_DECISION, $order );
		}

		if ( ! ( $payment_source->properties()->brand ?? '' ) ) {
			return $this->return_decision( self::NO_DECISION, $order );
		}
		if ( ! ( $payment_source->properties()->authentication_result ?? '' ) ) {
			return $this->return_decision( self::NO_DECISION, $order );
		}

		$authentication_result = $payment_source->properties()->authentication_result ?? null;
		if ( $authentication_result ) {
			$result = $this->card_authentication_result_factory->from_paypal_response( $authentication_result );

			$this->logger->info( '3DS Authentication Result: ' . wc_print_r( $result->to_array(), true ) );

			if ( $result->liability_shift() === AuthResult::LIABILITY_SHIFT_POSSIBLE ) {
				return $this->return_decision( self::PROCCEED, $order );
			}

			if ( $result->liability_shift() === AuthResult::LIABILITY_SHIFT_UNKNOWN ) {
				return $this->return_decision( self::RETRY, $order );
			}
			if ( $result->liability_shift() === AuthResult::LIABILITY_SHIFT_NO ) {
				return $this->return_decision( $this->no_liability_shift( $result ), $order );
			}
		}

		return $this->return_decision( self::NO_DECISION, $order );
	}

	/**
	 * Processes and returns a ThreeD secure decision.
	 *
	 * @param int   $decision The ThreeD secure decision.
	 * @param Order $order The PayPal Order object.
	 * @return int
	 */
	public function return_decision( int $decision, Order $order ) {
		$decision = apply_filters( 'woocommerce_paypal_payments_three_d_secure_decision', $decision, $order );
		do_action( 'woocommerce_paypal_payments_three_d_secure_after_check', $order, $decision );
		return $decision;
	}

	/**
	 * Determines how to proceed depending on the Liability Shift.
	 *
	 * @param AuthResult $result The AuthResult object based on which we make the decision.
	 *
	 * @return int
	 */
	private function no_liability_shift( AuthResult $result ): int {

		if (
			$result->enrollment_status() === AuthResult::ENROLLMENT_STATUS_BYPASS
			&& ! $result->authentication_result()
		) {
			return self::PROCCEED;
		}
		if (
			$result->enrollment_status() === AuthResult::ENROLLMENT_STATUS_UNAVAILABLE
			&& ! $result->authentication_result()
		) {
			return self::PROCCEED;
		}
		if (
			$result->enrollment_status() === AuthResult::ENROLLMENT_STATUS_NO
			&& ! $result->authentication_result()
		) {
			return self::PROCCEED;
		}

		if ( $result->authentication_result() === AuthResult::AUTHENTICATION_RESULT_REJECTED ) {
			return self::REJECT;
		}

		if ( $result->authentication_result() === AuthResult::AUTHENTICATION_RESULT_NO ) {
			return self::REJECT;
		}

		if ( $result->authentication_result() === AuthResult::AUTHENTICATION_RESULT_UNABLE ) {
			return self::RETRY;
		}

		if ( ! $result->authentication_result() ) {
			return self::RETRY;
		}
		return self::NO_DECISION;
	}
}
