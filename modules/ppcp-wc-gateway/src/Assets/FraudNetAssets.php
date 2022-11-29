<?php
/**
 * Register and configure FraudNet assets
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Assets
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Assets;

use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\WcGateway\FraudNet\FraudNet;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PayUponInvoiceGateway;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class FraudNetAssets
 */
class FraudNetAssets {

	/**
	 * The URL of this module.
	 *
	 * @var string
	 */
	protected $module_url;

	/**
	 * The assets version.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * The FraudNet entity.
	 *
	 * @var FraudNet
	 */
	protected $fraud_net;

	/**
	 * The environment.
	 *
	 * @var Environment
	 */
	protected $environment;

	/**
	 * The Settings.
	 *
	 * @var Settings
	 */
	protected $settings;

	/**
	 * The list of enabled PayPal gateways.
	 *
	 * @var string[]
	 */
	protected $enabled_ppcp_gateways;

	/**
	 * The current context.
	 *
	 * @var string
	 */
	protected $context;

	/**
	 * True if FraudNet support is enabled in settings, otherwise false.
	 *
	 * @var bool
	 */
	protected $is_fraudnet_enabled;

	/**
	 * Assets constructor.
	 *
	 * @param string      $module_url The url of this module.
	 * @param string      $version The assets version.
	 * @param FraudNet    $fraud_net The FraudNet entity.
	 * @param Environment $environment The environment.
	 * @param Settings    $settings The Settings.
	 * @param string[]    $enabled_ppcp_gateways The list of enabled PayPal gateways.
	 * @param string      $context The current context.
	 * @param bool        $is_fraudnet_enabled true if FraudNet support is enabled in settings, otherwise false.
	 */
	public function __construct(
		string $module_url,
		string $version,
		FraudNet $fraud_net,
		Environment $environment,
		Settings $settings,
		array $enabled_ppcp_gateways,
		string $context,
		bool $is_fraudnet_enabled
	) {
		$this->module_url            = $module_url;
		$this->version               = $version;
		$this->fraud_net             = $fraud_net;
		$this->environment           = $environment;
		$this->settings              = $settings;
		$this->enabled_ppcp_gateways = $enabled_ppcp_gateways;
		$this->context               = $context;
		$this->is_fraudnet_enabled   = $is_fraudnet_enabled;
	}

	/**
	 * Registers FraudNet assets.
	 */
	public function register_assets(): void {
		add_action(
			'wp_enqueue_scripts',
			function() {
				if ( $this->should_load_fraudnet_script() ) {
					wp_enqueue_script(
						'ppcp-fraudnet',
						trailingslashit( $this->module_url ) . 'assets/js/fraudnet.js',
						array(),
						$this->version,
						true
					);

					wp_localize_script(
						'ppcp-fraudnet',
						'FraudNetConfig',
						array(
							'f'       => $this->fraud_net->session_id(),
							's'       => $this->fraud_net->source_website_id(),
							'sandbox' => $this->environment->current_environment_is( Environment::SANDBOX ),
						)
					);
				}
			}
		);
	}

	/**
	 * Checks if FraudNet script should be loaded.
	 *
	 * @return bool true if FraudNet script should be loaded, otherwise false.
	 */
	protected function should_load_fraudnet_script(): bool {
		if ( empty( $this->enabled_ppcp_gateways ) ) {
			return false;
		}

		$is_pui_gateway_enabled           = in_array( PayUponInvoiceGateway::ID, $this->enabled_ppcp_gateways, true );
		$is_only_standard_gateway_enabled = $this->enabled_ppcp_gateways === array( PayPalGateway::ID );

		if ( $this->context !== 'checkout' || $is_only_standard_gateway_enabled ) {
			return $this->is_fraudnet_enabled && $this->are_buttons_enabled_for_context();
		}

		return $is_pui_gateway_enabled ? true : $this->is_fraudnet_enabled;

	}

	/**
	 * Checks if buttons are enabled for current context.
	 *
	 * @return bool true if enabled, otherwise false.
	 */
	protected function are_buttons_enabled_for_context() : bool {
		if ( ! in_array( PayPalGateway::ID, $this->enabled_ppcp_gateways, true ) ) {
			return false;
		}

		$location_prefix             = $this->context === 'checkout' ? '' : "{$this->context}_";
		$setting_name                = "button_{$location_prefix}enabled";
		$buttons_enabled_for_context = $this->settings->has( $setting_name ) && $this->settings->get( $setting_name );

		if ( $this->context === 'product' ) {
			return $buttons_enabled_for_context || $this->settings->has( 'mini-cart' ) && $this->settings->get( 'mini-cart' );
		}

		if ( $this->context === 'pay-now' ) {
			return true;
		}

		return $buttons_enabled_for_context;
	}
}
