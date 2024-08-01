<?php
/**
 * Advanced card payment method.
 *
 * @package WooCommerce\PayPalCommerce\Blocks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButtonInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class AdvancedCardPaymentMethod
 */
class AdvancedCardPaymentMethod extends AbstractPaymentMethodType {

	/**
	 * The URL of this module.
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
	 * Credit card gateway.
	 *
	 * @var CreditCardGateway
	 */
	private $gateway;

	/**
	 * The smart button script loading handler.
	 *
	 * @var SmartButtonInterface|callable
	 */
	private $smart_button;

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	protected $plugin_settings;

	/**
	 * AdvancedCardPaymentMethod constructor.
	 *
	 * @param string                        $module_url The URL of this module.
	 * @param string                        $version The assets version.
	 * @param CreditCardGateway             $gateway Credit card gateway.
	 * @param SmartButtonInterface|callable $smart_button The smart button script loading handler.
	 * @param Settings                      $settings The settings.
	 */
	public function __construct(
		string $module_url,
		string $version,
		CreditCardGateway $gateway,
		$smart_button,
		Settings $settings
	) {
		$this->name            = CreditCardGateway::ID;
		$this->module_url      = $module_url;
		$this->version         = $version;
		$this->gateway         = $gateway;
		$this->smart_button    = $smart_button;
		$this->plugin_settings = $settings;
	}

	/**
	 * {@inheritDoc}
	 */
	public function initialize() {}

	/**
	 * {@inheritDoc}
	 */
	public function is_active() {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_payment_method_script_handles() {
		wp_register_script(
			'ppcp-advanced-card-checkout-block',
			trailingslashit( $this->module_url ) . 'assets/js/advanced-card-checkout-block.js',
			array(),
			$this->version,
			true
		);

		return array( 'ppcp-advanced-card-checkout-block' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_payment_method_data() {
		$script_data = $this->smart_button_instance()->script_data();

		return array(
			'id'                  => $this->name,
			'title'               => $this->gateway->title,
			'description'         => $this->gateway->description,
			'scriptData'          => $script_data,
			'supports'            => $this->gateway->supports,
			'save_card_text'      => esc_html__( 'Save your card', 'woocommerce-paypal-payments' ),
			'is_vaulting_enabled' => $this->plugin_settings->has( 'vault_enabled_dcc' ) && $this->plugin_settings->get( 'vault_enabled_dcc' ),
			'card_icons'          => $this->plugin_settings->has( 'card_icons' ) ? (array) $this->plugin_settings->get( 'card_icons' ) : array(),
		);
	}

	/**
	 * The smart button.
	 *
	 * @return SmartButtonInterface
	 */
	private function smart_button_instance(): SmartButtonInterface {
		if ( $this->smart_button instanceof SmartButtonInterface ) {
			return $this->smart_button;
		}

		if ( is_callable( $this->smart_button ) ) {
			$this->smart_button = ( $this->smart_button )();
		}

		return $this->smart_button;
	}
}
