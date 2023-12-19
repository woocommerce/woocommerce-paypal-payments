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
	use RequestHandlerTrait;

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
		if ( is_null( $request['resource'] ) ) {
			return $this->failure_response();
		}

		$plan_id = wc_clean( wp_unslash( $request['resource']['id'] ?? '' ) );
		if ( $plan_id && ! empty( $request['resource']['billing_cycles'] ) ) {
			$args = array(
				// phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_key' => 'ppcp_subscription_plan',
			);

			$products = wc_get_products( $args );
			if ( is_array( $products ) ) {
				foreach ( $products as $product ) {
					if ( $product->get_meta( 'ppcp_subscription_plan' )['id'] === $plan_id ) {
						foreach ( $request['resource']['billing_cycles'] as $cycle ) {
							if ( $cycle['tenure_type'] === 'REGULAR' ) {
								$product->update_meta_data( '_subscription_price', $cycle['pricing_scheme']['fixed_price']['value'] );
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
