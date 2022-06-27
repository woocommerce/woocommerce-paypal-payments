<?php
/**
 * PUI payment source.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice;

/**
 * Class PaymentSource.
 */
class PaymentSource {

	/**
	 * The given name.
	 *
	 * @var string
	 */
	protected $given_name;

	/**
	 * The surname.
	 *
	 * @var string
	 */
	protected $surname;

	/**
	 * The email.
	 *
	 * @var string
	 */
	protected $email;

	/**
	 * The birth date.
	 *
	 * @var string
	 */
	protected $birth_date;

	/**
	 * The phone number.
	 *
	 * @var string
	 */
	protected $national_number;

	/**
	 * The phone country code.
	 *
	 * @var string
	 */
	protected $phone_country_code;

	/**
	 * The address line 1.
	 *
	 * @var string
	 */
	protected $address_line_1;

	/**
	 * The admin area 2.
	 *
	 * @var string
	 */
	protected $admin_area_2;

	/**
	 * The postal code.
	 *
	 * @var string
	 */
	protected $postal_code;

	/**
	 * The country code.
	 *
	 * @var string
	 */
	protected $country_code;

	/**
	 * The locale.
	 *
	 * @var string
	 */
	protected $locale;

	/**
	 * The brand name.
	 *
	 * @var string
	 */
	protected $brand_name;

	/**
	 * The logo URL.
	 *
	 * @var string
	 */
	protected $logo_url;

	/**
	 * The customer service instructions.
	 *
	 * @var array
	 */
	protected $customer_service_instructions;

	/**
	 * PaymentSource constructor.
	 *
	 * @param string $given_name The given name.
	 * @param string $surname The surname.
	 * @param string $email The email.
	 * @param string $birth_date The birth date.
	 * @param string $national_number The phone number.
	 * @param string $phone_country_code The phone country code.
	 * @param string $address_line_1 The address line 1.
	 * @param string $admin_area_2 The admin area 2.
	 * @param string $postal_code The postal code.
	 * @param string $country_code The country code.
	 * @param string $locale The locale.
	 * @param string $brand_name The brand name.
	 * @param string $logo_url The logo URL.
	 * @param array  $customer_service_instructions The customer service instructions.
	 */
	public function __construct(
		string $given_name,
		string $surname,
		string $email,
		string $birth_date,
		string $national_number,
		string $phone_country_code,
		string $address_line_1,
		string $admin_area_2,
		string $postal_code,
		string $country_code,
		string $locale,
		string $brand_name,
		string $logo_url,
		array $customer_service_instructions
	) {
		$this->given_name                    = $given_name;
		$this->surname                       = $surname;
		$this->email                         = $email;
		$this->birth_date                    = $birth_date;
		$this->national_number               = $national_number;
		$this->phone_country_code            = $phone_country_code;
		$this->address_line_1                = $address_line_1;
		$this->admin_area_2                  = $admin_area_2;
		$this->postal_code                   = $postal_code;
		$this->country_code                  = $country_code;
		$this->locale                        = $locale;
		$this->brand_name                    = $brand_name;
		$this->logo_url                      = $logo_url;
		$this->customer_service_instructions = $customer_service_instructions;
	}

	/**
	 * Returns the given name.
	 *
	 * @return string
	 */
	public function given_name(): string {
		return $this->given_name;
	}

	/**
	 * Returns the surname.
	 *
	 * @return string
	 */
	public function surname(): string {
		return $this->surname;
	}

	/**
	 * Returns the email.
	 *
	 * @return string
	 */
	public function email(): string {
		return $this->email;
	}

	/**
	 * Returns the birth date.
	 *
	 * @return string
	 */
	public function birth_date(): string {
		return $this->birth_date;
	}

	/**
	 * Returns the national number.
	 *
	 * @return string
	 */
	public function national_number(): string {
		return $this->national_number;
	}

	/**
	 * Returns the phone country code.
	 *
	 * @return string
	 */
	public function phone_country_code(): string {
		return $this->phone_country_code;
	}

	/**
	 * Returns the address line 1.
	 *
	 * @return string
	 */
	public function address_line_1(): string {
		return $this->address_line_1;
	}

	/**
	 * Returns the admin area 2.
	 *
	 * @return string
	 */
	public function admin_area_2(): string {
		return $this->admin_area_2;
	}

	/**
	 * Returns the postal code.
	 *
	 * @return string
	 */
	public function postal_code(): string {
		return $this->postal_code;
	}

	/**
	 * Returns the country code.
	 *
	 * @return string
	 */
	public function country_code(): string {
		return $this->country_code;
	}

	/**
	 * Returns the locale.
	 *
	 * @return string
	 */
	public function locale(): string {
		return $this->locale;
	}

	/**
	 * Returns the brand name.
	 *
	 * @return string
	 */
	public function brand_name(): string {
		return $this->brand_name;
	}

	/**
	 * The logo URL.
	 *
	 * @return string
	 */
	public function logo_url(): string {
		return $this->logo_url;
	}

	/**
	 * Returns the customer service instructions.
	 *
	 * @return array
	 */
	public function customer_service_instructions(): array {
		return $this->customer_service_instructions;
	}

	/**
	 * Returns payment source as array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'name'               => array(
				'given_name' => $this->given_name(),
				'surname'    => $this->surname(),
			),
			'email'              => $this->email(),
			'birth_date'         => $this->birth_date(),
			'phone'              => array(
				'national_number' => $this->national_number(),
				'country_code'    => $this->phone_country_code(),
			),
			'billing_address'    => array(
				'address_line_1' => $this->address_line_1(),
				'admin_area_2'   => $this->admin_area_2(),
				'postal_code'    => $this->postal_code(),
				'country_code'   => $this->country_code(),
			),
			'experience_context' => array(
				'locale'                        => $this->locale(),
				'brand_name'                    => $this->brand_name(),
				'logo_url'                      => $this->logo_url(),
				'customer_service_instructions' => $this->customer_service_instructions(),
			),
		);
	}
}
