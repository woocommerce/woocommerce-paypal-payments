<?php declare(strict_types=1);
/**
 * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
 * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
 */

namespace Inpsyde\Woocommerce\Logging;

use Inpsyde\Woocommerce\Logging\Logger\NullLogger;
use Inpsyde\Woocommerce\Logging\Logger\WooCommerceLogger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return [
    'woocommerce.logger.source' => function(): string {
        return 'woocommerce-paypal-commerce-gateway';
    },
    'woocommerce.logger.woocommerce' => function (ContainerInterface $container): LoggerInterface {
        if (!class_exists(\WC_Logger::class)) {
            return new NullLogger();
        }

        $source = $container->get('woocommerce.logger.source');

        return new WooCommerceLogger(
            wc_get_logger(),
            $source
        );
    },
];
