<?php
/**
 * The googlepay blocks module.
 *
 * @package WooCommerce\PayPalCommerce\Googlepay
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Googlepay\Assets;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodTypeInterface;
use WooCommerce\PayPalCommerce\Button\Assets\ButtonInterface;

/**
 * Class BlocksPaymentMethod
 */
class BlocksPaymentMethod extends AbstractPaymentMethodType {
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
	 * The button.
	 *
	 * @var ButtonInterface
	 */
	private $button;

	/**
	 * The paypal payment method.
	 *
	 * @var PaymentMethodTypeInterface
	 */
	private $paypal_payment_method;

	/**
	 * Assets constructor.
	 *
	 * @param string                     $name The name of this module.
	 * @param string                     $module_url The url of this module.
	 * @param string                     $version The assets version.
	 * @param ButtonInterface            $button The button.
	 * @param PaymentMethodTypeInterface $paypal_payment_method The paypal payment method.
	 */
	public function __construct(
		string $name,
		string $module_url,
		string $version,
		ButtonInterface $button,
		PaymentMethodTypeInterface $paypal_payment_method
	) {
		$this->name                  = $name;
		$this->module_url            = $module_url;
		$this->version               = $version;
		$this->button                = $button;
		$this->paypal_payment_method = $paypal_payment_method;
	}

	/**
	 * {@inheritDoc}
	 */
	public function initialize() {  }

	/**
	 * {@inheritDoc}
	 */
	public function is_active() {
		return $this->paypal_payment_method->is_active();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_payment_method_script_handles() {
		$handle = $this->name . '-block';

		wp_register_script(
			$handle,
			trailingslashit( $this->module_url ) . 'assets/js/boot-block.js',
			array(),
			$this->version,
			true
		);

		return array( $handle );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_payment_method_data() {
		$paypal_data = $this->paypal_payment_method->get_payment_method_data();

		return array(
			'id'          => $this->name,
			'title'       => $paypal_data['title'], // See if we should use another.
			'description' => $paypal_data['description'], // See if we should use another.
			'enabled'     => $paypal_data['enabled'], // This button is enabled when PayPal buttons are.
			'scriptData'  => $this->button->script_data(),
		);
	}
}
