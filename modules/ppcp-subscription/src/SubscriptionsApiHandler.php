<?php
/**
 * The subscription module.
 *
 * @package WooCommerce\PayPalCommerce\Subscription
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Subscription;

use Psr\Log\LoggerInterface;
use WC_Product;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\BillingPlans;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\CatalogProducts;
use WooCommerce\PayPalCommerce\ApiClient\Entity\BillingCycle;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\BillingCycleFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PaymentPreferencesFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ProductFactory;

/**
 * Class SubscriptionsApiHandler
 */
class SubscriptionsApiHandler {

	/**
	 * Catalog products.
	 *
	 * @var CatalogProducts
	 */
	private $products_endpoint;

	/**
	 * Product factory.
	 *
	 * @var ProductFactory
	 */
	private $product_factory;

	/**
	 * Billing plans.
	 *
	 * @var BillingPlans
	 */
	private $billing_plans_endpoint;

	/**
	 * Billing cycle factory.
	 *
	 * @var BillingCycleFactory
	 */
	private $billing_cycle_factory;

	/**
	 * Payment preferences factory.
	 *
	 * @var PaymentPreferencesFactory
	 */
	private $payment_preferences_factory;

	/**
	 * The currency.
	 *
	 * @var string
	 */
	private $currency;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * SubscriptionsApiHandler constructor.
	 *
	 * @param CatalogProducts           $products_endpoint Products endpoint.
	 * @param ProductFactory            $product_factory Product factory.
	 * @param BillingPlans              $billing_plans_endpoint Billing plans endpoint.
	 * @param BillingCycleFactory       $billing_cycle_factory Billing cycle factory.
	 * @param PaymentPreferencesFactory $payment_preferences_factory Payment preferences factory.
	 * @param string                    $currency The currency.
	 * @param LoggerInterface           $logger The logger.
	 */
	public function __construct(
		CatalogProducts $products_endpoint,
		ProductFactory $product_factory,
		BillingPlans $billing_plans_endpoint,
		BillingCycleFactory $billing_cycle_factory,
		PaymentPreferencesFactory $payment_preferences_factory,
		string $currency,
		LoggerInterface $logger
	) {
		$this->products_endpoint           = $products_endpoint;
		$this->product_factory             = $product_factory;
		$this->billing_plans_endpoint      = $billing_plans_endpoint;
		$this->billing_cycle_factory       = $billing_cycle_factory;
		$this->payment_preferences_factory = $payment_preferences_factory;
		$this->currency                    = $currency;
		$this->logger                      = $logger;
	}

	/**
	 * Creates a Catalog Product and adds it as WC product meta.
	 *
	 * @param WC_Product $product The WC product.
	 * @return void
	 */
	public function create_product( WC_Product $product ) {
		try {
			$subscription_product = $this->products_endpoint->create( $product->get_title(), $product->get_description() );
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

	/**
	 * Creates a subscription plan.
	 *
	 * @param string     $plan_name The plan name.
	 * @param WC_Product $product The WC product.
	 * @return void
	 */
	public function create_plan( string $plan_name, WC_Product $product ): void {
		try {
			$subscription_plan = $this->billing_plans_endpoint->create(
				$plan_name,
				$product->get_meta( 'ppcp_subscription_product' )['id'] ?? '',
				$this->billing_cycles( $product ),
				$this->payment_preferences_factory->from_wc_product( $product )->to_array()
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

	/**
	 * Updates a product.
	 *
	 * @param WC_Product $product The WC product.
	 * @return void
	 */
	public function update_product( WC_Product $product ): void {
		try {
			$catalog_product_id = $product->get_meta( 'ppcp_subscription_product' )['id'] ?? '';
			if ( $catalog_product_id ) {
				$catalog_product             = $this->products_endpoint->product( $catalog_product_id );
				$catalog_product_name        = $catalog_product->name() ?: '';
				$catalog_product_description = $catalog_product->description() ?: '';
				if ( $catalog_product_name !== $product->get_title() || $catalog_product_description !== $product->get_description() ) {
					$data = array();
					if ( $catalog_product_name !== $product->get_title() ) {
						$data[] = (object) array(
							'op'    => 'replace',
							'path'  => '/name',
							'value' => $product->get_title(),
						);
					}
					if ( $catalog_product_description !== $product->get_description() ) {
						$data[] = (object) array(
							'op'    => 'replace',
							'path'  => '/description',
							'value' => $product->get_description(),
						);
					}

					$this->products_endpoint->update( $catalog_product_id, $data );
				}
			}
		} catch ( RuntimeException $exception ) {
			$error = $exception->getMessage();
			if ( is_a( $exception, PayPalApiException::class ) ) {
				$error = $exception->get_details( $error );
			}

			$this->logger->error( 'Could not update catalog product on PayPal. ' . $error );
		}
	}

	/**
	 * Updates a plan.
	 *
	 * @param WC_Product $product The WC product.
	 * @return void
	 */
	public function update_plan( WC_Product $product ): void {
		try {
			$subscription_plan_id = $product->get_meta( 'ppcp_subscription_plan' )['id'] ?? '';
			if ( $subscription_plan_id ) {
				$subscription_plan = $this->billing_plans_endpoint->plan( $subscription_plan_id );

				$price = $subscription_plan->billing_cycles()[0]->pricing_scheme()['fixed_price']['value'] ?? '';
				if ( $price && round( $price, 2 ) !== round( (float) $product->get_price(), 2 ) ) {
					$this->billing_plans_endpoint->update_pricing(
						$subscription_plan_id,
						$this->billing_cycle_factory->from_wc_product( $product )
					);
				}
			}
		} catch ( RuntimeException $exception ) {
			$error = $exception->getMessage();
			if ( is_a( $exception, PayPalApiException::class ) ) {
				$error = $exception->get_details( $error );
			}

			$this->logger->error( 'Could not update subscription plan on PayPal. ' . $error );
		}
	}

	/**
	 * Returns billing cycles based on WC Subscription product.
	 *
	 * @param WC_Product $product The WC Subscription product.
	 * @return array
	 */
	private function billing_cycles( WC_Product $product ): array {
		$billing_cycles = array();
		$sequence       = 1;

		$trial_length = $product->get_meta( '_subscription_trial_length' ) ?? '';
		if ( $trial_length ) {
			$billing_cycles[] = ( new BillingCycle(
				array(
					'interval_unit'  => $product->get_meta( '_subscription_trial_period' ),
					'interval_count' => $product->get_meta( '_subscription_trial_length' ),
				),
				$sequence,
				'TRIAL',
				array(
					'fixed_price' => array(
						'value'         => '0',
						'currency_code' => $this->currency,
					),
				),
				1
			) )->to_array();

			$sequence++;
		}

		$billing_cycles[] = ( new BillingCycle(
			array(
				'interval_unit'  => $product->get_meta( '_subscription_period' ),
				'interval_count' => $product->get_meta( '_subscription_period_interval' ),
			),
			$sequence,
			'REGULAR',
			array(
				'fixed_price' => array(
					'value'         => $product->get_meta( '_subscription_price' ) ?: $product->get_price(),
					'currency_code' => $this->currency,
				),
			),
			(int) $product->get_meta( '_subscription_length' )
		) )->to_array();

		return $billing_cycles;
	}
}
