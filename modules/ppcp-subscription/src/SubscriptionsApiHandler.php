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

class SubscriptionsApiHandler {

	/**
	 * @var CatalogProducts
	 */
	private $products_endpoint;

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
		BillingPlans $billing_plans_endpoint,
		BillingCycleFactory $billing_cycle_factory,
		PaymentPreferencesFactory $payment_preferences_factory,
		LoggerInterface $logger
	) {
		$this->products_endpoint = $products_endpoint;
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

			$this->logger->error( 'Could not create subscription product on PayPal. ' . $error );
		}
	}

	public function create_plan( WC_Product $product ) {
		try {
			$subscription_plan = $this->billing_plans_endpoint->create(
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

	public function update_product() {

	}

	public function update_plan() {

	}
}
