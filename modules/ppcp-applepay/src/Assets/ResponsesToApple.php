<?php

/**
 * Prepares the data for the response to Apple.
 *
 * @package WooCommerce\PayPalCommerce\Applepay
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Applepay\Assets;

/**
 * Class ResponsesToApple
 */
class ResponsesToApple
{
    /**
     * Returns the authorization response with according success/fail status
     * Adds the error list if provided to be handled by the script
     * On success it adds the redirection url
     *
     * @param int    $status 0 => success, 1 => error.
     * @param string $order_id The order ID.
     * @param array  $error_list [['errorCode'=>required, 'contactField'=>'']].
     * @param string $return_url The URL to redirect to.
     *
     * @return array
     */
    public function authorization_result_response($status, $order_id = '', $error_list = array(), $return_url = '')
    {
        $response = array();
        if ($status === 'STATUS_SUCCESS') {
            $response['returnUrl'] = $return_url;
            $response['responseToApple'] = array('status' => 0);
        } else {
            $response = array('status' => 1, 'errors' => $this->apple_pay_error($error_list));
        }
        return $response;
    }
    /**
     * Returns an error response to be handled by the script
     *
     * @param array $error_list [['errorCode'=>required, 'contactField'=>'']].
     * @return void
     */
    public function response_with_data_errors($error_list)
    {
        $response = array();
        $response['errors'] = $this->apple_pay_error($error_list);
        $response['newTotal'] = $this->apple_new_total_response(0, 'pending');
        wp_send_json_error($response);
    }
    /**
     * Creates a response formatted for ApplePay
     *
     * @param array $payment_details Payment details.
     * @return array
     */
    public function apple_formatted_response(array $payment_details)
    {
        $response = array();
        if ($payment_details['shippingMethods']) {
            $response['newShippingMethods'] = $payment_details['shippingMethods'];
        }
        $response['newLineItems'] = $this->apple_new_line_items_response($payment_details);
        $response['newTotal'] = $this->apple_new_total_response($payment_details['total']);
        return $response;
    }
    /**
     * Returns a success response to be handled by the script
     *
     * @param array $response Response to send.
     */
    public function response_success(array $response): void
    {
        wp_send_json_success($response);
    }
    /**
     * Creates an array of errors formatted
     *
     * @param array $error_list List of errors.
     * @param array $errors (optional).
     *
     * @return array
     */
    protected function apple_pay_error($error_list, $errors = array())
    {
        foreach ($error_list as $error) {
            $errors[] = array('code' => $error['errorCode'], 'contactField' => $error['contactField'] ?? null, 'message' => array_key_exists('contactField', $error) ? sprintf('Missing %s', $error['contactField']) : '');
        }
        return $errors;
    }
    /**
     * Creates NewTotals line
     *
     * @param float  $total Total amount.
     * @param string $type final or pending.
     *
     * @return array
     */
    protected function apple_new_total_response($total, string $type = 'final'): array
    {
        return $this->apple_item_format(get_bloginfo('name'), $total, $type);
    }
    /**
     * Creates item line
     *
     * @param string $subtotal_label Subtotal label.
     * @param float  $subtotal Subtotal amount.
     * @param string $type final or pending.
     *
     * @return array
     */
    protected function apple_item_format($subtotal_label, $subtotal, $type): array
    {
        return array('label' => $subtotal_label, 'amount' => $subtotal, 'type' => $type);
    }
    /**
     * Creates NewLineItems line
     *
     * @param array $payment_details Payment details.
     * @return array[]
     */
    protected function apple_new_line_items_response(array $payment_details): array
    {
        $type = 'final';
        $response = array();
        $response[] = $this->apple_item_format('Subtotal', round(floatval($payment_details['subtotal']), 2), $type);
        if ($payment_details['shipping']['amount']) {
            $response[] = $this->apple_item_format($payment_details['shipping']['label'] ?: '', round(floatval($payment_details['shipping']['amount']), 2), $type);
        }
        $isset_fee_amount = isset($payment_details['fee']) && isset($payment_details['fee']['amount']);
        if ($isset_fee_amount) {
            $response[] = $this->apple_item_format($payment_details['fee']['label'] ?: '', round(floatval($payment_details['fee']['amount']), 2), $type);
        }
        $response[] = $this->apple_item_format('Estimated Tax', round(floatval($payment_details['taxes']), 2), $type);
        return $response;
    }
}
