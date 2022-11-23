<?php
/**
 * Register and configure assets provided by this module.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Assets
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Assets;

use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\FraudNet\FraudNet;

/**
 * Class SettingsPageAssets
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
	 * The session handler.
	 *
	 * @var SessionHandler
	 */
	protected $session_handler;

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
	 * Assets constructor.
	 *
	 * @param string         $module_url The url of this module.
	 * @param string         $version The assets version.
	 * @param SessionHandler $session_handler The session handler.
	 * @param FraudNet       $fraud_net The FraudNet entity.
	 * @param Environment    $environment The environment.
	 */
	public function __construct(
		string $module_url,
		string $version,
		SessionHandler $session_handler,
		FraudNet $fraud_net,
		Environment $environment
	) {
		$this->module_url      = $module_url;
		$this->version         = $version;
		$this->fraud_net       = $fraud_net;
		$this->session_handler = $session_handler;
		$this->environment     = $environment;
	}

	/**
	 * Register assets provided by this module.
	 */
	public function register_assets() {
		add_action(
			'wp_enqueue_scripts',
			function() {
				$gateway_settings = get_option( 'woocommerce_ppcp-pay-upon-invoice-gateway_settings' );
				$gateway_enabled  = $gateway_settings['enabled'] ?? '';
				if ( $gateway_enabled === 'yes' && ! $this->session_handler->order() && ( is_checkout() || is_checkout_pay_page() ) ) {
					wp_enqueue_script(
						'ppcp-pay-upon-invoice',
						trailingslashit( $this->module_url ) . 'assets/js/pay-upon-invoice.js',
						array(),
						$this->version,
						true
					);

					wp_localize_script(
						'ppcp-pay-upon-invoice',
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
}
