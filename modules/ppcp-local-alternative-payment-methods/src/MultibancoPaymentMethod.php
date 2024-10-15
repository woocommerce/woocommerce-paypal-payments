<?php
/**
 * Multibanco payment method.
 *
 * @package WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Class MultibancoPaymentMethod
 */
class MultibancoPaymentMethod extends AbstractPaymentMethodType {

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
	 * Multibanco WC gateway.
	 *
	 * @var MultibancoGateway
	 */
	private $gateway;

	/**
	 * MultibancoPaymentMethod constructor.
	 *
	 * @param string            $module_url The URL of this module.
	 * @param string            $version The assets version.
	 * @param MultibancoGateway $gateway Multibanco WC gateway.
	 */
	public function __construct(
		string $module_url,
		string $version,
		MultibancoGateway $gateway
	) {
		$this->module_url = $module_url;
		$this->version    = $version;
		$this->gateway    = $gateway;

		$this->name = MultibancoGateway::ID;
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
			'ppcp-multibanco-payment-method',
			trailingslashit( $this->module_url ) . 'assets/js/multibanco-payment-method.js',
			array(),
			$this->version,
			true
		);

		return array( 'ppcp-multibanco-payment-method' );
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
