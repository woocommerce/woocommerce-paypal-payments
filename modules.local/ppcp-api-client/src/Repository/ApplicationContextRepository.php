<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Repository;

use Inpsyde\PayPalCommerce\ApiClient\Entity\ApplicationContext;
use Inpsyde\PayPalCommerce\WcGateway\Endpoint\ReturnUrlEndpoint;
use Psr\Container\ContainerInterface;

class ApplicationContextRepository
{

    private $settings;
    public function __construct(ContainerInterface $settings)
    {
        $this->settings = $settings;
    }

    public function currentContext(
        string $shippingPreference = ApplicationContext::SHIPPING_PREFERENCE_NO_SHIPPING
    ): ApplicationContext {

        $brandName = $this->settings->has('brand_name') ? $this->settings->get('brand_name') : '';
        // Todo: Put user_locale in container as well?
        $locale = str_replace('_', '-', get_user_locale());
        $landingpage = $this->settings->has('landing_page') ?
            $this->settings->get('landing_page') : ApplicationContext::LANDING_PAGE_NO_PREFERENCE;
        $context = new ApplicationContext(
            (string) home_url(\WC_AJAX::get_endpoint(ReturnUrlEndpoint::ENDPOINT)),
            (string) wc_get_checkout_url(),
            (string) $brandName,
            $locale,
            (string) $landingpage,
            $shippingPreference
        );
        return $context;
    }
}
