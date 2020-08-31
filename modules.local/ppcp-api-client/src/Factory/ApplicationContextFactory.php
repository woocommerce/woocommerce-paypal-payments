<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Factory;

use Inpsyde\PayPalCommerce\ApiClient\Entity\ApplicationContext;

class ApplicationContextFactory
{

    public function fromPayPalResponse(\stdClass $data): ApplicationContext
    {
        return new ApplicationContext(
            isset($data->return_url) ?
                $data->return_url : '',
            isset($data->cancel_url) ?
                $data->cancel_url : '',
            isset($data->brand_name) ?
                $data->brand_name : '',
            isset($data->locale) ?
                $data->locale : '',
            isset($data->landing_page) ?
                $data->landing_page : ApplicationContext::LANDING_PAGE_NO_PREFERENCE,
            isset($data->shipping_preference) ?
                $data->shipping_preference : ApplicationContext::SHIPPING_PREFERENCE_GET_FROM_FILE,
            isset($data->user_action) ?
                $data->user_action : ApplicationContext::USER_ACTION_CONTINUE,
        );
    }
}
