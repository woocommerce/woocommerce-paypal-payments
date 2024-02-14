<?php
/**
 * The AXO AxoManager
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Assets
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Axo\Assets;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class AxoManager.
 *
 * @param string $module_url The URL to the module.
 */
class AxoManager {

	/**
	 * The URL to the module.
	 *
	 * @var string
	 */
	private $module_url;

	/**
	 * The assets version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * The environment object.
	 *
	 * @var Environment
	 */
	private $environment;

	/**
	 * The Settings status helper.
	 *
	 * @var SettingsStatus
	 */
	private $settings_status;

	/**
	 * 3-letter currency code of the shop.
	 *
	 * @var string
	 */
	private $currency;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * Session handler.
	 *
	 * @var SessionHandler
	 */
	private $session_handler;

	/**
	 * AxoManager constructor.
	 *
	 * @param string          $module_url The URL to the module.
	 * @param string          $version The assets version.
	 * @param SessionHandler  $session_handler The Session handler.
	 * @param Settings        $settings The Settings.
	 * @param Environment     $environment The environment object.
	 * @param SettingsStatus  $settings_status The Settings status helper.
	 * @param string          $currency 3-letter currency code of the shop.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		string $module_url,
		string $version,
		SessionHandler $session_handler,
		Settings $settings,
		Environment $environment,
		SettingsStatus $settings_status,
		string $currency,
		LoggerInterface $logger
	) {

		$this->module_url      = $module_url;
		$this->version         = $version;
		$this->session_handler = $session_handler;
		$this->settings        = $settings;
		$this->environment     = $environment;
		$this->settings_status = $settings_status;
		$this->currency        = $currency;
		$this->logger          = $logger;
	}

	/**
	 * Enqueues scripts/styles.
	 *
	 * @return void
	 */
	public function enqueue() {

		// Register styles.
		wp_register_style(
			'wc-ppcp-axo',
			untrailingslashit( $this->module_url ) . '/assets/css/styles.css',
			array(),
			$this->version
		);
		wp_enqueue_style( 'wc-ppcp-axo' );

		// Register scripts.
		wp_register_script(
			'wc-ppcp-axo',
			untrailingslashit( $this->module_url ) . '/assets/js/boot.js',
			array(),
			$this->version,
			true
		);
		wp_enqueue_script( 'wc-ppcp-axo' );

		wp_localize_script(
			'wc-ppcp-axo',
			'wc_ppcp_axo',
			$this->script_data()
		);
	}

	/**
	 * The configuration for AXO.
	 *
	 * @return array
	 */
	private function script_data() {
		$email_widget   = $this->settings->has( 'axo_email_widget' ) ? $this->settings->get( 'axo_email_widget' ) : null;
		$address_widget = $this->settings->has( 'axo_address_widget' ) ? $this->settings->get( 'axo_address_widget' ) : null;
		$payment_widget = $this->settings->has( 'axo_payment_widget' ) ? $this->settings->get( 'axo_payment_widget' ) : null;

		return array(
			'widgets' => array(
				'email'   => $email_widget ?: 'render',
				'address' => $address_widget ?: 'render',
				'payment' => $payment_widget ?: 'render',
			)
		);
	}

}
