<?php
/**
 * The blocks module.
 *
 * @package WooCommerce\PayPalCommerce\Blocks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButtonInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Class PayPalPaymentMethod
 */
class PayPalPaymentMethod extends AbstractPaymentMethodType {
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
	 * The smart button script loading handler.
	 *
	 * @var SmartButtonInterface
	 */
	private $smart_button;

	/**
	 * The WC gateway.
	 *
	 * @var PayPalGateway
	 */
	private $gateway;

	/**
	 * Assets constructor.
	 *
	 * @param string               $module_url The url of this module.
	 * @param string               $version    The assets version.
	 * @param SmartButtonInterface $smart_button The smart button script loading handler.
	 * @param PayPalGateway        $gateway The WC gateway.
	 */
	public function __construct(
		string $module_url,
		string $version,
		SmartButtonInterface $smart_button,
		PayPalGateway $gateway
	) {
		$this->name         = PayPalGateway::ID;
		$this->module_url   = $module_url;
		$this->version      = $version;
		$this->smart_button = $smart_button;
		$this->gateway      = $gateway;
	}

	/**
	 * {@inheritDoc}
	 */
	public function initialize() {  }

	/**
	 * {@inheritDoc}
	 */
	public function get_payment_method_script_handles() {
		wp_register_script(
			'ppcp-checkout-block',
			trailingslashit( $this->module_url ) . 'assets/js/checkout-block.js',
			array(),
			$this->version,
			true
		);

		return array( 'ppcp-checkout-block' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_payment_method_data() {
		return array(
			'id'          => $this->gateway->id,
			'title'       => $this->gateway->title,
			'description' => $this->gateway->description,
			'scriptData'  => $this->smart_button->script_data(),
		);
	}
}
