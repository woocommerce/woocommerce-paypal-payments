<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

class PayerName
{

    private $givenName;
    private $surname;

    public function __construct(
        string $givenName,
        string $surname
    ) {

        $this->givenName = $givenName;
        $this->surname = $surname;
    }

    public function givenName() : string
    {
        return $this->givenName;
    }

    public function surname(): string
    {
        return $this->surname;
    }

    public function toArray() : array
    {
        return [
            'given_name' => $this->givenName(),
            'surname' => $this->surname(),
        ];
    }
}
