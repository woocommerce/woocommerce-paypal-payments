<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Settings\Service;

use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
use WooCommerce\PayPalCommerce\Settings\Enum\SellerTypeEnum;

class SellerTypeResolver {

	/**
	 * Determines the merchant account type based on seller status capabilities.
	 *
	 * @param SellerStatus $seller_status The seller status from PayPal API.
	 * @return string A SellerTypeEnum value.
	 */
	public function resolve( SellerStatus $seller_status ): string {
		if ( $this->has_capability_active( $seller_status, 'COMMERCIAL_ENTITY' ) ) {
			return SellerTypeEnum::BUSINESS;
		}

		$business_capabilities = array(
			'CUSTOM_CARD_PROCESSING',
			'CARD_PROCESSING_VIRTUAL_TERMINAL',
			'FRAUD_TOOL_ACCESS',
			'PAY_UPON_INVOICE',
			'SEND_INVOICE',
		);

		foreach ( $business_capabilities as $capability ) {
			if ( $this->has_capability_active( $seller_status, $capability ) ) {
				return SellerTypeEnum::BUSINESS;
			}
		}

		foreach ( $seller_status->products() as $product ) {
			if ( $product->name() === 'PPCP_CUSTOM' &&
				$product->vetting_status() === 'SUBSCRIBED' ) {
				return SellerTypeEnum::BUSINESS;
			}
		}

		return SellerTypeEnum::UNKNOWN;
	}

	/**
	 * Checks if a specific capability is active for the seller.
	 *
	 * @param SellerStatus $seller_status  The seller status.
	 * @param string       $capability_name The capability name to check.
	 * @return bool True if the capability is active, false otherwise.
	 */
	private function has_capability_active( SellerStatus $seller_status, string $capability_name ): bool {
		foreach ( $seller_status->capabilities() as $capability ) {
			if ( $capability->name() === $capability_name &&
				$capability->status() === 'ACTIVE' ) {
				return true;
			}
		}
		return false;
	}
}
