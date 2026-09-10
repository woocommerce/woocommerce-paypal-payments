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
use WooCommerce\PayPalCommerce\VaultComponent\VaultComponentData;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
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
	private CreditCardGateway $card_gateway;

	/**
	 * The saved-PayPal vault-component data provider, or null when the
	 * ppcp-vault-component module is not loaded (its own feature flag).
	 */
	private ?VaultComponentData $vault_data;

	/**
	 * Whether the saved-PayPal vault component may render (merchant eligibility),
	 * or null when the vault-component module is not loaded.
	 *
	 * @var callable():bool|null
	 */
	private $vault_eligibility;

	private string $vault_client_id;

	/**
	 * Cart-dependent state of the non-express "Place order" method:
	 * array{enabled: bool, text: string, description: string}.
	 * Null when that method is not offered.
	 *
	 * @var callable():array|null
	 */
	private $place_order_data;

	public function __construct(
		SdkV6Manager $manager,
		AssetGetter $asset_getter,
		string $version,
		PayPalGateway $gateway,
		CreditCardGateway $card_gateway,
		?VaultComponentData $vault_data,
		?callable $vault_eligibility,
		string $vault_client_id,
		?callable $place_order_data = null
	) {
		$this->manager           = $manager;
		$this->asset_getter      = $asset_getter;
		$this->version           = $version;
		$this->gateway           = $gateway;
		$this->card_gateway      = $card_gateway;
		$this->vault_data        = $vault_data;
		$this->vault_eligibility = $vault_eligibility;
		$this->vault_client_id   = $vault_client_id;
		$this->place_order_data  = $place_order_data;
	}

	/**
	 * @return void
	 */
	public function initialize() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_style' ) );
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

		$handle = 'wc-ppcp-sdk-v6-blocks';
		$asset  = $this->asset_getter->get_asset_data(
			'checkout-block.js',
			$this->version
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

	/**
	 * Enqueues the styles for the express buttons on block pages.
	 *
	 * Block pages bypass SdkV6Manager::enqueue(), which serves the classic
	 * pages only, so their styles are registered here instead.
	 */
	public function enqueue_style(): void {
		if ( ! $this->is_active() ) {
			return;
		}

		$style_url = $this->asset_getter->get_asset_url( 'checkout-block.css' );
		if ( ! $style_url ) {
			return;
		}

		$handle = 'wc-ppcp-sdk-v6-blocks-style';

		wp_register_style(
			$handle,
			$style_url,
			array(),
			$this->version
		);

		wp_enqueue_style( $handle );
	}

	public function get_payment_method_data(): array {
		/*
		 * - id: the WC gateway that processes the order. The block methods
		 *   registered from this data are several; the gateway behind them is one.
		 * - icon: WooCommerce Blocks' PaymentMethodIcons shape.
		 * - supported_features: without the gateway's own supports, Blocks filters
		 *   the method out of a cart that requires one.
		 */
		$gateway_data = array(
			'id'                 => PayPalGateway::ID,
			'title'              => $this->gateway->title,
			'description'        => $this->gateway->get_description(),
			'icon'               => array(
				array(
					'id'  => 'paypal',
					'alt' => 'PayPal',
					'src' => $this->gateway->icon,
				),
			),
			'supported_features' => array_values( (array) $this->gateway->supports ),
		);

		$data = array_merge( $this->manager->script_data(), $gateway_data );

		// The non-express row: a "Place order" button that redirects to PayPal.
		if ( $this->place_order_data ) {
			$data['place_order'] = ( $this->place_order_data )();
		}

		// The card method registers under the credit-card gateway, so it must
		// advertise that gateway's own supports (independently vaulting-gated).
		if ( isset( $data['card_fields'] ) && is_array( $data['card_fields'] ) ) {
			$data['card_fields']['supported_features'] = array_values( (array) $this->card_gateway->supports );
		}

		// Saved-PayPal selector for returning buyers: the v5 smart-button stack
		// that carried this in blocks is disabled under v6, so surface the same
		// vault-component data here for checkout-block.js to register a saved-token
		// method (mirrors the classic checkout carve-out).
		if ( $this->vault_data && $this->vault_eligibility && ( $this->vault_eligibility )() ) {
			$vault = $this->vault_data->add_localized_data( array() );
			if ( isset( $vault['vault_component'] ) ) {
				$data['vault_component'] = $vault['vault_component'];

				// The saved-payment-methods SDK component is v5 and needs a
				// client-id (v6 authenticates with a client token elsewhere).
				$data['vault_client_id'] = $this->vault_client_id;

				/**
				 * Filters the SDK script attributes forwarded to the vault
				 * component's own SDK load (e.g. a `sdkBaseUrl` override for
				 * staging), mirroring the v5 script_attributes.
				 *
				 * @param array $attributes The SDK script attributes.
				 */
				$data['script_attributes'] = (object) apply_filters(
					'woocommerce_paypal_payments_sdk_script_attributes',
					array()
				);
			}
		}

		return $data;
	}
}
