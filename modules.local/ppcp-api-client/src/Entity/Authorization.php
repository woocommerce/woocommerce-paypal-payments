<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

class Authorization
{
    private $id;
    private $authorizationStatus;

    public function __construct(
        string $id,
        AuthorizationStatus $authorizationStatus
    ) {

        $this->id = $id;
        $this->authorizationStatus = $authorizationStatus;
    }

    public function id() : string
    {
        return $this->id;
    }

    public function status() : AuthorizationStatus
    {
        return $this->authorizationStatus;
    }
}
