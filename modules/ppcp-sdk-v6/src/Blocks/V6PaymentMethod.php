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

	public function __construct(
		SdkV6Manager $manager,
		AssetGetter $asset_getter,
		string $version,
		PayPalGateway $gateway,
		CreditCardGateway $card_gateway,
		?VaultComponentData $vault_data,
		?callable $vault_eligibility,
		string $vault_client_id
	) {
		$this->manager           = $manager;
		$this->asset_getter      = $asset_getter;
		$this->version           = $version;
		$this->gateway           = $gateway;
		$this->card_gateway      = $card_gateway;
		$this->vault_data        = $vault_data;
		$this->vault_eligibility = $vault_eligibility;
		$this->vault_client_id   = $vault_client_id;
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
		$data = array_merge(
			$this->manager->script_data(),
			array(
				'id'                 => PayPalGateway::ID,
				'title'              => $this->gateway->title,
				'description'        => $this->gateway->get_description(),
				// The gateway's (subscription-aware) supported features, so the
				// block methods declare them to WooCommerce Blocks and are not
				// filtered out when the cart requires one (e.g. `subscriptions`).
				// Mirrors the v5 PayPalPaymentMethod::get_payment_method_data().
				'supported_features' => array_values( (array) $this->gateway->supports ),
			)
		);

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
				$data['vault_component']   = $vault['vault_component'];
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
