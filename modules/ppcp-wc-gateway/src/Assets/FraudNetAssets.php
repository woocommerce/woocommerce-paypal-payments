<?php
/**
 * Register and configure FraudNet assets
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Assets
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Assets;

use WooCommerce\PayPalCommerce\Button\Helper\ContextTrait;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\FraudNet\FraudNet;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\GatewayRepository;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PayUponInvoiceGateway;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class FraudNetAssets
 */
class FraudNetAssets {

	use ContextTrait;

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
	 * @var string[]|null
	 */
	protected $enabled_ppcp_gateways;

	/**
	 * The GatewayRepository.
	 *
	 * @var GatewayRepository
	 */
	protected $gateway_repository;

	/**
	 * The session handler
	 *
	 * @var SessionHandler
	 */
	protected $session_handler;

	/**
	 * True if FraudNet support is enabled in settings, otherwise false.
	 *
	 * @var bool
	 */
	protected $is_fraudnet_enabled;

	/**
	 * Assets constructor.
	 *
	 * @param string            $module_url The url of this module.
	 * @param string            $version The assets version.
	 * @param FraudNet          $fraud_net The FraudNet entity.
	 * @param Environment       $environment The environment.
	 * @param Settings          $settings The Settings.
	 * @param GatewayRepository $gateway_repository The GatewayRepository.
	 * @param SessionHandler    $session_handler The session handler.
	 * @param bool              $is_fraudnet_enabled true if FraudNet support is enabled in settings, otherwise false.
	 */
	public function __construct(
		string $module_url,
		string $version,
		FraudNet $fraud_net,
		Environment $environment,
		Settings $settings,
		GatewayRepository $gateway_repository,
		SessionHandler $session_handler,
		bool $is_fraudnet_enabled
	) {
		$this->module_url          = $module_url;
		$this->version             = $version;
		$this->fraud_net           = $fraud_net;
		$this->environment         = $environment;
		$this->settings            = $settings;
		$this->gateway_repository  = $gateway_repository;
		$this->session_handler     = $session_handler;
		$this->is_fraudnet_enabled = $is_fraudnet_enabled;
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
		if ( empty( $this->enabled_ppcp_gateways() ) ) {
			return false;
		}

		$is_pui_gateway_enabled           = in_array( PayUponInvoiceGateway::ID, $this->enabled_ppcp_gateways(), true );
		$is_only_standard_gateway_enabled = $this->enabled_ppcp_gateways() === array( PayPalGateway::ID );

		if ( $this->context() !== 'checkout' || $is_only_standard_gateway_enabled ) {
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
		if ( ! in_array( PayPalGateway::ID, $this->enabled_ppcp_gateways(), true ) ) {
			return false;
		}
		try {
			$button_locations = $this->settings->get( 'smart_button_locations' );
		} catch ( NotFoundException $exception ) {
			return false;
		}

		if ( $this->context() === 'pay-now' ) {
			return true;
		}

		if ( $this->context() === 'product' ) {
			return in_array( 'product', $button_locations, true ) || in_array( 'mini-cart', $button_locations, true );
		}

		return in_array( $this->context(), $button_locations, true );
	}

	/**
	 * Returns IDs of the currently enabled PPCP gateways.
	 *
	 * @return string[]
	 */
	protected function enabled_ppcp_gateways(): array {
		if ( null === $this->enabled_ppcp_gateways ) {
			$this->enabled_ppcp_gateways = $this->gateway_repository->get_enabled_ppcp_gateway_ids();
		}
		return $this->enabled_ppcp_gateways;
	}
}
