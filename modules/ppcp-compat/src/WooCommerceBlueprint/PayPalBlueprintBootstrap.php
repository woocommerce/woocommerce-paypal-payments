<?php

/**
 * PayPal Blueprint Bootstrap - Registers exporters and importers.
 *
 * @package WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint;

/**
 * Bootstrap class for PayPal Blueprint functionality.
 */
class PayPalBlueprintBootstrap
{
    /**
     * PayPal Settings Exporter instance.
     *
     * @var PayPalSettingsExporter
     */
    private \WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint\PayPalSettingsExporter $exporter;
    /**
     * PayPal Settings Importer instance.
     *
     * @var PayPalSettingsImporter
     */
    private \WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint\PayPalSettingsImporter $importer;
    /**
     * Constructor.
     *
     * @param PayPalSettingsExporter $exporter PayPal settings exporter.
     * @param PayPalSettingsImporter $importer PayPal settings importer.
     */
    public function __construct(\WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint\PayPalSettingsExporter $exporter, \WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint\PayPalSettingsImporter $importer)
    {
        $this->exporter = $exporter;
        $this->importer = $importer;
    }
    /**
     * Initialize the PayPal Blueprint functionality.
     *
     * @return void
     */
    public function init(): void
    {
        $this->register_hooks();
    }
    /**
     * Register WordPress hooks.
     *
     * @return void
     */
    private function register_hooks(): void
    {
        add_filter('wooblueprint_exporters', array($this, 'register_exporters'));
        add_filter('wooblueprint_importers', array($this, 'register_importers'));
    }
    /**
     * Register PayPal exporters.
     *
     * @param array $exporters Existing exporters.
     * @return array
     */
    public function register_exporters(array $exporters): array
    {
        $exporters[] = $this->exporter;
        return $exporters;
    }
    /**
     * Register PayPal importers.
     *
     * @param array $importers Existing importers.
     * @return array
     */
    public function register_importers(array $importers): array
    {
        $importers[] = $this->importer;
        return $importers;
    }
}
