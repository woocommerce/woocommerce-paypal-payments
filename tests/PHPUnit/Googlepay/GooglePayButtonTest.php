<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Googlepay;

use Mockery;
use WC_Cart;
use WC_Product;
use function Brain\Monkey\Functions\when;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\Googlepay\Assets\GooglePayButton;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\Settings\DTO\LocationStylingDTO;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;

/**
 * @covers \WooCommerce\PayPalCommerce\Googlepay\Assets\GooglePayButton
 */
class GooglePayButtonTest extends TestCase
{
	private $settings;
	private $context;
	private GooglePayButton $sut;

	public function setUp(): void
	{
		parent::setUp();

		$this->settings = Mockery::mock(SettingsProvider::class);
		$this->context  = Mockery::mock(Context::class);

		$environment = Mockery::mock(Environment::class);
		$environment->shouldReceive('is_sandbox')->andReturn(true);

		$this->sut = new GooglePayButton(
			Mockery::mock(AssetGetter::class),
			'https://example.test/sdk.js',
			'1.0.0',
			$this->settings,
			$environment,
			$this->context
		);
	}

	public function testDisabledWhenGooglePayGloballyDisabled(): void
	{
		$this->settings->shouldReceive('googlepay_enabled')->andReturn(false);

		$this->assertFalse($this->sut->is_enabled());
	}

	/**
	 * @scenario Regression test for PCP-4084. A merchant unchecks "Enable payment methods in
	 *           this location" for Classic Checkout but leaves Google Pay selected in that
	 *           location's method list. The location's enabled flag is a master switch, so the
	 *           button must not render even though the method checkbox is still selected.
	 */
	public function testDisabledWhenLocationDisabledEvenIfMethodSelected(): void
	{
		$this->settings->shouldReceive('googlepay_enabled')->andReturn(true);
		$this->context->shouldReceive('context')->andReturn('checkout');
		$this->settings->shouldReceive('button_styling')->with('checkout')->andReturn(
			new LocationStylingDTO('classic_checkout', false, array(GooglePayGateway::ID))
		);

		$this->assertFalse($this->sut->is_enabled());
	}

	public function testEnabledWhenLocationEnabledAndMethodSelected(): void
	{
		$this->settings->shouldReceive('googlepay_enabled')->andReturn(true);
		$this->context->shouldReceive('context')->andReturn('checkout');
		$this->settings->shouldReceive('button_styling')->with('checkout')->andReturn(
			new LocationStylingDTO('classic_checkout', true, array(GooglePayGateway::ID))
		);

		$this->assertTrue($this->sut->is_enabled());
	}

	public function testDisabledWhenLocationEnabledButMethodNotSelected(): void
	{
		$this->settings->shouldReceive('googlepay_enabled')->andReturn(true);
		$this->context->shouldReceive('context')->andReturn('checkout');
		$this->settings->shouldReceive('button_styling')->with('checkout')->andReturn(
			new LocationStylingDTO('classic_checkout', true, array())
		);

		$this->assertFalse($this->sut->is_enabled());
	}

	/**
	 * GIVEN the buyer is on a page of the given context and Pay Now is on or off
	 * WHEN the button script data is built
	 * THEN shipping address collection is enabled only if Pay Now is on and the product
	 *      (product page) or the cart (any other page) requires shipping
	 * AND the shipping country list is exposed only when collection is enabled
	 *
	 * @dataProvider shipping_collection_provider
	 */
	public function test_shipping_collection_follows_what_is_being_bought(
		string $context,
		bool $pay_now,
		bool $is_virtual,
		bool $is_downloadable,
		bool $cart_needs_shipping,
		bool $expected_enabled
	): void {
		$product = Mockery::mock(WC_Product::class);
		$product->shouldReceive('is_virtual')->andReturn($is_virtual);
		$product->shouldReceive('is_downloadable')->andReturn($is_downloadable);
		when('wc_get_product')->justReturn($product);

		$cart = Mockery::mock(WC_Cart::class);
		$cart->shouldReceive('needs_shipping')->andReturn($cart_needs_shipping);

		$shipping = $this->shipping_script_data($context, $pay_now, $cart);

		$this->assertSame($expected_enabled, $shipping['enabled']);
		$this->assertSame($expected_enabled, array_key_exists('countries', $shipping));
	}

	public function shipping_collection_provider(): array {
		return array(
			'product page, physical product, empty cart' => array('product', true, false, false, false, true),
			'product page, physical product, cart needs shipping' => array('product', true, false, false, true, true),
			'product page, virtual product' => array('product', true, true, false, true, false),
			'product page, downloadable product' => array('product', true, false, true, true, false),
			'cart page, cart needs shipping' => array('cart', true, false, false, true, true),
			'cart page, cart does not need shipping' => array('cart', true, false, false, false, false),
			'Pay Now off, product page, physical product' => array('product', false, false, false, false, false),
		);
	}

	private function shipping_script_data(string $context, bool $pay_now, $cart): array {
		$this->settings->shouldReceive('enable_pay_now')->andReturn($pay_now);
		$this->settings->shouldReceive('googlepay_enabled')->andReturn(false);
		$this->settings->shouldReceive('googlepay_styles')->andReturn(new LocationStylingDTO());
		$this->settings->shouldReceive('googlepay_button_language')->andReturn('en');
		$this->context->shouldReceive('context')->andReturn($context);

		$payment_gateways = Mockery::mock();
		$payment_gateways->shouldReceive('get_available_payment_gateways')->andReturn(array());

		when('WC')->justReturn((object) array(
			'cart'             => $cart,
			'payment_gateways' => $payment_gateways,
		));
		when('wc_shipping_enabled')->justReturn(true);
		when('wc_get_shipping_method_count')->justReturn(1);
		when('wp_create_nonce')->justReturn('nonce');

		return $this->sut->script_data()['shipping'];
	}
}
