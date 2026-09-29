<?php

/**
 * Controls the cancel mechanism to step out of the PayPal order session.
 *
 * @package WooCommerce\PayPalCommerce\Session\Cancellation
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Session\Cancellation;

use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
/**
 * Class CancelController
 */
class CancelController
{
    public const NONCE = 'ppcp-cancel';
    /**
     * @var Context Context data provider.
     */
    private Context $context;
    /**
     * The Session handler.
     *
     * @var SessionHandler
     */
    private $session_handler;
    /**
     * The view.
     *
     * @var CancelView
     */
    private $view;
    /**
     * CancelController constructor.
     *
     * @param SessionHandler $session_handler The session handler.
     * @param CancelView     $view The view object.
     * @param Context        $context Context data provider.
     */
    public function __construct(SessionHandler $session_handler, \WooCommerce\PayPalCommerce\Session\Cancellation\CancelView $view, Context $context)
    {
        $this->view = $view;
        $this->session_handler = $session_handler;
        $this->context = $context;
    }
    /**
     * Runs the controller.
     */
    public function run(): void
    {
        $param_name = self::NONCE;
        if (isset($_GET[$param_name]) && wp_verify_nonce(
            sanitize_text_field(wp_unslash($_GET[$param_name])),
            // Input var ok.
            self::NONCE
        )) {
            // Input var ok.
            $this->session_handler->destroy_session_data();
        }
        if (!$this->context->is_paypal_continuation()) {
            return;
        }
        $url = add_query_arg(array($param_name => wp_create_nonce(self::NONCE)), wc_get_checkout_url());
        add_action('woocommerce_review_order_after_submit', function () use ($url) {
            // phpcs:ignore WordPress.Security.EscapeOutput
            echo $this->view->render_session_cancellation($url, $this->session_handler->funding_source());
        });
    }
}
