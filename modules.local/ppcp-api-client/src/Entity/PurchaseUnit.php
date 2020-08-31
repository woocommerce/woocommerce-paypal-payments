<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

//phpcs:disable Inpsyde.CodeQuality.PropertyPerClassLimit.TooManyProperties
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
    private $payments;

    /**
     * @var bool
     */
    private $containsPhysicalGoodsItems = false;

    public function __construct(
        Amount $amount,
        array $items = [],
        Shipping $shipping = null,
        string $referenceId = 'default',
        string $description = '',
        Payee $payee = null,
        string $customId = '',
        string $invoiceId = '',
        string $softDescriptor = '',
        Payments $payments = null
    ) {

        $this->amount = $amount;
        $this->shipping = $shipping;
        $this->referenceId = $referenceId;
        $this->description = $description;
        //phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType
        $this->items = array_values(array_filter(
            $items,
            function ($item): bool {
                $isItem = is_a($item, Item::class);
                /**
                 * @var Item $item
                 */
                if ($isItem && Item::PHYSICAL_GOODS === $item->category()) {
                    $this->containsPhysicalGoodsItems = true;
                }

                return $isItem;
            }
        ));
        //phpcs:enable Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType
        $this->payee = $payee;
        $this->customId = $customId;
        $this->invoiceId = $invoiceId;
        $this->softDescriptor = $softDescriptor;
        $this->payments = $payments;
    }

    public function amount(): Amount
    {
        return $this->amount;
    }

    public function shipping(): ?Shipping
    {
        return $this->shipping;
    }

    public function referenceId(): string
    {
        return $this->referenceId;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function customId(): string
    {
        return $this->customId;
    }

    public function invoiceId(): string
    {
        return $this->invoiceId;
    }

    public function softDescriptor(): string
    {
        return $this->softDescriptor;
    }

    public function payee(): ?Payee
    {
        return $this->payee;
    }

    public function payments(): ?Payments
    {
        return $this->payments;
    }

    /**
     * @return Item[]
     */
    public function items(): array
    {
        return $this->items;
    }

    public function containsPhysicalGoodsItems(): bool
    {
        return $this->containsPhysicalGoodsItems;
    }

    public function toArray(): array
    {
        $purchaseUnit = [
            'reference_id' => $this->referenceId(),
            'amount' => $this->amount()->toArray(),
            'description' => $this->description(),
            'items' => array_map(
                static function (Item $item): array {
                    return $item->toArray();
                },
                $this->items()
            ),
        ];
        if ($this->ditchItemsWhenMismatch($this->amount(), ...$this->items())) {
            unset($purchaseUnit['items']);
            unset($purchaseUnit['amount']['breakdown']);
        }

        if ($this->payee()) {
            $purchaseUnit['payee'] = $this->payee()->toArray();
        }

        if ($this->payments()) {
            $purchaseUnit['payments'] = $this->payments()->toArray();
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

    /**
     * All money values send to PayPal can only have 2 decimal points. Woocommerce internally does
     * not have this restriction. Therefore the totals of the cart in Woocommerce and the totals
     * of the rounded money values of the items, we send to PayPal, can differ. In those cases,
     * we can not send the line items.
     *
     * @param Amount $amount
     * @param Item ...$items
     * @return bool
     */
    //phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh
    private function ditchItemsWhenMismatch(Amount $amount, Item ...$items): bool
    {
        $feeItemsTotal = ($amount->breakdown() && $amount->breakdown()->itemTotal()) ?
            $amount->breakdown()->itemTotal()->value() : null;
        $feeTaxTotal = ($amount->breakdown() && $amount->breakdown()->taxTotal()) ?
            $amount->breakdown()->taxTotal()->value() : null;

        foreach ($items as $item) {
            if (null !== $feeItemsTotal) {
                $feeItemsTotal -= $item->unitAmount()->value() * $item->quantity();
            }
            if (null !== $feeTaxTotal) {
                $feeTaxTotal -= $item->tax()->value() * $item->quantity();
            }
        }

        $feeItemsTotal = round($feeItemsTotal, 2);
        $feeTaxTotal = round($feeTaxTotal, 2);

        if ($feeItemsTotal !== 0.0 || $feeTaxTotal !== 0.0) {
            return true;
        }

        $breakdown = $this->amount()->breakdown();
        if (! $breakdown) {
            return false;
        }
        $amountTotal = 0;
        if ($breakdown->shipping()) {
            $amountTotal += $breakdown->shipping()->value();
        }
        if ($breakdown->itemTotal()) {
            $amountTotal += $breakdown->itemTotal()->value();
        }
        if ($breakdown->discount()) {
            $amountTotal -= $breakdown->discount()->value();
        }
        if ($breakdown->taxTotal()) {
            $amountTotal += $breakdown->taxTotal()->value();
        }
        if ($breakdown->shippingDiscount()) {
            $amountTotal -= $breakdown->shippingDiscount()->value();
        }
        if ($breakdown->handling()) {
            $amountTotal += $breakdown->handling()->value();
        }
        if ($breakdown->insurance()) {
            $amountTotal += $breakdown->insurance()->value();
        }

        $amountValue = $this->amount()->value();
        $needsToDitch = (string) $amountTotal !== (string) $amountValue;
        return $needsToDitch;
    }
}
