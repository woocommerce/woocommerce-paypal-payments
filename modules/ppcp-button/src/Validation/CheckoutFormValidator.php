<?php
/**
 * Executes WC checkout validation.
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Validation;

use WC_Checkout;
use WooCommerce\PayPalCommerce\Button\Exception\ValidationException;
use WP_Error;

/**
 * Class FormValidator
 */
class CheckoutFormValidator extends WC_Checkout {
	/**
	 * Validates the form data.
	 *
	 * @param array $data The form data.
	 * @return void
	 * @throws ValidationException When validation fails.
	 */
	public function validate( array $data ) {
		$errors = new WP_Error();

		if ( isset( $data['terms-field'] ) ) {
			// WC checks this field via $_POST https://github.com/woocommerce/woocommerce/issues/35328 .
			$_POST['terms-field'] = $data['terms-field'];
		}

		// It throws some notices when checking fields etc., also from other plugins via hooks.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@$this->validate_checkout( $data, $errors );

		if ( $errors->has_errors() ) {
			throw new ValidationException( $errors->get_error_messages() );
		}
	}
}
