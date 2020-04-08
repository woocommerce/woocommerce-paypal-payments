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
            <a
                href="<?php echo esc_url($url); ?>"
            ><?php
                esc_html_e('Cancel', 'woocommerce-paypal-commerce-gateway');
                ?></a>
        </p>
        <?php
    }
}
