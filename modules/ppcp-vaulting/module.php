<?php
/**
 * The vaulting module.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vaulting;

use Dhii\Modular\Module\ModuleInterface;

return static function (): ModuleInterface {
	return new VaultingModule();
};
