<?php
/**
 * Renders the "Connect to PayPal" button.
 *
 * @package WooCommerce\PayPalCommerce\Onboarding\Render
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Onboarding\Render;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnerReferrals;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\ApiClient\Repository\PartnerReferralsData;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class OnboardingRenderer
 */
class OnboardingRenderer {

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * The partner referrals object for the production environment.
	 *
	 * @var PartnerReferrals
	 */
	private $production_partner_referrals;

	/**
	 * The partner referrals object for the sandbox environment.
	 *
	 * @var PartnerReferrals
	 */
	private $sandbox_partner_referrals;

	/**
	 * The default partner referrals data.
	 *
	 * @var PartnerReferralsData
	 */
	private $partner_referrals_data;

	/**
	 * The cache
	 *
	 * @var Cache
	 */
	protected $cache;

	/**
	 * OnboardingRenderer constructor.
	 *
	 * @param Settings             $settings The settings.
	 * @param PartnerReferrals     $production_partner_referrals The PartnerReferrals for production.
	 * @param PartnerReferrals     $sandbox_partner_referrals The PartnerReferrals for sandbox.
	 * @param PartnerReferralsData $partner_referrals_data The default partner referrals data.
	 * @param Cache                $cache The cache.
	 */
	public function __construct(
		Settings $settings,
		PartnerReferrals $production_partner_referrals,
		PartnerReferrals $sandbox_partner_referrals,
		PartnerReferralsData $partner_referrals_data,
		Cache $cache
	) {
		$this->settings                     = $settings;
		$this->production_partner_referrals = $production_partner_referrals;
		$this->sandbox_partner_referrals    = $sandbox_partner_referrals;
		$this->partner_referrals_data       = $partner_referrals_data;
		$this->cache                        = $cache;
	}

	/**
	 * Returns the action URL for the onboarding button/link.
	 *
	 * @param boolean  $is_production Whether the production or sandbox button should be rendered.
	 * @param string[] $products The list of products ('PPCP', 'EXPRESS_CHECKOUT').
	 * @return string URL.
	 */
	public function get_signup_link( bool $is_production, array $products ) {
		$args = array(
			'displayMode' => 'minibrowser',
		);

		$data = $this->partner_referrals_data
			->with_products( $products )
			->data();

		$environment = $is_production ? 'production' : 'sandbox';
		$product     = 'PPCP' === $data['products'][0] ? 'ppcp' : 'express_checkout';
		if ( $this->cache->has( $environment . '-' . $product ) ) {
			return $this->cache->get( $environment . '-' . $product );
		}

		$url = $is_production ? $this->production_partner_referrals->signup_link( $data ) : $this->sandbox_partner_referrals->signup_link( $data );
		$url = add_query_arg( $args, $url );

		$this->cache->set( $environment . '-' . $product, $url, 3 * MONTH_IN_SECONDS );

		return $url;
	}

	/**
	 * Renders the "Connect to PayPal" button.
	 *
	 * @param bool     $is_production Whether the production or sandbox button should be rendered.
	 * @param string[] $products The list of products ('PPCP', 'EXPRESS_CHECKOUT').
	 */
	public function render( bool $is_production, array $products ) {
		try {
			$id = 'connect-to' . ( $is_production ? 'production' : 'sandbox' ) . strtolower( implode( '-', $products ) );

			$this->render_button(
				$this->get_signup_link( $is_production, $products ),
				$id,
				$is_production ? __( 'Activate PayPal', 'woocommerce-paypal-payments' ) : __( 'Test payments with PayPal sandbox', 'woocommerce-paypal-payments' ),
				$is_production ? 'primary' : 'secondary',
				$is_production ? 'production' : 'sandbox'
			);
		} catch ( RuntimeException $exception ) {
			echo esc_html(
				__(
					'We could not properly connect to PayPal. Try reloading the page.',
					'woocommerce-paypal-payments'
				) . " {$exception->getMessage()} {$exception->getFile()}:{$exception->getLine()}"
			);
		}
	}

	/**
	 * Renders the button.
	 *
	 * @param string $url The url of the button.
	 * @param string $id The ID of the button.
	 * @param string $label The button text.
	 * @param string $class The CSS class for button ('primary', 'secondary').
	 * @param string $env The environment ('production' or 'sandbox').
	 */
	private function render_button( string $url, string $id, string $label, string $class, string $env ) {
		?>
					<a
							target="_blank"
							class="button-<?php echo esc_attr( $class ); ?>"
							id="<?php echo esc_attr( $id ); ?>"
							data-paypal-onboard-complete="ppcp_onboarding_<?php echo esc_attr( $env ); ?>Callback"
							data-paypal-onboard-button="true"
							href="<?php echo esc_url( $url ); ?>"
							data-paypal-button="true"
					>
					<?php echo esc_html( $label ); ?>
						</a>
		<?php
	}
}
