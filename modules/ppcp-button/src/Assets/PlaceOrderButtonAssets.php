<?php
/**
 * Register and configure the assets for the Place Order button
 *
 * @package WooCommerce\PayPalCommerce\Button\Assets
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Assets;

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
 * Class PlaceOrderButtonAssets
 */
class PlaceOrderButtonAssets {

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
	 * Session handler.
	 *
	 * @var SessionHandler
	 */
	private $session_handler;

	/**
	 * Whether to use the standard "Place order" button.
	 *
	 * @var bool
	 */
	protected $use_place_order;

	/**
	 * The text for the standard "Place order" button.
	 *
	 * @var string
	 */
	protected $button_text;

	/**
	 * Assets constructor.
	 *
	 * @param string         $module_url The url of this module.
	 * @param string         $version The assets version.
	 * @param SessionHandler $session_handler The Session handler.
	 * @param bool           $use_place_order Whether to use the standard "Place order" button.
	 * @param string         $button_text The text for the standard "Place order" button.
	 */
	public function __construct(
		string $module_url,
		string $version,
		SessionHandler $session_handler,
		bool $use_place_order,
		string $button_text
	) {
		$this->module_url      = $module_url;
		$this->version         = $version;
		$this->session_handler = $session_handler;
		$this->use_place_order = $use_place_order;
		$this->button_text     = $button_text;
	}

	/**
	 * Registers the assets.
	 */
	public function register_assets(): void {
		if ( $this->should_load() ) {
			wp_enqueue_script(
				'ppcp-place-order-button',
				trailingslashit( $this->module_url ) . 'assets/js/place-order-button.js',
				array(),
				$this->version,
				true
			);

			wp_localize_script(
				'ppcp-place-order-button',
				'PpcpPlaceOrderButton',
				array(
					'buttonText' => $this->button_text,
				)
			);
		}
	}

	/**
	 * Checks if the assets should be loaded.
	 */
	protected function should_load(): bool {
		return $this->use_place_order && in_array( $this->context(), array( 'checkout', 'pay-now' ), true );
	}
}
