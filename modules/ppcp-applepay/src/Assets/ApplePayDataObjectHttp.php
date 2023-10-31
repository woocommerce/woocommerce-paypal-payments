<?php
/**
 * Builds the object containing the data to send to Apple.
 *
 * @package WooCommerce\PayPalCommerce\Applepay
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Applepay\Assets;

use Psr\Log\LoggerInterface as Logger;

/**
 * Class ApplePayDataObjectHttp
 */
class ApplePayDataObjectHttp {

	/**
	 * The nonce of the request.
	 *
	 * @var string
	 * @psalm-suppress PropertyNotSetInConstructor
	 */
	protected $nonce;

	/**
	 * The contact with less fields.
	 *
	 * @var mixed
	 */
	protected $simplified_contact;

	/**
	 * If the product needs shipping.
	 *
	 * @var mixed|null
	 */
	protected $need_shipping;

	/**
	 * The caller page.
	 *
	 * @var mixed
	 */
	protected $caller_page;

	/**
	 * The product id.
	 *
	 * @var mixed
	 */
	protected $product_id = '';

	/**
	 * The product quantity.
	 *
	 * @var string
	 */
	protected $product_quantity = '';

	/**
	 * The product variations.
	 *
	 * @var array
	 */
	protected $product_variations = array();

	/**
	 * The product extra.
	 *
	 * @var array
	 */
	protected $product_extra = array();

	/**
	 * The product booking.
	 *
	 * @var array
	 */
	protected $product_booking = array();

	/**
	 * The shipping methods.
	 *
	 * @var array|mixed
	 */
	protected $shipping_method = array();

	/**
	 * The billing address.
	 *
	 * @var string[]
	 */
	protected $billing_contact = array();

	/**
	 * The shipping address.
	 *
	 * @var string[]
	 */
	protected $shipping_contact = array();

	/**
	 * The list of errors.
	 *
	 * @var array
	 */
	protected $errors = array();

	/**
	 * The logger.
	 *
	 * @var Logger
	 */
	protected $logger;
	/**
	 * The validation flag.
	 *
	 * @var bool
	 */
	protected $validation_flag = false;

	/**
	 * ApplePayDataObjectHttp constructor.
	 *
	 * @param Logger $logger The logger.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Resets the errors array
	 */
	protected function reset_errors(): void {
		$this->errors = array();
	}

	/**
	 * Returns if the object has any errors
	 *
	 * @return bool
	 */
	public function has_errors(): bool {
		return ! empty( $this->errors );
	}
	/**
	 * Returns errors
	 *
	 * @return array
	 */
	public function errors(): array {
		return $this->errors;
	}

	/**
	 * Assigns the validation flag
	 *
	 * @return void
	 */
	public function validation_data(): void {
		$data = filter_input( INPUT_POST, 'validation', FILTER_VALIDATE_BOOL );
		if ( ! $data ) {
			return;
		}
		$this->validation_flag = $data;
	}

	/**
	 * Set the object with the data relevant to ApplePay on update shipping contact
	 * Required data depends on callerPage
	 */
	public function update_contact_data(): void {
		$nonce = filter_input( INPUT_POST, 'woocommerce-process-checkout-nonce', FILTER_SANITIZE_SPECIAL_CHARS );
		if ( ! $nonce ) {
			return;
		}
		$is_nonce_valid = wp_verify_nonce(
			$nonce,
			'woocommerce-process_checkout'
		);
		if ( ! $is_nonce_valid ) {
			return;
		}
		$data = $this->get_filtered_request_data();
		if ( ! $data ) {
			return;
		}

		$data = $this->preprocess_request_data( $data );

		$result = $this->update_required_data(
			$data,
			PropertiesDictionary::UPDATE_CONTACT_SINGLE_PROD_REQUIRED_FIELDS,
			PropertiesDictionary::UPDATE_CONTACT_CART_REQUIRED_FIELDS
		);
		if ( ! $result ) {
			return;
		}
		$this->update_simplified_contact( $data[ PropertiesDictionary::SIMPLIFIED_CONTACT ] );
	}

