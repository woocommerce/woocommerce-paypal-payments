<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\VaultComponent\Endpoint;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingPreferenceFactory;
use WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;

class CreateVaultOrderEndpoint implements EndpointInterface {

	const ENDPOINT = 'ppc-vault-create-order';

	private RequestData $request_data;
	private OrderEndpoint $order_endpoint;
	private PurchaseUnitFactory $purchase_unit_factory;
	private ShippingPreferenceFactory $shipping_preference_factory;
	private LoggerInterface $logger;

	public function __construct(
		RequestData $request_data,
		OrderEndpoint $order_endpoint,
		PurchaseUnitFactory $purchase_unit_factory,
		ShippingPreferenceFactory $shipping_preference_factory,
		LoggerInterface $logger
	) {
		$this->request_data                = $request_data;
		$this->order_endpoint              = $order_endpoint;
		$this->purchase_unit_factory       = $purchase_unit_factory;
		$this->shipping_preference_factory = $shipping_preference_factory;
		$this->logger                      = $logger;
	}

	public static function nonce(): string {
		return self::ENDPOINT;
	}

	public function handle_request(): void {
		try {
			$this->request_data->read_request( $this->nonce() );

			$purchase_unit       = $this->purchase_unit_factory->from_wc_cart();
			$shipping_preference = $this->shipping_preference_factory->from_state(
				$purchase_unit,
				'checkout'
			);

			// The Vault Component opens the PayPal paysheet so the consumer can edit
			// their funding instrument. The customer context is supplied via the SDK's
			// `data-user-id-token` (minted with target_customer_id), so the create-order
			// call must be a bare order — no payment_source.token (which would auto-
			// capture the vault and prevent the paysheet from opening) and no payer/
			// items/shipping/breakdown/custom_id (which can trigger Orders v2 5xx).
			$strip_body = static function ( array $data ): array {
				if ( isset( $data['purchase_units'][0]['amount'] ) ) {
					$data['purchase_units'] = array(
						array( 'amount' => $data['purchase_units'][0]['amount'] ),
					);
				}
				unset( $data['payer'], $data['payment_source'] );
				return $data;
			};
			add_filter( 'ppcp_create_order_request_body_data', $strip_body, 99 );

			try {
				$order = $this->order_endpoint->create(
					array( $purchase_unit ),
					$shipping_preference,
					null
				);
			} finally {
				remove_filter( 'ppcp_create_order_request_body_data', $strip_body, 99 );
			}

			wp_send_json_success( array( 'id' => $order->id() ) );
		} catch ( \Exception $exception ) {
			$this->logger->error( 'Vault Component: Failed to create order. ' . $exception->getMessage() );

			wp_send_json_error(
				array( 'message' => $exception->getMessage() )
			);
		}
	}
}
