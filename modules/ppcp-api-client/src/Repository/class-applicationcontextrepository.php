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
		$locale      = str_replace( '_', '-', get_user_locale() );
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
}
