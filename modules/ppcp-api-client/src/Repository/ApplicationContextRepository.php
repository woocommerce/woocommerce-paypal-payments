<?php
/**
 * Returns the current application context.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Repository
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Repository;

use WooCommerce\PayPalCommerce\ApiClient\Entity\ApplicationContext;
use WooCommerce\PayPalCommerce\WcGateway\Endpoint\ReturnUrlEndpoint;
use Psr\Container\ContainerInterface;

/**
 * Class ApplicationContextRepository
 */
class ApplicationContextRepository {

	/**
	 * The Settings.
	 *
	 * @var ContainerInterface
	 */
	private $settings;

	/**
	 * ApplicationContextRepository constructor.
	 *
	 * @param ContainerInterface $settings The settings.
	 */
	public function __construct( ContainerInterface $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Returns the current application context.
	 *
	 * @param string $shipping_preferences The shipping preferences.
	 *
	 * @return ApplicationContext
	 */
	public function current_context(
		string $shipping_preferences = ApplicationContext::SHIPPING_PREFERENCE_NO_SHIPPING
	): ApplicationContext {

		$brand_name  = $this->settings->has( 'brand_name' ) ? $this->settings->get( 'brand_name' ) : '';
		$locale      = $this->valid_bcp47_code();
		$landingpage = $this->settings->has( 'landing_page' ) ?
			$this->settings->get( 'landing_page' ) : ApplicationContext::LANDING_PAGE_NO_PREFERENCE;
		$context     = new ApplicationContext(
			(string) home_url( \WC_AJAX::get_endpoint( ReturnUrlEndpoint::ENDPOINT ) ),
			(string) wc_get_checkout_url(),
			(string) $brand_name,
			$locale,
			(string) $landingpage,
			$shipping_preferences
		);
		return $context;
	}

	/**
	 * Returns a PayPal-supported BCP-47 code, for example de-DE-formal becomes de-DE.
	 *
	 * @return string
	 */
	protected function valid_bcp47_code() {
		$locale = str_replace( '_', '-', get_user_locale() );

		if ( preg_match( '/^[a-z]{2}(?:-[A-Z][a-z]{3})?(?:-(?:[A-Z]{2}))?$/', $locale ) ) {
			return $locale;
		}

		$parts = explode( '-', $locale );
		if ( count( $parts ) === 3 ) {
			$ret = substr( $locale, 0, strrpos( $locale, '-' ) );
			if ( false !== $ret ) {
				return $ret;
			}
		}

		return 'en';
	}
}
