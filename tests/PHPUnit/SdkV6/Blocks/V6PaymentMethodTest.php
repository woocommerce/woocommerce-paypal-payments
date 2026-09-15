<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SdkV6\Blocks;

use Mockery;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\SdkV6\Assets\SdkV6Manager;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use function Brain\Monkey\Functions\expect;

class V6PaymentMethodTest extends TestCase
{
    private $manager;
    private $asset_getter;
    private $gateway;
    private $card_gateway;

    public function setUp(): void
    {
        parent::setUp();

        $this->manager      = Mockery::mock(SdkV6Manager::class);
        $this->asset_getter = Mockery::mock(AssetGetter::class);
        $this->gateway      = Mockery::mock(PayPalGateway::class);
        $this->card_gateway = Mockery::mock(CreditCardGateway::class);
    }

    private function createTestee(?callable $place_order_data = null): V6PaymentMethod
    {
        return new V6PaymentMethod(
            $this->manager,
            $this->asset_getter,
            '1.0.0',
            $this->gateway,
            $this->card_gateway,
            null,
            null,
            '',
            $place_order_data
        );
    }

    /**
     * GIVEN a compiled checkout-block bundle whose webpack asset file records the
     *       @wordpress/* script handles it depends on and a content-hash version
     * WHEN the block payment method resolves its script handles
     * THEN the script is registered with those exact dependencies and version, not an
     *      empty dependency list and not just the plugin version.
     * AND the resolved handle is returned so WooCommerce Blocks loads it
     */
    public function test_get_payment_method_script_handles_passes_through_webpack_dependencies_and_version(): void
    {
        $this->asset_getter->shouldReceive('get_asset_url')
            ->with('checkout-block.js')
            ->andReturn('https://example.com/assets/checkout-block.js');
        $this->asset_getter->shouldReceive('get_asset_data')
            ->with('checkout-block.js', '1.0.0')
            ->andReturn(['dependencies' => ['wp-data', 'wp-element'], 'version' => 'deadbeef']);

        expect('wp_register_script')
            ->once()
            ->with(
                'wc-ppcp-sdk-v6-blocks',
                'https://example.com/assets/checkout-block.js',
                ['wp-data', 'wp-element'],
                'deadbeef',
                true
            );

        $testee  = $this->createTestee();
        $handles = $testee->get_payment_method_script_handles();

        $this->assertSame(['wc-ppcp-sdk-v6-blocks'], $handles);
    }

    /**
     * GIVEN no compiled checkout-block bundle URL is available
     * WHEN the block payment method resolves its script handles
     * THEN no script is registered and an empty handle list is returned
     */
    public function test_get_payment_method_script_handles_returns_empty_array_when_no_asset_url(): void
    {
        $this->asset_getter->shouldReceive('get_asset_url')
            ->with('checkout-block.js')
            ->andReturn('');

        expect('wp_register_script')->never();

        $testee  = $this->createTestee();
        $handles = $testee->get_payment_method_script_handles();

        $this->assertSame([], $handles);
    }

    private function stubGatewayForPaymentMethodData(): void
    {
        $this->manager->shouldReceive('script_data')->andReturn([]);
        $this->gateway->shouldReceive('get_description')->andReturn('Pay with PayPal.');
        $this->gateway->title    = 'PayPal';
        $this->gateway->icon     = 'https://example.com/paypal-icon.png';
        $this->gateway->supports = ['products'];
    }

    /**
     * GIVEN a PayPal gateway with an icon URL configured
     * WHEN the block payment method data is built
     * THEN the icon is exposed as a single-entry array shaped for WooCommerce
     *      Blocks' PaymentMethodIcons, carrying the gateway's own icon URL
     */
    public function test_get_payment_method_data_exposes_icon_shaped_for_payment_method_icons(): void
    {
        $this->stubGatewayForPaymentMethodData();

        $testee = $this->createTestee();
        $data   = $testee->get_payment_method_data();

        $this->assertSame(
            [
                [
                    'id'  => 'paypal',
                    'alt' => 'PayPal',
                    'src' => 'https://example.com/paypal-icon.png',
                ],
            ],
            $data['icon']
        );
    }

    /**
     * GIVEN no place-order data provider was supplied to the payment method
     * WHEN the block payment method data is built
     * THEN no "place_order" key is present, so no non-express PayPal row is offered
     */
    public function test_get_payment_method_data_omits_place_order_when_no_provider_supplied(): void
    {
        $this->stubGatewayForPaymentMethodData();

        $testee = $this->createTestee();
        $data   = $testee->get_payment_method_data();

        $this->assertArrayNotHasKey('place_order', $data);
    }

    /**
     * GIVEN a place-order data provider whose answer depends on the current cart
     * WHEN the block payment method data is built more than once, after the cart's
     *      state (and therefore the provider's answer) has changed
     * THEN each call exposes the provider's current answer under "place_order",
     *      not the answer captured on the first call
     */
    public function test_get_payment_method_data_reflects_current_place_order_state_on_each_call(): void
    {
        $this->stubGatewayForPaymentMethodData();

        $cartHasSubscription = false;
        $place_order_data    = static function () use (&$cartHasSubscription): array {
            return [
                'enabled'     => ! $cartHasSubscription,
                'text'        => 'Place order',
                'description' => '',
            ];
        };

        $testee = $this->createTestee($place_order_data);

        $firstCallData = $testee->get_payment_method_data();
        $this->assertSame(
            [
                'enabled'     => true,
                'text'        => 'Place order',
                'description' => '',
            ],
            $firstCallData['place_order']
        );

        $cartHasSubscription = true;
        $secondCallData      = $testee->get_payment_method_data();
        $this->assertSame(
            [
                'enabled'     => false,
                'text'        => 'Place order',
                'description' => '',
            ],
            $secondCallData['place_order']
        );
    }
}
