<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;

class PayerTaxInfo
{

    const VALID_TYPES = [
        'BR_CPF',
        'BR_CNPJ',
    ];
    private $taxId;
    private $type;
    public function __construct(
        string $taxId,
        string $type
    ) {

        if (! in_array($type, self::VALID_TYPES, true)) {
            throw new RuntimeException(sprintf(
                // translators: %s is the current type.
                __("%s is not a valid tax type.", "woocommerce-paypal-commerce-gateway"),
                $type
            ));
        }
        $this->taxId = $taxId;
        $this->type = $type;
    }

    public function type() : string
    {
        return $this->type;
    }

    public function taxId() : string
    {
        return $this->taxId;
    }

    public function toArray() : array
    {
        return [
            'tax_id' => $this->taxId(),
            'tax_id_type' => $this->type(),
        ];
    }
}
