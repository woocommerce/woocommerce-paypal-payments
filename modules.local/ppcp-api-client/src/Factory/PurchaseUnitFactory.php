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
    public function __construct(
        AmountFactory $amountFactory,
        PayeeRepository $payeeRepository,
        PayeeFactory $payeeFactory,
        ItemFactory $itemFactory,
        ShippingFactory $shippingFactory
    ) {

        $this->amountFactory = $amountFactory;
        $this->payeeRepository = $payeeRepository;
        $this->payeeFactory = $payeeFactory;
        $this->itemFactory = $itemFactory;
        $this->shippingFactory = $shippingFactory;
    }

    public function fromWcOrder(\WC_Order $order) : PurchaseUnit
    {
        $amount = $this->amountFactory->fromWcOrder($order);
        $items = $this->itemFactory->fromWcOrder($order);
        $shipping = $this->shippingFactory->fromWcOrder($order);
        if (empty($shipping->address()->countryCode()) ||
            ($shipping->address()->countryCode() && !$shipping->address()->postalCode())
        ) {
            $shipping = null;
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

    public function fromWcCart(\WC_Cart $cart) : PurchaseUnit
    {
        $amount = $this->amountFactory->fromWcCart($cart);
        $items = $this->itemFactory->fromWcCart($cart);

        /**
         * // ToDo:
         * When we send a shipping information while creating the order, this does
         * currently not mean, this address will be shown as default.
         *
         * Maybe discuss.
         */
        $shipping = null;
        $customer = \WC()->customer;
        if (is_a($customer, \WC_Customer::class)) {
            $shipping = $this->shippingFactory->fromWcCustomer(\WC()->customer);
            if (! $shipping->address()->countryCode() || ($shipping->address()->countryCode() && !$shipping->address()->postalCode())) {
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

    public function fromPayPalResponse(\stdClass $data) : PurchaseUnit
    {
        if (! isset($data->reference_id) || ! is_string($data->reference_id)) {
            throw new RuntimeException(__("No reference ID given.", "woocommercepaypal-commerce-gateway"));
        }

        $amount = $this->amountFactory->fromPayPalResponse($data->amount);
        $description = (isset($data->description)) ? $data->description : '';
        $customId = (isset($data->customId)) ? $data->customId : '';
        $invoiceId = (isset($data->invoiceId)) ? $data->invoiceId : '';
        $softDescriptor = (isset($data->softDescriptor)) ? $data->softDescriptor : '';
        $items = [];
        if (isset($data->items) && is_array($data->items)) {
            $items = array_map(
                function (\stdClass $item) : Item {
                    return $this->itemFactory->fromPayPalRequest($item);
                },
                $data->items
            );
        }
        $payee = isset($data->payee) ? $this->payeeFactory->fromPayPalResponse($data->payee) : null;
        $shipping = isset($data->shipping) ?
            $this->shippingFactory->fromPayPalResponse($data->shipping)
            : null;
        return new PurchaseUnit(
            $amount,
            $items,
            $shipping,
            $data->reference_id,
            $description,
            $payee,
            $customId,
            $invoiceId,
            $softDescriptor
        );
    }
}
