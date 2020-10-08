<?php
/**
 * Renders the Sections Tab.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Settings
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Settings;

use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Class SectionsRenderer
 */
class SectionsRenderer {

	const KEY = 'ppcp-tab';

	/**
	 * Whether the sections tab should be rendered.
	 *
	 * @return bool
	 */
	public function should_render() : bool {

		global $current_section;
		return PayPalGateway::ID === $current_section;
	}

	/**
	 * Renders the Sections tab.
	 */
	public function render() {
		if ( ! $this->should_render() ) {
			return;
		}

		//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current  = ! isset( $_GET[ self::KEY ] ) ? PayPalGateway::ID : sanitize_text_field( wp_unslash( $_GET[ self::KEY ] ) );
		$sections = array(
			PayPalGateway::ID     => __( 'PayPal Checkout', 'woocommerce-paypal-payments' ),
			CreditCardGateway::ID => __( 'PayPal Card Processing', 'woocommerce-paypal-payments' ),
		);

		echo '<ul class="subsubsub">';

		$array_keys = array_keys( $sections );

		foreach ( $sections as $id => $label ) {
			$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&' . self::KEY . '=' . $id );
			echo '<li><a href="' . esc_url( $url ) . '" class="' . ( $current === $id ? 'current' : '' ) . '">' . esc_html( $label ) . '</a> ' . ( end( $array_keys ) === $id ? '' : '|' ) . ' </li>';
		}

		echo '</ul><br class="clear" />';
	}
}
