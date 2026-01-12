<?php
/**
 * Status of local alternative payment methods.
 *
 * @package WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
use WooCommerce\PayPalCommerce\ApiClient\Helper\FailureRegistry;
use WooCommerce\PayPalCommerce\ApiClient\Helper\ProductStatus;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsModel;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class LocalApmProductStatus
 */
class LocalApmProductStatus extends ProductStatus {
	public const SETTINGS_KEY = 'products_local_apms_enabled';

	public const SETTINGS_VALUE_ENABLED   = 'yes';
	public const SETTINGS_VALUE_DISABLED  = 'no';
	public const SETTINGS_VALUE_UNDEFINED = '';

	/**
	 * The settings model.
	 *
	 * @var SettingsModel
	 */
	private SettingsModel $settings_model;

	/**
	 * ApmProductStatus constructor.
	 *
	 * @param SettingsModel    $settings_model       The Settings Model.
	 * @param PartnersEndpoint $partners_endpoint    The Partner Endpoint.
	 * @param bool             $is_connected         The onboarding state.
	 * @param FailureRegistry  $api_failure_registry The API failure registry.
	 */
	public function __construct(
		SettingsModel $settings_model,
		PartnersEndpoint $partners_endpoint,
		bool $is_connected,
		FailureRegistry $api_failure_registry
	) {
		parent::__construct( $is_connected, $partners_endpoint, $api_failure_registry );

		$this->settings_model = $settings_model;
	}

	/** {@inheritDoc} */
	protected function check_local_state(): ?bool {
		$cached_value = $this->settings_model->get_local_apms_enabled();
		if ( $cached_value ) {
			return wc_string_to_bool( $cached_value );
		}

		return null;
	}

	/** {@inheritDoc} */
	protected function check_active_state( SellerStatus $seller_status ): bool {
		$has_capability = false;

		foreach ( $seller_status->capabilities() as $capability ) {
			if ( 'ACTIVE' !== $capability->status() ) {
				continue;
			}
			if ( 'PAYPAL_CHECKOUT_ALTERNATIVE_PAYMENT_METHODS' === $capability->name() ) {
				$has_capability = true;
				break;
			}
		}

		if ( $has_capability ) {
			$this->settings_model->set_local_apms_enabled( self::SETTINGS_VALUE_ENABLED );
		} else {
			$this->settings_model->set_local_apms_enabled( self::SETTINGS_VALUE_DISABLED );
		}
		$this->settings_model->save();

		return $has_capability;
	}

	/** {@inheritDoc} */
	protected function clear_state( ?Settings $settings = null ): void {
		$cached_value = $this->settings_model->get_local_apms_enabled();
		if ( $cached_value ) {
			$this->settings_model->set_local_apms_enabled( self::SETTINGS_VALUE_UNDEFINED );
			$this->settings_model->save();
		}
	}
}
