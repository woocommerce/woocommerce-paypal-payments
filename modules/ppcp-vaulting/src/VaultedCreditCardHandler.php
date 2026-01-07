<?php

/**
 * Handles payment through saved credit card.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Vaulting;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WC_Customer;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayerFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingPreferenceFactory;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\WcSubscriptions\FreeTrialHandlerTrait;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderMetaTrait;
use WooCommerce\PayPalCommerce\WcGateway\Processor\PaymentsStatusHandlingTrait;
use WooCommerce\PayPalCommerce\WcGateway\Processor\TransactionIdHandlingTrait;
/**
 * Class VaultedCreditCardHandler
 */
class VaultedCreditCardHandler
{
    use OrderMetaTrait;
    use TransactionIdHandlingTrait;
    use PaymentsStatusHandlingTrait;
    use FreeTrialHandlerTrait;
    /**
     * The subscription helper.
     *
     * @var SubscriptionHelper
     */
    protected $subscription_helper;
    /**
     * The payment token repository.
     *
     * @var PaymentTokenRepository
     */
    private $payment_token_repository;
    /**
     * The purchase unit factory.
     *
     * @var PurchaseUnitFactory
     */
    private $purchase_unit_factory;
    /**
     * The payer factory.
     *
     * @var PayerFactory
     */
    private $payer_factory;
    /**
     * The shipping_preference factory.
     *
     * @var ShippingPreferenceFactory
     */
    private $shipping_preference_factory;
    /**
     * The order endpoint.
     *
     * @var OrderEndpoint
     */
    private $order_endpoint;
    /**
     * The environment.
     *
     * @var Environment
     */
    protected $environment;
    /**
     * The processor for authorized payments.
     *
     * @var AuthorizedPaymentsProcessor
     */
    protected $authorized_payments_processor;
    /**
     * The settings.
     *
     * @var ContainerInterface
     */
    protected $config;
    /**
     * VaultedCreditCardHandler constructor
     *
     * @param SubscriptionHelper          $subscription_helper The subscription helper.
     * @param PaymentTokenRepository      $payment_token_repository The payment token repository.
     * @param PurchaseUnitFactory         $purchase_unit_factory The purchase unit factory.
     * @param PayerFactory                $payer_factory The payer factory.
     * @param ShippingPreferenceFactory   $shipping_preference_factory The shipping_preference factory.
     * @param OrderEndpoint               $order_endpoint The order endpoint.
     * @param Environment                 $environment The environment.
     * @param AuthorizedPaymentsProcessor $authorized_payments_processor The processor for authorized payments.
     * @param ContainerInterface          $config The settings.
     */
    public function __construct(SubscriptionHelper $subscription_helper, \WooCommerce\PayPalCommerce\Vaulting\PaymentTokenRepository $payment_token_repository, PurchaseUnitFactory $purchase_unit_factory, PayerFactory $payer_factory, ShippingPreferenceFactory $shipping_preference_factory, OrderEndpoint $order_endpoint, Environment $environment, AuthorizedPaymentsProcessor $authorized_payments_processor, ContainerInterface $config)
    {
        $this->subscription_helper = $subscription_helper;
        $this->payment_token_repository = $payment_token_repository;
        $this->purchase_unit_factory = $purchase_unit_factory;
        $this->payer_factory = $payer_factory;
        $this->shipping_preference_factory = $shipping_preference_factory;
        $this->order_endpoint = $order_endpoint;
        $this->environment = $environment;
        $this->authorized_payments_processor = $authorized_payments_processor;
        $this->config = $config;
    }
    /**
     * Handles the saved credit card payment.
     *
     * @param string   $saved_credit_card The saved credit card.
     * @param WC_Order $wc_order The WC order.
     * @return WC_Order
     * @throws RuntimeException When something went wrong with the payment process.
     */
    public function handle_payment(string $saved_credit_card, WC_Order $wc_order): WC_Order
    {
        $tokens = $this->payment_token_repository->all_for_user_id($wc_order->get_customer_id());
        $selected_token = null;
        foreach ($tokens as $token) {
            if ($token->id() === $saved_credit_card) {
                $selected_token = $token;
                break;
            }
        }
        if (!$selected_token) {
            throw new RuntimeException('Saved card token not found.');
        }
        $purchase_unit = $this->purchase_unit_factory->from_wc_order($wc_order);
        $payer = $this->payer_factory->from_wc_order($wc_order);
        $shipping_preference = $this->shipping_preference_factory->from_state($purchase_unit, '');
        try {
            $order = $this->order_endpoint->create(array($purchase_unit), $shipping_preference, $payer, '', array(), $selected_token->to_payment_source());
            $this->add_paypal_meta($wc_order, $order, $this->environment);
            if (!$order->status()->is(OrderStatus::COMPLETED)) {
                throw new RuntimeException("Unexpected status for order {$order->id()} using a saved card: {$order->status()->name()}.");
            }
            if (!in_array($order->intent(), array('CAPTURE', 'AUTHORIZE'), \true)) {
                throw new RuntimeException("Could neither capture nor authorize order {$order->id()} using a saved card. Status: {$order->status()->name()}. Intent: {$order->intent()}.");
            }
            if ($order->intent() === 'AUTHORIZE') {
                $order = $this->order_endpoint->authorize($order);
                $wc_order->update_meta_data(AuthorizedPaymentsProcessor::CAPTURED_META_KEY, 'false');
            }
            $transaction_id = $this->get_paypal_order_transaction_id($order);
            if ($transaction_id) {
                $this->update_transaction_id($transaction_id, $wc_order);
            }
            $this->handle_new_order_status($order, $wc_order);
            if ($this->is_free_trial_order($wc_order)) {
                $this->authorized_payments_processor->void_authorizations($order);
                $wc_order->payment_complete();
            } elseif ($this->config->has('intent') && strtoupper((string) $this->config->get('intent')) === 'CAPTURE') {
                $this->authorized_payments_processor->capture_authorized_payment($wc_order);
            }
            return $wc_order;
        } catch (RuntimeException $error) {
            throw new RuntimeException($error->getMessage());
        }
    }
}
