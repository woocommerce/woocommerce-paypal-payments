<?php
/**
 * The Card Fields module.
 *
 * @package WooCommerce\PayPalCommerce\CardFields
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\CardFields;

use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;

return static function (): ModuleInterface {
	return new CardFieldsModule();
};
