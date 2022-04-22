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
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PayUponInvoiceGateway;
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
	 * The api shop country.
	 *
	 * @var string
	 */
	protected $api_shop_country;

	/**
	 * SectionsRenderer constructor.
	 *
	 * @param string $page_id ID of the current PPCP gateway settings page, or empty if it is not such page.
	 * @param string $api_shop_country The api shop country.
	 */
	public function __construct( string $page_id, string $api_shop_country ) {
		$this->page_id          = $page_id;
		$this->api_shop_country = $api_shop_country;
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
			PayPalGateway::ID         => __( 'PayPal Checkout', 'woocommerce-paypal-payments' ),
			CreditCardGateway::ID     => __( 'PayPal Card Processing', 'woocommerce-paypal-payments' ),
			PayUponInvoiceGateway::ID => __( 'Pay Upon Invoice', 'woocommerce-paypal-payments' ),
			WebhooksStatusPage::ID    => __( 'Webhooks Status', 'woocommerce-paypal-payments' ),
		);

		if ( 'DE' !== $this->api_shop_country ) {
			unset( $sections[ PayUponInvoiceGateway::ID ] );
		}

		echo '<ul class="subsubsub">';

		$array_keys = array_keys( $sections );

		foreach ( $sections as $id => $label ) {
			$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&' . self::KEY . '=' . $id );
			if ( PayUponInvoiceGateway::ID === $id ) {
				$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-pay-upon-invoice-gateway' );
			}
			echo '<li><a href="' . esc_url( $url ) . '" class="' . ( $this->page_id === $id ? 'current' : '' ) . '">' . esc_html( $label ) . '</a> ' . ( end( $array_keys ) === $id ? '' : '|' ) . ' </li>';
		}

		echo '</ul><br class="clear" />';
	}
}
