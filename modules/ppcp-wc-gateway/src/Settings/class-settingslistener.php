<?php
/**
 * Listens to requests and updates the settings if necessary.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Settings
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Settings;

use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\PayPalBearer;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\Webhooks\WebhookRegistrar;

/**
 * Class SettingsListener
 */
class SettingsListener {

	const NONCE = 'ppcp-settings';

	/**
	 * The Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Array contains the setting fields.
	 *
	 * @var array
	 */
	private $setting_fields;

	/**
	 * The Webhook Registrar.
	 *
	 * @var WebhookRegistrar
	 */
	private $webhook_registrar;

	/**
	 * The Cache.
	 *
	 * @var Cache
	 */
	private $cache;

	/**
	 * The State.
	 *
	 * @var State
	 */
	private $state;

	/**
	 * The Bearer.
	 *
	 * @var Bearer
	 */
	private $bearer;

	/**
	 * SettingsListener constructor.
	 *
	 * @param Settings         $settings The settings.
	 * @param array            $setting_fields The setting fields.
	 * @param WebhookRegistrar $webhook_registrar The Webhook Registrar.
	 * @param Cache            $cache The Cache.
	 * @param State            $state The state.
	 * @param Bearer           $bearer The bearer.
	 */
	public function __construct(
		Settings $settings,
		array $setting_fields,
		WebhookRegistrar $webhook_registrar,
		Cache $cache,
		State $state,
		Bearer $bearer
	) {

		$this->settings          = $settings;
		$this->setting_fields    = $setting_fields;
		$this->webhook_registrar = $webhook_registrar;
		$this->cache             = $cache;
		$this->state             = $state;
		$this->bearer            = $bearer;
	}

