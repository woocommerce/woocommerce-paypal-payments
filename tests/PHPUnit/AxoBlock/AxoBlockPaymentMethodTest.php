<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\AxoBlock;

use Mockery;
use WC_Payment_Gateway;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use function Brain\Monkey\Functions\when;

class AxoBlockPaymentMethodTest extends TestCase
{
    private ?array $localized_data = null;

    public function setUp(): void
    {
        parent::setUp();

        when('is_admin')->justReturn(false);
        when('wp_register_script')->justReturn(true);
        when('wp_create_nonce')->justReturn('nonce');

        when('wp_localize_script')->alias(
            function (string $handle, string $object_name, array $data): bool {
                if ($object_name === 'wc_ppcp_axo') {
                    $this->localized_data = $data;
                }
                return true;
            }
        );
    }

    private function stub_wc(?float $cart_total): void
    {
        $countries = Mockery::mock();
        $countries->allows('get_states')->andReturn(array());
        $countries->allows('get_base_country')->andReturn('DE');

        $wc            = Mockery::mock();
        $wc->countries = $countries;
        $wc->cart      = null;

        if ($cart_total !== null) {
            $cart = Mockery::mock(\WC_Cart::class);
            $cart->allows('get_total')->andReturn($cart_total);
            $wc->cart = $cart;
        }

        when('WC')->justReturn($wc);
    }

    private function create_testee(array $insights_data): AxoBlockPaymentMethod
    {
        $asset_getter = Mockery::mock(AssetGetter::class)->shouldIgnoreMissing();
        $asset_getter->allows('get_asset_php_path')->andReturn('/nonexistent/index.asset.php');

        return new AxoBlockPaymentMethod(
            $asset_getter,
            Mockery::mock(WC_Payment_Gateway::class),
            Mockery::mock(SettingsProvider::class)->shouldIgnoreMissing(),
            Mockery::mock(CardPaymentsConfiguration::class)->shouldIgnoreMissing(),
            new Environment(false),
            Mockery::mock(AssetGetter::class)->shouldIgnoreMissing(),
            array(),
            $insights_data
        );
    }

    /**
     * GIVEN Insights data, with or without a cart
     * WHEN the Axo block registers its script data
     * THEN the amount value is the cart total, or null without a cart
     *
     * @dataProvider cart_total_provider
     */
    public function test_insights_amount_value_reflects_cart_when_script_data_is_registered(
        ?float $cart_total,
        ?float $expected_value
    ): void {
        $this->stub_wc($cart_total);

        $testee = $this->create_testee(
            array(
                'client_id'  => 'client-id',
                'session_id' => 'session',
                'amount'     => array( 'currency_code' => 'EUR' ),
            )
        );

        $testee->get_payment_method_script_handles();

        $this->assertSame(
            array(
                'client_id'  => 'client-id',
                'session_id' => 'session',
                'amount'     => array(
                    'currency_code' => 'EUR',
                    'value'         => $expected_value,
                ),
            ),
            $this->localized_data['insights']
        );
    }

    public function cart_total_provider(): array
    {
        return [
            'cart total is sent' => [42.5, 42.5],
            'no cart sends null' => [null, null],
        ];
    }
}
