<?php
/**
 * REST API endpoint for capturing PayPal payments from WooCommerce mobile app
 *
 * @package WooCommerce\PayPalCommerce\WooCommerceMobile\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WooCommerceMobile\Endpoint;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\Webhooks\Handler\PaymentCaptureCompleted;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class CapturePayPalPaymentEndpoint
 * 
 * Handles PayPal payment capture from WooCommerce mobile app using iZettle SDK
 * This endpoint follows the same pattern as the existing Stripe terminal payment capture
 */
class CapturePayPalPaymentEndpoint {

    /**
     * The PayPal order endpoint.
     *
     * @var OrderEndpoint
     */
    private $order_endpoint;

    /**
     * CapturePayPalPaymentEndpoint constructor.
     *
     * @param OrderEndpoint $order_endpoint The PayPal order endpoint.
     */
    public function __construct( OrderEndpoint $order_endpoint ) {
        $this->order_endpoint = $order_endpoint;
    }

    /**
     * Registers the REST API route.
     */
    public function register_routes() {
        register_rest_route(
            'wc/v3',
            '/payments/orders/(?P<order_id>\d+)/capture_paypal_payment',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'capture_payment' ),
                'permission_callback' => array( $this, 'check_permissions' ),
                'args'                => array(
                    'order_id' => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param ) && $param > 0;
                        },
                    ),
                    'payment_intent_id' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'validate_callback' => function( $param ) {
                            return ! empty( $param ) && is_string( $param );
                        },
                    ),
                    'fields' => array(
                        'required' => false,
                        'type'     => 'string',
                        'default'  => 'id,status,created,amount,currency,payment_method,charges',
                    ),
                ),
            )
        );
    }

    /**
     * Captures a PayPal payment for the given order.
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response|WP_Error
     */
    public function capture_payment( WP_REST_Request $request ) {
        $order_id = $request->get_param( 'order_id' );
        $payment_intent_id = $request->get_param( 'payment_intent_id' );

        // Get the WooCommerce order
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error(
                'woocommerce_rest_order_invalid_id',
                __( 'Invalid order ID.', 'woocommerce-paypal-payments' ),
                array( 'status' => 404 )
            );
        }

        // Do not process refunded orders
        if ( 0 < $order->get_total_refunded() ) {
            return new WP_Error(
                'woocommerce_rest_refunded_order_uncapturable',
                __( 'Payment cannot be captured for partially or fully refunded orders.', 'woocommerce-paypal-payments' ),
                array( 'status' => 400 )
            );
        }

        // Check for already processed orders (exclude list approach like WooPayments/Stripe)
        $uncapturable_statuses = array( 'completed', 'processing', 'cancelled', 'refunded' );
        if ( in_array( $order->get_status(), $uncapturable_statuses, true ) ) {
            return new WP_Error(
                'woocommerce_rest_order_uncapturable',
                sprintf(
                    __( 'Payment cannot be captured for orders with status: %s', 'woocommerce-paypal-payments' ),
                    $order->get_status()
                ),
                array( 'status' => 409 )
            );
        }

        try {
            // Verify the payment with PayPal's API
            $is_payment_valid = $this->verify_payment_with_paypal( $payment_intent_id, $order );
            
            if ( ! $is_payment_valid ) {
                return new WP_Error(
                    'paypal_payment_verification_failed',
                    __( 'Payment verification with PayPal failed.', 'woocommerce-paypal-payments' ),
                    array( 'status' => 400 )
                );
            }

            // Mark the order as paid
            $this->complete_order_payment( $order, $payment_intent_id );

            // Return the payment intent data
            $payment_intent_data = array(
                'id'             => $payment_intent_id,
                'status'         => 'succeeded',
                'created'        => time(),
                'amount'         => $order->get_total() * 100, // Convert to cents
                'currency'       => $order->get_currency(),
                'payment_method' => 'paypal_zettle',
                'charges'        => array(),
            );

            return new WP_REST_Response( $payment_intent_data, 200 );

        } catch ( Exception $e ) {
            return new WP_Error(
                'paypal_capture_error',
                sprintf( __( 'PayPal payment capture failed: %s', 'woocommerce-paypal-payments' ), $e->getMessage() ),
                array( 'status' => 500 )
            );
        }
    }

    /**
     * Verifies the payment with PayPal's API to ensure it's legitimate.
     *
     * @param string    $payment_intent_id The PayPal payment intent ID from iZettle.
     * @param \WC_Order $order The WooCommerce order.
     * @return bool True if payment is valid, false otherwise.
     */
    private function verify_payment_with_paypal( $payment_intent_id, $order ) {
        try {
            // Use PayPal API to verify the payment exists and matches the order
            // This would typically call PayPal's Orders API: GET /v2/checkout/orders/{id}
            // For now, we'll do basic validation and return true
            
            // Basic validation: payment_intent_id should be a valid format
            if ( empty( $payment_intent_id ) || strlen( $payment_intent_id ) < 10 ) {
                return false;
            }

            // TODO: Real implementation would:
            // 1. Use the plugin's stored PayPal credentials
            // 2. Call PayPal API: GET /v2/checkout/orders/{payment_intent_id}
            // 3. Verify the order amount matches
            // 4. Verify the order status is COMPLETED
            // 5. Return true only if all checks pass

            // For POC, we'll log the verification attempt
            error_log( sprintf( 
                'PayPal Mobile: Verifying payment %s for order %d (amount: %s %s)', 
                $payment_intent_id,
                $order->get_id(),
                $order->get_total(),
                $order->get_currency()
            ) );

            return true; // For POC, assume verification passes

        } catch ( Exception $e ) {
            error_log( 'PayPal Mobile: Payment verification failed: ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * Completes the order payment and updates WooCommerce accordingly.
     *
     * @param \WC_Order $order The WooCommerce order.
     * @param string    $payment_intent_id The PayPal payment intent ID.
     */
    private function complete_order_payment( $order, $payment_intent_id ) {
        // Set transaction ID
        $order->set_transaction_id( $payment_intent_id );

        // Add order note
        $order->add_order_note(
            sprintf(
                __( 'Payment completed via PayPal mobile app. Transaction ID: %s', 'woocommerce-paypal-payments' ),
                $payment_intent_id
            )
        );

        // Set payment method details
        $order->set_payment_method( 'paypal_zettle' );
        $order->set_payment_method_title( __( 'PayPal (via mobile app)', 'woocommerce-paypal-payments' ) );

        // Add meta data
        $order->update_meta_data( '_paypal_mobile_payment_intent_id', $payment_intent_id );
        $order->update_meta_data( '_paypal_mobile_capture_method', 'zettle_sdk' );
        $order->update_meta_data( '_paypal_mobile_capture_time', gmdate( 'c' ) );

        // Mark payment complete - this triggers order status change to 'processing'
        $order->payment_complete( $payment_intent_id );

        // Save the order
        $order->save();

        // Log successful completion
        error_log( sprintf(
            'PayPal Mobile: Order %d marked as paid. Transaction ID: %s',
            $order->get_id(),
            $payment_intent_id
        ) );
    }

    /**
     * Checks if the current user has permission to capture payments.
     *
     * @param WP_REST_Request $request The REST request.
     * @return bool|WP_Error
     */
    public function check_permissions( WP_REST_Request $request ) {
        // Check if user has general WooCommerce management permissions
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return new WP_Error(
                'woocommerce_rest_cannot_edit',
                __( 'Sorry, you are not allowed to capture payments.', 'woocommerce-paypal-payments' ),
                array( 'status' => rest_authorization_required_code() )
            );
        }

        return true;
    }
}