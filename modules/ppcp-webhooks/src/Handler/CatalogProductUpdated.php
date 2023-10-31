<?php
/**
 * Handles the Webhook CATALOG.PRODUCT.UPDATED
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use Psr\Log\LoggerInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class CatalogProductUpdated
 */
class CatalogProductUpdated implements RequestHandler {
	use RequestHandlerTrait;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * CatalogProductUpdated constructor.
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
			'CATALOG.PRODUCT.UPDATED',
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

		$product_id = wc_clean( wp_unslash( $request['resource']['id'] ?? '' ) );
		$name       = wc_clean( wp_unslash( $request['resource']['name'] ?? '' ) );
		if ( $product_id && $name ) {
			$args     = array(
				// phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_key' => 'ppcp_subscription_product',
			);
			$products = wc_get_products( $args );

			if ( is_array( $products ) ) {
				foreach ( $products as $product ) {
					if (
						$product->meta_exists( 'ppcp_subscription_product' )
						&& isset( $product->get_meta( 'ppcp_subscription_product' )['id'] )
						&& $product->get_meta( 'ppcp_subscription_product' )['id'] === $product_id
						&& $product->get_title() !== $name
					) {
						/**
						 * Suppress ArgumentTypeCoercion
						 *
						 * @psalm-suppress ArgumentTypeCoercion
						 */
						wp_update_post(
							array(
								'ID'         => $product->get_id(),
								'post_title' => $name,
							)
						);

						break;
					}
				}
			}
		}

		return $this->success_response();
	}
}
