<?php

/**
 * Helper class to determine how to proceed with an order depending on the 3d secure feedback.
 *
 * @package WooCommerce\PayPalCommerce\Button\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Button\Helper;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Entity\CardAuthenticationResult as AuthResult;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Factory\CardAuthenticationResultFactory;
/**
 * Class ThreeDSecure
 */
class ThreeDSecure
{
    public const NO_DECISION = 0;
    public const PROCEED = 1;
    public const REJECT = 2;
    public const RETRY = 3;
    /**
     * Card authentication result factory.
     *
     * @var CardAuthenticationResultFactory
     */
    private CardAuthenticationResultFactory $authentication_result;
    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;
    /**
     * ThreeDSecure constructor.
     *
     * @param CardAuthenticationResultFactory $authentication_factory Card authentication result factory.
     * @param LoggerInterface                 $logger                 The logger.
     */
    public function __construct(CardAuthenticationResultFactory $authentication_factory, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->authentication_result = $authentication_factory;
    }
    /**
     * Determine, how we proceed with a given order.
     *
     * @link https://developer.paypal.com/docs/checkout/advanced/customize/3d-secure/response-parameters/
     *
     * @param Order $order The order for which the decision is needed.
     *
     * @return int
     */
    public function proceed_with_order(Order $order): int
    {
        do_action('woocommerce_paypal_payments_three_d_secure_before_check', $order);
        $payment_source = $order->payment_source();
        if (!$payment_source) {
            return $this->return_decision(self::NO_DECISION, $order);
        }
        if (isset($payment_source->properties()->card)) {
            /**
             * GooglePay provides the credit-card details and authentication-result
             * via the "cards" attribute. We assume, that this structure is also
             * used for other payment methods that support 3DS.
             */
            $card_properties = $payment_source->properties()->card;
        } else {
            /**
             * For regular credit card payments (via PayPal) we get all details
             * directly in the payment_source properties.
             */
            $card_properties = $payment_source->properties();
        }
        if (empty($card_properties->brand)) {
            return $this->return_decision(self::NO_DECISION, $order);
        }
        if (empty($card_properties->authentication_result)) {
            return $this->return_decision(self::NO_DECISION, $order);
        }
        $result = $this->authentication_result->from_paypal_response($card_properties->authentication_result);
        $liability = $result->liability_shift();
        $this->logger->info('3DS Authentication Result: ' . wc_print_r($result->to_array(), \true));
        if ($liability === AuthResult::LIABILITY_SHIFT_POSSIBLE) {
            return $this->return_decision(self::PROCEED, $order);
        }
        if ($liability === AuthResult::LIABILITY_SHIFT_UNKNOWN) {
            return $this->return_decision(self::RETRY, $order);
        }
        if ($liability === AuthResult::LIABILITY_SHIFT_NO) {
            return $this->return_decision($this->no_liability_shift($result), $order);
        }
        return $this->return_decision(self::NO_DECISION, $order);
    }
    /**
     * Processes and returns a ThreeD secure decision.
     *
     * @param int   $decision The ThreeD secure decision.
     * @param Order $order    The PayPal Order object.
     * @return int
     */
    public function return_decision(int $decision, Order $order): int
    {
        $decision = apply_filters('woocommerce_paypal_payments_three_d_secure_decision', $decision, $order);
        do_action('woocommerce_paypal_payments_three_d_secure_after_check', $order, $decision);
        return $decision;
    }
    /**
     * Determines how to proceed depending on the Liability Shift.
     *
     * @param AuthResult $result The AuthResult object based on which we make the decision.
     *
     * @return int
     */
    private function no_liability_shift(AuthResult $result): int
    {
        $enrollment = $result->enrollment_status();
        $authentication = $result->authentication_result();
        if (!$authentication) {
            if ($enrollment === AuthResult::ENROLLMENT_STATUS_BYPASS) {
                return self::PROCEED;
            }
            if ($enrollment === AuthResult::ENROLLMENT_STATUS_UNAVAILABLE) {
                return self::PROCEED;
            }
            if ($enrollment === AuthResult::ENROLLMENT_STATUS_NO) {
                return self::PROCEED;
            }
        }
        if ($authentication === AuthResult::AUTHENTICATION_RESULT_REJECTED) {
            return self::REJECT;
        }
        if ($authentication === AuthResult::AUTHENTICATION_RESULT_NO) {
            return self::REJECT;
        }
        if ($authentication === AuthResult::AUTHENTICATION_RESULT_UNABLE) {
            return self::RETRY;
        }
        if (!$authentication) {
            return self::RETRY;
        }
        return self::NO_DECISION;
    }
}
