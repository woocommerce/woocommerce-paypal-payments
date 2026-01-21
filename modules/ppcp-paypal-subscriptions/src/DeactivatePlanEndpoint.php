<?php

/**
 * The deactivate Subscription Plan Endpoint.
 *
 * @package WooCommerce\PayPalCommerce\WcSubscriptions
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\PayPalSubscriptions;

use Exception;
use WC_Product;
use WC_Subscriptions_Product;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\BillingPlans;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
/**
 * Class DeactivatePlanEndpoint
 */
class DeactivatePlanEndpoint
{
    const ENDPOINT = 'ppc-deactivate-plan';
    /**
     * The request data.
     *
     * @var RequestData
     */
    private $request_data;
    /**
     * The billing plans.
     *
     * @var BillingPlans
     */
    private $billing_plans;
    /**
     * DeactivatePlanEndpoint constructor.
     *
     * @param RequestData  $request_data The request data.
     * @param BillingPlans $billing_plans The billing plans.
     */
    public function __construct(RequestData $request_data, BillingPlans $billing_plans)
    {
        $this->request_data = $request_data;
        $this->billing_plans = $billing_plans;
    }
    /**
     * Handles the request.
     *
     * @return void
     */
    public function handle_request(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Not admin.', 403);
            return;
        }
        try {
            $data = $this->request_data->read_request(self::ENDPOINT);
            $plan_id = $data['plan_id'] ?? '';
            if ($plan_id) {
                $this->billing_plans->deactivate_plan($plan_id);
            }
            $product_id = $data['product_id'] ?? '';
            if ($product_id) {
                $product = wc_get_product($product_id);
                if (is_a($product, WC_Product::class) && WC_Subscriptions_Product::is_subscription($product)) {
                    $product->delete_meta_data('_ppcp_enable_subscription_product');
                    $product->delete_meta_data('_ppcp_subscription_plan_name');
                    $product->delete_meta_data('ppcp_subscription_product');
                    $product->delete_meta_data('ppcp_subscription_plan');
                    $product->save();
                }
            }
            wp_send_json_success(array('product_id' => (string) $product_id));
        } catch (Exception $error) {
            wp_send_json_error();
        }
    }
}
