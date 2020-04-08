<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Session\Cancellation;

use Inpsyde\PayPalCommerce\Session\SessionHandler;

class CancelController
{

    private $sessionHandler;
    private $view;
    public function __construct(
        SessionHandler $sessionHandler,
        CancelView $view
    ) {

        $this->view = $view;
        $this->sessionHandler = $sessionHandler;
    }

    public function run()
    {
        $paramName = 'ppcp-cancel';
        $nonce = 'ppcp-cancel-' . get_current_user_id();
        if (isset($_GET[$paramName]) && // Input var ok.
            wp_verify_nonce(
                sanitize_text_field(wp_unslash($_GET[$paramName])), // Input var ok.
                $nonce
            )
        ) { // Input var ok.
            $this->sessionHandler->cancelOrder();
        }
        if (! $this->sessionHandler->order()) {
            return;
        }

        $url = add_query_arg([$paramName => wp_create_nonce($nonce)], wc_get_checkout_url());
        add_action(
            'woocommerce_review_order_after_submit',
            function () use ($url) {
                $this->view->renderSessionCancelation($url);
            }
        );
    }
}
