<?php
/**
 * @package WooCommerce\PayPalCommerce\WcGateway\WcInboxNotes
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway\WcInboxNotes;

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
		bool $is_enabled,
		InboxNoteActionInterface ...$actions
	): InboxNoteInterface {
		return new InboxNote( $title, $content, $type, $name, $status, $is_enabled, ...$actions );
	}
}
