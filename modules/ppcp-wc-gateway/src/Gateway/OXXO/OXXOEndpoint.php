<?php
/**
 * Handles OXXO payer action.
 *
 * @package WooCommerce\PayPalCommerce\Onboarding\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingPreferenceFactory;
use WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;

/**
 * OXXOEndpoint constructor.
 */
class OXXOEndpoint implements EndpointInterface {


	/**
	 * The request data
	 *
	 * @var RequestData
	 */
	protected $request_data;

	/**
	 * The purchase unit factory.
	 *
	 * @var PurchaseUnitFactory
	 */
	protected $purchase_unit_factory;

	/**
	 * The shipping preference factory.
	 *
	 * @var ShippingPreferenceFactory
	 */
	protected $shipping_preference_factory;

	/**
	 * The order endpoint.
	 *
	 * @var OrderEndpoint
	 */
	protected $order_endpoint;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * OXXOEndpoint constructor
	 *
	 * @param RequestData               $request_data The request data.
	 * @param OrderEndpoint             $order_endpoint The order endpoint.
	 * @param PurchaseUnitFactory       $purchase_unit_factory The purchase unit factory.
	 * @param ShippingPreferenceFactory $shipping_preference_factory The shipping preference factory.
	 * @param LoggerInterface           $logger The logger.
	 */
	public function __construct(
		RequestData $request_data,
		OrderEndpoint $order_endpoint,
		PurchaseUnitFactory $purchase_unit_factory,
		ShippingPreferenceFactory $shipping_preference_factory,
		LoggerInterface $logger
	) {
		$this->request_data                = $request_data;
		$this->purchase_unit_factory       = $purchase_unit_factory;
		$this->shipping_preference_factory = $shipping_preference_factory;
		$this->order_endpoint              = $order_endpoint;
		$this->logger                      = $logger;
	}

	/**
	 * The nonce
	 *
	 * @return string
	 */
	public static function nonce(): string {
		return 'ppc-oxxo';
	}

	/**
	 * Handles the request.
	 *
	 * @return bool
	 */
	public function handle_request(): bool {
		$purchase_unit = $this->purchase_unit_factory->from_wc_cart();
		$payer_action  = '';

		try {
			$shipping_preference = $this->shipping_preference_factory->from_state(
				$purchase_unit,
				'checkout'
			);

			$order = $this->order_endpoint->create( array( $purchase_unit ), $shipping_preference );

			$payment_source = array(
				'oxxo' => array(
					'name'         => 'John Doe',
					'email'        => 'foo@bar.com',
					'country_code' => 'MX',
				),
			);

			$payment_method = $this->order_endpoint->confirm_payment_source( $order->id(), $payment_source );

			foreach ( $payment_method->links as $link ) {
				if ( $link->rel === 'payer-action' ) {
					$payer_action = $link->href;
				}
			}
		} catch ( RuntimeException $exception ) {
			$error = $exception->getMessage();

			if ( is_a( $exception, PayPalApiException::class ) && is_array( $exception->details() ) ) {
				$details = '';
				foreach ( $exception->details() as $detail ) {
					$issue       = $detail->issue ?? '';
					$field       = $detail->field ?? '';
					$description = $detail->description ?? '';
					$details    .= $issue . ' ' . $field . ' ' . $description . '<br>';
				}

				$error = $details;
			}

			$this->logger->error( $error );
			wc_add_notice( $error, 'error' );

			wp_send_json_error( 'Could not get OXXO payer action.' );
			return false;
		}

		WC()->session->set( 'ppcp_payer_action', $payer_action );

		wp_send_json_success(
			array( 'payer_action' => $payer_action )
		);

		return true;
	}
}
