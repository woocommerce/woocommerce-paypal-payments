<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;

use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;

class ApplicationContext
{
    public const LANDING_PAGE_LOGIN = 'LOGIN';
    public const LANDING_PAGE_BILLING = 'BILLING';
    public const LANDING_PAGE_NO_PREFERENCE = 'NO_PREFERENCE';
    private const VALID_LANDING_PAGE_VALUES = [
        self::LANDING_PAGE_LOGIN,
        self::LANDING_PAGE_BILLING,
        self::LANDING_PAGE_NO_PREFERENCE,
    ];

    public const SHIPPING_PREFERENCE_GET_FROM_FILE = 'GET_FROM_FILE';
    public const SHIPPING_PREFERENCE_NO_SHIPPING = 'NO_SHIPPING';
    public const SHIPPING_PREFERENCE_SET_PROVIDED_ADDRESS = 'SET_PROVIDED_ADDRESS';
    private const VALID_SHIPPING_PREFERENCE_VALUES = [
        self::SHIPPING_PREFERENCE_GET_FROM_FILE,
        self::SHIPPING_PREFERENCE_NO_SHIPPING,
        self::SHIPPING_PREFERENCE_SET_PROVIDED_ADDRESS,
    ];

    public const USER_ACTION_CONTINUE = 'CONTINUE';
    public const USER_ACTION_PAY_NOW = 'PAY_NOW';
    private const VALID_USER_ACTION_VALUES = [
        self::USER_ACTION_CONTINUE,
        self::USER_ACTION_PAY_NOW,
    ];

    private $brandName;
    private $locale;
    private $landingPage;
    private $shippingPreference;
    private $userAction;
    private $returnUrl;
    private $cancelUrl;
    private $paymentMethod;

    public function __construct(
        string $returnUrl = '',
        string $cancelUrl = '',
        string $brandName = '',
        string $locale = '',
        string $landingPage = self::LANDING_PAGE_NO_PREFERENCE,
        string $shippingPreference = self::SHIPPING_PREFERENCE_NO_SHIPPING,
        string $userAction = self::USER_ACTION_CONTINUE
    ) {

        if (! in_array($landingPage, self::VALID_LANDING_PAGE_VALUES, true)) {
            throw new RuntimeException("Landingpage not correct");
        }
        if (! in_array($shippingPreference, self::VALID_SHIPPING_PREFERENCE_VALUES, true)) {
            throw new RuntimeException("Shipping preference not correct");
        }
        if (! in_array($userAction, self::VALID_USER_ACTION_VALUES, true)) {
            throw new RuntimeException("User action preference not correct");
        }
        $this->returnUrl = $returnUrl;
        $this->cancelUrl = $cancelUrl;
        $this->brandName = $brandName;
        $this->locale = $locale;
        $this->landingPage = $landingPage;
        $this->shippingPreference = $shippingPreference;
        $this->userAction = $userAction;

        //Currently we have not implemented the payment method.
        $this->paymentMethod = null;
    }

    public function brandName(): string
    {
        return $this->brandName;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function landingPage(): string
    {
        return $this->landingPage;
    }

    public function shippingPreference(): string
    {
        return $this->shippingPreference;
    }

    public function userAction(): string
    {
        return $this->userAction;
    }

    public function returnUrl(): string
    {
        return $this->returnUrl;
    }

    public function cancelUrl(): string
    {
        return $this->cancelUrl;
    }

    /**
     * Currently, we have not implemented this.
     *
     * If we would follow our schema, we would create a paymentMethod entity which could
     * get returned here.
     */
    public function paymentMethod(): ?\stdClass
    {
        return $this->paymentMethod;
    }

    public function toArray(): array
    {
        $data = [];
        if ($this->userAction()) {
            $data['user_action'] = $this->userAction();
        }
        if ($this->paymentMethod()) {
            $data['payment_method'] = $this->paymentMethod();
        }
        if ($this->shippingPreference()) {
            $data['shipping_preference'] = $this->shippingPreference();
        }
        if ($this->landingPage()) {
            $data['landing_page'] = $this->landingPage();
        }
        if ($this->locale()) {
            $data['locale'] = $this->locale();
        }
        if ($this->brandName()) {
            $data['brand_name'] = $this->brandName();
        }
        if ($this->returnUrl()) {
            $data['return_url'] = $this->returnUrl();
        }
        if ($this->cancelUrl()) {
            $data['cancel_url'] = $this->cancelUrl();
        }
        if ($this->paymentMethod()) {
            $data['payment_method'] = $this->paymentMethod();
        }
        return $data;
    }
}
