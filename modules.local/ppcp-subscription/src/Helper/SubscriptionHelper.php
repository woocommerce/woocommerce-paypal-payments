<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Subscription\Helper;

class SubscriptionHelper {


	public function currentProductIsSubscription(): bool {
		if ( ! $this->pluginIsActive() ) {
			return false;
		}
		$product = wc_get_product();
		return is_a( $product, \WC_Product::class ) && $product->is_type( 'subscription' );
	}

	public function cartContainsSubscription(): bool {
		if ( ! $this->pluginIsActive() ) {
			return false;
		}
		$cart = WC()->cart;
		if ( ! $cart || $cart->is_empty() ) {
			return false;
		}

		foreach ( $cart->get_cart() as $item ) {
			if ( ! isset( $item['data'] ) || ! is_a( $item['data'], \WC_Product::class ) ) {
				continue;
			}
			if ( $item['data']->is_type( 'subscription' ) ) {
				return true;
			}
		}

		return false;
	}

	public function acceptOnlyAutomaticPaymentGateways(): bool {

		if ( ! $this->pluginIsActive() ) {
			return false;
		}
		$acceptManualRenewals = ( 'no' !== get_option(
            //phpcs:disable Inpsyde.CodeQuality.VariablesName.SnakeCaseVar
			\WC_Subscriptions_Admin::$option_prefix . '_accept_manual_renewals',
            //phpcs:enable Inpsyde.CodeQuality.VariablesName.SnakeCaseVar
			'no'
		) ) ? true : false;
		return ! $acceptManualRenewals;
	}

	public function pluginIsActive(): bool {

		return class_exists( \WC_Subscriptions::class );
	}
}
