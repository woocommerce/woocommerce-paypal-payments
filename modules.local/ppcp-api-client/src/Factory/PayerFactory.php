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

    public function fromPayPalResponse(\stdClass $data) : Payer
    {

        $address = $this->addressFactory->fromPayPalRequest($data->address);
        $payerName = new PayerName(
            $data->name->given_name,
            $data->name->surname
        );
        $phone = (isset($data->phone)) ? new PhoneWithType(
            $data->phone->type,
            new Phone(
                $data->phone->phone_number->national_number
            )
        ) : null;
        $taxInfo = (isset($data->tax_info)) ? new PayerTaxInfo($data->tax_info->tax_id,$data->tax_info->tax_id_type) : null;
        $birthDate = (isset($data->birth_date)) ? \DateTime::createFromFormat('Y-m-d', $data->birth_date) : null;
        return new Payer(
            $payerName,
            $data->email_address,
            $data->payer_id,
            $address,
            $birthDate,
            $phone,
            $taxInfo
        );
    }
}