<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayLaterWCBlocks;

use Mockery;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;

use function Brain\Monkey\Filters\expectAdded;

/**
 * Exercises the 'paylater-wc-blocks.hooked-blocks-registrar' service exactly as it is
 * defined in modules/ppcp-paylater-wc-blocks/services.php: the container closure is
 * loaded from the real production file and invoked against a stub container, rather
 * than re-implemented here, so a future edit to that file is what these tests actually
 * protect.
 *
 * PayLaterWCBlocksModule::run() reads this service and calls ->register() on it to
 * auto-insert the cart and checkout Pay Later messaging blocks into block-theme
 * templates via the Block Hooks API.
 *
 * @covers \WooCommerce\PayPalCommerce\PayLaterWCBlocks\HookedBlocksRegistrar
 */
class ServicesTest extends TestCase
{
    /**
     * Resolves the 'paylater-wc-blocks.hooked-blocks-registrar' callable from the real
     * services.php, backed by a container that only ever serves 'wcgateway.settings.status'.
     */
    private function resolveRegistrar(SettingsStatus $settings_status): HookedBlocksRegistrar
    {
        $container = Mockery::mock(ContainerInterface::class);
        $container->shouldReceive('get')
            ->with('wcgateway.settings.status')
            ->andReturn($settings_status);

        $services = require ROOT_DIR . '/modules/ppcp-paylater-wc-blocks/services.php';

        $factory = $services['paylater-wc-blocks.hooked-blocks-registrar'];

        return $factory($container);
    }

    /**
     * GIVEN the container serves a SettingsStatus instance
     * WHEN the 'paylater-wc-blocks.hooked-blocks-registrar' service is resolved
     * THEN it produces a HookedBlocksRegistrar
     */
    public function testFactoryReturnsAHookedBlocksRegistrar(): void
    {
        $registrar = $this->resolveRegistrar(Mockery::mock(SettingsStatus::class));

        $this->assertInstanceOf(HookedBlocksRegistrar::class, $registrar);
    }

    /**
     * GIVEN the registrar resolved from the real service definition, configured with
     *       the cart and checkout Pay Later messaging block insertions
     * WHEN ->register() is called
     * THEN it wires the shared 'hooked_block_types' filter plus one filter for each of
     *      the cart and checkout messaging block types, so the Block Hooks API can
     *      insert them into block-theme templates
     */
    public function testRegisterWiresTheHookedBlockFilters(): void
    {
        expectAdded('hooked_block_types');
        expectAdded('hooked_block_woocommerce-paypal-payments/cart-paylater-messages');
        expectAdded('hooked_block_woocommerce-paypal-payments/checkout-paylater-messages');

        $registrar = $this->resolveRegistrar(Mockery::mock(SettingsStatus::class));
        $registrar->register();

        $this->addToAssertionCount(1);
    }
}
