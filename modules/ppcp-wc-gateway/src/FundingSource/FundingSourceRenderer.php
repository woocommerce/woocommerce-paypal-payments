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
	 * Map funding source ID -> human-readable name.
	 *
	 * @var array<string, string>
	 */
	protected $funding_sources;

	/**
	 * The IDs of the sources belonging to PayPal that do not need to mention "via PayPal".
	 *
	 * @var string[]
	 */
	protected $own_funding_sources = array( 'venmo', 'paylater' );

	/**
	 * FundingSourceRenderer constructor.
	 *
	 * @param ContainerInterface    $settings The settings.
	 * @param array<string, string> $funding_sources Map funding source ID -> human-readable name.
	 */
	public function __construct(
		ContainerInterface $settings,
		array $funding_sources
	) {
		$this->settings        = $settings;
		$this->funding_sources = $funding_sources;
	}

	/**
	 * Returns name of the funding source (suitable for displaying to user).
	 *
	 * @param string $id The ID of the funding source, such as 'venmo'.
	 */
	public function render_name( string $id ): string {
		if ( array_key_exists( $id, $this->funding_sources ) ) {
			if ( in_array( $id, $this->own_funding_sources, true ) ) {
				return $this->funding_sources[ $id ];
			}
			return sprintf(
				/* translators: %s - Sofort, BLIK, iDeal, Mercado Pago, etc. */
				__( '%s (via PayPal)', 'woocommerce-paypal-payments' ),
				$this->funding_sources[ $id ]
			);
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
		if ( array_key_exists( $id, $this->funding_sources ) ) {
			return sprintf(
				/* translators: %s - Sofort, BLIK, iDeal, Mercado Pago, etc. */
				__( 'Pay via %s.', 'woocommerce-paypal-payments' ),
				$this->funding_sources[ $id ]
			);
		}

		return $this->settings->has( 'description' ) ?
			$this->settings->get( 'description' )
			: __( 'Pay via PayPal.', 'woocommerce-paypal-payments' );
	}
}
