<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\Helper;

/**
 * Thrown by the test wp_die AJAX handler to unwind out of wp_send_json_*().
 *
 * Extends \Error on purpose: the WC-AJAX endpoints wrap their wp_send_json_*
 * calls in broad catch ( Exception ) blocks (see AbstractCartEndpoint::handle_request(),
 * CreateOrderEndpoint::handle_request()). An \Exception-based signal would be
 * swallowed there and the endpoint would respond a second time. No endpoint
 * catches \Throwable around a wp_send_json_* call, so \Error escapes cleanly.
 */
final class WpAjaxDieSignal extends \Error {

}
