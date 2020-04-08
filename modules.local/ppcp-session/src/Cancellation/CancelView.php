<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Session\Cancellation;

class CancelView
{

    public function renderSessionCancelation(string $paramName, string $nonce)
    {
        echo '<a href="' . esc_url(add_query_arg([$paramName => $nonce], wc_get_checkout_url())) . '">' . esc_html('Cancel', 'woocommerce-paypal-commerce-gateway') . '</a>';
    }
}