	/**
	 * Set the object with the data relevant to ApplePay on update shipping method
	 * Required data depends on callerPage
	 */
	public function update_method_data(): void {
		$nonce = filter_input( INPUT_POST, 'woocommerce-process-checkout-nonce', FILTER_SANITIZE_SPECIAL_CHARS );
		if ( ! $nonce ) {
			return;
		}
		$is_nonce_valid = wp_verify_nonce(
			$nonce,
			'woocommerce-process_checkout'
		);
		if ( ! $is_nonce_valid ) {
			return;
		}

		$data = $this->get_filtered_request_data();
		if ( ! $data ) {
			return;
		}

		$data = $this->preprocess_request_data( $data );

		$result = $this->update_required_data(
			$data,
			PropertiesDictionary::UPDATE_METHOD_SINGLE_PROD_REQUIRED_FIELDS,
			PropertiesDictionary::UPDATE_METHOD_CART_REQUIRED_FIELDS
		);
		if ( ! $result ) {
			return;
		}
		$this->update_simplified_contact( $data[ PropertiesDictionary::SIMPLIFIED_CONTACT ] );
		$this->update_shipping_method( $data );
	}

	/**
	 * Set the object with the data relevant to ApplePay on authorized order
	 * Required data depends on callerPage
	 *
	 * @param string $caller_page The caller page.
	 */
	public function order_data( string $caller_page ): void {

		if ( ! $this->is_nonce_valid() ) {
			return;
		}
		//phpcs:disable WordPress.Security.NonceVerification
		$data = filter_var_array( $_POST, FILTER_SANITIZE_SPECIAL_CHARS );
		if ( ! $data ) {
			return;
		}

		$data = $this->append_products_to_data( $data, $_POST );
		$data = $this->preprocess_request_data( $data );

		$data[ PropertiesDictionary::CALLER_PAGE ] = $caller_page;
		$result                                    = $this->update_required_data(
			$data,
			PropertiesDictionary::CREATE_ORDER_SINGLE_PROD_REQUIRED_FIELDS,
			PropertiesDictionary::CREATE_ORDER_CART_REQUIRED_FIELDS
		);

		if ( ! $result ) {
			return;
		}
		if (
			! array_key_exists( 'emailAddress', $data[ PropertiesDictionary::SHIPPING_CONTACT ] )
			|| ! $data[ PropertiesDictionary::SHIPPING_CONTACT ]['emailAddress']
		) {
			$this->errors[] = array(
				'errorCode'    => PropertiesDictionary::SHIPPING_CONTACT_INVALID,
				'contactField' => 'emailAddress',
			);

			return;
		}

		$filtered_shipping_contact = $data[ PropertiesDictionary::SHIPPING_CONTACT ];
		$this->shipping_contact    = $this->complete_address(
			$filtered_shipping_contact,
			PropertiesDictionary::SHIPPING_CONTACT_INVALID
		);
		$filtered_billing_contact  = $data[ PropertiesDictionary::BILLING_CONTACT ];
		$this->billing_contact     = $this->complete_address(
			$filtered_billing_contact,
			PropertiesDictionary::BILLING_CONTACT_INVALID
		);
		$this->update_shipping_method( $data );
	}

