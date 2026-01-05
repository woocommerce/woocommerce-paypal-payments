<?php
/**
 * IDeal payment method.
 *
 * @package WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Class IDealPaymentMethod
 */
class IDealPaymentMethod extends AbstractPaymentMethodType {

	/**
	 * The getter of the URLs for asset files.
	 *
	 * @var callable(string):string
	 */
	private $asset_url_getter;

	/**
	 * The assets version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * IDeal WC gateway.
	 *
	 * @var IDealGateway
	 */
	private $gateway;

	/**
	 * @param callable(string):string $asset_url_getter
	 * @param string                  $version The assets version.
	 * @param IDealGateway            $gateway IDeal WC gateway.
	 */
	public function __construct(
		callable $asset_url_getter,
		string $version,
		IDealGateway $gateway
	) {
		$this->asset_url_getter = $asset_url_getter;
		$this->version          = $version;
		$this->gateway          = $gateway;

		$this->name = IDealGateway::ID;
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
			'ppcp-ideal-payment-method',
			( $this->asset_url_getter )( 'ideal-payment-method.js' ),
			array(),
			$this->version,
			true
		);

		return array( 'ppcp-ideal-payment-method' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_payment_method_data() {
		return array(
			'id'          => $this->name,
			'title'       => $this->gateway->title,
			'description' => $this->gateway->description,
			'icon'        => $this->gateway->icon,
		);
	}
}
