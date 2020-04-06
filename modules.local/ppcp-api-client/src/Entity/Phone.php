<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;


class Phone
{

    private $nationalNumber;
    public function __construct(string $nationalNumber)
    {
        $this->nationalNumber = $nationalNumber;
    }

    public function nationalNumber() : string
    {
        return $this->nationalNumber;
    }

    public function toArray() : array
    {
        return [
            'national_number' => $this->nationalNumber(),
        ];
    }
}