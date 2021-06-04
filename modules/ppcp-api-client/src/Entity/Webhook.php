<?php
/**
 * The Webhook object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class Webhook
 */
class Webhook {

	/**
	 * The ID of the webhook.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * The URL of the webhook.
	 *
	 * @var string
	 */
	private $url;

	/**
	 * The event types.
	 *
	 * @var string[]
	 */
	private $event_types;

	/**
	 * Webhook constructor.
	 *
	 * @param string   $url The URL of the webhook.
	 * @param string[] $event_types The associated event types.
	 * @param string   $id The id of the webhook.
	 */
	public function __construct( string $url, array $event_types, string $id = '' ) {
		$this->url         = $url;
		$this->event_types = $event_types;
		$this->id          = $id;
	}

	/**
	 * Returns the id of the webhook.
	 *
	 * @return string
	 */
	public function id(): string {

		return $this->id;
	}

	/**
	 * Returns the URL listening to the hook.
	 *
	 * @return string
	 */
	public function url(): string {

		return $this->url;
	}

	/**
	 * Returns the event types.
	 *
	 * @return array
	 */
	public function event_types(): array {

		return $this->event_types;
	}

	/**
	 * Returns the object as array.
	 *
	 * @return array
	 */
	public function to_array(): array {

		$data = array(
			'url'         => $this->url(),
			'event_types' => $this->event_types(),
		);
		if ( $this->id() ) {
			$data['id'] = $this->id();
		}
		return $data;
	}
}
