<?php
/**
 * Renders the onboarding options.
 *
 * @package WooCommerce\PayPalCommerce\Onboarding\Render
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Onboarding\Render;

/**
 * Class OnboardingRenderer
 */
class OnboardingOptionsRenderer {
	/**
	 * Renders the onboarding options.
	 *
	 * @param bool $is_shop_supports_dcc Whether the shop can use DCC (country, currency).
	 */
	public function render( bool $is_shop_supports_dcc ): string {
		return '
<ul class="ppcp-onboarding-options">
	<li>
		<label><input type="checkbox" disabled checked> ' .
			__( 'Accept PayPal, Venmo, Pay Later and local payment methods', 'woocommerce-paypal-payments' ) . '
		</label>
	</li>
	<li>
		<label><input type="checkbox" id="ppcp-onboarding-accept-cards" checked> ' .
			__( 'Securely accept all major credit & debit cards on the strength of the PayPal network', 'woocommerce-paypal-payments' ) . '
		</label>
	</li>
	<li>' . $this->render_dcc( $is_shop_supports_dcc ) . '</li>
</ul>';
	}

	/**
	 * Renders the onboarding DCC options.
	 *
	 * @param bool $is_shop_supports_dcc Whether the shop can use DCC (country, currency).
	 */
	private function render_dcc( bool $is_shop_supports_dcc ): string {
		$items = array();

		if ( $is_shop_supports_dcc ) {
			$items[] = '
<li>
	<label><input type="radio" id="ppcp-onboarding-dcc-acdc" name="ppcp_onboarding_dcc" value="acdc" checked> ' .
			__( 'Advanced credit and debit card processing', 'woocommerce-paypal-payments' ) . '*<br/> ' .
			__( '(With advanced fraud protection and fully customizable card fields)', 'woocommerce-paypal-payments' ) . '
		<span class="ppcp-muted-text">*' . __( 'Additional onboarding steps required', 'woocommerce-paypal-payments' ) . '</span>
	</label>
</li>';
		}

		$items[] = '
<li>
	<label><input type="radio" id="ppcp-onboarding-dcc-basic" name="ppcp_onboarding_dcc" value="basic" ' . ( ! $is_shop_supports_dcc ? 'checked' : '' ) . '> ' .
		__( 'Standard credit and debit card processing', 'woocommerce-paypal-payments' ) . '
	</label>
</li>';

		return '<ul id="ppcp-onboarding-dcc-options" class="ppcp-onboarding-options-sublist">' .
			implode( '', $items ) .
			'</ul>';
	}
}
