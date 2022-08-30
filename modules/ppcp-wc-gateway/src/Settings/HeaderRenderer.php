<?php
/**
 * Renders the settings page header.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Settings
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Settings;

/**
 * Class HeaderRenderer
 */
class HeaderRenderer {

	const KEY = 'ppcp-tab';

	/**
	 * ID of the current PPCP gateway settings page, or empty if it is not such page.
	 *
	 * @var string
	 */
	private $page_id;

	/**
	 * The URL to the module.
	 *
	 * @var string
	 */
	private $module_url;

	/**
	 * HeaderRenderer constructor.
	 *
	 * @param string $page_id ID of the current PPCP gateway settings page, or empty if it is not such page.
	 * @param string $module_url The URL to the module.
	 */
	public function __construct( string $page_id, string $module_url ) {
		$this->page_id    = $page_id;
		$this->module_url = $module_url;
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
	public function render(): string {
		if ( ! $this->should_render() ) {
			return '';
		}

		return '
			<div class="ppcp-settings-page-header">
				<img alt="PayPal" src="' . esc_url( $this->module_url ) . 'assets/images/paypal.png"/>
				<h4> <span class="ppcp-inline-only">-</span> ' . __( 'The all-in-one checkout solution for WooCommerce', 'woocommerce-paypal-payments' ) . '</h4>
				<a class="button" target="_blank" href="https://woocommerce.com/document/woocommerce-paypal-payments/">'
					. __( 'Documentation', 'woocommerce-paypal-payments' ) .
				'</a>
				<a class="button" target="_blank" href="https://woocommerce.com/document/woocommerce-paypal-payments/#get-help">'
					. __( 'Get Help', 'woocommerce-paypal-payments' ) .
				'</a>
				<span class="ppcp-right-align">
					<a target="_blank" href="https://woocommerce.com/feature-requests/woocommerce-paypal-payments/">'
						. __( 'Request a feature', 'woocommerce-paypal-payments' ) .
					'</a>
					<a target="_blank" href="https://github.com/woocommerce/woocommerce-paypal-payments/issues/new?assignees=&labels=type%3A+bug&template=bug_report.md">'
						. __( 'Submit a bug', 'woocommerce-paypal-payments' ) .
					'</a>
				</span>
			</div>
		';
	}
}
