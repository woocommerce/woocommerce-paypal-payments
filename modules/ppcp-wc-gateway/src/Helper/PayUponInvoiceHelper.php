<?php
/**
 * Helper methods for PUI.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use DateTime;

/**
 * Class PayUponInvoiceHelper
 */
class PayUponInvoiceHelper {

	/**
	 * Ensures date is valid and at least 18 years back.
	 *
	 * @param string $date The date.
	 * @param string $format The date format.
	 * @return bool
	 */
	public function validate_birth_date( string $date, string $format = 'Y-m-d' ): bool {
		$d = DateTime::createFromFormat( $format, $date );
		if ( false === $d ) {
			return false;
		}

		if ( $date !== $d->format( $format ) ) {
			return false;
		}

		if ( time() < strtotime( '+18 years', strtotime( $date ) ) ) {
			return false;
		}

		return true;
	}
}
