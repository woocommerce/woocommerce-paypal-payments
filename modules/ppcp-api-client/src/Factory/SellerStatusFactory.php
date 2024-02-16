<?php
/**
 * Factory for the SellerStatus object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatusProduct;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatusCapability;

/**
 * Class SellerStatusFactory
 */
class SellerStatusFactory {

	/**
	 * Creates a SellerStatus Object out of a PayPal response.
	 *
	 * @param \stdClass $json The response object.
	 *
	 * @return SellerStatus
	 */
	public function from_paypal_reponse( \stdClass $json ) : SellerStatus {
		$products = array_map(
			function( $json ) : SellerStatusProduct {
				$product = new SellerStatusProduct(
					isset( $json->name ) ? (string) $json->name : '',
					isset( $json->vetting_status ) ? (string) $json->vetting_status : '',
					isset( $json->capabilities ) ? (array) $json->capabilities : array()
				);
				return $product;
			},
			isset( $json->products ) ? (array) $json->products : array()
		);

		$capabilities = array_map(
			function( $json ) : SellerStatusCapability {
				$capability = new SellerStatusCapability(
					isset( $json->name ) ? (string) $json->name : '',
					isset( $json->status ) ? (string) $json->status : ''
				);
				return $capability;
			},
			isset( $json->capabilities ) ? (array) $json->capabilities : array()
		);

		return new SellerStatus( $products, $capabilities );
	}
}
