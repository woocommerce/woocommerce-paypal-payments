<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

class PurchaseUnit
{

    private $amount;
    private $items;
    private $shipping;
    private $referenceId;
    private $description;
    private $payee;
    private $customId;
    private $invoiceId;
    private $softDescriptor;
    public function __construct(
        Amount $amount,
        array $items = [],
        Shipping $shipping = null,
        string $referenceId = 'default',
        string $description = '',
        Payee $payee = null,
        string $customId = '',
        string $invoiceId = '',
        string $softDescriptor = ''
    ) {

        $this->amount = $amount;
        $this->shipping = $shipping;
        $this->referenceId = $referenceId;
        $this->description = $description;
        //phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType
        $this->items = array_values(
            array_filter(
                $items,
                function ($item) : bool {
                    return is_a($item, Item::class);
                }
            )
        );
        //phpcs:enable Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType
        $this->payee = $payee;
        $this->customId = $customId;
        $this->invoiceId = $invoiceId;
        $this->softDescriptor = $softDescriptor;
    }

    public function amount() : Amount
    {
        return $this->amount;
    }

    public function shipping() : ?Shipping
    {
        return $this->shipping;
    }

    public function referenceId() : string
    {
        return $this->referenceId;
    }

    public function description() : string
    {
        return $this->description;
    }

    public function customId() : string
    {
        return $this->customId;
    }

    public function invoiceId() : string
    {
        return $this->invoiceId;
    }

    public function softDescriptor() : string
    {
        return $this->softDescriptor;
    }

    public function payee() : ?Payee
    {
        return $this->payee;
    }

    /**
     * @return Item[]
     */
    public function items() : array
    {
        return $this->items;
    }

    public function toArray() : array
    {
        $purchaseUnit = [
            'reference_id' => $this->referenceId(),
            'amount' => $this->amount()->toArray(),
            'description' => $this->description(),
            'items' => array_map(
                function (Item $item) : array {
                    return $item->toArray();
                },
                $this->items()
            ),
        ];

        if ($this->payee()) {
            $purchaseUnit['payee'] = $this->payee()->toArray();
        }

        if ($this->shipping()) {
            $purchaseUnit['shipping'] = $this->shipping()->toArray();
        }
        if ($this->customId()) {
            $purchaseUnit['custom_id'] = $this->customId();
        }
        if ($this->invoiceId()) {
            $purchaseUnit['invoice_id'] = $this->invoiceId();
        }
        if ($this->softDescriptor()) {
            $purchaseUnit['soft_descriptor'] = $this->softDescriptor();
        }
        return $purchaseUnit;
    }
}
