<?php
/**
 * Renders the Sections Tab.
 *
 * @package Inpsyde\PayPalCommerce\WcGateway\Settings
 */

declare( strict_types=1 );

namespace Inpsyde\PayPalCommerce\WcGateway\Settings;

use Inpsyde\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Class SectionsRenderer
 */
class SectionsRenderer {

	public const KEY = 'ppcp-tab';

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
			PayPalGateway::ID => __( 'PayPal', 'paypal-for-woocommerce' ),
			CreditCardGateway::ID    => __( 'Credit Card', 'paypal-for-woocommerce' ),
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