	/**
	 * Pre-processes request data to transform it to a standard format.
	 *
	 * @param array $data The data.
	 * @return array
	 */
	protected function preprocess_request_data( array $data ): array {
		// Fill product variables if a products object is received.
		if ( is_array( $data[ PropertiesDictionary::PRODUCTS ] ?? null ) ) {
			$product = $data[ PropertiesDictionary::PRODUCTS ][0];

			$data[ PropertiesDictionary::PRODUCT_ID ]         = $product['id'] ?? 0;
			$data[ PropertiesDictionary::PRODUCT_QUANTITY ]   = $product['quantity'] ?? array();
			$data[ PropertiesDictionary::PRODUCT_VARIATIONS ] = $product['variations'] ?? array();
			$data[ PropertiesDictionary::PRODUCT_EXTRA ]      = $product['extra'] ?? array();
			$data[ PropertiesDictionary::PRODUCT_BOOKING ]    = $product['booking'] ?? array();
		}
		unset( $data[ PropertiesDictionary::PRODUCTS ] );
		return $data;
	}

	/**
	 * Checks if the array contains all required fields and if those
	 * are not empty.
	 * If not it adds an unkown error to the object's error list, as this errors
	 * are not supported by ApplePay.
	 *
	 * @param array $data The data.
	 * @param array $required The required fields.
	 *
	 * @return bool
	 */
	protected function has_required_fields_values_or_error( array $data, array $required ) {
		foreach ( $required as $required_field ) {
			if ( ! array_key_exists( $required_field, $data ) ) {
				$this->logger->debug(
					sprintf( 'ApplePay Data Error: Missing index %s', $required_field )
				);

				$this->errors[] = array( 'errorCode' => 'unknown' );
				continue;
			}
			if ( $data[ $required_field ] === null || $data[ $required_field ] === '' ) {
				$this->logger->debug(
					sprintf( 'ApplePay Data Error: Missing value for %s', $required_field )
				);
				$this->errors[] = array( 'errorCode' => 'unknown' );
				continue;
			}
		}
		return ! $this->has_errors();
	}

	/**
	 * Sets the value to the appropriate field in the object.
	 *
	 * @param array $data The data.
	 */
	protected function assign_data_object_values( array $data ): void {
		foreach ( $data as $key => $value ) {
			// Null values may give origin to type errors. If necessary replace this condition with a specialized field filter.
			if ( null === $value ) {
				continue;
			}
			if ( $key === 'woocommerce-process-checkout-nonce' ) {
				$key = 'nonce';
			}
			$this->$key = $value;
		}

	}

	/**
	 * Returns the address details used in pre-authorization steps.
	 *
	 * @param array $contact_info The contact info.
	 *
	 * @return string[]
	 */
	protected function simplified_address( array $contact_info ) {
		$required = array(
			'locality'    => 'locality',
			'postalCode'  => 'postalCode',
			'countryCode' => 'countryCode',
		);
		if (
			! $this->address_has_required_fields_values(
				$contact_info,
				$required,
				PropertiesDictionary::SHIPPING_CONTACT_INVALID
			)
		) {
			return array();
		}
		return array(
			'city'     => $contact_info['locality'],
			'postcode' => $contact_info['postalCode'],
			'country'  => strtoupper( $contact_info['countryCode'] ),
		);
	}

	/**
	 * Checks if the address array contains all required fields and if those
	 * are not empty.
	 * If not it adds a contacField error to the object's error list.
	 *
	 * @param array  $post      The address to check.
	 * @param array  $required  The required fields for the given address.
	 * @param string $error_code Either shipping or billing kind.
	 *
	 * @return bool
	 */
	protected function address_has_required_fields_values(
		array $post,
		array $required,
		string $error_code
	) {

		foreach ( $required as $required_field => $error_value ) {
			if ( ! array_key_exists( $required_field, $post ) ) {
				$this->logger->debug(
					sprintf( 'ApplePay Data Error: Missing index %s', $required_field )
				);

				$this->errors[] = array( 'errorCode' => 'unknown' );
				continue;
			}
			if ( ! $post[ $required_field ] ) {
				$this->logger->debug(
					sprintf( 'ApplePay Data Error: Missing value for %s', $required_field )
				);
				$this->errors[]
				= array(
					'errorCode'    => $error_code,
					'contactField' => $error_value,
				);
				continue;
			}
		}
		return ! $this->has_errors();
	}

