<?php
/**
 * Registers the SDK v6 express buttons with the WooCommerce Blocks
 * payment-method pipeline.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Blocks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SdkV6\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\SdkV6\Assets\SdkV6Manager;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * The v6 express buttons are rendered by the React entry this type
 * enqueues; payment processing flows through the existing PayPalGateway
 * (the JS registers express methods with paymentMethodId 'ppcp-gateway').
 */
class V6PaymentMethod extends AbstractPaymentMethodType {

	protected $name = 'ppcp-sdk-v6';

	private SdkV6Manager $manager;
	private AssetGetter $asset_getter;
	private string $version;
	private PayPalGateway $gateway;

	public function __construct(
		SdkV6Manager $manager,
		AssetGetter $asset_getter,
		string $version,
		PayPalGateway $gateway
	) {
		$this->manager      = $manager;
		$this->asset_getter = $asset_getter;
		$this->version      = $version;
		$this->gateway      = $gateway;
	}

	/**
	 * @return void
	 */
	public function initialize() {
	}

	public function is_active() {
		return $this->manager->should_load_on_current_page()
			&& $this->manager->is_block_context();
	}

	public function get_payment_method_script_handles() {
		$script_url = $this->asset_getter->get_asset_url( 'checkout-block.js' );
		if ( ! $script_url ) {
			return array();
		}

		$handle    = 'wc-ppcp-sdk-v6-blocks';
		$asset_php = $this->asset_getter->get_asset_php_path( 'checkout-block.js' );
		$asset     = file_exists( $asset_php )
			? require $asset_php
			: array(
				'dependencies' => array(),
				'version'      => $this->version,
			);

		wp_register_script(
			$handle,
			$script_url,
			$asset['dependencies'],
			$asset['version'],
			true
		);

		return array( $handle );
	}

	public function get_payment_method_data() {
		return array_merge(
			$this->manager->script_data(),
			array(
				'id'          => PayPalGateway::ID,
				'title'       => $this->gateway->title,
				'description' => $this->gateway->get_description(),
			)
		);
	}
}
