<?php
/**
 * The webhook module.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks;

use Dhii\Modular\Module\ModuleInterface;

return static function (): ModuleInterface {
	return new WebhookModule();
};
