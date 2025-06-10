<?php
/**
 * PayPal Commerce Settings Model
 *
 * @package WooCommerce\PayPalCommerce\Settings\Data
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Data;

use RuntimeException;
use WooCommerce\PayPalCommerce\Settings\Service\DataSanitizer;

/**
 * Class SettingsModel
 *
 * Handles the storage and retrieval of PayPal Commerce settings in WordPress options table.
 */
class SettingsModel extends AbstractDataModel {

	/**
	 * Option key where settings are stored.
	 *
	 * @var string
	 */
	protected const OPTION_KEY = 'woocommerce-ppcp-data-settings';

	/**
	 * Valid options for subtotal adjustment.
	 *
	 * @var array
	 */
	public const SUBTOTAL_ADJUSTMENT_OPTIONS = array( 'no_details', 'correction' );

	/**
	 * Valid options for landing page.
	 *
	 * @var array
	 */
	public const LANDING_PAGE_OPTIONS = array( 'any', 'login', 'guest_checkout' );

	/**
	 * Data sanitizer service.
	 *
	 * @var DataSanitizer
	 */
	protected DataSanitizer $sanitizer;

	/**
	 * Constructor.
	 *
	 * @param DataSanitizer $sanitizer Data sanitizer service.
	 * @throws RuntimeException If the OPTION_KEY is not defined in the child class.
	 */
	public function __construct( DataSanitizer $sanitizer ) {
		$this->sanitizer = $sanitizer;
		parent::__construct();
	}

	/**
	 * Get default values for the model.
	 *
	 * @return array
	 */
	protected function get_defaults() : array {
		return array(
			// Free-form string values.
			'invoice_prefix'         => '',
			'brand_name'             => '',
			'soft_descriptor'        => '',

			// Enum-type string values.
			'subtotal_adjustment'    => 'correction', // Options: [correction|no_details].
			'landing_page'           => 'any',          // Options: [any|login|guest_checkout].
			'button_language'        => '',             // empty or a language locale code.

			// Boolean flags.
			'authorize_only'         => false,
			'capture_virtual_orders' => false,
			'save_paypal_and_venmo'  => false,
			'save_card_details'      => false,
			'enable_pay_now'         => false,
			'enable_logging'         => false,
			'stay_updated'           => true,

			// Array of string values.
			'disabled_cards'         => array(),
		);
	}

	/**
	 * Gets the invoice prefix.
	 *
	 * @return string The invoice prefix.
	 */
	public function get_invoice_prefix() : string {
		return $this->data['invoice_prefix'];
	}

	/**
	 * Sets the invoice prefix.
	 *
	 * @param string $prefix The invoice prefix to set.
	 */
	public function set_invoice_prefix( string $prefix ) : void {
		$this->data['invoice_prefix'] = $this->sanitizer->sanitize_text( $prefix );
	}

	/**
	 * Gets the brand name.
	 *
	 * @return string The brand name.
	 */
	public function get_brand_name(): string {
		return ! empty( $this->data['brand_name'] ) ? $this->data['brand_name'] : get_bloginfo( 'name' );
	}

	/**
	 * Sets the brand name.
	 *
	 * @param string $name The brand name to set.
	 */
	public function set_brand_name( string $name ) : void {
		$this->data['brand_name'] = $this->sanitizer->sanitize_text( $name );
	}

	/**
	 * Gets the soft descriptor.
	 *
	 * @return string The soft descriptor.
	 */
	public function get_soft_descriptor() : string {
		return $this->data['soft_descriptor'];
	}

	/**
	 * Sets the soft descriptor.
	 *
	 * @param string $descriptor The soft descriptor to set.
	 */
	public function set_soft_descriptor( string $descriptor ) : void {
		$descriptor = $this->sanitizer->sanitize_text( $descriptor );
		$descriptor = preg_replace( '/[^a-zA-Z0-9\-*. ]/', '', $descriptor ) ?? '';

		$this->data['soft_descriptor'] = substr( $descriptor, 0, 22 );
	}

	/**
	 * Gets the subtotal adjustment setting.
	 *
	 * @return string The subtotal adjustment setting.
	 */
	public function get_subtotal_adjustment() : string {
		return $this->data['subtotal_adjustment'];
	}

	/**
	 * Sets the subtotal adjustment setting.
	 *
	 * @param string $adjustment The subtotal adjustment to set.
	 */
	public function set_subtotal_adjustment( string $adjustment ) : void {
		$this->data['subtotal_adjustment'] = $this->sanitizer->sanitize_enum( $adjustment, self::SUBTOTAL_ADJUSTMENT_OPTIONS );
	}

