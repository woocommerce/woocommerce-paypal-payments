<?php
/**
 * Encapsulates all configuration details for "Credit & Debit Card" gateway.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\Axo\Helper\PropertiesDictionary;

/**
 * A simple DTO that provides access to the DCC/AXO gateway settings.
 *
 * This class should not implement business logic, but only provide a convenient
 * way to access gateway settings by wrapping the Settings instance.
 */
class DCCGatewayConfiguration {
	/**
	 * The plugin settings instance.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Whether the Credit Card gateway is enabled.
	 *
	 * @var bool
	 */
	private bool $is_enabled = false;

	/**
	 * Whether to use the Fastlane UI.
	 *
	 * @var bool
	 */
	private bool $use_fastlane = false;

	/**
	 * Gateway title.
	 *
	 * @var string
	 */
	private string $gateway_title = '';

	/**
	 * Gateway description.
	 *
	 * @var string
	 */
	private string $gateway_description = '';

	/**
	 * Whether to display the cardholder's name on the payment form.
	 *
	 * @var string
	 */
	private string $show_name_on_card = 'no';


	/**
	 * Whether the Fastlane watermark should be hidden on the front-end.
	 *
	 * @var bool
	 */
	private bool $hide_fastlane_watermark = false;


	/**
	 * Initializes the gateway details based on the provided Settings instance.
	 *
	 * @throws NotFoundException If an expected gateway setting is not found.
	 *
	 * @param Settings $settings Plugin settings instance.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;

		$this->refresh();
	}

	/**
	 * Refreshes the gateway configuration based on the current settings.
	 *
	 * This method should be used sparingly, usually only on the settings page
	 * when changes in gateway settings must be reflected immediately.
	 *
	 * @throws NotFoundException If an expected gateway setting is not found.
	 */
	public function refresh() : void {
		$is_paypal_enabled = $this->settings->has( 'enabled' )
			&& filter_var( $this->settings->get( 'enabled' ), FILTER_VALIDATE_BOOLEAN );
		$is_dcc_enabled    = $this->settings->has( 'dcc_enabled' )
			&& filter_var( $this->settings->get( 'dcc_enabled' ), FILTER_VALIDATE_BOOLEAN );
		$is_axo_enabled    = $this->settings->has( 'axo_enabled' )
			&& filter_var( $this->settings->get( 'axo_enabled' ), FILTER_VALIDATE_BOOLEAN );

		$this->is_enabled   = $is_paypal_enabled && $is_dcc_enabled;
		$this->use_fastlane = $this->is_enabled && $is_axo_enabled;

		$this->gateway_title       = $this->settings->has( 'dcc_gateway_title' ) ?
			$this->settings->get( 'dcc_gateway_title' ) : '';
		$this->gateway_description = $this->settings->has( 'dcc_gateway_description' ) ?
			$this->settings->get( 'dcc_gateway_description' ) : '';

		$show_on_card = '';
		if ( $this->settings->has( 'dcc_name_on_card' ) ) {
			$show_on_card = $this->settings->get( 'dcc_name_on_card' );
		} elseif ( $this->settings->has( 'axo_name_on_card' ) ) {
			// Legacy. The AXO gateway setting was replaced by the DCC setting.
			$show_on_card = $this->settings->get( 'axo_name_on_card' );
		}
		$valid_options = array_keys( PropertiesDictionary::cardholder_name_options() );

		$this->show_name_on_card = in_array( $show_on_card, $valid_options, true )
			? $show_on_card
			: $valid_options[0];

		/**
		 * Moved from setting "axo_privacy" to a hook-only filter:
		 * Changing this to true (and hiding the watermark) has potential legal
		 * consequences, and therefore is generally discouraged.
		 */
		$this->hide_fastlane_watermark = add_filter(
			'woocommerce_paypal_payments_fastlane_watermark_enabled',
			'__return_false'
		);
	}

	/**
	 * Whether the Credit Card gateway is enabled.
	 *
	 * Requires PayPal features to be enabled.
	 *
	 * @return bool
	 * @todo Some classes still directly access `$settings->get('dcc_enabled')`
	 */
	public function is_enabled() : bool {
		return $this->is_enabled;
	}

	/**
	 * Whether to prefer Fastlane instead of the default Credit Card UI, if
	 * available in the shop's region.
	 *
	 * Requires PayPal features and the Credit Card gateway to be enabled.
	 *
	 * @return bool
	 */
	public function use_fastlane() : bool {
		return $this->use_fastlane;
	}

	/**
	 * User facing title of the gateway.
	 *
	 * @param string $fallback Fallback title if the gateway title is not set.
	 *
	 * @return string Display title of the gateway.
	 */
	public function gateway_title( string $fallback = '' ) : string {
		if ( $this->gateway_title ) {
			return $this->gateway_title;
		}

		return $fallback ?: __( 'Advanced Card Processing', 'woocommerce-paypal-payments' );
	}

	/**
	 * Descriptive text to display on the frontend.
	 *
	 * @param string $fallback Fallback description if the gateway description is not set.
	 *
	 * @return string Display description of the gateway.
	 */
	public function gateway_description( string $fallback = '' ) : string {
		if ( $this->gateway_description ) {
			return $this->gateway_description;
		}

		return $fallback ?: __(
			'Accept debit and credit cards, and local payment methods with PayPal’s latest solution.',
			'woocommerce-paypal-payments'
		);
	}

	/**
	 * Whether to show a field for the cardholder's name in the payment form.
	 *
	 * Note, that this getter returns a string (not a boolean) because the
	 * setting is integrated as a select-list, not a toggle or checkbox.
	 *
	 * @return string ['yes'|'no']
	 */
	public function show_name_on_card() : string {
		return $this->show_name_on_card;
	}

	/**
	 * Whether to display the watermark (text branding) for the Fastlane payment
	 * method in the front end.
	 *
	 * Note: This setting is planned but not implemented yet.
	 *
	 * @retun bool True means, the default watermark is displayed to customers.
	 */
	public function show_fastlane_watermark() : bool {
		return ! $this->hide_fastlane_watermark;
	}
}
