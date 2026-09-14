<?php

/**
 * Base class for settings extension data models.
 *
 * Automatically prefixes option keys with 'woocommerce-ppcp-ext-'.
 * Extensions only need to provide their unique NAME constant.
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Extension;

use WooCommerce\PayPalCommerce\Settings\Data\AbstractDataModel;
abstract class ExtensionDataModel extends AbstractDataModel
{
    /**
     * Extension must define the option name!
     *
     * Example: 'agentic' is stored in DB as 'woocommerce-ppcp-ext-agentic'
     */
    protected const NAME = '';
    private const OPTION_PREFIX = 'woocommerce-ppcp-ext-';
    protected function get_option_key(): string
    {
        return self::OPTION_PREFIX . static::NAME;
    }
}