	/**
	 * Gets the landing page setting.
	 *
	 * @return string The landing page setting.
	 */
	public function get_landing_page() : string {
		return $this->data['landing_page'];
	}

	/**
	 * Sets the landing page setting.
	 *
	 * @param string $page The landing page to set.
	 */
	public function set_landing_page( string $page ) : void {
		$this->data['landing_page'] = $this->sanitizer->sanitize_enum( $page, self::LANDING_PAGE_OPTIONS );
	}

	/**
	 * Gets the button language setting.
	 *
	 * @return string The button language.
	 */
	public function get_button_language() : string {
		return $this->data['button_language'];
	}

	/**
	 * Sets the button language.
	 *
	 * @param string $language The button language to set.
	 */
	public function set_button_language( string $language ) : void {
		$this->data['button_language'] = $this->sanitizer->sanitize_text( $language );
	}

	/**
	 * Gets the authorize only setting.
	 *
	 * @return bool True if authorize only is enabled, false otherwise.
	 */
	public function get_authorize_only() : bool {
		return $this->data['authorize_only'];
	}

	/**
	 * Sets the authorize only setting.
	 *
	 * @param bool $authorize Whether to enable authorize only.
	 */
	public function set_authorize_only( bool $authorize ) : void {
		$this->data['authorize_only'] = $this->sanitizer->sanitize_bool( $authorize );
	}

	/**
	 * Gets the capture virtual orders setting.
	 *
	 * @return bool True if capturing virtual orders is enabled, false otherwise.
	 */
	public function get_capture_virtual_orders() : bool {
		return $this->data['capture_virtual_orders'];
	}

	/**
	 * Sets the capture virtual orders setting.
	 *
	 * @param bool $capture Whether to capture virtual orders.
	 */
	public function set_capture_virtual_orders( bool $capture ) : void {
		$this->data['capture_virtual_orders'] = $this->sanitizer->sanitize_bool( $capture );
	}

	/**
	 * Gets the save PayPal and Venmo setting.
	 *
	 * @return bool True if saving PayPal and Venmo is enabled, false otherwise.
	 */
	public function get_save_paypal_and_venmo() : bool {
		return $this->data['save_paypal_and_venmo'];
	}

	/**
	 * Sets the save PayPal and Venmo setting.
	 *
	 * @param bool $save Whether to save PayPal and Venmo.
	 */
	public function set_save_paypal_and_venmo( bool $save ) : void {
		$this->data['save_paypal_and_venmo'] = $this->sanitizer->sanitize_bool( $save );
	}

	/**
	 * Gets the save card details setting.
	 *
	 * @return bool True if saving card details is enabled, false otherwise.
	 */
	public function get_save_card_details() : bool {
		return $this->data['save_card_details'];
	}

	/**
	 * Sets the save card details setting.
	 *
	 * @param bool $save Whether to save card details.
	 */
	public function set_save_card_details( bool $save ) : void {
		$this->data['save_card_details'] = $this->sanitizer->sanitize_bool( $save );
	}

	/**
	 * Gets the enable Pay Now setting.
	 *
	 * @return bool True if Pay Now is enabled, false otherwise.
	 */
	public function get_enable_pay_now() : bool {
		return $this->data['enable_pay_now'];
	}

	/**
	 * Sets the enable Pay Now setting.
	 *
	 * @param bool $enable Whether to enable Pay Now.
	 */
	public function set_enable_pay_now( bool $enable ) : void {
		$this->data['enable_pay_now'] = $this->sanitizer->sanitize_bool( $enable );
	}

	/**
	 * Gets the enable logging setting.
	 *
	 * @return bool True if logging is enabled, false otherwise.
	 */
	public function get_enable_logging() : bool {
		return $this->data['enable_logging'];
	}

	/**
	 * Sets the enable logging setting.
	 *
	 * @param bool $enable Whether to enable logging.
	 */
	public function set_enable_logging( bool $enable ) : void {
		$this->data['enable_logging'] = $this->sanitizer->sanitize_bool( $enable );
	}

	/**
	 * Gets the disabled cards.
	 *
	 * @return array The array of disabled cards.
	 */
	public function get_disabled_cards() : array {
		return $this->data['disabled_cards'];
	}

	/**
	 * Sets the disabled cards.
	 *
	 * @param array $cards The array of cards to disable.
	 */
	public function set_disabled_cards( array $cards ) : void {
		$this->data['disabled_cards'] = array_map(
			array( $this->sanitizer, 'sanitize_text' ),
			$cards
		);
	}
}
