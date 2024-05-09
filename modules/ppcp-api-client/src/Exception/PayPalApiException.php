<?php
/**
 * The PayPal API Exception.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Exception
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Exception;

use stdClass;

/**
 * Class PayPalApiException
 */
class PayPalApiException extends RuntimeException {

	/**
	 * The JSON response object of PayPal.
	 *
	 * @var stdClass
	 */
	private $response;

	/**
	 * The HTTP status code of the PayPal response.
	 *
	 * @var int
	 */
	private $status_code;

	/**
	 * PayPalApiException constructor.
	 *
	 * @param stdClass|null $response The JSON object.
	 * @param int           $status_code The HTTP status code.
	 */
	public function __construct( stdClass $response = null, int $status_code = 0 ) {
		if ( is_null( $response ) ) {
			$response = new \stdClass();
		}
		if ( ! isset( $response->message ) ) {
			$response->message = sprintf(
				/* translators: %1$d - HTTP status code number (404, 500, ...) */
				__(
					'Unknown error while connecting to PayPal. Status code: %1$d.',
					'woocommerce-paypal-payments'
				),
				$this->status_code
			);
		}
		if ( ! isset( $response->name ) ) {
			$response->name = __( 'Error', 'woocommerce-paypal-payments' );
		}
		if ( ! isset( $response->details ) ) {
			$response->details = array();
		}
		if ( ! isset( $response->links ) || ! is_array( $response->links ) ) {
			$response->links = array();
		}

		/**
		 * The JSON response object.
		 *
		 * @var \stdClass $response
		 */
		$this->response    = $response;
		$this->status_code = $status_code;
		$message           = $this->get_customer_friendly_message( $response );
		if ( $response->name ) {
			$message = '[' . $response->name . '] ' . $message;
		}
		foreach ( $response->links as $link ) {
			if ( isset( $link->rel ) && 'information_link' === $link->rel ) {
				$message .= ' ' . $link->href;
			}
		}
		parent::__construct( $message, $status_code );
	}

	/**
	 * The name of the exception.
	 *
	 * @return string
	 */
	public function name(): string {
		return $this->response->name;
	}

	/**
	 * The details of the Exception.
	 *
	 * @return array
	 */
	public function details(): array {
		return $this->response->details;
	}

	/**
	 * Whether a certain detail is part of the exception reason.
	 *
	 * @param string $issue The issue.
	 *
	 * @return bool
	 */
	public function has_detail( string $issue ): bool {
		foreach ( $this->details() as $detail ) {
			if ( isset( $detail->issue ) && $detail->issue === $issue ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The HTTP status code.
	 *
	 * @return int
	 */
	public function status_code(): int {
		return $this->status_code;
	}

	/**
	 * Return exception details if exists.
	 *
	 * @param string $error The error to return in case no details found.
	 * @return string
	 */
	public function get_details( string $error ): string {
		if ( empty( $this->details() ) ) {
			return $error;
		}

		$details = '';
		foreach ( $this->details() as $detail ) {
			$issue       = $detail->issue ?? '';
			$field       = $detail->field ?? '';
			$description = $detail->description ?? '';
			$details    .= $issue . ' ' . $field . ' ' . $description . '<br>';
		}

		return $details;
	}

	/**
	 * Returns a friendly message if the error detail is known.
	 *
	 * @param stdClass $json The response.
	 * @return string
	 */
	public function get_customer_friendly_message( stdClass $json ): string {
		if ( empty( $json->details ) ) {
			return $json->message;
		}
		$improved_keys_messages = array(
			'PAYMENT_DENIED'              => __( 'PayPal rejected the payment. Please reach out to the PayPal support for more information.', 'woocommerce-paypal-payments' ),
			'TRANSACTION_REFUSED'         => __( 'The transaction has been refused by the payment processor. Please reach out to the PayPal support for more information.', 'woocommerce-paypal-payments' ),
			'DUPLICATE_INVOICE_ID'        => __( 'The transaction has been refused because the Invoice ID already exists. Please create a new order or reach out to the store owner.', 'woocommerce-paypal-payments' ),
			'PAYER_CANNOT_PAY'            => __( 'There was a problem processing this transaction. Please reach out to the store owner.', 'woocommerce-paypal-payments' ),
			'PAYEE_ACCOUNT_RESTRICTED'    => __( 'There was a problem processing this transaction. Please reach out to the store owner.', 'woocommerce-paypal-payments' ),
			'AGREEMENT_ALREADY_CANCELLED' => __( 'The requested agreement is already canceled. Please reach out to the PayPal support for more information.', 'woocommerce-paypal-payments' ),
		);
		$improved_errors        = array_filter(
			array_keys( $improved_keys_messages ),
			function ( $key ) use ( $json ): bool {
				foreach ( $json->details as $detail ) {
					if ( isset( $detail->issue ) && $detail->issue === $key ) {
						return true;
					}
				}
				return false;
			}
		);
		if ( $improved_errors ) {
			$improved_errors = array_values( $improved_errors );
			return $improved_keys_messages[ $improved_errors[0] ];
		}
		return $json->message;
	}
}
