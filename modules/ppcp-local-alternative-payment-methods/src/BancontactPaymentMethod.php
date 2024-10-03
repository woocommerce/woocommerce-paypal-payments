<?php
/**
 * Bancontact payment method.
 *
 * @package WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Class BancontactPaymentMethod
 */
class BancontactPaymentMethod extends AbstractPaymentMethodType {

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
	 * Bancontact WC gateway.
	 *
	 * @var BancontactGateway
	 */
	private $gateway;

	/**
	 * BancontactPaymentMethod constructor.
	 *
	 * @param string            $module_url The URL of this module.
	 * @param string            $version The assets version.
	 * @param BancontactGateway $gateway Bancontact WC gateway.
	 */
	public function __construct(
		string $module_url,
		string $version,
		BancontactGateway $gateway
	) {
		$this->module_url = $module_url;
		$this->version    = $version;
		$this->gateway    = $gateway;

		$this->name = BancontactGateway::ID;
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
			'ppcp-bancontact-payment-method',
			trailingslashit( $this->module_url ) . 'assets/js/bancontact-payment-method.js',
			array(),
			$this->version,
			true
		);

		return array( 'ppcp-bancontact-payment-method' );
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
