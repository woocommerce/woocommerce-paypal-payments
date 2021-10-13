<?php
/**
 * The Webhook event notification object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

use DateTime;
use stdClass;

/**
 * Class WebhookEvent
 */
class WebhookEvent {

	/**
	 * The ID of the event notification.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * The date and time when the event notification was created.
	 *
	 * @var DateTime|null
	 */
	private $create_time;

	/**
	 * The name of the resource related to the webhook notification event, such as 'checkout-order'.
	 *
	 * @var string
	 */
	private $resource_type;

	/**
	 * The event version in the webhook notification, such as '1.0'.
	 *
	 * @var string
	 */
	private $event_version;

	/**
	 * The event that triggered the webhook event notification, such as 'CHECKOUT.ORDER.APPROVED'.
	 *
	 * @var string
	 */
	private $event_type;

	/**
	 * A summary description for the event notification.
	 *
	 * @var string
	 */
	private $summary;

	/**
	 * The resource version in the webhook notification, such as '1.0'.
	 *
	 * @var string
	 */
	private $resource_version;

	/**
	 * The resource that triggered the webhook event notification.
	 *
	 * @var stdClass
	 */
	private $resource;

	/**
	 * WebhookEvent constructor.
	 *
	 * @param string        $id The ID of the event notification.
	 * @param DateTime|null $create_time The date and time when the event notification was created.
	 * @param string        $resource_type The name of the resource related to the webhook notification event, such as 'checkout-order'.
	 * @param string        $event_version The event version in the webhook notification, such as '1.0'.
	 * @param string        $event_type The event that triggered the webhook event notification, such as 'CHECKOUT.ORDER.APPROVED'.
	 * @param string        $summary A summary description for the event notification.
	 * @param string        $resource_version The resource version in the webhook notification, such as '1.0'.
	 * @param stdClass      $resource The resource that triggered the webhook event notification.
	 */
	public function __construct( string $id, ?DateTime $create_time, string $resource_type, string $event_version, string $event_type, string $summary, string $resource_version, stdClass $resource ) {
		$this->id               = $id;
		$this->create_time      = $create_time;
		$this->resource_type    = $resource_type;
		$this->event_version    = $event_version;
		$this->event_type       = $event_type;
		$this->summary          = $summary;
		$this->resource_version = $resource_version;
		$this->resource         = $resource;
	}

	/**
	 * The ID of the event notification.
	 *
	 * @return string
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * The date and time when the event notification was created.
	 *
	 * @return DateTime|null
	 */
	public function create_time(): ?DateTime {
		return $this->create_time;
	}

	/**
	 * The name of the resource related to the webhook notification event, such as 'checkout-order'.
	 *
	 * @return string
	 */
	public function resource_type(): string {
		return $this->resource_type;
	}

	/**
	 * The event version in the webhook notification, such as '1.0'.
	 *
	 * @return string
	 */
	public function event_version(): string {
		return $this->event_version;
	}

	/**
	 * The event that triggered the webhook event notification, such as 'CHECKOUT.ORDER.APPROVED'.
	 *
	 * @return string
	 */
	public function event_type(): string {
		return $this->event_type;
	}

	/**
	 * A summary description for the event notification.
	 *
	 * @return string
	 */
	public function summary(): string {
		return $this->summary;
	}

	/**
	 * The resource version in the webhook notification, such as '1.0'.
	 *
	 * @return string
	 */
	public function resource_version(): string {
		return $this->resource_version;
	}

	/**
	 * The resource that triggered the webhook event notification.
	 *
	 * @return stdClass
	 */
	public function resource(): stdClass {
		return $this->resource;
	}
}
