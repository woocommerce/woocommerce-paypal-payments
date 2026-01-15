<?php

/**
 * The process_payment functionality for the both gateways.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Gateway;

use Exception;
use Throwable;
use WC_Order;
use WooCommerce\PayPalCommerce\WcGateway\Exception\GatewayGenericException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
/**
 * Trait ProcessPaymentTrait
 */
trait ProcessPaymentTrait
{
    /**
     * Handles the payment failure.
     *
     * @param WC_Order|null $wc_order The order.
     * @param Exception     $error The error causing the failure.
     * @return array The data that can be returned by the gateway process_payment method.
     */
    protected function handle_payment_failure(?WC_Order $wc_order, Exception $error): array
    {
        $this->logger->error('Payment failed: ' . $this->format_exception($error));
        if ($wc_order) {
            $wc_order->update_status('failed', $this->format_exception($error));
            if (WC()->session->get('ppcp_delete_wc_order_on_payment_failure') ?? \false) {
                $wc_order->delete(\true);
            }
        }
        $this->session_handler->destroy_session_data();
        WC()->session->set('ppcp_subscription_id', '');
        WC()->session->set('ppcp_delete_wc_order_on_payment_failure', \false);
        wc_add_notice($error->getMessage(), 'error');
        return array('result' => 'failure', 'redirect' => wc_get_checkout_url(), 'errorMessage' => $error->getMessage());
    }
    /**
     * Handles the payment completion.
     *
     * @param WC_Order|null $wc_order The order.
     * @param string|null   $url The redirect URL.
     * @return array The data that can be returned by the gateway process_payment method.
     */
    protected function handle_payment_success(?WC_Order $wc_order, ?string $url = null): array
    {
        if (!$url) {
            $url = $this->get_return_url($wc_order);
        }
        $this->session_handler->destroy_session_data();
        WC()->session->set('ppcp_subscription_id', '');
        return array('result' => 'success', 'redirect' => $url);
    }
    /**
     * Outputs the exception, including the inner exception.
     *
     * @param Throwable $exception The exception to format.
     * @return string
     */
    protected function format_exception(Throwable $exception): string
    {
        $message = $exception->getMessage();
        if (is_a($exception, PayPalApiException::class)) {
            $message = $exception->get_details($message);
        }
        $output = $message . ' ' . basename($exception->getFile()) . ':' . $exception->getLine();
        $prev = $exception->getPrevious();
        if (!$prev) {
            return $output;
        }
        if ($exception instanceof GatewayGenericException) {
            $output = '';
        }
        return $output . ' ' . $this->format_exception($prev);
    }
}
