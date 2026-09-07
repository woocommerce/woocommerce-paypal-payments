<?php

/**
 * The abilities module.
 *
 * @package WooCommerce\PayPalCommerce\Abilities
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Abilities;

use Throwable;
use WooCommerce\PayPalCommerce\Abilities\Domain\AbstractPpcpAbility;
use WooCommerce\WooCommerce\Logging\Logger\NullLogger;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
/**
 * Wires the AbilitiesRegistrar into the plugin lifecycle. Per-ability
 * registration is gated behind the
 * `woocommerce_paypal_payments_abilities_enabled` flag (default false) and the
 * WC 10.9 AbilitiesLoader check inside AbilitiesRegistrar::init().
 */
class AbilitiesModule implements ServiceModule, ExecutableModule
{
    use ModuleClassNameIdTrait;
    /**
     * {@inheritDoc}
     */
    public function services(): array
    {
        return require __DIR__ . '/../services.php';
    }
    /**
     * {@inheritDoc}
     *
     * Resolves the plugin's PSR-3 logger once and hands it to
     * AbstractPpcpAbility's static seam so runtime error paths flow through
     * wc_get_logger(); a NullLogger fallback keeps resolution failure from
     * breaking the abilities surface. AbilitiesRegistrar is a static
     * coordinator; Shape-3 abilities resolve backing services lazily at execute().
     *
     * @param ContainerInterface $c A services container instance.
     */
    public function run(ContainerInterface $c): bool
    {
        try {
            $logger = $c->get('woocommerce.logger.woocommerce');
        } catch (Throwable $e) {
            $logger = new NullLogger();
        }
        AbstractPpcpAbility::set_logger($logger);
        \WooCommerce\PayPalCommerce\Abilities\AbilitiesRegistrar::init();
        return \true;
    }
}
