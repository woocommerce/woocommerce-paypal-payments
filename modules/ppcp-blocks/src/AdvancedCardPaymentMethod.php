<?php
/**
 * Advanced card payment method.
 *
 * @package WooCommerce\PayPalCommerce\Blocks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;

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

	public function __construct(
		string $module_url,
		string $version,
		CreditCardGateway $gateway
	) {
		$this->name       = CreditCardGateway::ID;
		$this->module_url = $module_url;
		$this->version    = $version;
		$this->gateway    = $gateway;
	}

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
		return array(
			'id'          => $this->name,
			'title'       => $this->gateway->title,
			'description' => $this->gateway->description,
		);
	}
}
