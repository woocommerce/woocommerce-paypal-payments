<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\FraudProtection\Recaptcha;

use Psr\Log\LoggerInterface;

class Recaptcha {
	private const V2_CONTAINER_ID                = 'ppcp-recaptcha-v2-container';
	private const ERROR_CODE_MISSING_TOKEN       = 'ppcp_recaptcha_missing_token';
	private const ERROR_CODE_VERIFICATION_FAILED = 'ppcp_recaptcha_verification_failed';
	private const CAPTCHA_USAGE_LIMIT            = 5;
	private const CAPTCHA_RESULT_TRANSIENT_KEY   = 'ppcp_recaptcha_result_';

	private RecaptchaIntegration $integration;

	private string $module_url;

	private string $asset_version;

	private LoggerInterface $logger;

	public function __construct(
		RecaptchaIntegration $integration,
		string $module_url,
		string $asset_version,
		LoggerInterface $logger
	) {

		$this->integration   = $integration;
		$this->module_url    = $module_url;
		$this->asset_version = $asset_version;
		$this->logger        = $logger;
	}

	protected function should_use_recaptcha(): bool {
		if ( ! $this->integration->enabled ) {
			return false;
		}

		if ( wc_string_to_bool( 'guest_only' ) && is_user_logged_in() ) {
			return false;
		}

		$has_v3 = ! empty( $this->integration->get_option( 'site_key_v3' ) ) && ! empty( $this->integration->get_option( 'secret_key_v3' ) );
		$has_v2 = ! empty( $this->integration->get_option( 'site_key_v2' ) ) && ! empty( $this->integration->get_option( 'secret_key_v2' ) );

		if ( ! $has_v3 || ! $has_v2 ) {
			return false;
		}

		return true;
	}

	public function enqueue_scripts(): void {
		if ( ! is_checkout() && ! is_cart() && ! is_product() ) {
			return;
		}

		if ( ! $this->should_use_recaptcha() ) {
			return;
		}

		$is_blocks = has_block( 'woocommerce/checkout' ) || has_block( 'woocommerce/cart' );

		wp_enqueue_script(
			'ppcp-recaptcha',
			'https://www.google.com/recaptcha/api.js?render=' . esc_attr( $this->integration->get_option( 'site_key_v3' ) ),
			array(),
			$this->asset_version,
			true
		);

		$dependencies = array( 'ppcp-recaptcha' );
		if ( $is_blocks ) {
			$dependencies[] = 'wp-data';
		}

		wp_enqueue_script(
			'ppcp-recaptcha-handler',
			untrailingslashit( $this->module_url ) . '/assets/recaptcha-handler.js',
			$dependencies,
			$this->asset_version,
			true
		);

		wp_localize_script(
			'ppcp-recaptcha-handler',
			'ppcpRecaptchaSettings',
			array(
				'siteKeyV3'                   => $this->integration->get_option( 'site_key_v3' ),
				'siteKeyV2'                   => $this->integration->get_option( 'site_key_v2' ),
				'theme'                       => $this->integration->get_option( 'v2_theme', 'light' ),
				'isBlocks'                    => $is_blocks,
				'isCheckout'                  => is_checkout(),
				'isCart'                      => is_cart(),
				'isSingleProduct'             => is_product(),
				'v2ContainerId'               => self::V2_CONTAINER_ID,
				'errorCodeMissingToken'       => self::ERROR_CODE_MISSING_TOKEN,
				'errorCodeVerificationFailed' => self::ERROR_CODE_VERIFICATION_FAILED,
			)
		);
	}

	public function intercept_paypal_ajax( array $request_data ): void {
		if ( ! $this->should_use_recaptcha() ) {
			return;
		}

		$token   = sanitize_text_field(
			wp_unslash(
				$request_data['ppcp_recaptcha_token'] ?? ''
			)
		);
		$version = sanitize_text_field(
			wp_unslash(
				$request_data['ppcp_recaptcha_version'] ?? ''
			)
		);

		if ( empty( $token ) ) {
			wp_send_json_error(
				array(
					'message' => __(
						'Please complete the CAPTCHA verification.',
						'woocommerce-paypal-payments'
					),
					'code'    => self::ERROR_CODE_MISSING_TOKEN,
				),
				400
			);
			exit;
		}

		$success = ( $version === 'v3' )
			? $this->verify_v3(
				$token,
				$this->integration->get_option( 'secret_key_v3' ),
				$this->score_threshold()
			)
			: $this->verify_v2( $token, $this->integration->get_option( 'secret_key_v2' ) );

		if ( ! $success ) {
			wp_send_json_error(
				array(
					'message' => __(
						'CAPTCHA verification failed. Please try again.',
						'woocommerce-paypal-payments'
					),
					'code'    => self::ERROR_CODE_VERIFICATION_FAILED,
				),
				403
			);
			exit;
		}
	}


