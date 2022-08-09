<?php
/**
 * Renders the Sections Tab.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Settings
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Settings;

use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
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
	 * Key - page/gateway ID, value - displayed text.
	 *
	 * @var array<string, string>
	 */
	protected $sections;

	/**
	 * SectionsRenderer constructor.
	 *
	 * @param string                $page_id ID of the current PPCP gateway settings page, or empty if it is not such page.
	 * @param array<string, string> $sections Key - page/gateway ID, value - displayed text.
	 */
	public function __construct( string $page_id, array $sections ) {
		$this->page_id  = $page_id;
		$this->sections = $sections;
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
	public function render(): void {
		if ( ! $this->should_render() ) {
			return;
		}

		echo '<ul class="subsubsub">';

		$array_keys = array_keys( $this->sections );

		foreach ( $this->sections as $id => $label ) {
			$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $id );
			if ( in_array( $id, array( CreditCardGateway::ID, WebhooksStatusPage::ID ), true ) ) {
				// We need section=ppcp-gateway for the webhooks page because it is not a gateway,
				// and for DCC because otherwise it will not render the page if gateway is not available (country/currency).
				// Other gateways render fields differently, and their pages are not expected to work when gateway is not available.
				$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&' . self::KEY . '=' . $id );
			}
			echo '<li><a href="' . esc_url( $url ) . '" class="' . ( $this->page_id === $id ? 'current' : '' ) . '">' . esc_html( $label ) . '</a> ' . ( end( $array_keys ) === $id ? '' : '|' ) . ' </li>';
		}

		echo '</ul><br class="clear" />';
	}
}
