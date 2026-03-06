<?php

/**
 * Service to create WC Payment Tokens.
 *
 * @package WooCommerce\PayPalCommerce\Applepay
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Vaulting;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use stdClass;
use WC_Payment_Token_CC;
use WC_Payment_Tokens;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentTokensEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
/**
 * Class WooCommercePaymentTokens
 */
class WooCommercePaymentTokens
{
    /**
     * The payment token helper.
     *
     * @var PaymentTokenHelper
     */
    private $payment_token_helper;
    /**
     * The payment token factory.
     *
     * @var PaymentTokenFactory
     */
    private $payment_token_factory;
    /**
     * Payment tokens endpoint.
     *
     * @var PaymentTokensEndpoint
     */
    private $payment_tokens_endpoint;
    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    private $logger;
    /**
     * WooCommercePaymentTokens constructor.
     *
     * @param PaymentTokenHelper    $payment_token_helper The payment token helper.
     * @param PaymentTokenFactory   $payment_token_factory The payment token factory.
     * @param PaymentTokensEndpoint $payment_tokens_endpoint Payment tokens endpoint.
     * @param LoggerInterface       $logger The logger.
     */
    public function __construct(\WooCommerce\PayPalCommerce\Vaulting\PaymentTokenHelper $payment_token_helper, \WooCommerce\PayPalCommerce\Vaulting\PaymentTokenFactory $payment_token_factory, PaymentTokensEndpoint $payment_tokens_endpoint, LoggerInterface $logger)
    {
        $this->payment_token_helper = $payment_token_helper;
        $this->payment_token_factory = $payment_token_factory;
        $this->payment_tokens_endpoint = $payment_tokens_endpoint;
        $this->logger = $logger;
    }
    /**
     * Creates a WC Payment Token for PayPal payment.
     *
     * @param int    $customer_id    The WC customer ID.
     * @param string $token          The PayPal payment token.
     * @param string $email          The PayPal customer email.
     *
     * @return int
     */
    public function create_payment_token_paypal(int $customer_id, string $token, string $email): int
    {
        if ($customer_id === 0) {
            return 0;
        }
        $wc_tokens = WC_Payment_Tokens::get_customer_tokens($customer_id, PayPalGateway::ID);
        if ($this->payment_token_helper->token_exist($wc_tokens, $token, \WooCommerce\PayPalCommerce\Vaulting\PaymentTokenPayPal::class)) {
            return 0;
        }
        // Try to update existing token of type before creating a new one.
        $payment_token_paypal = $this->payment_token_helper->first_token_of_type($wc_tokens, \WooCommerce\PayPalCommerce\Vaulting\PaymentTokenPayPal::class);
        if (!$payment_token_paypal) {
            $payment_token_paypal = $this->payment_token_factory->create('paypal');
        }
        assert($payment_token_paypal instanceof \WooCommerce\PayPalCommerce\Vaulting\PaymentTokenPayPal);
        $payment_token_paypal->set_token($token);
        $payment_token_paypal->set_user_id($customer_id);
        $payment_token_paypal->set_gateway_id(PayPalGateway::ID);
        if ($email && is_email($email)) {
            $payment_token_paypal->set_email($email);
        }
        try {
            $payment_token_paypal->save();
        } catch (Exception $exception) {
            $this->logger->error("Could not create WC payment token PayPal for customer {$customer_id}. " . $exception->getMessage());
        }
        return $payment_token_paypal->get_id();
    }
    /**
     * Creates a WC Payment Token for Venmo payment.
     *
     * @param int    $customer_id The WC customer ID.
     * @param string $token       The Venmo payment token.
     * @param string $email       The Venmo customer email.
     *
     * @return int
     */
    public function create_payment_token_venmo(int $customer_id, string $token, string $email): int
    {
        if ($customer_id === 0) {
            return 0;
        }
        $wc_tokens = WC_Payment_Tokens::get_customer_tokens($customer_id, PayPalGateway::ID);
        if ($this->payment_token_helper->token_exist($wc_tokens, $token, \WooCommerce\PayPalCommerce\Vaulting\PaymentTokenVenmo::class)) {
            return 0;
        }
        // Try to update existing token of type before creating a new one.
        $payment_token_venmo = $this->payment_token_helper->first_token_of_type($wc_tokens, \WooCommerce\PayPalCommerce\Vaulting\PaymentTokenVenmo::class);
        if (!$payment_token_venmo) {
            $payment_token_venmo = $this->payment_token_factory->create('venmo');
        }
        assert($payment_token_venmo instanceof \WooCommerce\PayPalCommerce\Vaulting\PaymentTokenVenmo);
        $payment_token_venmo->set_token($token);
        $payment_token_venmo->set_user_id($customer_id);
        $payment_token_venmo->set_gateway_id(PayPalGateway::ID);
        if ($email && is_email($email)) {
            $payment_token_venmo->set_email($email);
        }
        try {
            $payment_token_venmo->save();
        } catch (Exception $exception) {
            $this->logger->error("Could not create WC payment token Venmo for customer {$customer_id}. " . $exception->getMessage());
        }
        return $payment_token_venmo->get_id();
    }
    /**
     * Creates a WC Payment Token for ApplePay payment.
     *
     * @param int    $customer_id    The WC customer ID.
     * @param string $token          The ApplePay payment token.
     *
     * @return int
     */
    public function create_payment_token_applepay(int $customer_id, string $token): int
    {
        if ($customer_id === 0) {
            return 0;
        }
        $wc_tokens = WC_Payment_Tokens::get_customer_tokens($customer_id, PayPalGateway::ID);
        if ($this->payment_token_helper->token_exist($wc_tokens, $token, \WooCommerce\PayPalCommerce\Vaulting\PaymentTokenApplePay::class)) {
            return 0;
        }
        // Try to update existing token of type before creating a new one.
        $payment_token_applepay = $this->payment_token_helper->first_token_of_type($wc_tokens, \WooCommerce\PayPalCommerce\Vaulting\PaymentTokenApplePay::class);
        if (!$payment_token_applepay) {
            $payment_token_applepay = $this->payment_token_factory->create('apple_pay');
        }
        assert($payment_token_applepay instanceof \WooCommerce\PayPalCommerce\Vaulting\PaymentTokenApplePay);
        $payment_token_applepay->set_token($token);
        $payment_token_applepay->set_user_id($customer_id);
        $payment_token_applepay->set_gateway_id(PayPalGateway::ID);
        try {
            $payment_token_applepay->save();
        } catch (Exception $exception) {
            $this->logger->error("Could not create WC payment token ApplePay for customer {$customer_id}. " . $exception->getMessage());
        }
        return $payment_token_applepay->get_id();
    }
    /**
     * Creates a WC Payment Token for Credit Card payment.
     *
     * @param int      $customer_id The WC customer ID.
     * @param stdClass $payment_token The Credit Card payment token.
     *
     * @return int
     */
    public function create_payment_token_card(int $customer_id, stdClass $payment_token): int
    {
        if ($customer_id === 0) {
            return 0;
        }
        $wc_tokens = WC_Payment_Tokens::get_customer_tokens($customer_id, CreditCardGateway::ID);
        if ($this->payment_token_helper->token_exist($wc_tokens, $payment_token->id)) {
            return 0;
        }
        $token = new WC_Payment_Token_CC();
        $token->set_token($payment_token->id);
        $token->set_user_id($customer_id);
        $token->set_gateway_id(CreditCardGateway::ID);
        $token->set_last4($payment_token->payment_source->card->last_digits ?? '');
        $expiry = explode('-', $payment_token->payment_source->card->expiry ?? '');
        $token->set_expiry_year($expiry[0] ?? '');
        $token->set_expiry_month($expiry[1] ?? '');
        $brand = $payment_token->payment_source->card->brand ?? __('N/A', 'woocommerce-paypal-payments');
        if ($brand) {
            $token->set_card_type($brand);
        }
        try {
            $token->save();
        } catch (Exception $exception) {
            $this->logger->error("Could not create WC payment token card for customer {$customer_id}. " . $exception->getMessage());
        }
        $token->save();
        return $token->get_id();
    }
    /**
     * Returns PayPal payment tokens for the given WP user id.
     *
     * @param int $user_id WP user id.
     * @return array
     */
    public function customer_tokens(int $user_id): array
    {
        $customer_id = get_user_meta($user_id, '_ppcp_target_customer_id', \true);
        if (!$customer_id) {
            $customer_id = get_user_meta($user_id, 'ppcp_customer_id', \true);
        }
        try {
            $customer_tokens = $this->payment_tokens_endpoint->payment_tokens_for_customer($customer_id);
        } catch (RuntimeException $exception) {
            $customer_tokens = array();
        }
        return $customer_tokens;
    }
    /**
     * Creates WC payment tokens for the given WP user id using PayPal payment tokens as source.
     *
     * @param array $customer_tokens PayPal customer payment tokens.
     * @param int   $user_id WP user id.
     * @return void
     */
    public function create_wc_tokens(array $customer_tokens, int $user_id): void
    {
        foreach ($customer_tokens as $customer_token) {
            if ($customer_token['payment_source']->name() === 'paypal') {
                $this->create_payment_token_paypal($user_id, $customer_token['id'], $customer_token['payment_source']->properties()->email_address ?? '');
            }
            if ($customer_token['payment_source']->name() === 'card') {
                /**
                 * Suppress ArgumentTypeCoercion
                 *
                 * @psalm-suppress ArgumentTypeCoercion
                 */
                $this->create_payment_token_card($user_id, (object) array('id' => $customer_token['id'], 'payment_source' => (object) array($customer_token['payment_source']->name() => $customer_token['payment_source']->properties())));
            }
        }
    }
}
