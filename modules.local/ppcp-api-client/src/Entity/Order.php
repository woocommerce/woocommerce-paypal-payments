<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

class Order
{

    private $id;
    private $createTime;
    private $purchaseUnits;
    private $payer;
    private $orderStatus;
    private $intent;
    private $updateTime;
    private $applicationContext;
    private $paymentSource;

    /**
     * Order constructor.
     *
     * @see https://developer.paypal.com/docs/api/orders/v2/#orders-create-response
     */
    public function __construct(
        string $id,
        array $purchaseUnits,
        OrderStatus $orderStatus,
        ApplicationContext $applicationContext = null,
        PaymentSource $paymentSource = null,
        Payer $payer = null,
        string $intent = 'CAPTURE',
        \DateTime $createTime = null,
        \DateTime $updateTime = null
    ) {

        $this->id = $id;
        $this->applicationContext = $applicationContext;
        //phpcs:disable Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType
        $this->purchaseUnits = array_values(array_filter(
            $purchaseUnits,
            static function ($unit): bool {
                return is_a($unit, PurchaseUnit::class);
            }
        ));
        //phpcs:enable Inpsyde.CodeQuality.ArgumentTypeDeclaration.NoArgumentType
        $this->payer = $payer;
        $this->orderStatus = $orderStatus;
        $this->intent = ($intent === 'CAPTURE') ? 'CAPTURE' : 'AUTHORIZE';
        $this->purchaseUnits = $purchaseUnits;
        $this->createTime = $createTime;
        $this->updateTime = $updateTime;
        $this->paymentSource = $paymentSource;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function createTime(): ?\DateTime
    {
        return $this->createTime;
    }

    public function updateTime(): ?\DateTime
    {
        return $this->updateTime;
    }

    public function intent(): string
    {
        return $this->intent;
    }

    public function payer(): ?Payer
    {
        return $this->payer;
    }

    /**
     * @return PurchaseUnit[]
     */
    public function purchaseUnits(): array
    {
        return $this->purchaseUnits;
    }

    public function status(): OrderStatus
    {
        return $this->orderStatus;
    }

    public function applicationContext(): ?ApplicationContext
    {

        return $this->applicationContext;
    }

    public function paymentSource(): ?PaymentSource
    {

        return $this->paymentSource;
    }

    public function toArray(): array
    {
        $order = [
            'id' => $this->id(),
            'intent' => $this->intent(),
            'status' => $this->status()->name(),
            'purchase_units' => array_map(
                static function (PurchaseUnit $unit): array {
                    return $unit->toArray();
                },
                $this->purchaseUnits()
            ),
        ];
        if ($this->createTime()) {
            $order['create_time'] = $this->createTime()->format(\DateTimeInterface::ISO8601);
        }
        if ($this->payer()) {
            $order['payer'] = $this->payer()->toArray();
        }
        if ($this->updateTime()) {
            $order['update_time'] = $this->updateTime()->format(\DateTimeInterface::ISO8601);
        }
        if ($this->applicationContext()) {
            $order['application_context'] = $this->applicationContext()->toArray();
        }
        if ($this->paymentSource()) {
            $order['payment_source'] = $this->paymentSource()->toArray();
        }

        return $order;
    }
}
