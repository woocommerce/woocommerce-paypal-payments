<?php
/**
 * Saves the form data to the WC customer and session.
 *
 * @package WooCommerce\PayPalCommerce\Button\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Helper;

use WC_Checkout;

/**
 * Class CheckoutFormSaver
 */
class CheckoutFormSaver extends WC_Checkout {
	/**
	 * Saves the form data to the WC customer and session.
	 *
	 * @param array $data The form data.
	 * @return void
	 */
	public function save( array $data ) {
		foreach ( $data as $key => $value ) {
			$_POST[ $key ] = $value;
		}
		$data = $this->get_posted_data();

		$this->update_session( $data );
	}
}
