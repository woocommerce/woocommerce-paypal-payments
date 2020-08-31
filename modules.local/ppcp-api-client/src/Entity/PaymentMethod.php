<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

class PaymentMethod
{

    public const PAYER_SELECTED_DEFAULT = 'PAYPAL';

    public const PAYEE_PREFERRED_UNRESTRICTED = 'UNRESTRICTED';
    public const PAYEE_PREFERRED_IMMEDIATE_PAYMENT_REQUIRED = 'IMMEDIATE_PAYMENT_REQUIRED';

    private $preferred;
    private $selected;
    public function __construct(
        string $preferred = self::PAYEE_PREFERRED_UNRESTRICTED,
        string $selected = self::PAYER_SELECTED_DEFAULT
    ) {

        $this->preferred = $preferred;
        $this->selected = $selected;
    }

    public function payeePreferred(): string
    {
        return $this->preferred;
    }

    public function payerSelected(): string
    {
        return $this->selected;
    }

    public function toArray(): array
    {
        return [
            'payee_preferred' => $this->payeePreferred(),
            'payer_selected' => $this->payerSelected(),
        ];
    }
}
