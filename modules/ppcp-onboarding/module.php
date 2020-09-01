<?php
/**
 * The onboarding module.
 *
 * @package Inpsyde\PayPalCommerce\Onboarding
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Onboarding;

use Dhii\Modular\Module\ModuleInterface;

return static function (): ModuleInterface {
	return new OnboardingModule();
};
