<?php declare(strict_types=1);
/**
 * phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType
 */

namespace Inpsyde\Woocommerce\Logging\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

class NullLogger implements LoggerInterface
{

    use LoggerTrait;

    /**
     * @inheritDoc
     */
    public function log($level, $message, array $context = [])
    {
    }
}
