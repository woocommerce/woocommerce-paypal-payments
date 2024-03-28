<?php
/**
 * The Card Fields module.
 *
 * @package WooCommerce\PayPalCommerce\CardFields
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\CardFields;

return static function (): CardFieldsModule {
	return new CardFieldsModule();
};
