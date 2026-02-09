<?php

/**
 * Processes orders for the gateways.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Processor
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Processor;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ExperienceContextBuilder;
use WooCommerce\PayPalCommerce\ApiClient\Factory\OrderFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayerFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingPreferenceFactory;
use WooCommerce\PayPalCommerce\ApiClient\Helper\OrderHelper;
use WooCommerce\PayPalCommerce\Button\Helper\ThreeDSecure;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenRepository;
use WooCommerce\PayPalCommerce\WcGateway\Exception\PayPalOrderMissingException;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use Automattic\WooCommerce\Utilities\OrderUtil;
/**
 * Class OrderProcessor
 */
class OrderProcessor
{
    use \WooCommerce\PayPalCommerce\WcGateway\Processor\OrderMetaTrait;
    use \WooCommerce\PayPalCommerce\WcGateway\Processor\PaymentsStatusHandlingTrait;
    use \WooCommerce\PayPalCommerce\WcGateway\Processor\TransactionIdHandlingTrait;
    /**
     * The environment.
     *
     * @var Environment
     */
    protected $environment;
    /**
     * The payment token repository.
     *
     * @var PaymentTokenRepository
     */
    protected $payment_token_repository;
    /**
     * The Session Handler.
     *
     * @var SessionHandler
     */
    private $session_handler;
    /**
     * The Order Endpoint.
     *
     * @var OrderEndpoint
     */
    private $order_endpoint;
    /**
     * The Order Factory.
     *
     * @var OrderFactory
     */
    private $order_factory;
    /**
     * The helper for 3d secure.
     *
     * @var ThreeDSecure
     */
    private $threed_secure;
    /**
     * The processor for authorized payments.
     *
     * @var AuthorizedPaymentsProcessor
     */
    private $authorized_payments_processor;
    /**
     * The settings.
     *
     * @var Settings
     */
    private $settings;
    /**
     * A logger.
     *
     * @var LoggerInterface
     */
    private $logger;
    /**
     * The subscription helper.
     *
     * @var SubscriptionHelper
     */
    private $subscription_helper;
    /**
     * The order helper.
     *
     * @var OrderHelper
     */
    private $order_helper;
    /**
     * The PurchaseUnit factory.
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
     * Array to store temporary order data changes to restore after processing.
     *
     * @var array
     */
    private $restore_order_data = array();
    /**
     * The ExperienceContextBuilder.
     */
    private ExperienceContextBuilder $experience_context_builder;
    /**
     * OrderProcessor constructor.
     *
     * @param SessionHandler              $session_handler The Session Handler.
     * @param OrderEndpoint               $order_endpoint The Order Endpoint.
     * @param OrderFactory                $order_factory The Order Factory.
     * @param ThreeDSecure                $three_d_secure The ThreeDSecure Helper.
     * @param AuthorizedPaymentsProcessor $authorized_payments_processor The Authorized Payments Processor.
     * @param Settings                    $settings The Settings.
     * @param LoggerInterface             $logger A logger service.
     * @param Environment                 $environment The environment.
     * @param SubscriptionHelper          $subscription_helper The subscription helper.
     * @param OrderHelper                 $order_helper The order helper.
     * @param PurchaseUnitFactory         $purchase_unit_factory The PurchaseUnit factory.
     * @param PayerFactory                $payer_factory The payer factory.
     * @param ShippingPreferenceFactory   $shipping_preference_factory The shipping_preference factory.
     * @param ExperienceContextBuilder    $experience_context_builder The ExperienceContextBuilder.
     */
    public function __construct(SessionHandler $session_handler, OrderEndpoint $order_endpoint, OrderFactory $order_factory, ThreeDSecure $three_d_secure, \WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor $authorized_payments_processor, Settings $settings, LoggerInterface $logger, Environment $environment, SubscriptionHelper $subscription_helper, OrderHelper $order_helper, PurchaseUnitFactory $purchase_unit_factory, PayerFactory $payer_factory, ShippingPreferenceFactory $shipping_preference_factory, ExperienceContextBuilder $experience_context_builder)
    {
        $this->session_handler = $session_handler;
        $this->order_endpoint = $order_endpoint;
        $this->order_factory = $order_factory;
        $this->threed_secure = $three_d_secure;
        $this->authorized_payments_processor = $authorized_payments_processor;
        $this->settings = $settings;
        $this->environment = $environment;
        $this->logger = $logger;
        $this->subscription_helper = $subscription_helper;
        $this->order_helper = $order_helper;
        $this->purchase_unit_factory = $purchase_unit_factory;
        $this->payer_factory = $payer_factory;
        $this->shipping_preference_factory = $shipping_preference_factory;
        $this->experience_context_builder = $experience_context_builder;
    }
    /**
     * Processes a given WooCommerce order and captured/authorizes the connected PayPal orders.
     *
     * @param WC_Order $wc_order The WooCommerce order.
     *
     * @throws PayPalOrderMissingException If no PayPal order.
     * @throws Exception If processing fails.
     */
    public function process(WC_Order $wc_order): void
    {
        if (!$this->verify_order_can_be_processed($wc_order)) {
            return;
        }
        if (!$this->acquire_processing_lock($wc_order)) {
            return;
        }
        try {
            $order = $this->session_handler->order();
            if (!$order) {
                // phpcs:ignore WordPress.Security.NonceVerification
                $order_id = $wc_order->get_meta(PayPalGateway::ORDER_ID_META_KEY) ?: wc_clean(wp_unslash($_POST['paypal_order_id'] ?? ''));
                if (is_string($order_id) && $order_id) {
                    try {
                        $order = $this->order_endpoint->order($order_id);
                    } catch (RuntimeException $exception) {
                        throw new Exception(__('Could not retrieve PayPal order.', 'woocommerce-paypal-payments'));
                    }
                } else {
                    $is_paypal_return = isset($_GET['wc-ajax']) && wc_clean(wp_unslash($_GET['wc-ajax'])) === 'ppc-return-url';
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    if ($is_paypal_return) {
                        $this->logger->warning(sprintf('No PayPal order ID found for WooCommerce order #%d.', $wc_order->get_id()));
                    }
                    throw new PayPalOrderMissingException(esc_attr__('There was an error processing your order. Please check for any charges in your payment method and review your order history before placing the order again.', 'woocommerce-paypal-payments'));
                }
            }
            // Do not continue if PayPal order status is completed.
            $order = $this->order_endpoint->order($order->id());
            if ($order->status()->is(OrderStatus::COMPLETED)) {
                $this->logger->warning('Could not process PayPal completed order #' . $order->id() . ', Status: ' . $order->status()->name());
                return;
            }
            $this->add_paypal_meta($wc_order, $order, $this->environment);
            if ($this->order_helper->contains_physical_goods($order) && !$this->order_is_ready_for_process($order)) {
                throw new Exception(__('The payment is not ready for processing yet.', 'woocommerce-paypal-payments'));
            }
            $order = $this->patch_order($wc_order, $order);
            if ($order->intent() === 'CAPTURE') {
                $order = $this->order_endpoint->capture($order);
            }
            if ($order->intent() === 'AUTHORIZE') {
                $order = $this->order_endpoint->authorize($order);
                $wc_order->update_meta_data(\WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor::CAPTURED_META_KEY, 'false');
                if ($this->subscription_helper->has_subscription($wc_order->get_id())) {
                    $wc_order->update_meta_data('_ppcp_captured_vault_webhook', 'false');
                }
            }
            $transaction_id = $this->get_paypal_order_transaction_id($order);
            if ($transaction_id) {
                $this->update_transaction_id($transaction_id, $wc_order);
            }
            $this->handle_new_order_status($order, $wc_order);
            if ($this->capture_authorized_downloads($order)) {
                $this->authorized_payments_processor->capture_authorized_payment($wc_order);
            }
            do_action('woocommerce_paypal_payments_after_order_processor', $wc_order, $order);
        } finally {
            $this->release_processing_lock($wc_order);
        }
    }
    /**
     * Processes a given WooCommerce order and captured/authorizes the connected PayPal orders.
     *
     * @param WC_Order $wc_order The WooCommerce order.
     * @param Order    $order The PayPal order.
     *
     * @throws Exception If processing fails.
     */
    public function process_captured_and_authorized(WC_Order $wc_order, Order $order): void
    {
        $this->add_paypal_meta($wc_order, $order, $this->environment);
        if ($order->intent() === 'AUTHORIZE') {
            $wc_order->update_meta_data(\WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor::CAPTURED_META_KEY, 'false');
            if ($this->subscription_helper->has_subscription($wc_order->get_id())) {
                $wc_order->update_meta_data('_ppcp_captured_vault_webhook', 'false');
            }
        }
        $transaction_id = $this->get_paypal_order_transaction_id($order);
        if ($transaction_id) {
            $this->update_transaction_id($transaction_id, $wc_order);
        }
        $this->handle_new_order_status($order, $wc_order);
        if ($this->capture_authorized_downloads($order)) {
            $this->authorized_payments_processor->capture_authorized_payment($wc_order);
        }
        do_action('woocommerce_paypal_payments_after_order_processor', $wc_order, $order);
    }
    /**
     * Creates a PayPal order for the given WC order.
     *
     * @param WC_Order $wc_order The WC order.
     * @return Order
     * @throws RuntimeException If order creation fails.
     */
    public function create_order(WC_Order $wc_order): Order
    {
        $pu = $this->purchase_unit_factory->from_wc_order($wc_order);
        $shipping_preference = $this->shipping_preference_factory->from_state($pu, 'checkout');
        $order = $this->order_endpoint->create(array($pu), $shipping_preference, $this->payer_factory->from_wc_order($wc_order), '', array(), new PaymentSource('paypal', (object) array('experience_context' => $this->experience_context_builder->with_default_paypal_config($shipping_preference, ExperienceContext::USER_ACTION_PAY_NOW)->build()->to_array())));
        return $order;
    }
    /**
     * Patches a given PayPal order with a WooCommerce order.
     *
     * @param WC_Order $wc_order The WooCommerce order.
     * @param Order    $order The PayPal order.
     *
     * @return Order
     */
    public function patch_order(WC_Order $wc_order, Order $order): Order
    {
        $this->apply_outbound_order_filters($wc_order);
        $updated_order = $this->order_factory->from_wc_order($wc_order, $order);
        $this->restore_order_from_filters($wc_order);
        $order = $this->order_endpoint->patch_order_with($order, $updated_order);
        return $order;
    }
    /**
     * Verifies whether the order can be processed.
     *
     * @param WC_Order $wc_order The WooCommerce order.
     * @return bool
     */
    private function verify_order_can_be_processed(WC_Order $wc_order): bool
    {
        if (!in_array($wc_order->get_status(), array('pending', 'on-hold'), \true)) {
            $this->logger->info(sprintf('Order #%d has status "%s", skipping payment processing.', $wc_order->get_id(), $wc_order->get_status()));
            return \false;
        }
        if ($wc_order->get_transaction_id()) {
            $this->logger->info(sprintf('Order #%d already has transaction ID "%s", skipping payment processing.', $wc_order->get_id(), $wc_order->get_transaction_id()));
            return \false;
        }
        return \true;
    }
    /**
     * Atomically acquires a processing lock for the order.
     *
     * Uses direct SQL to ensure atomic lock acquisition, preventing race conditions
     * where two concurrent processes could both acquire the lock.
     * Stores an expiration timestamp instead of a simple flag, allowing stale locks
     * from crashed processes to automatically expire.
     * Supports both HPOS (wc_orders_meta) and legacy (postmeta) storage.
     *
     * @param WC_Order $wc_order The WooCommerce order.
     * @return bool True if lock was acquired, false if already locked.
     */
    private function acquire_processing_lock(WC_Order $wc_order): bool
    {
        global $wpdb;
        $order_id = $wc_order->get_id();
        $current_time = time();
        $expiration = $current_time + 5 * MINUTE_IN_SECONDS;
        if (class_exists(OrderUtil::class) && OrderUtil::custom_orders_table_usage_is_enabled()) {
            $table = $wpdb->prefix . 'wc_orders_meta';
            $id_column = 'order_id';
        } else {
            $table = $wpdb->postmeta;
            $id_column = 'post_id';
        }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows_updated = $wpdb->query($wpdb->prepare("UPDATE {$table}\n\t\t\t\tSET meta_value = %d\n\t\t\t\tWHERE {$id_column} = %d\n\t\t\t\tAND meta_key = '_ppcp_processing'\n\t\t\t\tAND meta_value < %d", $expiration, $order_id, $current_time));
        if ($rows_updated > 0) {
            return \true;
        }
        $rows_inserted = $wpdb->query($wpdb->prepare("INSERT INTO {$table} ({$id_column}, meta_key, meta_value)\n\t\t\t\tSELECT %d, '_ppcp_processing', %d\n\t\t\t\tFROM (SELECT 1) AS dummy\n\t\t\t\tWHERE NOT EXISTS (\n\t\t\t\t\tSELECT 1 FROM {$table} AS t WHERE t.{$id_column} = %d AND t.meta_key = '_ppcp_processing'\n\t\t\t\t)", $order_id, $expiration, $order_id));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ($rows_inserted > 0) {
            return \true;
        }
        $this->logger->warning(sprintf('Order #%d is already being processed (lock active), skipping payment processing.', $order_id));
        return \false;
    }
    /**
     * Releases the processing lock for the order.
     *
     * Supports both HPOS (wc_orders_meta) and legacy (postmeta) storage.
     *
     * @param WC_Order $wc_order The WooCommerce order.
     * @return void
     */
    private function release_processing_lock(WC_Order $wc_order): void
    {
        global $wpdb;
        if (class_exists(OrderUtil::class) && OrderUtil::custom_orders_table_usage_is_enabled()) {
            $table = $wpdb->prefix . 'wc_orders_meta';
            $id_column = 'order_id';
        } else {
            $table = $wpdb->postmeta;
            $id_column = 'post_id';
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete($table, array($id_column => $wc_order->get_id(), 'meta_key' => '_ppcp_processing'), array('%d', '%s'));
    }
    /**
     * Returns if an order should be captured immediately.
     *
     * @param Order $order The PayPal order.
     *
     * @return bool
     */
    private function capture_authorized_downloads(Order $order): bool
    {
        if (!$this->settings->has('capture_for_virtual_only') || !$this->settings->get('capture_for_virtual_only')) {
            return \false;
        }
        if ($order->intent() === 'CAPTURE') {
            return \false;
        }
        /**
         * We fetch the order again as the authorize endpoint (from which the Order derives)
         * drops the item's category, making it impossible to check, if purchase units contain
         * physical goods.
         */
        $order = $this->order_endpoint->order($order->id());
        foreach ($order->purchase_units() as $unit) {
            if ($unit->contains_physical_goods()) {
                return \false;
            }
        }
        return \true;
    }
    /**
     * Whether a given order is ready for processing.
     *
     * @param Order $order The order.
     *
     * @return bool
     */
    private function order_is_ready_for_process(Order $order): bool
    {
        if ($order->status()->is(OrderStatus::APPROVED) || $order->status()->is(OrderStatus::CREATED)) {
            return \true;
        }
        $payment_source = $order->payment_source();
        if (!$payment_source) {
            return \false;
        }
        if ($payment_source->name() !== 'card') {
            return \false;
        }
        return in_array($this->threed_secure->proceed_with_order($order), array(ThreeDSecure::NO_DECISION, ThreeDSecure::PROCEED), \true);
    }
    /**
     * Applies filters to the WC_Order, so they are reflected only on PayPal Order.
     *
     * @param WC_Order $wc_order The WoocOmmerce Order.
     * @return void
     */
    private function apply_outbound_order_filters(WC_Order $wc_order): void
    {
        $items = $wc_order->get_items();
        $this->restore_order_data['names'] = array();
        foreach ($items as $item) {
            if (!$item instanceof \WC_Order_Item) {
                continue;
            }
            $original_name = $item->get_name();
            $new_name = apply_filters('woocommerce_paypal_payments_order_line_item_name', $original_name, $item->get_id(), $wc_order->get_id());
            if ($new_name !== $original_name) {
                $this->restore_order_data['names'][$item->get_id()] = $original_name;
                $item->set_name($new_name);
            }
        }
    }
    /**
     * Restores the WC_Order to it's state before filters.
     *
     * @param WC_Order $wc_order The WooCommerce Order.
     * @return void
     */
    private function restore_order_from_filters(WC_Order $wc_order): void
    {
        if (is_array($this->restore_order_data['names'] ?? null)) {
            foreach ($this->restore_order_data['names'] as $wc_item_id => $original_name) {
                $wc_item = $wc_order->get_item($wc_item_id, \false);
                if ($wc_item) {
                    $wc_item->set_name($original_name);
                }
            }
        }
    }
}
