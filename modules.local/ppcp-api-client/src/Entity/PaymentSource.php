<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

class PaymentSource
{

    private $card;
    private $wallet;
    public function __construct(
        PaymentSourceCard $card = null,
        PaymentSourceWallet $wallet = null
    ) {

        $this->card = $card;
        $this->wallet = $wallet;
    }

    public function card(): ?PaymentSourceCard
    {

        return $this->card;
    }

    public function wallet(): ?PaymentSourceWallet
    {

        return $this->wallet;
    }

    public function toArray(): array
    {

        $data = [];
        if ($this->card()) {
            $data['card'] = $this->card()->toArray();
        }
        if ($this->wallet()) {
            $data['wallet'] = $this->wallet()->toArray();
        }
        return $data;
    }
}
