<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Session\Cancellation;

class CancelView
{

    public function renderSessionCancelation(string $url)
    {
        ?>
        <p id="ppcp-cancel"
            class="has-text-align-center ppcp-cancel"
        >
            <?php
            printf(
                    // translators: the placeholders are html tags for a link
                    esc_html__(
                            'You are currently paying with PayPal. If you want to cancel
                            this process, please click %1$shere%2$s.',
                            'woocommerce-paypal-commerce-gateway'
                    ),
                '<a href="'. esc_url($url) . '">',
                '</a>'
            );
            ?>
        </p>
        <?php
    }
}
