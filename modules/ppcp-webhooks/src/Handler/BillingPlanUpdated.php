<?php

/**
 * Handles the Webhook BILLING.PLAN.UPDATED
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WP_REST_Request;
use WP_REST_Response;
/**
 * Class BillingPlanUpdated
 */
class BillingPlanUpdated implements \WooCommerce\PayPalCommerce\Webhooks\Handler\RequestHandler
{
    use \WooCommerce\PayPalCommerce\Webhooks\Handler\RequestHandlerTrait;
    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    private $logger;
    /**
     * BillingPlanUpdated constructor.
     *
     * @param LoggerInterface $logger The logger.
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    /**
     * The event types a handler handles.
     *
     * @return string[]
     */
    public function event_types(): array
    {
        return array('BILLING.PLAN.UPDATED');
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
            return $this->failure_response();
        }
        $plan_id = wc_clean(wp_unslash($request['resource']['id'] ?? ''));
        if ($plan_id) {
            $products = wc_get_products(array(
                // phpcs:ignore WordPress.DB.SlowDBQuery
                'meta_key' => 'ppcp_subscription_product',
            ));
            if (is_array($products)) {
                foreach ($products as $product) {
                    if ($product->meta_exists('ppcp_subscription_plan')) {
                        $plan_name = wc_clean(wp_unslash($request['resource']['name'] ?? ''));
                        if ($plan_name !== $product->get_meta('_ppcp_subscription_plan_name')) {
                            $product->update_meta_data('_ppcp_subscription_plan_name', $plan_name);
                            $product->save();
                        }
                        $billing_cycles = wc_clean(wp_unslash($request['resource']['billing_cycles'] ?? array()));
                        if ($billing_cycles) {
                            $price = $billing_cycles[0]['pricing_scheme']['fixed_price']['value'] ?? '';
                            if ($price && round($price, 2) !== round($product->get_meta('_subscription_price'), 2)) {
                                $product->update_meta_data('_subscription_price', $price);
                                $product->save();
                            }
                        }
                        $payment_preferences = wc_clean(wp_unslash($request['resource']['payment_preferences'] ?? array()));
                        if ($payment_preferences) {
                            $setup_fee = $payment_preferences['setup_fee']['value'] ?? '';
                            if ($setup_fee && round($setup_fee, 2) !== round($product->get_meta('_subscription_sign_up_fee'), 2)) {
                                $product->update_meta_data('_subscription_sign_up_fee', $setup_fee);
                                $product->save();
                            }
                        }
                    }
                }
            }
        }
        return $this->success_response();
    }
}
