<?php
/**
 * Renders the Sections Tab.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Settings
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Settings;

use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;

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
	 * The onboarding state.
	 *
	 * @var State
	 */
	private $state;

	/**
	 * SectionsRenderer constructor.
	 *
	 * @param string                $page_id ID of the current PPCP gateway settings page, or empty if it is not such page.
	 * @param array<string, string> $sections Key - page/gateway ID, value - displayed text.
	 * @param State                 $state The onboarding state.
	 */
	public function __construct( string $page_id, array $sections, State $state ) {
		$this->page_id  = $page_id;
		$this->sections = $sections;
		$this->state    = $state;
	}

	/**
	 * Whether the sections tab should be rendered.
	 *
	 * @return bool
	 */
	public function should_render() : bool {
		return ! empty( $this->page_id ) &&
			( $this->state->production_state() === State::STATE_ONBOARDED ||
			$this->state->sandbox_state() === State::STATE_ONBOARDED );
	}

	/**
	 * Renders the Sections tab.
	 */
	public function render(): string {
		if ( ! $this->should_render() ) {
			return '';
		}

		$html = '<nav class="nav-tab-wrapper woo-nav-tab-wrapper">';

		foreach ( $this->sections as $id => $label ) {
			$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $id );
			if ( in_array( $id, array( Settings::CONNECTION_TAB_ID, CreditCardGateway::ID, Settings::PAY_LATER_TAB_ID ), true ) ) {
				// We need section=ppcp-gateway for the webhooks page because it is not a gateway,
				// and for DCC because otherwise it will not render the page if gateway is not available (country/currency).
				// Other gateways render fields differently, and their pages are not expected to work when gateway is not available.
				$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&' . self::KEY . '=' . $id );
			}
			$html .= '<a href="' . esc_url( $url ) . '" class="nav-tab ' . ( $this->page_id === $id ? 'nav-tab-active' : '' ) . '">' . esc_html( $label ) . '</a> ';
		}

		$html .= '</nav>';

		return $html;
	}
}
