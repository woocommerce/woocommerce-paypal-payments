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
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class AxoBlockPaymentMethodTest extends TestCase
{
    /**
     * @var array<string, mixed>|null
     */
    private $localized_data;

    public function setUp(): void
    {
        parent::setUp();

        $this->localized_data = null;

        when('is_admin')->justReturn(false);
        when('wp_register_script')->justReturn(true);
        when('wp_create_nonce')->justReturn('nonce');

        expect('wp_localize_script')->andReturnUsing(
            function (string $handle, string $object_name, array $data): bool {
                if ($object_name === 'wc_ppcp_axo') {
                    $this->localized_data = $data;
                }
                return true;
            }
        );
    }

    private function stub_wc(?string $cart_total): void
    {
        $countries = Mockery::mock();
        $countries->shouldReceive('get_states')->andReturn(array());
        $countries->shouldReceive('get_base_country')->andReturn('DE');

        $wc            = Mockery::mock();
        $wc->countries = $countries;
        $wc->cart      = null;

        if ($cart_total !== null) {
            $cart = Mockery::mock(\WC_Cart::class);
            $cart->shouldReceive('get_total')->with('numeric')->andReturn((float) $cart_total);
            $wc->cart = $cart;
        }

        when('WC')->justReturn($wc);
    }

    private function createTestee(array $insights_data): AxoBlockPaymentMethod
    {
        return new AxoBlockPaymentMethod(
            Mockery::mock(AssetGetter::class)->shouldIgnoreMissing(),
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
     * GIVEN Insights data for the checkout and a cart with a total
     * WHEN the Axo block registers its frontend script data
     * THEN the localized "insights" data carries the injected values, including whether Insights is enabled
     * AND the amount value is the current cart total
     *
     * @dataProvider insights_enabled_provider
     */
    public function test_insights_data_contains_cart_total_and_keeps_enabled_flag(bool $enabled): void
    {
        $this->stub_wc('42.5');

        $testee = $this->createTestee(
            array(
                'enabled'    => $enabled,
                'client_id'  => 'client-id',
                'session_id' => 'session',
                'amount'     => array( 'currency_code' => 'EUR' ),
            )
        );

        $testee->get_payment_method_script_handles();

        $this->assertSame(
            array(
                'enabled'    => $enabled,
                'client_id'  => 'client-id',
                'session_id' => 'session',
                'amount'     => array(
                    'currency_code' => 'EUR',
                    'value'         => 42.5,
                ),
            ),
            $this->localized_data['insights']
        );
    }

    public function insights_enabled_provider(): array
    {
        return array(
            'insights enabled'  => array( true ),
            'insights disabled' => array( false ),
        );
    }

    /**
     * GIVEN Insights data and no cart available
     * WHEN the Axo block registers its frontend script data
     * THEN the localized insights amount value is null
     */
    public function test_insights_amount_is_null_without_cart(): void
    {
        $this->stub_wc(null);

        $testee = $this->createTestee(
            array(
                'enabled' => true,
                'amount'  => array( 'currency_code' => 'EUR' ),
            )
        );

        $testee->get_payment_method_script_handles();

        $this->assertNull($this->localized_data['insights']['amount']['value']);
        $this->assertSame('EUR', $this->localized_data['insights']['amount']['currency_code']);
    }
}
