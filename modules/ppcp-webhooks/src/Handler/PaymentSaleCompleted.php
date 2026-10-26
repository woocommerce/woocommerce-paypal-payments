<?php

/**
 * Handles the Webhook PAYMENT.SALE.COMPLETED
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WC_Data_Exception;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerReceivableBreakdown;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\SellerReceivableBreakdownFactory;
use WooCommerce\PayPalCommerce\PayPalSubscriptions\RenewalHandler;
use WP_REST_Request;
use WP_REST_Response;
/**
 * Class PaymentSaleCompleted
 */
class PaymentSaleCompleted implements \WooCommerce\PayPalCommerce\Webhooks\Handler\RequestHandler
{
    use \WooCommerce\PayPalCommerce\Webhooks\Handler\RequestHandlerTrait;
    /**
     * Renewal handler.
     *
     * @var RenewalHandler
     */
    private $renewal_handler;
    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    private $logger;
    private SellerReceivableBreakdownFactory $breakdown_factory;
    /**
     * PaymentSaleCompleted constructor.
     *
     * @param LoggerInterface                  $logger The logger.
     * @param RenewalHandler                   $renewal_handler Renewal handler.
     * @param SellerReceivableBreakdownFactory $breakdown_factory Builds the sale's fee breakdown.
     */
    public function __construct(LoggerInterface $logger, RenewalHandler $renewal_handler, SellerReceivableBreakdownFactory $breakdown_factory)
    {
        $this->logger = $logger;
        $this->renewal_handler = $renewal_handler;
        $this->breakdown_factory = $breakdown_factory;
    }
    /**
     * The event types a handler handles.
     *
     * @return string[]
     */
    public function event_types(): array
    {
        return array('PAYMENT.SALE.COMPLETED');
    }
    /**
     * Whether a handler is responsible for a given request or not.
     *
     * @param WP_REST_Request $request The request.
     *
     * @return bool
     */
    public function responsible_for_request(WP_REST_Request $request): bool
    {
        return in_array($request['event_type'], $this->event_types(), \true);
    }
    /**
     * Responsible for handling the request.
     *
     * @param WP_REST_Request $request The request.
     *
     * @return WP_REST_Response
     */
    public function handle_request(WP_REST_Request $request): WP_REST_Response
    {
        if (is_null($request['resource'])) {
            return $this->failure_response('Could not retrieve resource.');
        }
        if (!function_exists('wcs_get_subscriptions')) {
            return $this->failure_response('WooCommerce Subscriptions plugin is not active.');
        }
        $billing_agreement_id = wc_clean(wp_unslash($request['resource']['billing_agreement_id'] ?? ''));
        if (!$billing_agreement_id) {
            return $this->failure_response('Could not retrieve billing agreement id for subscription.');
        }
        $transaction_id = wc_clean(wp_unslash($request['resource']['id'] ?? ''));
        if (!$transaction_id || !is_string($transaction_id)) {
            return $this->failure_response('Could not retrieve transaction id for subscription.');
        }
        $args = array(
            // phpcs:ignore WordPress.DB.SlowDBQuery
            'meta_query' => array(array('key' => 'ppcp_subscription', 'value' => $billing_agreement_id, 'compare' => '=')),
        );
        $subscriptions = wcs_get_subscriptions($args);
        if ($subscriptions) {
            try {
                $this->renewal_handler->process($subscriptions, $transaction_id, $this->sale_breakdown((array) $request['resource']));
            } catch (WC_Data_Exception $exception) {
                return $this->failure_response('Could not update payment method.');
            }
        }
        return $this->success_response();
    }
    /**
     * The sale's fee breakdown, in the shape a capture's seller_receivable_breakdown
     * has, so FeesRenderer shows it like any other PayPal payment.
     *
     * A sale reports its gross amount and fee but no net amount, which is derived
     * here. Webhook data is untrusted: an incomplete or inconsistent sale yields
     * no breakdown, and the order is then processed without fees.
     *
     * @param array $resource The webhook's sale resource.
     */
    private function sale_breakdown(array $resource): ?SellerReceivableBreakdown
    {
        $total = $resource['amount']['total'] ?? null;
        $currency = $resource['amount']['currency'] ?? null;
        $fee = $resource['transaction_fee']['value'] ?? null;
        $fee_currency = $resource['transaction_fee']['currency'] ?? null;
        if (!is_numeric($total) || !is_numeric($fee) || !is_string($currency) || '' === $currency || $fee_currency !== $currency) {
            $this->logger->debug('PAYMENT.SALE.COMPLETED carries no usable amount or fee; the order is processed without PayPal fees.');
            return null;
        }
        // The net amount keeps the gross amount's precision, so zero-decimal
        // currencies stay whole.
        $decimals = strlen((string) substr((string) strrchr((string) $total, '.'), 1));
        $net = number_format((float) $total - (float) $fee, $decimals, '.', '');
        try {
            return $this->breakdown_factory->from_paypal_response((object) array('gross_amount' => (object) array('value' => (string) $total, 'currency_code' => $currency), 'paypal_fee' => (object) array('value' => (string) $fee, 'currency_code' => $currency), 'net_amount' => (object) array('value' => $net, 'currency_code' => $currency)));
        } catch (RuntimeException $exception) {
            $this->logger->debug('Could not build the PayPal fee breakdown of a sale: ' . $exception->getMessage());
            return null;
        }
    }
}