	/**
	 * Returns the address details for after authorization steps.
	 *
	 * @param array  $data     The data.
	 * @param string $error_code differentiates between billing and shipping information.
	 *
	 * @return string[]
	 */
	protected function complete_address( array $data, string $error_code ): array {
		$required = array(
			'givenName'    => 'name',
			'familyName'   => 'name',
			'addressLines' => 'addressLines',
			'locality'     => 'locality',
			'postalCode'   => 'postalCode',
			'countryCode'  => 'countryCode',
		);
		if (
			! $this->address_has_required_fields_values(
				$data,
				$required,
				$error_code
			)
		) {
			return array();
		}

		return array(
			'first_name' => $data['givenName'],
			'last_name'  => $data['familyName'],
			'email'      => $data['emailAddress'] ?? '',
			'phone'      => $data['phoneNumber'] ?? '',
			'address_1'  => $data['addressLines'][0] ?? '',
			'address_2'  => $data['addressLines'][1] ?? '',
			'city'       => $data['locality'],
			'state'      => $data['administrativeArea'],
			'postcode'   => $data['postalCode'],
			'country'    => strtoupper( $data['countryCode'] ),
		);
	}

	/**
	 * Updates the object with the required data.
	 *
	 * @param array $data The data.
	 * @param array $required_product_fields The required product fields.
	 * @param array $required_cart_fields The required cart fields.
	 * @return bool
	 */
	protected function update_required_data( array $data, array $required_product_fields, array $required_cart_fields ) {
		$this->reset_errors();
		$required_fields = $required_product_fields;
		if (
			isset( $data[ PropertiesDictionary::CALLER_PAGE ] )
			&& $data[ PropertiesDictionary::CALLER_PAGE ] !== 'product'
		) {
			$required_fields = $required_cart_fields;
		}
		$has_required_fields_values = $this->has_required_fields_values_or_error(
			$data,
			$required_fields
		);
		if ( ! $has_required_fields_values ) {
			return false;
		}
		$this->assign_data_object_values( $data );
		return true;
	}

	/**
	 * Updates the data object with the contact values from the request.
	 *
	 * @param array $data The data.
	 * @return void
	 */
	protected function update_simplified_contact( array $data ) : void {
		$simplified_contact_info  = array_map( 'sanitize_text_field', $data );
		$this->simplified_contact = $this->simplified_address(
			$simplified_contact_info
		);
	}

	/**
	 * Updates the data object with the shipping values from the request.
	 *
	 * @param array $data The data.
	 * @return void
	 */
	protected function update_shipping_method( array $data ): void {
		if (
			array_key_exists(
				PropertiesDictionary::SHIPPING_METHOD,
				$data
			)
		) {
			$this->shipping_method = filter_var_array(
				$data[ PropertiesDictionary::SHIPPING_METHOD ],
				FILTER_SANITIZE_SPECIAL_CHARS
			);
		}
	}

	/**
	 * Returns the billing address.
	 *
	 * @return string[]
	 */
	public function billing_address(): array {
		return $this->billing_contact;
	}

	/**
	 * Returns the shipping address.
	 *
	 * @return string[]
	 */
	public function shipping_address(): array {
		return $this->shipping_contact;
	}

	/**
	 * Returns the shipping method.
	 *
	 * @return array
	 */
	public function shipping_method(): array {
		return $this->shipping_method ?? array();
	}

	/**
	 * Returns if the shipping is needed.
	 *
	 * @return bool
	 */
	public function need_shipping(): bool {
		return $this->need_shipping ?? false;
	}

	/**
	 * Returns the product id.
	 *
	 * @return string
	 */
	public function product_id(): string {
		return $this->product_id;
	}

	/**
	 * Returns the product id.
	 *
	 * @return string
	 */
	public function caller_page(): string {
		return $this->caller_page;
	}