	/**
	 * Listens if the merchant ID should be updated.
	 */
	public function listen_for_merchant_id() {

		if ( ! $this->is_valid_site_request() ) {
			return;
		}

		/**
		 * No nonce provided.
		 * phpcs:disable WordPress.Security.NonceVerification.Missing
		 * phpcs:disable WordPress.Security.NonceVerification.Recommended
		 */
		if ( ! isset( $_GET['merchantIdInPayPal'] ) || ! isset( $_GET['merchantId'] ) ) {
			return;
		}
		$merchant_id    = sanitize_text_field( wp_unslash( $_GET['merchantIdInPayPal'] ) );
		$merchant_email = sanitize_text_field( wp_unslash( $_GET['merchantId'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$this->settings->set( 'merchant_id', $merchant_id );
		$this->settings->set( 'merchant_email', $merchant_email );

		$is_sandbox = $this->settings->has( 'sandbox_on' ) && $this->settings->get( 'sandbox_on' );
		if ( $is_sandbox ) {
			$this->settings->set( 'merchant_id_sandbox', $merchant_id );
			$this->settings->set( 'merchant_email_sandbox', $merchant_email );
		} else {
			$this->settings->set( 'merchant_id_production', $merchant_id );
			$this->settings->set( 'merchant_email_production', $merchant_email );
		}
		$this->settings->persist();

		do_action( 'woocommerce_paypal_payments_onboarding_before_redirect' );

		$redirect_url = apply_filters( 'woocommerce_paypal_payments_onboarding_redirect_url', admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway' ) );
		if ( ! $this->settings->has( 'client_id' ) || ! $this->settings->get( 'client_id' ) ) {
			$redirect_url = add_query_arg( 'ppcp-onboarding-error', '1', $redirect_url );
		}

		wp_safe_redirect( $redirect_url, 302 );
		exit;
	}

	/**
	 * Prevent enabling both Pay Later messaging and PayPal vaulting
	 */
	public function listen_for_vaulting_enabled() {
		if ( ! $this->is_valid_site_request() ) {
			return;
		}

		$token = $this->bearer->bearer();
		if ( ! $token->vaulting_available() ) {
			$this->settings->set( 'vault_enabled', false );
			$this->settings->persist();
			return;
		}

		/**
		 * No need to verify nonce here.
		 *
		 * phpcs:disable WordPress.Security.NonceVerification.Missing
		 * phpcs:disable WordPress.Security.NonceVerification.Recommended
		 */
		if ( ! isset( $_POST['ppcp']['vault_enabled'] ) ) {
			return;
		}

		$this->settings->set( 'message_enabled', false );
		$this->settings->set( 'message_product_enabled', false );
		$this->settings->set( 'message_cart_enabled', false );
		$this->settings->persist();

		$redirect_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway' );
		wp_safe_redirect( $redirect_url, 302 );
		exit;
	}

	/**
	 * Listens to the request.
	 *
	 * @throws \WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException When a setting was not found.
	 */
	public function listen() {

		if ( ! $this->is_valid_update_request() ) {
			return;
		}

		/**
		 * Sanitization is done in retrieve_settings_from_raw_data().
		 *
		 * phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		 *
		 * Nonce verification is done in is_valid_update_request().
		 *
		 * phpcs:disable WordPress.Security.NonceVerification.Missing
		 * phpcs:disable WordPress.Security.NonceVerification.Recommended
		 */
		$raw_data = ( isset( $_POST['ppcp'] ) ) ? (array) wp_unslash( $_POST['ppcp'] ) : array();
		// phpcs:enable phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$settings = $this->retrieve_settings_from_raw_data( $raw_data );

		$settings = $this->read_active_credentials_from_settings( $settings );

		if ( ! isset( $_GET[ SectionsRenderer::KEY ] ) || PayPalGateway::ID === $_GET[ SectionsRenderer::KEY ] ) {
			$settings['enabled'] = isset( $_POST['woocommerce_ppcp-gateway_enabled'] )
				&& 1 === absint( $_POST['woocommerce_ppcp-gateway_enabled'] );
			$this->maybe_register_webhooks( $settings );
		}
		// phpcs:enable phpcs:disable WordPress.Security.NonceVerification.Missing
		// phpcs:enable phpcs:disable WordPress.Security.NonceVerification.Missing

		foreach ( $settings as $id => $value ) {
			$this->settings->set( $id, $value );
		}
		$this->settings->persist();
		if ( $this->cache->has( PayPalBearer::CACHE_KEY ) ) {
			$this->cache->delete( PayPalBearer::CACHE_KEY );
		}

		if ( isset( $_GET['ppcp-onboarding-error'] ) ) {
			$url = remove_query_arg( 'ppcp-onboarding-error' );
			wp_safe_redirect( $url, 302 );
			exit;
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * The actual used client credentials are stored in 'client_secret', 'client_id', 'merchant_id' and 'merchant_email'.
	 * This method populates those fields depending on the sandbox status.
	 *
	 * @param array $settings The settings array.
	 *
	 * @return array
	 */
	private function read_active_credentials_from_settings( array $settings ) : array {
		if ( ! isset( $settings['client_id_sandbox'] ) && ! isset( $settings['client_id_production'] ) ) {
			return $settings;
		}
		$is_sandbox                 = isset( $settings['sandbox_on'] ) && $settings['sandbox_on'];
		$settings['client_id']      = $is_sandbox ? $settings['client_id_sandbox'] : $settings['client_id_production'];
		$settings['client_secret']  = $is_sandbox ? $settings['client_secret_sandbox'] : $settings['client_secret_production'];
		$settings['merchant_id']    = $is_sandbox ? $settings['merchant_id_sandbox'] : $settings['merchant_id_production'];
		$settings['merchant_email'] = $is_sandbox ? $settings['merchant_email_sandbox'] : $settings['merchant_email_production'];
		return $settings;
	}

	/**
	 * Depending on the settings change, we might need to register or unregister the Webhooks at PayPal.
	 *
	 * @param array $settings The settings.
	 *
	 * @throws \WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException If a setting hasn't been found.
	 */
	private function maybe_register_webhooks( array $settings ) {

		if ( ! $this->settings->has( 'client_id' ) && $settings['client_id'] ) {
			$this->settings->set( 'products_dcc_enabled', null );
			$this->webhook_registrar->register();
		}
		if ( $this->settings->has( 'client_id' ) && $this->settings->get( 'client_id' ) ) {
			$current_secret = $this->settings->has( 'client_secret' ) ?
				$this->settings->get( 'client_secret' ) : '';
			if (
				$settings['client_id'] !== $this->settings->get( 'client_id' )
				|| $settings['client_secret'] !== $current_secret
			) {
				$this->settings->set( 'products_dcc_enabled', null );
				$this->webhook_registrar->unregister();
				$this->webhook_registrar->register();
			}
		}
	}

	/**
	 * Sanitizes the settings input data and returns a valid settings array.
	 *
	 * @param array $raw_data The Raw data.
	 *
	 * @return array
	 */
	private function retrieve_settings_from_raw_data( array $raw_data ): array {
		/**
		 * Nonce verification has already been done.
		 * phpcs:disable WordPress.Security.NonceVerification.Recommended
		 */
		if ( ! isset( $_GET['section'] ) ) {
			return array();
		}
		$settings = array();
		foreach ( $this->setting_fields as $key => $config ) {
			if ( ! in_array( $this->state->current_state(), $config['screens'], true ) ) {
				continue;
			}
			if (
				'dcc' === $config['gateway']
				&& (
					! isset( $_GET[ SectionsRenderer::KEY ] )
					|| sanitize_text_field( wp_unslash( $_GET[ SectionsRenderer::KEY ] ) ) !== CreditCardGateway::ID
				)
			) {
				continue;
			}
			if (
			'paypal' === $config['gateway']
				&& isset( $_GET[ SectionsRenderer::KEY ] )
				&& sanitize_text_field( wp_unslash( $_GET[ SectionsRenderer::KEY ] ) ) !== PayPalGateway::ID
			) {
				continue;
			}
			switch ( $config['type'] ) {
				case 'checkbox':
					$settings[ $key ] = isset( $raw_data[ $key ] );
					break;
				case 'text':
				case 'ppcp-text-input':
				case 'ppcp-password':
					$settings[ $key ] = isset( $raw_data[ $key ] ) ? sanitize_text_field( $raw_data[ $key ] ) : '';
					break;
				case 'password':
					if ( empty( $raw_data[ $key ] ) ) {
						break;
					}
					$settings[ $key ] = sanitize_text_field( $raw_data[ $key ] );
					break;
				case 'ppcp-multiselect':
					$values         = isset( $raw_data[ $key ] ) ? (array) $raw_data[ $key ] : array();
					$values_to_save = array();
					foreach ( $values as $index => $raw_value ) {
						$value = sanitize_text_field( $raw_value );
						if ( ! in_array( $value, array_keys( $config['options'] ), true ) ) {
							continue;
						}
						$values_to_save[] = $value;
					}
					$settings[ $key ] = $values_to_save;
					break;
				case 'select':
					$options          = array_keys( $config['options'] );
					$settings[ $key ] = isset( $raw_data[ $key ] ) && in_array(
						sanitize_text_field( $raw_data[ $key ] ),
						$options,
						true
					) ? sanitize_text_field( $raw_data[ $key ] ) : null;
					break;
			}
		}

		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return $settings;
	}

	/**
	 * Evaluates whether the current request is supposed to update the settings.
	 *
	 * @return bool
	 */
	private function is_valid_update_request(): bool {

		if ( ! $this->is_valid_site_request() ) {
			return false;
		}

		if (
			! isset( $_POST['ppcp-nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['ppcp-nonce'] ) ),
				self::NONCE
			)
		) {
			return false;
		}
		return true;
	}

	/**
	 * Whether we are on the settings page and are allowed to be here.
	 *
	 * @return bool
	 */
	private function is_valid_site_request() : bool {

		/**
		 * No nonce needed at this point.
		 *
		 * phpcs:disable WordPress.Security.NonceVerification.Missing
		 * phpcs:disable WordPress.Security.NonceVerification.Recommended
		 */
		if (
			! isset( $_REQUEST['section'] )
			|| ! in_array(
				sanitize_text_field( wp_unslash( $_REQUEST['section'] ) ),
				array( 'ppcp-gateway', 'ppcp-credit-card-gateway' ),
				true
			)
		) {
			return false;
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		return true;
	}
}
