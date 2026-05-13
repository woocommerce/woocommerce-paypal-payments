<?php
/**
 * SEPA payment method.
 *
 * @package WooCommerce\PayPalCommerce\SEPA
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SEPA;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Class SEPAPaymentMethod
 */
class SEPAPaymentMethod extends AbstractPaymentMethodType {

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
	 * SEPA WC gateway.
	 *
	 * @var SEPAGateway
	 */
	private $gateway;

	/**
	 * SEPAPaymentMethod constructor.
	 *
	 * @param string      $module_url The URL of this module.
	 * @param string      $version The assets version.
	 * @param SEPAGateway $gateway SEPA WC gateway.
	 */
	public function __construct(
		string $module_url,
		string $version,
		SEPAGateway $gateway
	) {
		$this->module_url = $module_url;
		$this->version    = $version;
		$this->gateway    = $gateway;

		$this->name = SEPAGateway::ID;
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
			'ppcp-sepa-payment-method',
			trailingslashit( $this->module_url ) . 'assets/index.js',
			array(),
			$this->version,
			true
		);

		return array( 'ppcp-sepa-payment-method' );
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
