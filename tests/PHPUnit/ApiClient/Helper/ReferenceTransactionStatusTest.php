<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

use Mockery;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\TestCase;

class ReferenceTransactionStatusTest extends TestCase
{
	public function testReferenceTransactionEnabledReturnsTrueWhenCapabilityIsActive(): void
	{
		$active_capability = new class() {
			public function name(): string
			{
				return 'PAYPAL_WALLET_VAULTING_ADVANCED';
			}

			public function status(): string
			{
				return 'ACTIVE';
			}
		};

		$seller_status = Mockery::mock(SellerStatus::class);
		$seller_status->shouldReceive('capabilities')->once()->andReturn([$active_capability]);

		$partners_endpoint = Mockery::mock(PartnersEndpoint::class);
		$partners_endpoint->shouldReceive('seller_status')->once()->andReturn($seller_status);

		$testee = new ReferenceTransactionStatus($partners_endpoint);

		$this->assertTrue($testee->reference_transaction_enabled());
	}

	public function testReferenceTransactionEnabledReturnsFalseWhenCapabilityIsMissing(): void
	{
		$inactive_capability = new class() {
			public function name(): string
			{
				return 'PAYPAL_WALLET_VAULTING_ADVANCED';
			}

			public function status(): string
			{
				return 'INACTIVE';
			}
		};

		$seller_status = Mockery::mock(SellerStatus::class);
		$seller_status->shouldReceive('capabilities')->once()->andReturn([$inactive_capability]);

		$partners_endpoint = Mockery::mock(PartnersEndpoint::class);
		$partners_endpoint->shouldReceive('seller_status')->once()->andReturn($seller_status);

		$testee = new ReferenceTransactionStatus($partners_endpoint);

		$this->assertFalse($testee->reference_transaction_enabled());
	}

	public function testReferenceTransactionEnabledReturnsFalseWhenSellerStatusFails(): void
	{
		$partners_endpoint = Mockery::mock(PartnersEndpoint::class);
		$partners_endpoint->shouldReceive('seller_status')->once()->andThrow(new RuntimeException('failed'));

		$testee = new ReferenceTransactionStatus($partners_endpoint);

		$this->assertFalse($testee->reference_transaction_enabled());
	}
}
