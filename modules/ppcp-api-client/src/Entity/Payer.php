<?php
/**
 * The payer object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class Payer
 * The customer who sends the money.
 */
class Payer {

	/**
	 * The name.
	 *
	 * @var PayerName
	 */
	private $name;

	/**
	 * The email address.
	 *
	 * @var string
	 */
	private $email_address;

	/**
	 * The payer id.
	 *
	 * @var string
	 */
	private $payer_id;

	/**
	 * The birth date.
	 *
	 * @var \DateTime|null
	 */
	private $birthdate;

	/**
	 * The address.
	 *
	 * @var Address
	 */
	private $address;

	/**
	 * The phone.
	 *
	 * @var PhoneWithType|null
	 */
	private $phone;

	/**
	 * The tax info.
	 *
	 * @var PayerTaxInfo|null
	 */
	private $tax_info;

	/**
	 * Payer constructor.
	 *
	 * @param PayerName          $name The name.
	 * @param string             $email_address The email.
	 * @param string             $payer_id The payer id.
	 * @param Address|null       $address The address.
	 * @param \DateTime|null     $birthdate The birth date.
	 * @param PhoneWithType|null $phone The phone.
	 * @param PayerTaxInfo|null  $tax_info The tax info.
	 */
	public function __construct(
		PayerName $name,
		string $email_address,
		string $payer_id,
		Address $address = null,
		\DateTime $birthdate = null,
		PhoneWithType $phone = null,
		PayerTaxInfo $tax_info = null
	) {

		$this->name          = $name;
		$this->email_address = $email_address;
		$this->payer_id      = $payer_id;
		$this->birthdate     = $birthdate;
		$this->address       = $address;
		$this->phone         = $phone;
		$this->tax_info      = $tax_info;
	}

	/**
	 * Returns the name.
	 *
	 * @return PayerName
	 */
	public function name(): PayerName {
		return $this->name;
	}

	/**
	 * Returns the email address.
	 *
	 * @return string
	 */
	public function email_address(): string {
		return $this->email_address;
	}

	/**
	 * Returns the payer id.
	 *
	 * @return string
	 */
	public function payer_id(): string {
		return $this->payer_id;
	}

	/**
	 * Returns the birth date.
	 *
	 * @return \DateTime|null
	 */
	public function birthdate() {
		return $this->birthdate;
	}

	/**
	 * Returns the address.
	 *
	 * @return Address|null
	 */
	public function address() {
		return $this->address;
	}

	/**
	 * Returns the phone.
	 *
	 * @return PhoneWithType|null
	 */
	public function phone() {
		return $this->phone;
	}

	/**
	 * Returns the tax info.
	 *
	 * @return PayerTaxInfo|null
	 */
	public function tax_info() {
		return $this->tax_info;
	}

	/**
	 * Returns the object as array.
	 *
	 * @return array
	 */
	public function to_array() {
		$payer = array(
			'name'          => $this->name()->to_array(),
			'email_address' => $this->email_address(),
		);
		if ( $this->address() ) {
			$payer['address'] = $this->address->to_array();
			if ( 2 !== strlen( $this->address()->country_code() ) ) {
				unset( $payer['address'] );
			}
		}
		if ( $this->payer_id() ) {
			$payer['payer_id'] = $this->payer_id();
		}

		if ( $this->phone() ) {
			$payer['phone'] = $this->phone()->to_array();
		}
		if ( $this->tax_info() ) {
			$payer['tax_info'] = $this->tax_info()->to_array();
		}
		if ( $this->birthdate() ) {
			$payer['birth_date'] = $this->birthdate()->format( 'Y-m-d' );
		}
		return $payer;
	}
}
