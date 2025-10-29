<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\FraudProtection\Recaptcha;

class Recaptcha {
	private const V2_CONTAINER_ID                = 'ppcp-recaptcha-v2-container';
	private const ERROR_CODE_MISSING_TOKEN       = 'ppcp_recaptcha_missing_token';
	private const ERROR_CODE_VERIFICATION_FAILED = 'ppcp_recaptcha_verification_failed';

	private RecaptchaIntegration $integration;

	private string $module_url;

	private string $asset_version;

	public function __construct(
		RecaptchaIntegration $integration,
		string $module_url,
		string $asset_version
	) {

		$this->integration   = $integration;
		$this->module_url    = $module_url;
		$this->asset_version = $asset_version;
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
			untrailingslashit( $this->module_url ) . '/assets/js/recaptcha-handler.js',
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
}
