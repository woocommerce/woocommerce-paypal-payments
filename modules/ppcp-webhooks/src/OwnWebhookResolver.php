<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Webhook;

/**
 * Decides which of the webhooks configured on the connected PayPal REST app
 * belongs to this install.
 *
 * Sometimes a REST app can end up with several sites, and PayPal returns every webhook
 * on the app in creation order.
 */
class OwnWebhookResolver {

	private IncomingWebhookEndpoint $incoming_webhook_endpoint;

	public function __construct( IncomingWebhookEndpoint $incoming_webhook_endpoint ) {
		$this->incoming_webhook_endpoint = $incoming_webhook_endpoint;
	}

	/**
	 * Whether the given webhook belongs to this install.
	 *
	 * A webhook is considered ours when either:
	 * - its id matches the one we previously stored (provably ours regardless of the
	 *   host it currently uses, so a domain migration or NGROK_HOST rotation still
	 *   recognizes our former webhook instead of leaking it), or
	 * - its URL identity (host + path) equals this install's incoming endpoint.
	 *
	 * @param Webhook $webhook The webhook to check.
	 */
	public function is_own( Webhook $webhook ): bool {
		$stored_id = $this->stored_id();
		if ( $stored_id !== '' && $webhook->id() === $stored_id ) {
			return true;
		}

		return $this->points_here( $webhook );
	}

	/**
	 * Whether the given webhook currently delivers to this install.
	 *
	 * Unlike is_own(), the stored webhook id is ignored: a webhook we registered
	 * ourselves but which still points at a former domain does not deliver here.
	 *
	 * @param Webhook $webhook The webhook to check.
	 */
	public function points_here( Webhook $webhook ): bool {
		$own_identity = $this->own_identity();

		return $own_identity !== '' && $own_identity === $this->identity( $webhook->url() );
	}

	/**
	 * Returns the webhook this install registered, out of the given list.
	 *
	 * @param Webhook[] $webhooks The webhooks configured on the PayPal REST app.
	 */
	public function find_own( array $webhooks ): ?Webhook {
		foreach ( $webhooks as $webhook ) {
			if ( $webhook instanceof Webhook && $this->is_own( $webhook ) ) {
				return $webhook;
			}
		}

		return null;
	}

	/**
	 * Builds a host+path identity for a webhook URL, used to compare webhooks
	 * independently of scheme, port, query string or a trailing slash.
	 *
	 * The path is included so that same-host installs (e.g. example.com/shop and
	 * example.com/staging, or subdirectory multisite) are told apart.
	 *
	 * @param string $url The webhook URL.
	 * @return string The lower-cased host followed by the path as-is (paths are case-sensitive),
	 *                or an empty string when the host cannot be parsed.
	 */
	public function identity( string $url ): string {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return '';
		}

		$path = wp_parse_url( $url, PHP_URL_PATH );
		$path = is_string( $path ) ? rtrim( $path, '/' ) : '';

		return strtolower( $host ) . $path;
	}

	/**
	 * This install's own webhook identity, or an empty string when its URL cannot be parsed.
	 */
	public function own_identity(): string {
		return $this->identity( $this->own_url() );
	}

	/**
	 * The webhook URL this install registers with PayPal.
	 */
	public function own_url(): string {
		return $this->incoming_webhook_endpoint->url();
	}

	/**
	 * The id of the webhook this install registered, or an empty string.
	 *
	 * Read fresh on every call, it can be updated mid-request.
	 */
	private function stored_id(): string {
		return (string) ( ( (array) get_option( WebhookRegistrar::KEY, array() ) )['id'] ?? '' );
	}
}
