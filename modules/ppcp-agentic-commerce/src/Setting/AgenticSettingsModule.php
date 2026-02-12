<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Setting;

use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Settings\Extension\ExtensionSettingsModule;
use WooCommerce\PayPalCommerce\Settings\Extension\ExtensionRestEndpoint;

use WooCommerce\PayPalCommerce\AgenticCommerce\Registration\RegistrationEligibility;

class AgenticSettingsModule extends ExtensionSettingsModule {

	protected const ASSETS_DIR = 'modules/ppcp-agentic-commerce/assets/';

	protected const SCRIPT_HANDLE = 'ppcp-agentic-commerce-settings';

	private RegistrationEligibility $eligibility_check;

	public function __construct(
		string $absolute_plugin_path,
		string $plugin_main_file,
		ExtensionRestEndpoint $settings_endpoint,
		RegistrationEligibility $eligibility_check,
		AssetGetter $asset_getter
	) {

		parent::__construct( $absolute_plugin_path, $plugin_main_file, $settings_endpoint, $asset_getter );

		$this->eligibility_check = $eligibility_check;
	}

	protected function is_available(): bool {
		return $this->eligibility_check->is_eligible();
	}
}
