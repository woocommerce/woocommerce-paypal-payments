<?php
/**
 * Renders info about funding sources like Venmo.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\FundingSource
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\FundingSource;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Class FundingSourceRenderer
 */
class FundingSourceRenderer {
	/**
	 * The settings.
	 *
	 * @var ContainerInterface
	 */
	protected $settings;

	/**
	 * FundingSourceRenderer constructor.
	 *
	 * @param ContainerInterface $settings The settings.
	 */
	public function __construct( ContainerInterface $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Returns name of the funding source (suitable for displaying to user).
	 *
	 * @param string $id The ID of the funding source, such as 'venmo'.
	 */
	public function render_name( string $id ): string {
		if ( 'venmo' === $id ) {
			return __( 'Venmo', 'woocommerce-paypal-payments' );
		}
		return $this->settings->has( 'title' ) ?
			$this->settings->get( 'title' )
			: __( 'PayPal', 'woocommerce-paypal-payments' );
	}

	/**
	 * Returns description of the funding source (for checkout).
	 *
	 * @param string $id The ID of the funding source, such as 'venmo'.
	 */
	public function render_description( string $id ): string {
		if ( 'venmo' === $id ) {
			return __( 'Pay via Venmo.', 'woocommerce-paypal-payments' );
		}
		return $this->settings->has( 'description' ) ?
			$this->settings->get( 'description' )
			: __( 'Pay via PayPal.', 'woocommerce-paypal-payments' );
	}
}
