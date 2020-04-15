<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

class Payments
{
    private $authorizations;

    /**
     * @var Authorization[] $authorizations
     */
    public function __construct(array $authorizations)
    {
        $this->authorizations = $authorizations;
    }

    public function toArray(): array
    {
        return [
            'authorizations' => array_map(
                function (Authorization $authorization) {
                    return $authorization->toArray();
                },
                $this->authorizations()
            )
        ];
    }

    /**
     * @return Authorization[]
     **/
    public function authorizations(): array
    {
        return $this->authorizations;
    }
}
