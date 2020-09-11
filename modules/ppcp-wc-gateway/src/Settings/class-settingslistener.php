<?php
/**
 * Listens to requests and updates the settings if necessary.
 *
 * @package Inpsyde\PayPalCommerce\WcGateway\Settings
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Settings;

use Inpsyde\PayPalCommerce\ApiClient\Authentication\PayPalBearer;
use Inpsyde\PayPalCommerce\ApiClient\Helper\Cache;
use Inpsyde\PayPalCommerce\Onboarding\State;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use Inpsyde\PayPalCommerce\Webhooks\WebhookRegistrar;
use Psr\SimpleCache\CacheInterface;

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
	 * SettingsListener constructor.
	 *
	 * @param Settings         $settings The settings.
	 * @param array            $setting_fields The setting fields.
	 * @param WebhookRegistrar $webhook_registrar The Webhook Registrar.
	 * @param Cache            $cache The Cache.
	 * @param State            $state The state.
	 */
	public function __construct(
		Settings $settings,
		array $setting_fields,
		WebhookRegistrar $webhook_registrar,
		Cache $cache,
		State $state
	) {

		$this->settings          = $settings;
		$this->setting_fields    = $setting_fields;
		$this->webhook_registrar = $webhook_registrar;
		$this->cache             = $cache;
		$this->state             = $state;
	}

	/**
	 * Listens to the request.
	 *
	 * @throws \Inpsyde\PayPalCommerce\WcGateway\Exception\NotFoundException When a setting was not found.
	 * @throws \Psr\SimpleCache\InvalidArgumentException  When the argument was invalid.
	 */
	public function listen() {

		if ( ! $this->is_valid_update_request() ) {
			return;
		}

		/**
		 * Nonce verification has been done in is_valid_update_request().
		 *
		 * phpcs:disable WordPress.Security.NonceVerification.Missing
		 * phpcs:disable WordPress.Security.NonceVerification.Recommended
		 */
		if ( isset( $_POST['save'] ) && sanitize_text_field( wp_unslash( $_POST['save'] ) ) === 'reset' ) {
			$this->settings->reset();
			$this->settings->persist();
			$this->webhook_registrar->unregister();
			if ( $this->cache->has( PayPalBearer::CACHE_KEY ) ) {
				$this->cache->delete( PayPalBearer::CACHE_KEY );
			}
			return;
		}

		/**
		 * Sanitization is done in retrieve_settings_from_raw_data().
		 *
		 * phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		 */
		$raw_data = ( isset( $_POST['ppcp'] ) ) ? (array) wp_unslash( $_POST['ppcp'] ) : array();
		// phpcs:enable phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$settings = $this->retrieve_settings_from_raw_data( $raw_data );
		if ( ! isset( $_GET[ SectionsRenderer::KEY ] ) || PayPalGateway::ID === $_GET[ SectionsRenderer::KEY ] ) {
			$settings['enabled'] = isset( $_POST['woocommerce_ppcp-gateway_enabled'] )
				&& 1 === absint( $_POST['woocommerce_ppcp-gateway_enabled'] );
			$this->maybe_register_webhooks( $settings );
		}

		foreach ( $settings as $id => $value ) {
			$this->settings->set( $id, $value );
		}
		$this->settings->persist();
		if ( $this->cache->has( PayPalBearer::CACHE_KEY ) ) {
			$this->cache->delete( PayPalBearer::CACHE_KEY );
		}

		// phpcs:enable WordPress.Security.NonceVerification.Missing
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Depending on the settings change, we might need to register or unregister the Webhooks at PayPal.
	 *
	 * @param array $settings The settings.
	 *
	 * @throws \Inpsyde\PayPalCommerce\WcGateway\Exception\NotFoundException If a setting hasn't been found.
	 */
	private function maybe_register_webhooks( array $settings ) {

		if ( ! $this->settings->has( 'client_id' ) && $settings['client_id'] ) {
			$this->webhook_registrar->register();
		}
		if ( $this->settings->has( 'client_id' ) && $this->settings->get( 'client_id' ) ) {
			$current_secret = $this->settings->has( 'client_secret' ) ?
				$this->settings->get( 'client_secret' ) : '';
			if (
				$settings['client_id'] !== $this->settings->get( 'client_id' )
				|| $settings['client_secret'] !== $current_secret
			) {
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

		if ( ! current_user_can( 'manage_options' ) ) {
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
}
