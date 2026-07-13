<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\PayUponInvoice;

use Mockery;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

class PUIPaymentMethodTest extends TestCase
{
	private $asset_getter;
	private $gateway;

	public function setUp(): void
	{
		parent::setUp();

		$this->asset_getter = Mockery::mock(AssetGetter::class);
		$this->gateway      = Mockery::mock(PayUponInvoiceGateway::class);
	}

	public function testIsActive(): void
	{
		$sut = new PUIPaymentMethod($this->asset_getter, '1.0.0', $this->gateway);

		$this->assertTrue($sut->is_active());
	}

	public function testGetPaymentMethodScriptHandles(): void
	{
		when('wp_register_script')->justReturn(true);

		$this->asset_getter->shouldReceive('get_asset_url')
			->once()
			->with('pui-payment-method.js')
			->andReturn('https://example.test/pui-payment-method.js');

		$sut = new PUIPaymentMethod($this->asset_getter, '1.0.0', $this->gateway);

		$this->assertSame(array('ppcp-pui-payment-method'), $sut->get_payment_method_script_handles());
	}

	public function testGetPaymentMethodData(): void
	{
		$this->gateway->title       = 'Pay upon Invoice';
		$this->gateway->description = 'Pay within 30 days';
		$this->gateway->icon        = 'https://example.test/ratepay.svg';

		when('get_bloginfo')->justReturn('de-DE');
		when('apply_filters')->alias(function ($hook, $value) {
			return $value;
		});

		// requires_phone() reads the checkout billing fields; with billing_phone
		// not required, PUI must collect the phone itself.
		$checkout = Mockery::mock();
		$checkout->shouldReceive('get_checkout_fields')->andReturn(
			array(
				'billing' => array(
					'billing_phone' => array('required' => false),
				),
			)
		);
		$wc = Mockery::mock();
		$wc->shouldReceive('checkout')->andReturn($checkout);
		$wc->cart = Mockery::mock(\WC_Cart::class);
		when('WC')->justReturn($wc);

		$sut  = new PUIPaymentMethod($this->asset_getter, '1.0.0', $this->gateway);
		$data = $sut->get_payment_method_data();

		$this->assertSame(PayUponInvoiceGateway::ID, $data['id']);
		$this->assertSame('Pay upon Invoice', $data['title']);
		$this->assertSame('Pay within 30 days', $data['description']);
		$this->assertSame('https://example.test/ratepay.svg', $data['icon']);
		$this->assertTrue($data['requiresBirthDate']);
		$this->assertTrue($data['requiresPhone']);
		$this->assertSame('de', $data['siteLanguage']);
		$this->assertArrayHasKey('placeOrderButtonText', $data);
		$this->assertArrayHasKey('de', $data['ratepayTerms']);
		$this->assertArrayHasKey('en', $data['ratepayTerms']);
		$this->assertNotSame('', $data['ratepayTerms']['de']);
		$this->assertNotSame('', $data['ratepayTerms']['en']);
	}

	public function testGetPaymentMethodDataPhoneNotRequiredWhenBillingPhoneIsRequired(): void
	{
		$this->gateway->title       = 'Pay upon Invoice';
		$this->gateway->description = 'Pay within 30 days';
		$this->gateway->icon        = 'https://example.test/ratepay.svg';

		when('get_bloginfo')->justReturn('en-US');
		when('apply_filters')->alias(function ($hook, $value) {
			return $value;
		});

		// billing_phone present and required, so PUI does not collect it again.
		$checkout = Mockery::mock();
		$checkout->shouldReceive('get_checkout_fields')->andReturn(
			array(
				'billing' => array(
					'billing_phone' => array('required' => true),
				),
			)
		);
		$wc = Mockery::mock();
		$wc->shouldReceive('checkout')->andReturn($checkout);
		$wc->cart = Mockery::mock(\WC_Cart::class);
		when('WC')->justReturn($wc);

		$sut  = new PUIPaymentMethod($this->asset_getter, '1.0.0', $this->gateway);
		$data = $sut->get_payment_method_data();

		$this->assertFalse($data['requiresPhone']);
	}

	public function testGetPaymentMethodDataSkipsCheckoutFieldsWithoutCartContext(): void
	{
		$this->gateway->title       = 'Pay upon Invoice';
		$this->gateway->description = 'Pay within 30 days';
		$this->gateway->icon        = 'https://example.test/ratepay.svg';

		when('get_bloginfo')->justReturn('de-DE');
		when('apply_filters')->alias(function ($hook, $value) {
			return $value;
		});

		// No cart context (e.g. Mini-Cart enqueue off-checkout): the checkout
		// fields must not be built.
		$checkout = Mockery::mock();
		$checkout->shouldNotReceive('get_checkout_fields');
		$wc = Mockery::mock();
		$wc->shouldReceive('checkout')->andReturn($checkout);
		$wc->cart = null;
		when('WC')->justReturn($wc);

		$sut  = new PUIPaymentMethod($this->asset_getter, '1.0.0', $this->gateway);
		$data = $sut->get_payment_method_data();

		// Falls back to the default (PUI collects the phone itself).
		$this->assertTrue($data['requiresPhone']);
	}
}
