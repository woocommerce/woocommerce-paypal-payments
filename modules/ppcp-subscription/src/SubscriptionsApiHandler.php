<?php

namespace WooCommerce\PayPalCommerce\Subscription;

use Psr\Log\LoggerInterface;
use WC_Product;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\BillingPlans;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\CatalogProducts;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\BillingCycleFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PaymentPreferencesFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ProductFactory;

class SubscriptionsApiHandler {

	/**
	 * @var CatalogProducts
	 */
	private $products_endpoint;

    /**
     * @var ProductFactory
     */
    private $product_factory;

	/**
	 * @var BillingPlans
	 */
	private $billing_plans_endpoint;

	/**
	 * @var BillingCycleFactory
	 */
	private $billing_cycle_factory;

	/**
	 * @var PaymentPreferencesFactory
	 */
	private $payment_preferences_factory;

	/**
	 * @var LoggerInterface
	 */
	private $logger;

    public function __construct(
		CatalogProducts $products_endpoint,
        ProductFactory $product_factory,
		BillingPlans $billing_plans_endpoint,
		BillingCycleFactory $billing_cycle_factory,
		PaymentPreferencesFactory $payment_preferences_factory,
		LoggerInterface $logger
	) {
		$this->products_endpoint = $products_endpoint;
        $this->product_factory = $product_factory;
		$this->billing_plans_endpoint = $billing_plans_endpoint;
		$this->billing_cycle_factory = $billing_cycle_factory;
		$this->payment_preferences_factory = $payment_preferences_factory;
		$this->logger            = $logger;
    }

	/**
	 * Creates a Catalog Product and adds it as WC product meta.
	 *
	 * @param WC_Product $product
	 * @return void
	 */
	public function create_product( WC_Product $product ) {
		try {
			$subscription_product = $this->products_endpoint->create( $product->get_title(), $product->get_description());
			$product->update_meta_data( 'ppcp_subscription_product', $subscription_product->to_array() );
			$product->save();
		} catch ( RuntimeException $exception ) {
			$error = $exception->getMessage();
			if ( is_a( $exception, PayPalApiException::class ) ) {
				$error = $exception->get_details( $error );
			}

			$this->logger->error( 'Could not create catalog product on PayPal. ' . $error );
		}
	}

	public function create_plan( string $plan_name, WC_Product $product ) {
		try {
			$subscription_plan = $this->billing_plans_endpoint->create(
                $plan_name,
				$product->get_meta( 'ppcp_subscription_product' )['id'] ?? '',
				array($this->billing_cycle_factory->from_wc_product($product)->to_array()),
				$this->payment_preferences_factory->from_wc_product($product)->to_array()
			);

			$product->update_meta_data( 'ppcp_subscription_plan', $subscription_plan->to_array() );
			$product->save();
		} catch ( RuntimeException $exception ) {
			$error = $exception->getMessage();
			if ( is_a( $exception, PayPalApiException::class ) ) {
				$error = $exception->get_details( $error );
			}

			$this->logger->error( 'Could not create subscription plan on PayPal. ' . $error );
		}
	}

	public function update_product(WC_Product $product) {
        try {
            $catalog_product_id = $product->get_meta( 'ppcp_subscription_product' )['id'] ?? '';
            if($catalog_product_id) {
                $catalog_product = $this->products_endpoint->product($catalog_product_id);
                $catalog_product_name = $catalog_product->name() ?? '';
                $catalog_product_description = $catalog_product->description() ?? '';
                if($catalog_product_name !== $product->get_title() || $catalog_product_description !== $product->get_description()) {
                    $data = array();
                    if($catalog_product_name !== $product->get_title()) {
                        $data[] = (object) array(
                            'op' => 'replace',
                            'path' => '/name',
                            'value' => $product->get_title(),
                        );
                    }
                    if($catalog_product_description !== $product->get_description()) {
                        $data[] = (object) array(
                            'op' => 'replace',
                            'path' => '/description',
                            'value' => $product->get_description(),
                        );
                    }

                    $this->products_endpoint->update($catalog_product_id, $data);
                }
            }

        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
            if ( is_a( $exception, PayPalApiException::class ) ) {
                $error = $exception->get_details( $error );
            }

            $this->logger->error( 'Could not update catalog product on PayPal. ' . $error );
        }
	}

	public function update_plan() {

	}
}
