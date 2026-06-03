<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use Mockery;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

class OXXOPaymentMethodTest extends TestCase
{
	private $asset_getter;
	private $gateway;

	public function setUp(): void
	{
		parent::setUp();

		$this->asset_getter = Mockery::mock(AssetGetter::class);
		$this->gateway      = Mockery::mock(OXXOGateway::class);
	}

	public function testIsActive(): void
	{
		$sut = new OXXOPaymentMethod($this->asset_getter, '1.0.0', $this->gateway);

		$this->assertTrue($sut->is_active());
	}

	public function testGetPaymentMethodData(): void
	{
		$this->gateway->title       = 'OXXO';
		$this->gateway->description = 'Pay with an OXXO voucher';
		$this->gateway->icon        = 'https://example.test/oxxo.svg';

		$sut = new OXXOPaymentMethod($this->asset_getter, '1.0.0', $this->gateway);

		$this->assertSame(
			array(
				'id'          => OXXOGateway::ID,
				'title'       => 'OXXO',
				'description' => 'Pay with an OXXO voucher',
				'icon'        => 'https://example.test/oxxo.svg',
			),
			$sut->get_payment_method_data()
		);
	}

	public function testGetPaymentMethodScriptHandles(): void
	{
		when('wp_register_script')->justReturn(true);

		$this->asset_getter->shouldReceive('get_asset_url')
			->once()
			->with('oxxo-payment-method.js')
			->andReturn('https://example.test/oxxo-payment-method.js');

		$sut = new OXXOPaymentMethod($this->asset_getter, '1.0.0', $this->gateway);

		$this->assertSame(array('ppcp-oxxo-payment-method'), $sut->get_payment_method_script_handles());
	}
}