	/**
	 * Returns the product quantity.
	 *
	 * @return string
	 */
	public function product_quantity(): string {
		return $this->product_quantity;
	}

	/**
	 * Returns the product variations.
	 *
	 * @return array
	 */
	public function product_variations(): array {
		return $this->product_variations;
	}

	/**
	 * Returns the product extra.
	 *
	 * @return array
	 */
	public function product_extra(): array {
		return $this->product_extra;
	}

	/**
	 * Returns the product booking.
	 *
	 * @return array
	 */
	public function product_booking(): array {
		return $this->product_booking;
	}

	/**
	 * Returns the nonce.
	 *
	 * @return string
	 */
	public function nonce(): string {
		return $this->nonce;
	}

	/**
	 * Returns the simplified contact.
	 *
	 * @return mixed
	 */
	public function simplified_contact() {
		return $this->simplified_contact;
	}

	/**
	 * Returns the validated flag.
	 *
	 * @return bool
	 */
	public function validated_flag() {
		return $this->validation_flag;
	}

	/**
	 * Returns the filtered request data.
	 *
	 * @return array|false|null
	 */
	public function get_filtered_request_data() {
		$data = filter_input_array(
			INPUT_POST,
			array(
				PropertiesDictionary::CALLER_PAGE        => FILTER_SANITIZE_SPECIAL_CHARS,
				'woocommerce-process-checkout-nonce'     => FILTER_SANITIZE_SPECIAL_CHARS,
				PropertiesDictionary::NEED_SHIPPING      => FILTER_VALIDATE_BOOLEAN,
				PropertiesDictionary::SIMPLIFIED_CONTACT => array(
					'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
					'flags'  => FILTER_REQUIRE_ARRAY,
				),
				PropertiesDictionary::SHIPPING_CONTACT   => array(
					'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
					'flags'  => FILTER_REQUIRE_ARRAY,
				),
				PropertiesDictionary::BILLING_CONTACT    => array(
					'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
					'flags'  => FILTER_REQUIRE_ARRAY,
				),
				PropertiesDictionary::SHIPPING_METHOD    => array(
					'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
					'flags'  => FILTER_REQUIRE_ARRAY,
				),
				PropertiesDictionary::PRODUCT_ID         => FILTER_SANITIZE_NUMBER_INT,
				PropertiesDictionary::PRODUCT_QUANTITY   => FILTER_SANITIZE_NUMBER_INT,
				PropertiesDictionary::PRODUCT_VARIATIONS => array(
					'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
					'flags'  => FILTER_REQUIRE_ARRAY,
				),
				PropertiesDictionary::PRODUCT_EXTRA      => array(
					'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
					'flags'  => FILTER_REQUIRE_ARRAY,
				),
				PropertiesDictionary::PRODUCT_BOOKING    => array(
					'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
					'flags'  => FILTER_REQUIRE_ARRAY,
				),
			)
		);

		if ( ! $data ) {
			return false;
		}

		return $this->append_products_to_data( $data, $_POST );
	}

	/**
	 * Appends product to a data array.
	 *
	 * @param array $data The data.
	 * @param array $request_data The request data.
	 * @return array
	 */
	public function append_products_to_data( array $data, array $request_data ): array {
		$products = json_decode( wp_unslash( $request_data[ PropertiesDictionary::PRODUCTS ] ?? '' ), true );

		if ( $products ) {
			$data[ PropertiesDictionary::PRODUCTS ] = $products;
		}

		return $data;
	}

	/**
	 * Returns if the nonce is valid.
	 *
	 * @return bool
	 */
	public function is_nonce_valid():bool {
		$nonce = filter_input( INPUT_POST, 'woocommerce-process-checkout-nonce', FILTER_SANITIZE_SPECIAL_CHARS );
		if ( ! $nonce ) {
			return false;
		}
		return (bool) wp_verify_nonce(
			$nonce,
			'woocommerce-process_checkout'
		);
	}
}
