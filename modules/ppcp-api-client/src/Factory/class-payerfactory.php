<?php
/**
 * The Payer factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Payer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PayerName;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PayerTaxInfo;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Phone;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PhoneWithType;

/**
 * Class PayerFactory
 */
class PayerFactory {

	/**
	 * The address factory.
	 *
	 * @var AddressFactory
	 */
	private $address_factory;

	/**
	 * PayerFactory constructor.
	 *
	 * @param AddressFactory $address_factory The Address factory.
	 */
	public function __construct( AddressFactory $address_factory ) {
		$this->address_factory = $address_factory;
	}

	/**
	 * Returns a Payer entity from a WooCommerce order.
	 *
	 * @param \WC_Order $wc_order The WooCommerce order.
	 *
	 * @return Payer
	 */
	public function from_wc_order( \WC_Order $wc_order ): Payer {
		$payer_id  = '';
		$birthdate = null;

		$phone = null;
		if ( $wc_order->get_billing_phone() ) {
			// make sure the phone number contains only numbers and is max 14. chars long.
			$national_number = $wc_order->get_billing_phone();
			$national_number = preg_replace( '/[^0-9]/', '', $national_number );
			$national_number = substr( $national_number, 0, 14 );

			$phone = new PhoneWithType(
				'HOME',
				new Phone( $national_number )
			);
		}
		return new Payer(
			new PayerName(
				$wc_order->get_billing_first_name(),
				$wc_order->get_billing_last_name()
			),
			$wc_order->get_billing_email(),
			$payer_id,
			$this->address_factory->from_wc_order( $wc_order, 'billing' ),
			$birthdate,
			$phone
		);
	}

	/**
	 * Returns a Payer object based off a WooCommerce customer.
	 *
	 * @param \WC_Customer $customer The WooCommerce customer.
	 *
	 * @return Payer
	 */
	public function from_customer( \WC_Customer $customer ): Payer {
		$payer_id  = '';
		$birthdate = null;

		$phone = null;
		if ( $customer->get_billing_phone() ) {
			// make sure the phone number contains only numbers and is max 14. chars long.
			$national_number = $customer->get_billing_phone();
			$national_number = preg_replace( '/[^0-9]/', '', $national_number );
			$national_number = substr( $national_number, 0, 14 );

			$phone = new PhoneWithType(
				'HOME',
				new Phone( $national_number )
			);
		}
		return new Payer(
			new PayerName(
				$customer->get_billing_first_name(),
				$customer->get_billing_last_name()
			),
			$customer->get_billing_email(),
			$payer_id,
			$this->address_factory->from_wc_customer( $customer, 'billing' ),
			$birthdate,
			$phone
		);
	}

	/**
	 * Returns a Payer object based off a PayPal Response.
	 *
	 * @param \stdClass $data The JSON object.
	 *
	 * @return Payer
	 */
	public function from_paypal_response( \stdClass $data ): Payer {
		$address = null;
		if ( isset( $data->address ) ) {
			$address = $this->address_factory->from_paypal_response( $data->address );
		}
		$payer_name = new PayerName(
			isset( $data->name->given_name ) ? (string) $data->name->given_name : '',
			isset( $data->name->surname ) ? (string) $data->name->surname : ''
		);
		// TODO deal with phones without type instead of passing a invalid type.
		$phone      = ( isset( $data->phone ) ) ? new PhoneWithType(
			( isset( $data->phone->phone_type ) ) ? $data->phone->phone_type : 'undefined',
			new Phone(
				$data->phone->phone_number->national_number
			)
		) : null;
		$tax_info   = ( isset( $data->tax_info ) ) ?
			new PayerTaxInfo( $data->tax_info->tax_id, $data->tax_info->tax_id_type )
			: null;
		$birth_date = ( isset( $data->birth_date ) ) ?
			\DateTime::createFromFormat( 'Y-m-d', $data->birth_date )
			: null;
		return new Payer(
			$payer_name,
			isset( $data->email_address ) ? $data->email_address : '',
			( isset( $data->payer_id ) ) ? $data->payer_id : '',
			$address,
			$birth_date,
			$phone,
			$tax_info
		);
	}
}
