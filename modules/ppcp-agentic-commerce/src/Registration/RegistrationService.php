<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Registration;

use Firebase\JWT\JWT;
use JsonException;
use WP_Error;
use WooCommerce\PayPalCommerce\AgenticCommerce\Merchant\MerchantMetadataProvider;
use WooCommerce\PayPalCommerce\WcGateway\Helper\ConnectionState;

class RegistrationService {

	private const LIVE_HOST              = 'https://d.joinhoney.com';
	private const SANDBOX_HOST           = 'https://d-sandbox.joinhoney.com';
	private const INSTALL_PATH           = '/webhooks/ws/install';
	private const UNINSTALL_PATH         = '/webhooks/ws/uninstall';
	private const REGISTRATION_TOKEN_KEY = 'ppcp_agentic_registration_token';

	private const ERROR_REGISTRATION_FAILED   = 'registration_failed';
	private const ERROR_DEREGISTRATION_FAILED = 'deregistration_failed';
	private const ERROR_WEBHOOK_REQUEST       = 'webhook_request_failed';
	private const ERROR_WEBHOOK_RESPONSE      = 'webhook_response_failed';

	private ConnectionState $connection_state;
	private MerchantMetadataProvider $metadata_provider;

	public function __construct(
		ConnectionState $connection_state,
		MerchantMetadataProvider $metadata_provider
	) {
		$this->connection_state  = $connection_state;
		$this->metadata_provider = $metadata_provider;
	}

	/**
	 * Register store with PayPal Agentic Commerce.
	 *
	 * @return RegistrationResult|WP_Error
	 */
	public function register() {
		if ( $this->is_registered() ) {
			return new WP_Error(
				self::ERROR_REGISTRATION_FAILED,
				'Already registered',
			);
		}

		$token  = $this->create_token();
		$result = $this->webhook_call( $token, self::INSTALL_PATH );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( $result->success ) {
			$this->save_registration_token( $token );

			do_action( 'woocommerce_paypal_payments_agentic_commerce_registered' );
		} else {
			$this->delete_registration_token();

			return new WP_Error(
				self::ERROR_REGISTRATION_FAILED,
				$result->error ?? 'Registration failed'
			);
		}

		return $result;
	}

	/**
	 * Deregister store from PayPal Agentic Commerce.
	 *
	 * @return RegistrationResult|WP_Error|null Null if store was not registered.
	 */
	public function deregister() {
		if ( ! $this->is_registered() ) {
			return null;
		}

		$token  = (string) $this->get_registration_token();
		$result = $this->webhook_call( $token, self::UNINSTALL_PATH );
		$this->delete_registration_token();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! $result->success ) {
			return new WP_Error(
				self::ERROR_DEREGISTRATION_FAILED,
				$result->error ?? 'Deregistration failed'
			);
		}

		do_action( 'woocommerce_paypal_payments_agentic_commerce_deregistered' );

		return $result;
	}

	/**
	 * Checks, if the current store is registered to support PayPal Agentic Commerce.
	 *
	 * @return bool
	 */
	public function is_registered(): bool {
		return (bool) $this->get_registration_token();
	}

	/**
	 * Create JWT token with store metadata.
	 *
	 * The token is signed with a dummy key (HS256) as PayPal does not validate
	 * the signature - it only serves as a transport mechanism for store metadata.
	 */
	private function create_token(): string {
		$metadata = $this->metadata_provider->get_metadata();

		$payload = array(
			'storeName'          => $metadata->store_name,
			'storeUrl'           => $metadata->store_url,
			'country'            => $metadata->store_country,
			'currency'           => $metadata->currency,
			'paypalMerchantId'   => $metadata->paypal_merchant_id,
			'wooMerchantId'      => $metadata->store_url,
			'catalogDownloadUrl' => $metadata->catalog_url,
			'favIcon'            => '',
			'shippingCountries'  => array( 'US' ),
		);

		return JWT::encode( $payload, 'no-signature', 'HS256' );
	}

	/**
	 * Make webhook call to PayPal.
	 *
	 * @param string $token JWT token with store metadata.
	 * @param string $path  Webhook path (INSTALL_PATH or UNINSTALL_PATH).
	 * @return RegistrationResult|WP_Error
	 */
	private function webhook_call( string $token, string $path ) {
		$base_host = $this->connection_state->is_production()
			? self::LIVE_HOST
			: self::SANDBOX_HOST;

		$url = $base_host . $path;

		$response = wp_remote_post(
			$url,
			array(
				'body'    => $token,
				'headers' => array(
					'Content-Type' => 'text/plain',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				self::ERROR_WEBHOOK_REQUEST,
				$response->get_error_message()
			);
		}

		try {
			$body = json_decode(
				wp_remote_retrieve_body( $response ),
				true,
				512,
				JSON_THROW_ON_ERROR
			);
		} catch ( JsonException $exception ) {
			return new WP_Error(
				self::ERROR_WEBHOOK_RESPONSE,
				$exception->getMessage()
			);
		}

		return new RegistrationResult(
			$body['success'] ?? false,
			$body['message'] ?? '',
			$body['error'] ?? null
		);
	}

	/**
	 * Get stored registration token.
	 *
	 * Protected to allow mocking in tests.
	 *
	 * @return string|false
	 */
	protected function get_registration_token() {
		return get_option( self::REGISTRATION_TOKEN_KEY );
	}

	/**
	 * Save registration token.
	 *
	 * Protected to allow mocking in tests.
	 *
	 * @param string $token Registration token.
	 */
	protected function save_registration_token( string $token ): void {
		update_option( self::REGISTRATION_TOKEN_KEY, $token );
	}

	/**
	 * Delete registration token.
	 *
	 * Protected to allow mocking in tests.
	 */
	protected function delete_registration_token(): void {
		delete_option( self::REGISTRATION_TOKEN_KEY );
	}
}
