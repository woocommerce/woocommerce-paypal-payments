<?php
/**
 * Blik payment method.
 *
 * @package WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Class BlikPaymentMethod
 */
class BlikPaymentMethod extends AbstractPaymentMethodType {

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
	 * Blik WC gateway.
	 *
	 * @var BlikGateway
	 */
	private $gateway;

	/**
	 * @param callable(string):string $asset_url_getter
	 * @param string                  $version The assets version.
	 * @param BlikGateway             $gateway Blik WC gateway.
	 */
	public function __construct(
		callable $asset_url_getter,
		string $version,
		BlikGateway $gateway
	) {
		$this->asset_url_getter = $asset_url_getter;
		$this->version          = $version;
		$this->gateway          = $gateway;

		$this->name = BlikGateway::ID;
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
			'ppcp-blick-payment-method',
			( $this->asset_url_getter )( 'blik-payment-method.js' ),
			array(),
			$this->version,
			true
		);

		return array( 'ppcp-blick-payment-method' );
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
