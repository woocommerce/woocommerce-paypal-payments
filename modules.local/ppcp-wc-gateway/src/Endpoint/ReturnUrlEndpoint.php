<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Endpoint;

use Inpsyde\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use Inpsyde\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use Inpsyde\PayPalCommerce\Webhooks\Handler\PrefixTrait;

class ReturnUrlEndpoint {

	use PrefixTrait;
	public const ENDPOINT = 'ppc-return-url';

	private $gateway;
	private $orderEndpoint;
	public function __construct( PayPalGateway $gateway, OrderEndpoint $orderEndpoint, string $prefix ) {
		$this->gateway       = $gateway;
		$this->orderEndpoint = $orderEndpoint;
		$this->prefix        = $prefix;
	}

	public function handleRequest() {

		if ( ! isset( $_GET['token'] ) ) {
			exit;
		}
		$token = sanitize_text_field( wp_unslash( $_GET['token'] ) );
		$order = $this->orderEndpoint->order( $token );
		if ( ! $order ) {
			exit;
		}

		$wcOrderId = $this->sanitize_custom_id( $order->purchaseUnits()[0]->customId() );
		if ( ! $wcOrderId ) {
			exit;
		}

		$wcOrder = wc_get_order( $wcOrderId );
		if ( ! $wcOrder ) {
			exit;
		}

		$success = $this->gateway->process_payment( $wcOrderId );
		if ( isset( $success['result'] ) && $success['result'] === 'success' ) {
			wp_redirect( $success['redirect'] );
			exit;
		}
		wp_redirect( wc_get_checkout_url() );
		exit;
	}
}
