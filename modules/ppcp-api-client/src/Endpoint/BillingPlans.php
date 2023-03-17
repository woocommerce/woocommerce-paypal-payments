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
	 * @var BillingCycleFactory
	 */
	private $billing_cycle_factory;

	/**
	 * @var PlanFactory
	 */
	private $plan_factory;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * BillingPlans constructor.
	 *
	 * @param string          $host The host.
	 * @param Bearer          $bearer The bearer.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		string $host,
		Bearer $bearer,
		BillingCycleFactory $billing_cycle_factory,
		PlanFactory $plan_factory,
		LoggerInterface $logger
	) {
		$this->host   = $host;
		$this->bearer = $bearer;
		$this->billing_cycle_factory = $billing_cycle_factory;
		$this->plan_factory = $plan_factory;
		$this->logger = $logger;
	}

	/**
	 * Creates a subscription plan.
	 *
	 * @param string $product_id The product id.
	 *
	 * @return Plan
	 *
	 * @throws RuntimeException If the request fails.
	 * @throws PayPalApiException If the request fails.
	 */
	public function create(
		string $product_id,
		array $billing_cycles,
		array $payment_preferences
	): Plan {

		$data = array(
			'product_id' => $product_id,
			'name' => 'Testing Plan',
			'billing_cycles' => $billing_cycles,
			'payment_preferences' => $payment_preferences
		);

		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v1/billing/plans';
		$args   = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => 'Bearer ' . $bearer->token(),
				'Content-Type'  => 'application/json',
				'Prefer' => 'return=representation'
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

		return $this->plan_factory->from_paypal_response($json);
	}

	/**
	 * Updates a subscription plan.
	 *
	 * @param string $billing_plan_id Billing plan ID.
	 * @param array $billing_cycles Billing cycles.
	 *
	 * @return void
	 *
	 * @throws RuntimeException If the request fails.
	 * @throws PayPalApiException If the request fails.
	 */
	public function update_pricing(string $billing_plan_id, array $billing_cycles):void {
		$data = array(
			"pricing_schemes" => array(
				(object)array(
					"billing_cycle_sequence" => 1,
					"pricing_scheme" => array(
						"fixed_price" => array(
							"value" => $billing_cycles['pricing_scheme']['fixed_price']['value'],
							"currency_code" => "USD"
						),
						"roll_out_strategy" => array(
							"effective_time" => "2022-11-01T00:00:00Z",
							"process_change_from" => "NEXT_PAYMENT"
						),
					),
				),
			),
		);

		$bearer = $this->bearer->bearer();
		$url    = trailingslashit( $this->host ) . 'v1/billing/plans/' . $billing_plan_id . '/update-pricing-schemes';
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
			throw new RuntimeException( 'Not able to create plan.' );
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
