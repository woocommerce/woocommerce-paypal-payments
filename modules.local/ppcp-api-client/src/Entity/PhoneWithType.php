<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

class PhoneWithType
{
    public const VALLID_TYPES = [
        'FAX',
        'HOME',
        'MOBILE',
        'OTHER',
        'PAGER',
    ];

    private $type;
    private $phone;
    public function __construct(string $type, Phone $phone)
    {
        $this->type = in_array($type, self::VALLID_TYPES, true) ? $type : 'OTHER';
        $this->phone = $phone;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function phone(): Phone
    {
        return $this->phone;
    }

    public function toArray(): array
    {
        return [
            'phone_type' => $this->type(),
            'phone_number' => $this->phone()->toArray(),
        ];
    }
}
