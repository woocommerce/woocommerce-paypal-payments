<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use Mockery;

/**
 * @covers \WooCommerce\PayPalCommerce\ApiClient\Helper\ProductStatus
 */
class ProductStatusTest extends TestCase {

	public function test_can_instantiate_concrete_implementation(): void {
		$is_connected         = true;
		$partners_endpoint    = Mockery::mock( PartnersEndpoint::class );
		$api_failure_registry = Mockery::mock( FailureRegistry::class );

		$testee = new TestProductStatus( $is_connected, $partners_endpoint, $api_failure_registry );

		$this->assertInstanceOf( ProductStatus::class, $testee );
	}

}

class TestProductStatus extends ProductStatus {

	protected function check_local_state(): ?bool {
		return null;
	}

	protected function check_active_state( SellerStatus $seller_status ): bool {
		return true;
	}

	protected function clear_state( ?Settings $settings = null ): void {
	}
}
