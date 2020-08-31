<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Factory;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Item;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\ApiClient\Repository\PayeeRepository;

class PurchaseUnitFactory
{

    private $amountFactory;
    private $payeeRepository;
    private $payeeFactory;
    private $itemFactory;
    private $shippingFactory;
    private $paymentsFactory;
    private $prefix;

    public function __construct(
        AmountFactory $amountFactory,
        PayeeRepository $payeeRepository,
        PayeeFactory $payeeFactory,
        ItemFactory $itemFactory,
        ShippingFactory $shippingFactory,
        PaymentsFactory $paymentsFactory,
        string $prefix = 'WC-'
    ) {

        $this->amountFactory = $amountFactory;
        $this->payeeRepository = $payeeRepository;
        $this->payeeFactory = $payeeFactory;
        $this->itemFactory = $itemFactory;
        $this->shippingFactory = $shippingFactory;
        $this->paymentsFactory = $paymentsFactory;
        $this->prefix = $prefix;
    }

    public function fromWcOrder(\WC_Order $order): PurchaseUnit
    {
        $amount = $this->amountFactory->fromWcOrder($order);
        $items = $this->itemFactory->fromWcOrder($order);
        $shipping = $this->shippingFactory->fromWcOrder($order);
        if (
            empty($shipping->address()->countryCode()) ||
            ($shipping->address()->countryCode() && !$shipping->address()->postalCode())
        ) {
            $shipping = null;
        }
        $referenceId = 'default';
        $description = '';
        $payee = $this->payeeRepository->payee();
        $wcOrderId = $order->get_id();
        $customId = $this->prefix . $wcOrderId;
        $invoiceId = $this->prefix . $wcOrderId;
        $softDescriptor = '';
        $purchaseUnit = new PurchaseUnit(
            $amount,
            $items,
            $shipping,
            $referenceId,
            $description,
            $payee,
            $customId,
            $invoiceId,
            $softDescriptor
        );
        return $purchaseUnit;
    }

    public function fromWcCart(\WC_Cart $cart): PurchaseUnit
    {
        $amount = $this->amountFactory->fromWcCart($cart);
        $items = $this->itemFactory->fromWcCart($cart);

        $shipping = null;
        $customer = \WC()->customer;
        if (is_a($customer, \WC_Customer::class)) {
            $shipping = $this->shippingFactory->fromWcCustomer(\WC()->customer);
            if (
                ! $shipping->address()->countryCode()
                || ($shipping->address()->countryCode() && !$shipping->address()->postalCode())
            ) {
                $shipping = null;
            }
        }

        $referenceId = 'default';
        $description = '';

        $payee = $this->payeeRepository->payee();

        $customId = '';
        $invoiceId = '';
        $softDescriptor = '';
        $purchaseUnit = new PurchaseUnit(
            $amount,
            $items,
            $shipping,
            $referenceId,
            $description,
            $payee,
            $customId,
            $invoiceId,
            $softDescriptor
        );

        return $purchaseUnit;
    }

    public function fromPayPalResponse(\stdClass $data): PurchaseUnit
    {
        if (! isset($data->reference_id) || ! is_string($data->reference_id)) {
            throw new RuntimeException(
                __("No reference ID given.", "woocommercepaypal-commerce-gateway")
            );
        }

        $amount = $this->amountFactory->fromPayPalResponse($data->amount);
        $description = (isset($data->description)) ? $data->description : '';
        $customId = (isset($data->custom_id)) ? $data->custom_id : '';
        $invoiceId = (isset($data->invoice_id)) ? $data->invoice_id : '';
        $softDescriptor = (isset($data->soft_descriptor)) ? $data->soft_descriptor : '';
        $items = [];
        if (isset($data->items) && is_array($data->items)) {
            $items = array_map(
                function (\stdClass $item): Item {
                    return $this->itemFactory->fromPayPalResponse($item);
                },
                $data->items
            );
        }
        $payee = isset($data->payee) ? $this->payeeFactory->fromPayPalResponse($data->payee) : null;
        $shipping = null;
        try {
            if (isset($data->shipping)) {
                $shipping = $this->shippingFactory->fromPayPalResponse($data->shipping);
            }
        } catch (RuntimeException $error) {
        }
        $payments = null;
        try {
            if (isset($data->payments)) {
                $payments = $this->paymentsFactory->fromPayPalResponse($data->payments);
            }
        } catch (RuntimeException $error) {
        }

        $purchaseUnit = new PurchaseUnit(
            $amount,
            $items,
            $shipping,
            $data->reference_id,
            $description,
            $payee,
            $customId,
            $invoiceId,
            $softDescriptor,
            $payments
        );
        return $purchaseUnit;
    }
}
