<?php
/**
 * Operations with the WC gateways.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway;

/**
 * Class GatewayRepository
 */
class GatewayRepository {
	/**
	 * IDs of our gateways.
	 *
	 * @var string[]
	 */
	protected $ppcp_gateway_ids;

	/**
	 * GatewayRepository constructor.
	 *
	 * @param string[] $ppcp_gateway_ids IDs of our gateways.
	 */
	public function __construct( array $ppcp_gateway_ids ) {
		$this->ppcp_gateway_ids = $ppcp_gateway_ids;
	}

	/**
	 * Returns IDs of the currently enabled PPCP gateways.
	 *
	 * @return array
	 */
	public function get_enabled_ppcp_gateway_ids(): array {
		$available_gateways = WC()->payment_gateways->get_available_payment_gateways();

		return array_filter(
			$this->ppcp_gateway_ids,
			function ( string $id ) use ( $available_gateways ): bool {
				return isset( $available_gateways[ $id ] );
			}
		);
	}
}
