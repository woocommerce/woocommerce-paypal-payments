<?php
/**
 * Pay with Crypto payment method.
 *
 * @package WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Class PWCPaymentMethod
 */
class PWCPaymentMethod extends AbstractPaymentMethodType {

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
	private string $version;

	/**
	 * PWCGateway WC gateway.
	 *
	 * @var PWCGateway
	 */
	private PWCGateway $gateway;

	/**
	 * @param callable(string):string $asset_url_getter
	 * @param string                  $version The assets version.
	 * @param PWCGateway              $gateway Pay with Crypto WC gateway.
	 */
	public function __construct(
		callable $asset_url_getter,
		string $version,
		PWCGateway $gateway
	) {
		$this->asset_url_getter = $asset_url_getter;
		$this->version          = $version;
		$this->gateway          = $gateway;

		$this->name = PWCGateway::ID;
	}

	/**
	 * {@inheritDoc}
	 */
	public function initialize(): void {}

	/**
	 * {@inheritDoc}
	 */
	public function is_active(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_payment_method_script_handles(): array {
		wp_register_script(
			'ppcp-pwc-payment-method',
			( $this->asset_url_getter )( 'pwc-payment-method.js' ),
			array(),
			$this->version,
			true
		);

		return array( 'ppcp-pwc-payment-method' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_payment_method_data(): array {
		return array(
			'id'          => $this->name,
			'title'       => $this->gateway->title,
			'description' => $this->gateway->description,
			'icon'        => $this->gateway->icon,
		);
	}
}
