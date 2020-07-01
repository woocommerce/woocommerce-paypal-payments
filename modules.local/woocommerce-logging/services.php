<?php declare(strict_types=1);
/**
 * phpcs:disable Inpsyde.CodeQuality.ReturnTypeDeclaration
 * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration
 */

namespace Inpsyde\Woocommerce\Logging;

use Inpsyde\Woocommerce\Logging\Logger\NullLogger;
use Inpsyde\Woocommerce\Logging\Logger\WooCommerceLogger;
use Psr\Container\ContainerInterface;

return [
    'woocommerce.logger.woocommerce' => function (ContainerInterface $container) {
        if (!class_exists(\WC_Logger::class)) {
            return new NullLogger();
        }

        return new WooCommerceLogger(
            wc_get_logger()
        );
    },
];
