<?php
/**
 * Creates WebhookEvent.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use DateTime;
use stdClass;
use WooCommerce\PayPalCommerce\ApiClient\Entity\WebhookEvent;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;

/**
 * Class WebhookEventFactory
 */
class WebhookEventFactory {

	/**
	 * Returns a webhook from a given data array.
	 *
	 * @param array $data The data array.
	 *
	 * @return WebhookEvent
	 */
	public function from_array( array $data ): WebhookEvent {
		return $this->from_paypal_response( (object) $data );
	}

	/**
	 * Returns a Webhook based of a PayPal JSON response.
	 *
	 * @param stdClass $data The JSON object.
	 *
	 * @return WebhookEvent
	 * @throws RuntimeException When JSON object is malformed.
	 */
	public function from_paypal_response( stdClass $data ): WebhookEvent {
		if ( ! isset( $data->id ) ) {
			throw new RuntimeException(
				__( 'ID for webhook event not found.', 'woocommerce-paypal-payments' )
			);
		}
		if ( ! isset( $data->event_type ) ) {
			throw new RuntimeException(
				__( 'Event type for webhook event not found.', 'woocommerce-paypal-payments' )
			);
		}

		$create_time = ( isset( $data->create_time ) ) ?
			DateTime::createFromFormat( 'Y-m-d\TH:i:sO', $data->create_time )
			: null;

		// Sometimes the time may be in weird format 2018-12-19T22:20:32.000Z (at least in simulation),
		// we do not care much about time, so just ignore on failure.
		if ( false === $create_time ) {
			$create_time = null;
		}

		return new WebhookEvent(
			(string) $data->id,
			$create_time,
			(string) $data->resource_type ?? '',
			(string) $data->event_version ?? '',
			(string) $data->event_type,
			(string) $data->summary ?? '',
			(string) $data->resource_version ?? '',
			(object) $data->resource ?? ( new stdClass() )
		);
	}
}
