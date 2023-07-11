<?php
/**
 * The ExperienceContext factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use stdClass;
use WC_AJAX;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Endpoint\ReturnUrlEndpoint;

/**
 * Class ExperienceContextFactory
 */
class ExperienceContextFactory {

	/**
	 * The Settings.
	 *
	 * @var ContainerInterface
	 */
	private $settings;

	/**
	 * ExperienceContextFactory constructor.
	 *
	 * @param ContainerInterface $settings The settings.
	 */
	public function __construct( ContainerInterface $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Returns an Experience Context based off a PayPal Response.
	 *
	 * @param stdClass $data The JSON object.
	 *
	 * @return ExperienceContext
	 */
	public function from_paypal_response( stdClass $data ): ExperienceContext {
		return new ExperienceContext(
			$data->return_url ?? '',
			$data->cancel_url ?? '',
			$data->brand_name ?? '',
			$data->locale ?? '',
			$data->landing_page ?? ExperienceContext::LANDING_PAGE_NO_PREFERENCE,
			$data->shipping_preference ?? ExperienceContext::SHIPPING_PREFERENCE_GET_FROM_FILE,
			$data->user_action ?? ExperienceContext::USER_ACTION_CONTINUE,
			$data->payment_method_preference ?? ExperienceContext::PAYMENT_METHOD_UNRESTRICTED
		);
	}

	/**
	 * Returns the current experience context, overriding some properties.
	 *
	 * @param string $shipping_preferences The shipping preferences.
	 * @param string $user_action The user action.
	 *
	 * @return ExperienceContext
	 */
	public function current_context(
		string $shipping_preferences = ExperienceContext::SHIPPING_PREFERENCE_NO_SHIPPING,
		string $user_action = ExperienceContext::USER_ACTION_CONTINUE
	): ExperienceContext {

		$brand_name         = $this->settings->has( 'brand_name' ) ? (string) $this->settings->get( 'brand_name' ) : '';
		$locale             = $this->locale_to_bcp47( get_user_locale() );
		$landing_page       = $this->settings->has( 'landing_page' ) ?
			(string) $this->settings->get( 'landing_page' ) : ExperienceContext::LANDING_PAGE_NO_PREFERENCE;
		$payment_preference = $this->settings->has( 'payee_preferred' ) && $this->settings->get( 'payee_preferred' ) ?
			ExperienceContext::PAYMENT_METHOD_IMMEDIATE_PAYMENT_REQUIRED : ExperienceContext::PAYMENT_METHOD_UNRESTRICTED;
		return new ExperienceContext(
			network_home_url( WC_AJAX::get_endpoint( ReturnUrlEndpoint::ENDPOINT ) ),
			wc_get_checkout_url(),
			$brand_name,
			$locale,
			$landing_page,
			$shipping_preferences,
			$user_action,
			$payment_preference
		);
	}

	/**
	 * Returns BCP-47 code supported by PayPal, for example de-DE-formal becomes de-DE.
	 *
	 * @param string $locale The locale, e.g. from get_user_locale.
	 */
	protected function locale_to_bcp47( string $locale ): string {
		$locale = str_replace( '_', '-', $locale );

		if ( preg_match( '/^[a-z]{2}(?:-[A-Z][a-z]{3})?(?:-(?:[A-Z]{2}))?$/', $locale ) ) {
			return $locale;
		}

		$parts = explode( '-', $locale );
		if ( count( $parts ) === 3 ) {
			$ret = substr( $locale, 0, (int) strrpos( $locale, '-' ) );
			if ( false !== $ret ) {
				return $ret;
			}
		}

		return 'en';
	}
}
