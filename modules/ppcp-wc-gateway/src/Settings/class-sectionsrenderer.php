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
use WooCommerce\PayPalCommerce\Webhooks\Status\WebhooksStatusPage;

/**
 * Class SectionsRenderer
 */
class SectionsRenderer {

	const KEY = 'ppcp-tab';

	/**
	 * ID of the current PPCP gateway settings page, or empty if it is not such page.
	 *
	 * @var string
	 */
	protected $page_id;

	/**
	 * SectionsRenderer constructor.
	 *
	 * @param string $page_id ID of the current PPCP gateway settings page, or empty if it is not such page.
	 */
	public function __construct( string $page_id ) {
		$this->page_id = $page_id;
	}

	/**
	 * Whether the sections tab should be rendered.
	 *
	 * @return bool
	 */
	public function should_render() : bool {
		return ! empty( $this->page_id );
	}

	/**
	 * Renders the Sections tab.
	 */
	public function render() {
		if ( ! $this->should_render() ) {
			return;
		}

		$sections = array(
			PayPalGateway::ID      => __( 'PayPal Checkout', 'woocommerce-paypal-payments' ),
			CreditCardGateway::ID  => __( 'PayPal Card Processing', 'woocommerce-paypal-payments' ),
			WebhooksStatusPage::ID => __( 'Webhooks Status', 'woocommerce-paypal-payments' ),
		);

		echo '<ul class="subsubsub">';

		$array_keys = array_keys( $sections );

		foreach ( $sections as $id => $label ) {
			$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&' . self::KEY . '=' . $id );
			echo '<li><a href="' . esc_url( $url ) . '" class="' . ( $this->page_id === $id ? 'current' : '' ) . '">' . esc_html( $label ) . '</a> ' . ( end( $array_keys ) === $id ? '' : '|' ) . ' </li>';
		}

		echo '</ul><br class="clear" />';
	}
}
