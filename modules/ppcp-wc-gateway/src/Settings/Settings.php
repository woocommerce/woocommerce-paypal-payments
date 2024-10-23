<?php
/**
 * The settings object.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Settings
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Settings;

use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Class Settings
 */
class Settings implements SettingsGetterInterface, ContainerInterface {

	const KEY               = 'woocommerce-ppcp-settings';
	const CONNECTION_TAB_ID = 'ppcp-connection';
	const PAY_LATER_TAB_ID  = 'ppcp-pay-later';

	/**
	 * The settings.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * The list of selected default button locations.
	 *
	 * @var string[]
	 */
	protected $default_button_locations;

	/**
	 * The list of selected default pay later button locations.
	 *
	 * @var string[]
	 */
	protected $default_pay_later_button_locations;

	/**
	 * The list of selected default pay later messaging locations.
	 *
	 * @var string[]
	 */
	protected $default_pay_later_messaging_locations;

	/**
	 * The default ACDC gateway title.
	 *
	 * @var string
	 */
	protected $default_dcc_gateway_title;

	/**
	 * Settings constructor.
	 *
	 * @param string[] $default_button_locations The list of selected default button locations.
	 * @param string   $default_dcc_gateway_title The default ACDC gateway title.
	 * @param string[] $default_pay_later_button_locations The list of selected default pay later button locations.
	 * @param string[] $default_pay_later_messaging_locations The list of selected default pay later messaging locations.
	 */
	public function __construct(
		array $default_button_locations,
		string $default_dcc_gateway_title,
		array $default_pay_later_button_locations,
		array $default_pay_later_messaging_locations
	) {
		$this->default_button_locations              = $default_button_locations;
		$this->default_dcc_gateway_title             = $default_dcc_gateway_title;
		$this->default_pay_later_button_locations    = $default_pay_later_button_locations;
		$this->default_pay_later_messaging_locations = $default_pay_later_messaging_locations;
	}

	/**
	 * Returns the value for an id.
	 *
	 * @param string $id The value identificator.
	 *
	 * @return mixed
	 * @throws NotFoundException When nothing was found.
	 */
	public function get( $id ) {
		if ( ! $this->has( $id ) ) {
			throw new NotFoundException();
		}
		return $this->settings[ $id ];
	}

	/**
	 * Whether a value exists.
	 *
	 * @param string $id The value identificator.
	 *
	 * @return bool
	 */
	public function has( $id ) {
		$this->load();
		return array_key_exists( $id, $this->settings );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Replaces `$settings->has() ? $settings->get() : $default` chains.
	 */
	public function get_value( string $key, $default = null ) {
		try {
			return $this->get( $key );
		} catch ( NotFoundException $e ) {
			return $default;
		}
	}

	/**
	 * Sets a value.
	 *
	 * @param string $id The value identificator.
	 * @param mixed  $value The value.
	 */
	public function set( $id, $value ) {
		$this->load();
		$this->settings[ $id ] = $value;
	}

	/**
	 * Stores the settings to the database.
	 */
	public function persist() {
		return update_option( self::KEY, $this->settings );
	}

	/**
	 * Loads the settings.
	 *
	 * @return bool
	 */
	private function load(): bool {
		if ( $this->settings ) {
			return false;
		}
		$this->settings = get_option( self::KEY, array() );

		$defaults = array(
			'title'                                    => __( 'PayPal', 'woocommerce-paypal-payments' ),
			'description'                              => __(
				'Pay via PayPal.',
				'woocommerce-paypal-payments'
			),
			'smart_button_locations'                   => $this->default_button_locations,
			'smart_button_enable_styling_per_location' => false,
			'pay_later_messaging_enabled'              => true,
			'pay_later_button_enabled'                 => true,
			'pay_later_button_locations'               => $this->default_pay_later_button_locations,
			'pay_later_messaging_locations'            => $this->default_pay_later_messaging_locations,
			'brand_name'                               => get_bloginfo( 'name' ),
			'dcc_gateway_title'                        => $this->default_dcc_gateway_title,
			'dcc_gateway_description'                  => __(
				'Pay with your credit card.',
				'woocommerce-paypal-payments'
			),
		);

		foreach ( $defaults as $key => $value ) {
			if ( isset( $this->settings[ $key ] ) ) {
				$this->settings[ $key ] = apply_filters( 'woocommerce_paypal_payments_settings_value', $this->settings[ $key ], $key );
				continue;
			}
			$this->settings[ $key ] = $value;
		}
		return true;
	}
}
