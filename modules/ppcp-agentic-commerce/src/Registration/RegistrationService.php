<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Registration;

use Firebase\JWT\JWT;
use JsonException;
use WP_Error;
use WooCommerce\PayPalCommerce\AgenticCommerce\Merchant\MerchantMetadataProvider;
use WooCommerce\PayPalCommerce\AgenticCommerce\Config\AgenticWebhookConfiguration;

class RegistrationService {

	private const REGISTRATION_TOKEN_KEY      = 'ppcp_agentic_registration_token';
	private const ERROR_REGISTRATION_FAILED   = 'registration_failed';
	private const ERROR_DEREGISTRATION_FAILED = 'deregistration_failed';
	private const ERROR_WEBHOOK_REQUEST       = 'webhook_request_failed';
	private const ERROR_WEBHOOK_RESPONSE      = 'webhook_response_failed';

	private AgenticWebhookConfiguration $webhook_urls;
	private MerchantMetadataProvider $metadata_provider;

	public function __construct(
		AgenticWebhookConfiguration $webhook_urls,
		MerchantMetadataProvider $metadata_provider
	) {

		$this->webhook_urls      = $webhook_urls;
		$this->metadata_provider = $metadata_provider;
	}

	/**
	 * Register this store with PayPal Agentic Commerce.
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
		$result = $this->call_installation_endpoint( $token );

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
	 * @return RegistrationResult|WP_Error|null Null if the store was not registered.
	 */
	public function deregister() {
		if ( ! $this->is_registered() ) {
			return null;
		}

		$token  = (string) $this->get_registration_token();
		$result = $this->call_uninstallation_endpoint( $token );
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
	 * Checks if the current store is registered to support PayPal Agentic Commerce.
	 *
	 * @return bool
	 */
	public function is_registered(): bool {
		return (bool) $this->get_registration_token();
	}

	/**
	 * Create a JWT token with store metadata.
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
	 * Call the "installation" (registration) endpoint.
	 *
	 * @param string $token JWT token with store metadata.
	 * @return RegistrationResult|WP_Error
	 */
	private function call_installation_endpoint( string $token ) {
		return $this->webhook_call( $token, $this->webhook_urls->get_registration_install_url() );
	}

	/**
	 * Call the "uninstallation" (deregistration) endpoint.
	 *
	 * @param string $token Previously generated registration token.
	 * @return RegistrationResult|WP_Error
	 */
	private function call_uninstallation_endpoint( string $token ) {
		return $this->webhook_call( $token, $this->webhook_urls->get_registration_uninstall_url() );
	}

	/**
	 * Make a call to PayPal's webhook endpoints.
	 *
	 * @param string $token       JWT token with store metadata.
	 * @param string $webhook_url The absolute webhook URL to call.
	 * @return RegistrationResult|WP_Error
	 */
	private function webhook_call( string $token, string $webhook_url ) {
		$response = wp_remote_post(
			$webhook_url,
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
	 * Return the previously stored registration token.
	 *
	 * Protected to allow mocking in tests.
	 *
	 * @return string|false
	 */
	protected function get_registration_token() {
		return get_option( self::REGISTRATION_TOKEN_KEY );
	}

	/**
	 * Save the new registration token.
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
