<?php
/**
 * PayPal Blueprint Bootstrap - Registers exporters and importers.
 *
 * @package WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat\WooCommerceBlueprint;

/**
 * Bootstrap class for PayPal Blueprint functionality.
 */
class PayPalBlueprintBootstrap {

	/**
	 * PayPal Settings Exporter instance, without connection details.
	 *
	 * @var PayPalSettingsExporter
	 */
	private PayPalSettingsExporter $exporter;

	/**
	 * PayPal Settings Exporter instance, including connection details.
	 *
	 * @var PayPalSettingsExporter
	 */
	private PayPalSettingsExporter $exporter_with_connection;

	/**
	 * PayPal Settings Importer instance.
	 *
	 * @var PayPalSettingsImporter
	 */
	private PayPalSettingsImporter $importer;

	/**
	 * Constructor.
	 *
	 * @param PayPalSettingsExporter $exporter                 Default exporter, without connection details.
	 * @param PayPalSettingsExporter $exporter_with_connection Opt-in exporter, including connection details.
	 * @param PayPalSettingsImporter $importer                 PayPal settings importer.
	 */
	public function __construct(
		PayPalSettingsExporter $exporter,
		PayPalSettingsExporter $exporter_with_connection,
		PayPalSettingsImporter $importer
	) {
		$this->exporter                 = $exporter;
		$this->exporter_with_connection = $exporter_with_connection;
		$this->importer                 = $importer;
	}

	/**
	 * Initialize the PayPal Blueprint functionality.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		add_filter( 'wooblueprint_exporters', array( $this, 'register_exporters' ) );
		add_filter( 'wooblueprint_importers', array( $this, 'register_importers' ) );
	}

	/**
	 * Register PayPal exporters.
	 *
	 * @param array $exporters Existing exporters.
	 * @return array
	 */
	public function register_exporters( array $exporters ): array {
		$exporters[] = $this->exporter;
		$exporters[] = $this->exporter_with_connection;
		return $exporters;
	}

	/**
	 * Register PayPal importers.
	 *
	 * @param array $importers Existing importers.
	 * @return array
	 */
	public function register_importers( array $importers ): array {
		$importers[] = $this->importer;
		return $importers;
	}
}
