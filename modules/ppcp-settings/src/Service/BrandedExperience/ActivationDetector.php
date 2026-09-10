<?php

/**
 * Branded Experience activation detector service.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service\BrandedExperience
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Service\BrandedExperience;

use WooCommerce\PayPalCommerce\Settings\Enum\InstallationPathEnum;
/**
 * Class that includes detection logic for Branded Experience.
 */
class ActivationDetector
{
    /**
     * The expected slug that identifies the "core-profiler" installation path.
     */
    private const ATTACHMENT_CORE_PROFILER = 'payments_settings';
    /**
     * Detects from which path the plugin was installed.
     *
     * @return string The installation path.
     */
    public function detect_activation_path(): string
    {
        /**
         * Get the custom attachment detail which was added by WooCommerce
         *
         * @see PaymentProviders::attach_extension_suggestion()
         */
        $branded_option = get_option('woocommerce_paypal_branded');
        if (self::ATTACHMENT_CORE_PROFILER === $branded_option) {
            return InstallationPathEnum::CORE_PROFILER;
        }
        return InstallationPathEnum::DIRECT;
    }
}
