<?php
/**
 * Possible values of card_billing_data_mode.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway;

/**
 * Class CardBillingMode
 */
interface CardBillingMode {
	public const USE_WC        = 'use_wc';
	public const MINIMAL_INPUT = 'minimal_input';
	public const NO_WC         = 'no_wc';
}
