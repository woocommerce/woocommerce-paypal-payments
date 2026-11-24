<?php
/**
 * Compatibility layer for WPML.
 *
 * @package WooCommerce\PayPalCommerce\Compat
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat;

/**
 * In URL-parameter mode, WPML adds no language to REST URLs, so a request from the
 * browser runs in the default language. Multi-currency plugins can then switch the
 * shopper's currency to that language's currency.
 */
class WpmlCompat {

	private const URL_PARAMETER_MODE = 3;

	public function register(): void {
		add_filter(
			'woocommerce_paypal_payments_frontend_rest_url',
			array( $this, 'add_current_language_to_url' )
		);
	}

	/**
	 * @param mixed $url The REST URL.
	 * @return mixed
	 */
	public function add_current_language_to_url( $url ) {
		if ( ! is_string( $url ) || $this->has_lang_argument( $url ) ) {
			return $url;
		}

		/**
		 * WPML API: reads a WPML setting.
		 *
		 * @param mixed  $default The value when the setting is missing.
		 * @param string $key     The setting key.
		 */
		$mode = (int) apply_filters( 'wpml_setting', 0, 'language_negotiation_type' );
		if ( self::URL_PARAMETER_MODE !== $mode ) {
			return $url;
		}

		/**
		 * WPML API: returns the language of the current request.
		 *
		 * @param mixed $default The value without WPML.
		 */
		$current_language = apply_filters( 'wpml_current_language', null );

		/**
		 * WPML API: returns the default language of the site.
		 *
		 * @param mixed $default The value without WPML.
		 */
		$default_language = apply_filters( 'wpml_default_language', null );
		if ( ! is_string( $current_language ) || '' === $current_language || $current_language === $default_language ) {
			return $url;
		}

		return add_query_arg( 'lang', $current_language, $url );
	}

	private function has_lang_argument( string $url ): bool {
		wp_parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query_args );

		return isset( $query_args['lang'] );
	}
}
