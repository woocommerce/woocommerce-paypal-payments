<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

/**
 * Class Payer
 * The customer who sends the money.
 *
 * @package Inpsyde\PayPalCommerce\ApiClient\Entity
 */
class Payer
{

    private $name;
    private $emailAddress;
    private $payerId;
    private $birthDate;
    private $address;
    private $phone;
    private $taxInfo;

    public function __construct(
        PayerName $name,
        string $emailAddress,
        string $payerId,
        Address $address,
        \DateTime $birthDate = null,
        PhoneWithType $phone = null,
        PayerTaxInfo $taxInfo = null
    ) {
        $this->name = $name;
        $this->emailAddress = $emailAddress;
        $this->payerId = $payerId;
        $this->birthDate = $birthDate;
        $this->address = $address;
        $this->phone = $phone;
        $this->taxInfo = $taxInfo;
    }

    public function name() : PayerName {
        return $this->name;
    }

    public function emailAddress() : string {
        $this->emailAddress;
    }

    public function payerId() : string {
        return $this->payerId;
    }

    public function birthDate() : \DateTime {
        return $this->birthDate;
    }

    public function address() : Address {
        return $this->address;
    }

    public function phone() : ?PhoneWithType {
        return $this->phone;
    }

    public function taxInfo() : ?PayerTaxInfo {
        return $this->taxInfo;
    }

    public function toArray() : array {
        $payer = [
            'name' => $this->name()->toArray(),
            'email_address' => $this->emailAddress(),
            'payer_id' => $this->payerId(),
            'address' => $this->address()->toArray(),
        ];

        if ($this->phone()) {
            $payer['phone'] = $this->phone()->toArray();
        }
        if ($this->taxInfo()) {
            $payer['tax_info'] = $this->taxInfo()->toArray();
        }
        return $payer;
    }
}