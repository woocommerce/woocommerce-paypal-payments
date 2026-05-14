<?php
/**
 * Get Last Webhook Event ability definition.
 *
 * @package WooCommerce\PayPalCommerce\Abilities
 */

// @phan-file-suppress PhanUndeclaredClassMethod, PhanUndeclaredFunction @phan-suppress-current-line UnusedSuppression -- Abilities API + AbilityDefinition added in WC 10.9; suppression covers older-WC compat runs where this class never loads.

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Abilities\Domain;

use Automattic\WooCommerce\Abilities\AbilityDefinition;
use LogicException;
use Throwable;
use WooCommerce\PayPalCommerce\Abilities\Abilities_Registrar;
use WooCommerce\PayPalCommerce\PPCP;
use WooCommerce\PayPalCommerce\Webhooks\WebhookEventStorage;

/**
 * Registers the woocommerce-paypal-payments/get-last-webhook-event ability.
 *
 * Returns the most recent PayPal webhook event the plugin processed
 * (event id and reception timestamp) so an agent can answer "is the
 * PayPal webhook actually firing? when did the last one come in?" — the
 * operational complement to get-webhook-status's "is the webhook
 * configured?" Backed by WebhookEventStorage::get_data() (Shape 3 —
 * direct service call).
 *
 * Storage retains only the most recent event; older events are
 * overwritten by design.
 *
 * @internal
 */
class GetLastWebhookEvent extends AbstractPpcpAbility implements AbilityDefinition {

	/**
	 * Container service id for the WebhookEventStorage.
	 *
	 * Cross-referenced at modules/ppcp-status-report/src/StatusReportModule.php
	 * line 71 and modules/ppcp-webhooks/services.php line 219.
	 *
	 * @var string
	 */
	private const SERVICE_ID = 'webhook.last-webhook-storage';

	public static function get_name(): string {
		return 'woocommerce-paypal-payments/get-last-webhook-event';
	}

	public static function get_registration_args(): array {
		return array(
			'label'               => __( 'Get last PayPal webhook event', 'woocommerce-paypal-payments' ),
			'description'         => __( 'Returns the id and reception timestamp of the most recent PayPal webhook event the plugin processed, or null when no event has been received. Useful for diagnosing webhook delivery health.', 'woocommerce-paypal-payments' ),
			'category'            => self::CATEGORY_SLUG,
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => (object) array(),
				'properties'           => array(),
				'additionalProperties' => false,
			),
			'execute_callback'    => array( self::class, 'execute' ),
			'permission_callback' => array( Abilities_Registrar::class, 'can_manage_woocommerce' ),
			'meta'                => array(
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
				'show_in_rest' => true,
				'mcp'          => array(
					'public' => true,
				),
			),
		);
	}

	/**
	 * Execute callback.
	 *
	 * @param mixed $input Optional; ignored.
	 * @return array|\WP_Error Either the storage payload (with received_iso
	 *                         appended for agent ergonomics), the special
	 *                         "no event" sentinel { received: false }, or
	 *                         WP_Error when the container is unavailable.
	 */
	public static function execute( $input = null ) {
		unset( $input );

		$storage = self::resolve_storage();
		if ( $storage instanceof \WP_Error ) {
			return $storage;
		}

		$data = $storage->get_data();
		if ( null === $data ) {
			return array( 'received' => false );
		}

		return self::project( $data );
	}

	/**
	 * Project the storage payload into the agent-facing shape.
	 *
	 * Public so unit tests can assert the projection without standing up
	 * the plugin container.
	 *
	 * @param array $data Storage payload as written by
	 *                    WebhookEventStorage::save() — { id, received_time }.
	 * @return array
	 */
	public static function project( array $data ): array {
		$received_time = isset( $data['received_time'] ) ? (int) $data['received_time'] : 0;

		return array(
			'received'      => true,
			'id'            => isset( $data['id'] ) ? (string) $data['id'] : '',
			'received_time' => $received_time,
			'received_iso'  => $received_time > 0
				? gmdate( 'c', $received_time )
				: null,
		);
	}

	/**
	 * Resolve the WebhookEventStorage service from the plugin container.
	 *
	 * @return WebhookEventStorage|\WP_Error
	 */
	private static function resolve_storage() {
		try {
			$service = PPCP::container()->get( self::SERVICE_ID );
		} catch ( LogicException $e ) {
			return new \WP_Error(
				'woocommerce_paypal_payments_not_initialized',
				__( 'WooCommerce PayPal Payments is not initialized; webhook storage is unavailable.', 'woocommerce-paypal-payments' )
			);
		} catch ( Throwable $e ) {
			return new \WP_Error(
				'woocommerce_paypal_payments_service_unavailable',
				__( 'Webhook storage service could not be resolved.', 'woocommerce-paypal-payments' )
			);
		}

		if ( ! $service instanceof WebhookEventStorage ) {
			return new \WP_Error(
				'woocommerce_paypal_payments_service_unavailable',
				__( 'Webhook storage service returned an unexpected type.', 'woocommerce-paypal-payments' )
			);
		}

		return $service;
	}
}
