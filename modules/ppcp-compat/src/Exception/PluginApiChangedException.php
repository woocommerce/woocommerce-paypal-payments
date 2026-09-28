<?php

/**
 * The modules Runtime Exception.
 *
 * @package WooCommerce\PayPalCommerce\Compat\Exception
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Compat\Exception;

/**
 * Thrown when an API method of a plugin doesn't exist although that plugin is active.
 */
class PluginApiChangedException extends \RuntimeException
{
}
