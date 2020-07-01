<?php declare(strict_types=1);

namespace Inpsyde\Woocommerce\Logging\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

/**
 * Class WooCommerceLogger
 * WooCommerce includes a logger interface, which is fully compatible to PSR-3,
 * but for some reason does not extend/implement it.
 *
 * This is a decorator that makes any WooCommerce Logger PSR-3-compatible
 *
 * @package Inpsyde\IZettle\Logging\Logger
 */
class WooCommerceLogger implements LoggerInterface
{

    use LoggerTrait;

    /**
     * @var \WC_Logger_Interface
     */
    private $wcLogger;
    private $source;

    public function __construct(\WC_Logger_Interface $wcLogger, string $source)
    {
        $this->wcLogger = $wcLogger;
        $this->source = $source;
    }

    /**
     * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType
     *
     * @inheritDoc
     */
    public function log($level, $message, array $context = [])
    {
        if (!isset($context['source'])) {
            $context['source'] = $this->source;
        }
        $this->wcLogger->log($level, $message, $context);
    }
}
