<?php
/**
 * Wrapper for more detailed gateway error.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Exception
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Exception;

use Exception;
use Throwable;

/**
 * Class GatewayGenericException
 */
class GatewayGenericException extends Exception {
	/**
	 * GatewayGenericException constructor.
	 *
	 * @param Throwable|null $inner The exception.
	 */
	public function __construct( ?Throwable $inner = null ) {
		parent::__construct(
			__( 'Failed to process the payment. Please try again or contact the shop admin.', 'woocommerce-paypal-payments' ),
			$inner ? (int) $inner->getCode() : 0,
			$inner
		);
	}
}
