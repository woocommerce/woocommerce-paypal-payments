<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Blocks;

use Mockery;
use WooCommerce\PayPalCommerce\Button\Helper\MessagesApply;
use WooCommerce\PayPalCommerce\PayLaterWCBlocks\HookedBlocksRegistrar;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;

use function Brain\Monkey\Filters\expectAdded;

/**
 * Exercises the 'blocks.product-hooked-blocks-registrar' service exactly as it is defined in
 * modules/ppcp-blocks/services.php: the container closure is loaded from the real
 * production file and invoked against a stub container, rather than re-implemented here, so
 * a future edit to that file is what these tests actually protect.
 *
 * ProductBlocks::register() reads this service and calls ->register() on it to auto-insert
 * the product Smart Buttons and Pay Later messaging blocks into block-theme templates via
 * the Block Hooks API.
 *
 * @covers \WooCommerce\PayPalCommerce\PayLaterWCBlocks\HookedBlocksRegistrar
 */
class ServicesTest extends TestCase
{
    private const SMART_BUTTONS_BLOCK = 'woocommerce-paypal-payments/product-smart-buttons';
    private const MESSAGING_BLOCK = 'woocommerce-paypal-payments/product-paylater-messages';

    /**
     * Resolves the 'blocks.product-hooked-blocks-registrar' callable from the real
     * services.php, backed by a container that serves 'wcgateway.settings.status' and
     * 'button.helper.messages-apply', and answers whether the
     * 'paylater-configurator.factory.config' service is available.
     */
    private function resolveRegistrar(
        SettingsStatus $settings_status,
        MessagesApply $messages_apply,
        bool $configurator_available = true
    ): HookedBlocksRegistrar {
        $container = Mockery::mock(ContainerInterface::class);
        $container->shouldReceive('get')
            ->with('wcgateway.settings.status')
            ->andReturn($settings_status);
        $container->shouldReceive('get')
            ->with('button.helper.messages-apply')
            ->andReturn($messages_apply);
        $container->shouldReceive('has')
            ->with('paylater-configurator.factory.config')
            ->andReturn($configurator_available);

        $services = require ROOT_DIR . '/modules/ppcp-blocks/services.php';

        $factory = $services['blocks.product-hooked-blocks-registrar'];

        return $factory($container);
    }

    /**
     * GIVEN the container serves the required collaborators
     * WHEN the 'blocks.product-hooked-blocks-registrar' service is resolved
     * THEN it produces a HookedBlocksRegistrar
     */
    public function testFactoryReturnsAHookedBlocksRegistrar(): void
    {
        $messages_apply = Mockery::mock(MessagesApply::class);
        $messages_apply->shouldReceive('for_country')->andReturn(true);

        $registrar = $this->resolveRegistrar(
            Mockery::mock(SettingsStatus::class),
            $messages_apply
        );

        $this->assertInstanceOf(HookedBlocksRegistrar::class, $registrar);
    }

    /**
     * GIVEN Pay Later messaging applies to the merchant's country and the Pay Later
     *       configurator service is available
     * WHEN the resolved registrar's ->register() is called
     * THEN it wires the shared 'hooked_block_types' filter, the Smart Buttons block filter,
     *      and the Pay Later messaging block filter, since both surfaces are eligible for
     *      auto-insertion
     */
    public function testRegisterWiresBothBlockFiltersWhenMessagingAppliesToTheCountry(): void
    {
        $messages_apply = Mockery::mock(MessagesApply::class);
        $messages_apply->shouldReceive('for_country')->andReturn(true);

        expectAdded('hooked_block_types');
        expectAdded('hooked_block_' . self::SMART_BUTTONS_BLOCK);
        expectAdded('hooked_block_' . self::MESSAGING_BLOCK);

        $registrar = $this->resolveRegistrar(
            Mockery::mock(SettingsStatus::class),
            $messages_apply,
            true
        );
        $registrar->register();

        $this->addToAssertionCount(1);
    }

    /**
     * GIVEN Pay Later messaging does not apply to the merchant's country
     * WHEN the resolved registrar's ->register() is called
     * THEN it wires the Smart Buttons block filter, since that surface is not country-gated
     * AND it does not wire a filter for the Pay Later messaging block, since that insertion
     *     was never added to the registrar's configuration
     */
    public function testRegisterWiresOnlyTheSmartButtonsFilterWhenMessagingDoesNotApplyToTheCountry(): void
    {
        $messages_apply = Mockery::mock(MessagesApply::class);
        $messages_apply->shouldReceive('for_country')->andReturn(false);

        expectAdded('hooked_block_types');
        expectAdded('hooked_block_' . self::SMART_BUTTONS_BLOCK);
        expectAdded('hooked_block_' . self::MESSAGING_BLOCK)->never();

        // The configurator's availability is not the gating factor in this scenario, so it
        // is left available; for_country() is what decides the outcome here.
        $registrar = $this->resolveRegistrar(
            Mockery::mock(SettingsStatus::class),
            $messages_apply,
            true
        );
        $registrar->register();

        $this->addToAssertionCount(1);
    }

    /**
     * GIVEN Pay Later messaging applies to the merchant's country, but the
     *       'paylater-configurator.factory.config' service is not available in the container
     * WHEN the resolved registrar's ->register() is called
     * THEN it wires the shared 'hooked_block_types' filter and the Smart Buttons block filter
     * AND it does not wire a filter for the Pay Later messaging block, since the configurator
     *     guard suppressed that insertion
     */
    public function testRegisterWiresOnlyTheSmartButtonsFilterWhenConfiguratorServiceIsUnavailable(): void
    {
        $messages_apply = Mockery::mock(MessagesApply::class);
        $messages_apply->shouldReceive('for_country')->andReturn(true);

        expectAdded('hooked_block_types');
        expectAdded('hooked_block_' . self::SMART_BUTTONS_BLOCK);
        expectAdded('hooked_block_' . self::MESSAGING_BLOCK)->never();

        $registrar = $this->resolveRegistrar(
            Mockery::mock(SettingsStatus::class),
            $messages_apply,
            false
        );
        $registrar->register();

        $this->addToAssertionCount(1);
    }
}
