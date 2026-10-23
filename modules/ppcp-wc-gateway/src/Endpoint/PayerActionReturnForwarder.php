<?php

/**
 * Forwards the return from a PayPal re-approval to the return URL endpoint.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Endpoint;

use WC_AJAX;
use WooCommerce\PayPalCommerce\Http\RedirectorInterface;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
/**
 * When a capture fails with PAYER_ACTION_REQUIRED or INSTRUMENT_DECLINED, PayPalGateway
 * sends the buyer to PayPal to approve the same order again. PayPal then returns the buyer
 * to the return URL of that order. For orders from the smart buttons, this is the page of
 * the button (see ReturnUrlFactory), which does not capture the order. This class forwards
 * that return to ReturnUrlEndpoint, which captures the order and completes the payment.
 *
 * Other flows also return to these pages with a token and resume in the browser, so only
 * the order that the session stored for re-approval is forwarded.
 */
class PayerActionReturnForwarder
{
    private SessionHandler $session_handler;
    private RedirectorInterface $redirector;
    public function __construct(SessionHandler $session_handler, RedirectorInterface $redirector)
    {
        $this->session_handler = $session_handler;
        $this->redirector = $redirector;
    }
    public function maybe_forward(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $token = wc_clean(wp_unslash($_GET['token'] ?? ''));
        $payer_id = wc_clean(wp_unslash($_GET['PayerID'] ?? ''));
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        // PayPal adds the PayerID only on approval. A canceled re-approval returns without it.
        if (!is_string($token) || $token === '' || !is_string($payer_id) || $payer_id === '') {
            return;
        }
        if ($token !== $this->session_handler->payer_action_order_id()) {
            return;
        }
        $this->redirector->redirect(add_query_arg(array('token' => $token, 'PayerID' => $payer_id), home_url(WC_AJAX::get_endpoint(\WooCommerce\PayPalCommerce\WcGateway\Endpoint\ReturnUrlEndpoint::ENDPOINT))));
    }
}
