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
    /**
     * PaymentSaleCompleted constructor.
     *
     * @param LoggerInterface $logger The logger.
     * @param RenewalHandler  $renewal_handler Renewal handler.
     */
    public function __construct(LoggerInterface $logger, RenewalHandler $renewal_handler)
    {
        $this->logger = $logger;
        $this->renewal_handler = $renewal_handler;
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
                $this->renewal_handler->process($subscriptions, $transaction_id);
            } catch (WC_Data_Exception $exception) {
                return $this->failure_response('Could not update payment method.');
            }
        }
        return $this->success_response();
    }
}