	public function validate_classic_checkout(): void {
		if ( ! $this->should_use_recaptcha() ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification handled by WooCommerce before this hook fires
		/** @psalm-suppress PossiblyInvalidCast */
		$token = sanitize_text_field(
			wp_unslash(
				(string) ( $_POST['ppcp_recaptcha_token'] ?? '' )
			)
		);
		/** @psalm-suppress PossiblyInvalidCast */
		$version = sanitize_text_field(
			wp_unslash(
				(string) ( $_POST['ppcp_recaptcha_version'] ?? '' )
			)
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( empty( $token ) ) {
			wc_add_notice(
				__(
					'Please complete the CAPTCHA verification.',
					'woocommerce-paypal-payments'
				),
				'error'
			);

			return;
		}

		$success = ( $version === 'v3' )
			? $this->verify_v3(
				$token,
				$this->integration->get_option( 'secret_key_v3' ),
				$this->score_threshold()
			)
			: $this->verify_v2( $token, $this->integration->get_option( 'secret_key_v2' ) );

		if ( ! $success ) {
			wc_add_notice(
				__(
					'CAPTCHA verification failed. Please try again.',
					'woocommerce-paypal-payments'
				),
				'error'
			);
		}
	}

	private function verify_v3(
		string $token,
		string $secret,
		float $threshold
	): bool {

		if ( $this->check_cached_verification( $token ) ) {
			return true;
		}

		$response = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'body' => array(
					'secret'   => $secret,
					'response' => $token,
					'remoteip' => $this->customer_ip(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->error(
				'reCAPTCHA v3 API error: ' . $response->get_error_message()
			);

			return false;
		}

		$result             = json_decode( wp_remote_retrieve_body( $response ), true );
		$score              = isset( $result['score'] ) ? floatval( $result['score'] ) : 0;
		$is_above_threshold = ! empty( $result['success'] ) && $score >= $threshold;
		$is_valid           = apply_filters(
			'woocommerce_paypal_payments_recaptcha_verify_v3_result',
			$is_above_threshold,
			$threshold,
			$result
		);

		if ( $is_valid ) {
			$cached_data = array(
				'result'      => $result,
				'token'       => $token,
				'usage_count' => 1,
			);
			set_transient(
				self::CAPTCHA_RESULT_TRANSIENT_KEY . $this->customer_identifier(),
				$cached_data,
				300
			);
		}

		return $is_valid;
	}

	private function verify_v2( string $token, string $secret ): bool {
		if ( $this->check_cached_verification( $token ) ) {
			return true;
		}

		$response = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'body' => array(
					'secret'   => $secret,
					'response' => $token,
					'remoteip' => $this->customer_ip(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->error(
				'reCAPTCHA v2 API error: ' . $response->get_error_message()
			);

			return false;
		}

		$result   = json_decode( wp_remote_retrieve_body( $response ), true );
		$is_valid = apply_filters(
			'woocommerce_paypal_payments_recaptcha_verify_v2_result',
			$result['success'],
			$result
		);

		if ( $is_valid ) {
			$cached_data = array(
				'result'      => $result,
				'token'       => $token,
				'usage_count' => 1,
			);
			set_transient(
				self::CAPTCHA_RESULT_TRANSIENT_KEY . $this->customer_identifier(),
				$cached_data,
				300
			);
		}

		return $is_valid;
	}

	private function check_cached_verification(
		string $token
	): bool {

		$cached_data = get_transient( self::CAPTCHA_RESULT_TRANSIENT_KEY . $this->customer_identifier() );

		if ( $cached_data === false || ! isset( $cached_data['usage_count'], $cached_data['token'] ) ) {
			return false;
		}

		if ( $cached_data['token'] === $token && $cached_data['usage_count'] < self::CAPTCHA_USAGE_LIMIT ) {
			++$cached_data['usage_count'];
			set_transient(
				self::CAPTCHA_RESULT_TRANSIENT_KEY . $this->customer_identifier(),
				$cached_data,
				300
			);

			return true;
		}

		if ( $cached_data['usage_count'] >= self::CAPTCHA_USAGE_LIMIT ) {
			delete_transient( self::CAPTCHA_RESULT_TRANSIENT_KEY . $this->customer_identifier() );
		}

		return false;
	}

	private function customer_identifier(): string {
		return (string) WC()->session->get_customer_id();
	}

	private function customer_ip(): string {
		return filter_var(
			wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ),
			FILTER_VALIDATE_IP
		) ?: '';
	}

	private function score_threshold(): float {
		return floatval( $this->integration->get_option( 'score_threshold', 0.5 ) );
	}
}
