<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Factory;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Payer;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PayerName;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PayerTaxInfo;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Phone;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PhoneWithType;

class PayerFactory
{

    private $addressFactory;
    public function __construct(AddressFactory $addressFactory)
    {
        $this->addressFactory = $addressFactory;
    }

    public function fromCustomer(\WC_Customer $customer): Payer
    {
        $payerId = '';
        $birthdate = null;

        $phone = null;
        if ($customer->get_billing_phone()) {
            // make sure the phone number contains only numbers and is max 14. chars long.
            $nationalNumber = $customer->get_billing_phone();
            $nationalNumber = preg_replace("/[^0-9]/", "", $nationalNumber);
            $nationalNumber = substr($nationalNumber, 0, 14);

            $phone = new PhoneWithType(
                'HOME',
                new Phone($nationalNumber)
            );
        }
        return new Payer(
            new PayerName(
                $customer->get_billing_first_name(),
                $customer->get_billing_last_name()
            ),
            $customer->get_billing_email(),
            $payerId,
            $this->addressFactory->fromWcCustomer($customer, 'billing'),
            $birthdate,
            $phone
        );
    }

    public function fromPayPalResponse(\stdClass $data): Payer
    {
        $address = $this->addressFactory->fromPayPalRequest($data->address);
        $payerName = new PayerName(
            isset($data->name->given_name) ? (string) $data->name->given_name : '',
            isset($data->name->surname) ? (string) $data->name->surname : ''
        );
        // TODO deal with phones without type instead of passing a invalid type
        $phone = (isset($data->phone)) ? new PhoneWithType(
            (isset($data->phone->phone_type)) ? $data->phone->phone_type : 'undefined',
            new Phone(
                $data->phone->phone_number->national_number
            )
        ) : null;
        $taxInfo = (isset($data->tax_info)) ?
            new PayerTaxInfo($data->tax_info->tax_id, $data->tax_info->tax_id_type)
            : null;
        $birthDate = (isset($data->birth_date)) ?
            \DateTime::createFromFormat('Y-m-d', $data->birth_date)
            : null;
        return new Payer(
            $payerName,
            isset($data->email_address) ? $data->email_address : '',
            (isset($data->payer_id)) ? $data->payer_id : '',
            $address,
            $birthDate,
            $phone,
            $taxInfo
        );
    }
}
