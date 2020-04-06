<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

class Shipping
{

    private $name;
    private $address;
    public function __construct(string $name, Address $address)
    {
        $this->name = $name;
        $this->address = $address;
    }

    public function name() : string
    {
        return $this->name;
    }

    public function address() : Address
    {
        return $this->address;
    }

    public function toArray() : array
    {
        return [
            'name' => [
                'full_name' => $this->name(),
            ],
            'address' => $this->address()->toArray(),
        ];
    }
}
