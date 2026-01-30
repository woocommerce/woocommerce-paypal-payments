<?php

/**
 * The onboarding module.
 *
 * @package WooCommerce\PayPalCommerce\Onboarding
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Onboarding;

return static function (): \WooCommerce\PayPalCommerce\Onboarding\OnboardingModule {
    return new \WooCommerce\PayPalCommerce\Onboarding\OnboardingModule();
};
