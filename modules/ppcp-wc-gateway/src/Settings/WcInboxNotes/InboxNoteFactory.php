<?php
/**
 * @package WooCommerce\PayPalCommerce\WcGateway\Settings
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Settings\WcInboxNotes;

/**
 * A factory for creating inbox notes.
 */
class InboxNoteFactory {

	public function create_note(
		string $title,
		string $content,
		string $type,
		string $name,
		string $status,
		InboxNoteActionInterface $action
	): InboxNoteInterface {
		return new InboxNote( $title, $content, $type, $name, $status, $action );
	}
}
