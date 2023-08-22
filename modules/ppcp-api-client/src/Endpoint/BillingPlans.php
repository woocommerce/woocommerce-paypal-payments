<?php
/**
 * The Billing Plans endpoint.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\BillingCycle;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Plan;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\BillingCycleFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PlanFactory;

/**
 * Class BillingPlans
 */
class BillingPlans {

	use RequestTrait;

	/**
	 * The host.
	 *
	 * @var string
	 */
	private $host;

	/**
	 * The bearer.
	 *
	 * @var Bearer
	 */
	private $bearer;

	/**
	 * Billing cycle factory
	 *
	 * @var BillingCycleFactory
	 */
	private $billing_cycle_factory;

	/**
	 * Plan factory
	 *
	 * @var PlanFactory
	 */
	private $plan_factory;

	/**
	 * The logger.
	 *
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * BillingPlans constructor.
	 *
	 * @param string              $host The host.
	 * @param Bearer              $bearer The bearer.
	 * @param BillingCycleFactory $billing_cycle_factory Billing cycle factory.
	 * @param PlanFactory         $plan_factory Plan factory.
	 * @param LoggerInterface     $logger The logger.
	 */
	public function __construct(
		string $host,
		Bearer $bearer,
		BillingCycleFactory $billing_cycle_factory,
		PlanFactory $plan_factory,
		LoggerInterface $logger
	) {
		$this->host                  = $host;
		$this->bearer                = $bearer;
		$this->billing_cycle_factory = $billing_cycle_factory;
		$this->plan_factory          = $plan_factory;
		$this->logger                = $logger;
	}

	/**
	 * Creates a subscription plan.
	 *
	 * @param string $name Product name.
	 * @param string $product_id Product ID.
	 * @param array  $billing_cycles Billing cycles.
	 * @param array  $payment_preferences Payment preferences.
	 *
	 * @return Plan
	 *
	 * @throws RuntimeException If the request fails.
	 * @throws PayPalApiException If the request fails.
	 */
	public function create(
		string $name,
		string $product_id,
		array $billing_cycles,
		array $payment_preferences
	): Plan {

		$data = array(
			'name'                => $name,
			'product_id'          => $product_id,
			'billing_cycles'      => $billing_cycles,
			'payment_preferences' => $payment_preferences,
		);

		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v1/billing/plans';
		$args   = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => 'Bearer ' . $bearer->token(),
				'Content-Type'  => 'application/json',
				'Prefer'        => 'return=representation',
			),
			'body'    => wp_json_encode( $data ),
		);

		$response = $this->request( $url, $args );

		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			throw new RuntimeException( 'Not able to create plan.' );
		}

		$json        = json_decode( $response['body'] );
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 201 !== $status_code ) {
			throw new PayPalApiException(
				$json,
				$status_code
			);
		}

		return $this->plan_factory->from_paypal_response( $json );
	}

	/**
	 * Returns a plan,
	 *
	 * @param string $id Plan ID.
	 *
	 * @return Plan
	 *
	 * @throws RuntimeException If the request fails.
	 * @throws PayPalApiException If the request fails.
	 */
	public function plan( string $id ): Plan {
		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v1/billing/plans/' . $id;
		$args   = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $bearer->token(),
				'Content-Type'  => 'application/json',
				'Prefer'        => 'return=representation',
			),
		);

		$response = $this->request( $url, $args );
		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			throw new RuntimeException( 'Not able to get plan.' );
		}

		$json        = json_decode( $response['body'] );
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			throw new PayPalApiException(
				$json,
				$status_code
			);
		}

		return $this->plan_factory->from_paypal_response( $json );
	}

	/**
	 * Updates pricing.
	 *
	 * @param string       $id Plan ID.
	 * @param BillingCycle $billing_cycle Billing cycle.
	 *
	 * @return void
	 *
	 * @throws RuntimeException If the request fails.
	 * @throws PayPalApiException If the request fails.
	 */
	public function update_pricing( string $id, BillingCycle $billing_cycle ): void {
		$data = array(
			'pricing_schemes' => array(
				(object) array(
					'billing_cycle_sequence' => 1,
					'pricing_scheme'         => $billing_cycle->pricing_scheme(),
				),
			),
		);

		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v1/billing/plans/' . $id . '/update-pricing-schemes';
		$args   = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => 'Bearer ' . $bearer->token(),
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $data ),
		);

		$response = $this->request( $url, $args );
		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			throw new RuntimeException( 'Could not update pricing.' );
		}

		$json        = json_decode( $response['body'] );
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 204 !== $status_code ) {
			throw new PayPalApiException(
				$json,
				$status_code
			);
		}
	}

	/**
	 * Deactivates a Subscription Plan.
	 *
	 * @param string $billing_plan_id The Plan ID.
	 *
	 * @return void
	 *
	 * @throws RuntimeException If the request fails.
	 * @throws PayPalApiException If the request fails.
	 */
	public function deactivate_plan( string $billing_plan_id ) {
		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v1/billing/plans/' . $billing_plan_id . '/deactivate';
		$args   = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => 'Bearer ' . $bearer->token(),
				'Content-Type'  => 'application/json',
			),
		);

		$response = $this->request( $url, $args );
		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			throw new RuntimeException( 'Could not deactivate plan.' );
		}

		$json        = json_decode( $response['body'] );
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 204 !== $status_code ) {
			throw new PayPalApiException(
				$json,
				$status_code
			);
		}
	}
}
