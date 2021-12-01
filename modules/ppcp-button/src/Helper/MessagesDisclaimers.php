<?php
/**
 * Helper class to determine which disclaimer content should display based on shop location country.
 *
 * @package WooCommerce\PayPalCommerce\Button\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Helper;

/**
 * Class MessagesDisclaimers
 *
 * @package WooCommerce\PayPalCommerce\Button\Helper
 */
class MessagesDisclaimers {

	/**
	 * Disclaimers content by country.
	 *
	 * @var array
	 */
	private $disclaimers = array(
		'US' => array(
			'link' => 'https://developer.paypal.com/docs/commerce-platforms/admin-panel/woocommerce/us/',
		),
		'GB' => array(
			'link' => 'https://developer.paypal.com/docs/commerce-platforms/admin-panel/woocommerce/uk/',
		),
		'DE' => array(
			'link' => 'https://developer.paypal.com/docs/commerce-platforms/admin-panel/woocommerce/de/',
		),
		'AU' => array(
			'link' => 'https://developer.paypal.com/docs/commerce-platforms/admin-panel/woocommerce/au/',
		),
		'FR' => array(
			'link' => 'https://developer.paypal.com/docs/commerce-platforms/admin-panel/woocommerce/fr/',
		),
	);

	/**
	 * 2-letter country code of the shop.
	 *
	 * @var string
	 */
	private $country;

	/**
	 * MessagesDisclaimers constructor.
	 *
	 * @param string $country 2-letter country code of the shop.
	 */
	public function __construct( string $country ) {
		$this->country = $country;
	}

	/**
	 * Returns a disclaimer link based on country.
	 *
	 * @return string
	 */
	public function link_for_country(): string {
		return $this->disclaimers[ $this->country ]['link'] ?? '';
	}
}
