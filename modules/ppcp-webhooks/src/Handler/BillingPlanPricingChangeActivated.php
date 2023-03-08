<?php
/**
 * Handles the Webhook BILLING.PLAN.PRICING-CHANGE.ACTIVATED
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use Psr\Log\LoggerInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class BillingPlanPricingChangeActivated
 */
class BillingPlanPricingChangeActivated implements RequestHandler {

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * BillingPlanPricingChangeActivated constructor.
	 *
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * The event types a handler handles.
	 *
	 * @return string[]
	 */
	public function event_types(): array {
		return array(
			'BILLING.PLAN.PRICING-CHANGE.ACTIVATED',
		);
	}

	/**
	 * Whether a handler is responsible for a given request or not.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return bool
	 */
	public function responsible_for_request( WP_REST_Request $request ): bool {
		return in_array( $request['event_type'], $this->event_types(), true );
	}

	/**
	 * Responsible for handling the request.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_request( WP_REST_Request $request ): WP_REST_Response {
		$response = array( 'success' => false );

		$plan_id = wc_clean( wp_unslash( $request['resource']['id'] ?? '' ) );
		$price   = wc_clean( wp_unslash( $request['resource']['billing_cycles'][0]['pricing_scheme']['fixed_price']['value'] ?? '' ) );
		if ( $plan_id && $price ) {
			$args     = array(
				'meta_key' => 'ppcp_subscription_plan',
			);
			$products = wc_get_products( $args );

			foreach ( $products as $product ) {
				if ( $product->get_meta( 'ppcp_subscription_plan' )->id === $plan_id ) {
					$product->update_meta_data( '_subscription_price', $price );
					$product->save();
				}
			}
		}

		$response['success'] = true;
		return new WP_REST_Response( $response );
	}
}
