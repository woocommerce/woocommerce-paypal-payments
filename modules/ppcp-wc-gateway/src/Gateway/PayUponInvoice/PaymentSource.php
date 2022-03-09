<?php

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice;

class PaymentSource {

	/**
	 * @var string
	 */
	protected $given_name;

	/**
	 * @var string
	 */
	protected $surname;

	/**
	 * @var string
	 */
	protected $email;

	/**
	 * @var string
	 */
	protected $birth_date;

	/**
	 * @var string
	 */
	protected $national_number;

	/**
	 * @var string
	 */
	protected $phone_country_code;

	/**
	 * @var string
	 */
	protected $address_line_1;

	/**
	 * @var string
	 */
	protected $admin_area_2;

	/**
	 * @var string
	 */
	protected $postal_code;

	/**
	 * @var string
	 */
	protected $country_code;

	/**
	 * @var string
	 */
	protected $locale;

	/**
	 * @var string
	 */
	protected $brand_name;

	/**
	 * @var string
	 */
	protected $logo_url;

	/**
	 * @var array
	 */
	protected $customer_service_instructions;

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

	public function to_array(): array {
		return array(
			'name'               => array(
				'given_name' => $this->given_name,
				'surname'    => $this->surname,
			),
			'email'              => $this->email,
			'birth_date'         => $this->birth_date,
			'phone'              => array(
				'national_number' => $this->national_number,
				'country_code'    => $this->phone_country_code,
			),
			'billing_address'    => array(
				'address_line_1' => $this->address_line_1,
				'admin_area_2'   => $this->admin_area_2,
				'postal_code'    => $this->postal_code,
				'country_code'   => $this->country_code,
			),
			'experience_context' => array(
				'locale'                        => $this->locale,
				'brand_name'                    => $this->brand_name,
				'logo_url'                      => $this->logo_url,
				'customer_service_instructions' => $this->customer_service_instructions,
			),
		);
	}
}
