<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\CardFields;

use Mockery;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ExperienceContextBuilder;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
use function Brain\Monkey\Actions\expectAdded as expectActionAdded;
use function Brain\Monkey\Filters\expectAdded as expectFilterAdded;

/**
 * @covers \WooCommerce\PayPalCommerce\CardFields\CardFieldsModule
 */
class CardFieldsModuleTest extends TestCase
{
    private $container;
    private $card_payments_configuration;
    private $settings;
    private $experience_context_builder;

    public function setUp(): void
    {
        parent::setUp();

        $this->card_payments_configuration = Mockery::mock(CardPaymentsConfiguration::class);
        $this->settings = Mockery::mock(SettingsProvider::class);
        $this->experience_context_builder = Mockery::mock(ExperienceContextBuilder::class);

        $this->container = Mockery::mock(ContainerInterface::class);
        $this->container->shouldReceive('get')
            ->with('card-fields.eligibility.check')
            ->andReturn(static fn(): bool => true);
        $this->container->shouldReceive('get')
            ->with('wcgateway.configuration.card-configuration')
            ->andReturn($this->card_payments_configuration);
        $this->container->shouldReceive('get')
            ->with('settings.settings-provider')
            ->andReturn($this->settings);
        $this->container->shouldReceive('get')
            ->with('wcgateway.builder.experience-context')
            ->andReturn($this->experience_context_builder);
    }

    /**
     * Runs the module, fires the deferred `init` registration (gated by eligibility), and
     * returns the captured filter callbacks so they can be invoked directly in assertions.
     *
     * @return array{0: callable, 1: callable} [request_body_filter, credit_card_form_fields_filter]
     */
    private function captured_filters(): array
    {
        $captured_init = null;
        $captured_request_body_filter = null;
        $captured_form_fields_filter = null;

        expectActionAdded('init')
            ->once()
            ->whenHappen(
                static function ($callback) use (&$captured_init) {
                    $captured_init = $callback;
                }
            );

        expectFilterAdded('ppcp_create_order_request_body_data')
            ->once()
            ->whenHappen(
                static function ($callback) use (&$captured_request_body_filter) {
                    $captured_request_body_filter = $callback;
                }
            );

        expectFilterAdded('woocommerce_credit_card_form_fields')
            ->once()
            ->whenHappen(
                static function ($callback) use (&$captured_form_fields_filter) {
                    $captured_form_fields_filter = $callback;
                }
            );

        (new CardFieldsModule())->run($this->container);

        $this->assertIsCallable($captured_init);

        // Fires the deferred registration that adds the module's filters.
        $captured_init();

        $this->assertIsCallable($captured_request_body_filter);
        $this->assertIsCallable($captured_form_fields_filter);

        return [$captured_request_body_filter, $captured_form_fields_filter];
    }

    /**
     * GIVEN Advanced Card Fields is enabled and the buyer submitted a cardholder name
     * WHEN the request body for a card payment order is being built
     * THEN the cardholder name is carried into payment_source.card.name, trimmed and
     *      capped at 300 characters
     */
    public function testRequestBodyMapsCardNameToPaymentSource(): void
    {
        $this->card_payments_configuration->shouldReceive('is_enabled')->andReturn(true);
        $this->settings->shouldReceive('three_d_secure_enum')->andReturn('');
        $this->experience_context_builder->shouldReceive('with_endpoint_return_urls')->andReturnSelf();
        $experience_context = Mockery::mock(ExperienceContext::class);
        $experience_context->shouldReceive('to_array')->andReturn(['return_url' => 'https://example.test']);
        $this->experience_context_builder->shouldReceive('build')->andReturn($experience_context);

        [$request_body_filter] = $this->captured_filters();

        $long_name = str_repeat('a', 400);

        $result = $request_body_filter(
            [],
            CreditCardGateway::ID,
            ['card_name' => '  ' . $long_name . '  ']
        );

        $this->assertSame(
            substr($long_name, 0, 300),
            $result['payment_source']['card']['name']
        );
    }

    /**
     * GIVEN Advanced Card Fields is enabled and the buyer did not submit a cardholder name
     * WHEN the request body for a card payment order is being built
     * THEN payment_source.card carries no name key
     */
    public function testRequestBodyOmitsCardNameWhenNotSubmitted(): void
    {
        $this->card_payments_configuration->shouldReceive('is_enabled')->andReturn(true);
        $this->settings->shouldReceive('three_d_secure_enum')->andReturn('');
        $this->experience_context_builder->shouldReceive('with_endpoint_return_urls')->andReturnSelf();
        $experience_context = Mockery::mock(ExperienceContext::class);
        $experience_context->shouldReceive('to_array')->andReturn([]);
        $this->experience_context_builder->shouldReceive('build')->andReturn($experience_context);

        [$request_body_filter] = $this->captured_filters();

        $result = $request_body_filter([], CreditCardGateway::ID, []);

        $this->assertArrayNotHasKey('name', $result['payment_source']['card']);
    }

    /**
     * GIVEN the cardholder name field is enabled for the credit card gateway
     * WHEN WooCommerce builds the credit card form fields
     * THEN the cardholder-name field is placed first
     * AND the existing card-number-field entry survives the reorder under its own key
     */
    public function testCreditCardFormFieldsPrependsNameFieldPreservingKeys(): void
    {
        $this->card_payments_configuration->shouldReceive('is_enabled')->andReturn(true);
        $this->card_payments_configuration->shouldReceive('show_name_on_card')->andReturn('yes');

        [, $form_fields_filter] = $this->captured_filters();

        $default_fields = [
            'card-number-field' => '<p>number field with &bull;&bull;&bull;&bull; placeholder</p>',
            'card-expiry-field' => '<p>expiry field</p>',
        ];

        $result = $form_fields_filter($default_fields, CreditCardGateway::ID);

        $this->assertSame('card-name-field', array_key_first($result));
        $this->assertArrayHasKey('card-number-field', $result);
        $this->assertArrayHasKey('card-expiry-field', $result);
    }
}
