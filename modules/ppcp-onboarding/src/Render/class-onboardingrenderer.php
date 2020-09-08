<?php
/**
 * Renders the "Connect to PayPal" button.
 *
 * @package Inpsyde\PayPalCommerce\Onboarding\Render
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Onboarding\Render;

use Inpsyde\PayPalCommerce\ApiClient\Endpoint\PartnerReferrals;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;

/**
 * Class OnboardingRenderer
 */
class OnboardingRenderer {

	/**
	 * The partner referrals object.
	 *
	 * @var PartnerReferrals
	 */
	private $partner_referrals;

	/**
	 * OnboardingRenderer constructor.
	 *
	 * @param PartnerReferrals $partner_referrals The PartnerReferrals.
	 */
	public function __construct( PartnerReferrals $partner_referrals ) {
		$this->partner_referrals = $partner_referrals;
	}

	/**
	 * Renders the "Connect to PayPal" button.
	 */
	public function render() {
		try {
			$url = add_query_arg(
				array(
					'displayMode' => 'minibrowser',
				),
				$this->partner_referrals->signup_link()
			);
			?>
					<a
							target="_blank"
							class="button-primary"
							data-paypal-onboard-complete="onboardingCallback"
							href="<?php echo esc_url( $url ); ?>"
							data-paypal-button="true"
					>
					<?php
						esc_html_e(
							'Connect to PayPal',
							'paypal-for-woocommerce'
						);
					?>
						</a>
            <script>document.querySelector('[data-paypal-onboard-complete=onboardingCallback]').addEventListener('click', (e) => {if ('undefined' === typeof PAYPAL ) e.preventDefault(); });</script>
					<script
							id="paypal-js"
							src="https://www.sandbox.paypal.com/webapps/merchantboarding/js/lib/lightbox/partner.js"
					></script>
			<?php
		} catch ( RuntimeException $exception ) {
			esc_html_e(
				'We could not properly connect to PayPal. Please reload the page to continue',
				'paypal-for-woocommerce'
			);
		}
	}
}
