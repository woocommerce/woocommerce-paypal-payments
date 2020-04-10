<?php
declare(strict_types=1);

namespace Inpsyde\CacheModule\Exception;

use Psr\SimpleCache\InvalidArgumentException;

/**
 * Class InvalidCacheArgumentException
 *
 * phpcs:disableInpsyde.CodeQuality.LineLength.TooLong
 */
class InvalidCacheArgumentException extends \InvalidArgumentException implements InvalidArgumentException
{
    // phpcs:enable
}
