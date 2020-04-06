<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;


class Address
{
    private $countryCode;
    private $addressLine1;
    private $addressLine2;
    private $adminArea1;
    private $adminArea2;
    private $postalCode;
    public function __construct(
        string $countryCode,
        string $addressLine1 = '',
        string $addressLine2 = '',
        string $adminArea1 = '',
        string $adminArea2 = '',
        string $postalCode = ''
    )
    {
        $this->countryCode = $countryCode;
        $this->addressLine1 = $addressLine1;
        $this->addressLine2 = $addressLine2;
        $this->adminArea1 = $adminArea1;
        $this->adminArea2 = $adminArea2;
        $this->postalCode = $postalCode;
    }

    public function countryCode() : string {
        return $this->countryCode;
    }
    public function addressLine1() : string {
        return $this->addressLine1;
    }
    public function addressLine2() : string {
        return $this->addressLine2;
    }
    public function adminArea1() : string {
        return $this->adminArea1;
    }
    public function adminArea2() : string {
        return $this->adminArea2;
    }
    public function postalCode() : string {
        return $this->postalCode;
    }

    public function toArray() : array
    {
        return [
            'country_code' => $this->countryCode(),
            'address_line_1' => $this->addressLine1(),
            'address_line_2' => $this->addressLine2(),
            'admin_area_1' => $this->adminArea1(),
            'admin_area_2' => $this->adminArea2(),
            'postal_code' => $this->postalCode(),
        ];
    }
}