<?php

/**
 * Persist the Branded Experience path.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service\BrandedExperience
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Service\BrandedExperience;

use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
/**
 * Class that includes logic for persisting the Branded Experience path.
 */
class PathRepository
{
    /**
     * The Branded Experience activation path detector.
     *
     * @var ActivationDetector
     */
    private \WooCommerce\PayPalCommerce\Settings\Service\BrandedExperience\ActivationDetector $activation_detector;
    /**
     * The general settings.
     *
     * @var GeneralSettings
     */
    private GeneralSettings $general_settings;
    /**
     * PathRepository constructor.
     *
     * @param ActivationDetector $activation_detector The Branded Experience activation path detector.
     * @param GeneralSettings    $general_settings The general settings.
     */
    public function __construct(\WooCommerce\PayPalCommerce\Settings\Service\BrandedExperience\ActivationDetector $activation_detector, GeneralSettings $general_settings)
    {
        $this->activation_detector = $activation_detector;
        $this->general_settings = $general_settings;
    }
    /**
     * Persists Branded Experience activation path only once.
     *
     * @return void
     */
    public function persist(): void
    {
        $persisted = $this->general_settings->get_installation_path();
        if ($persisted) {
            return;
        }
        $this->general_settings->set_installation_path($this->activation_detector->detect_activation_path());
        $this->general_settings->save();
    }
}
