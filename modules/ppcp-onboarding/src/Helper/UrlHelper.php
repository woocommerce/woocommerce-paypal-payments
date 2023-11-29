<?php
/**
 * Provides Helper functions for URL handling
 *
 * @package WooCommerce\PayPalCommerce\Onboarding\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Onboarding\Helper;

/**
 * Class OnboardingUrl
 */
class UrlHelper {

	/**
	 * Does a base64 encode of a string safe to be used on a URL
	 *
	 * @param string $string The string to be encoded.
	 * @return string
	 */
	public static function url_safe_base64_encode( string $string ): string {
		//phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$encoded_string  = base64_encode( $string );
		$url_safe_string = str_replace( array( '+', '/' ), array( '-', '_' ), $encoded_string );
		return rtrim( $url_safe_string, '=' );
	}

	/**
	 * Does a base64 decode of a string URL safe string
	 *
	 * @param string $url_safe_string The string to be decoded.
	 * @return false|string
	 */
	public static function url_safe_base64_decode( string $url_safe_string ) {
		$padded_string  = str_pad( $url_safe_string, strlen( $url_safe_string ) % 4, '=', STR_PAD_RIGHT );
		$encoded_string = str_replace( array( '-', '_' ), array( '+', '/' ), $padded_string );
		//phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		return base64_decode( $encoded_string );
	}
}
