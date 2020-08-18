<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Checkout;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Shipping;
use Inpsyde\PayPalCommerce\Session\SessionHandler;

/**
 * Service that fills checkout address fields
 * with address selected via PayPal
 */
class CheckoutPayPalAddressPreset
{

    private $shippingCache = [];

    /**
     * @var SessionHandler
     */
    private $sessionHandler;

    /**
     * @param SessionHandler $sessionHandler
     */
    public function __construct(SessionHandler $sessionHandler)
    {
        $this->sessionHandler = $sessionHandler;
    }

    /**
     * @wp-hook woocommerce_checkout_get_value
     * @param string|null
     * @param string $fieldId
     *
     * @return string|null
     */
    public function filterCheckoutFiled($defaultValue, $fieldId): ?string
    {
        if(! is_string($defaultValue)) {
            $defaultValue = null;
        }

        if(!is_string($fieldId)) {
            return $defaultValue;
        }

        return $this->readPresetForField($fieldId) ?? $defaultValue;
    }

    private function readPresetForField(string $fieldId): ?string
    {
        if(!$order = $this->sessionHandler->order()) {
            return null;
        }

        $shipping = $this->readShippingFromOrder();
        $payer = $order->payer();

        $addressMap = [
            'billing_address_1' => 'addressLine1',
            'billing_address_2' => 'addressLine2',
            'billing_postcode' => 'postalCode',
            'billing_country' => 'countryCode',
            'billing_city' => 'adminArea2',
            'billing_state' => 'adminArea1'
        ];
        $payerNameMap = [
            'billing_last_name' => 'surname',
            'billing_first_name' => 'givenName',
        ];
        $payerMap = [
            'billing_email' => 'emailAddress',
        ];
        $payerPhoneMap = [
            'billing_phone' => 'nationalNumber',
        ];

        if(array_key_exists($fieldId, $addressMap) && $shipping) {
            return $shipping->address()->{$addressMap[$fieldId]}() ?: null;
        }

        if(array_key_exists($fieldId, $payerNameMap) && $payer) {
            return $payer->name()->{$payerNameMap[$fieldId]}() ?: null;
        }

        if(array_key_exists($fieldId, $payerMap) && $payer) {
            return $payer->{$payerMap[$fieldId]}() ?: null;
        }

        if(array_key_exists($fieldId, $payerPhoneMap) && $payer && $payer->phone() && $payer->phone()->phone()) {
            return $payer->phone()->phone()->{$payerPhoneMap[$fieldId]}() ?: null;
        }

        return null;
    }

    private function readShippingFromOrder(): ?Shipping
    {
        if(!$order = $this->sessionHandler->order()) {
            return null;
        }

        if(array_key_exists($order->id(), $this->shippingCache)) {
            return $this->shippingCache[$order->id()];
        }

        $shipping = null;
        foreach($this->sessionHandler->order()->purchaseUnits() as $unit) {
            if($shipping = $unit->shipping()) {
                break;
            }
        }

        $this->shippingCache[$order->id()] = $shipping;

        return $shipping;
    }
}
