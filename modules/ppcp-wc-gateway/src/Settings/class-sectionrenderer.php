<?php
declare( strict_types=1 );

namespace Inpsyde\PayPalCommerce\WcGateway\Settings;


use Inpsyde\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

class SectionsRenderer {

	public const KEY = 'ppcp-tab';

	public function should_render() : bool {

		global $current_section;
		return $current_section === PayPalGateway::ID;
	}
	public function render() {
		if (! $this->should_render()) {
			return;
		}

		$current = ! isset($_GET[self::KEY]) ? 'paypal' : sanitize_text_field(wp_unslash($_GET[self::KEY]));
		$sections = [
			'paypal' => __( 'PayPal', 'paypal-for-woocommerce' ),
			'dcc'    => __( 'Credit Card', 'paypal-for-woocommerce' ),
		];

		echo '<ul class="subsubsub">';

		$array_keys = array_keys( $sections );

		foreach ( $sections as $id => $label ) {
			$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&' . self::KEY . '=' . $id );
			echo '<li><a href="' . $url . '" class="' . ( $current == $id ? 'current' : '' ) . '">' . esc_html($label) . '</a> ' . ( end( $array_keys ) == $id ? '' : '|' ) . ' </li>';
		}

		echo '</ul><br class="clear" />';
		return;
	}
}