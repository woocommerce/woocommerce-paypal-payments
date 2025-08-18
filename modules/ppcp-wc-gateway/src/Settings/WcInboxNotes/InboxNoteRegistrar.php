<?php
/**
 * @package WooCommerce\PayPalCommerce\WcGateway\Settings
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Settings\WcInboxNotes;

use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\Notes;
use Automattic\WooCommerce\Admin\Notes\NotesUnavailableException;
use Exception;
use WpOop\WordPress\Plugin\PluginInterface;

/**
 * Registers inbox notes in the WooCommerce Admin inbox section.
 */
class InboxNoteRegistrar {

	/**
	 * @var InboxNoteInterface[]
	 */
	protected array $inbox_notes;
	protected PluginInterface $plugin;

	public function __construct( array $inbox_notes, PluginInterface $plugin ) {
		$this->inbox_notes = $inbox_notes;
		$this->plugin      = $plugin;
	}

	public function register(): void {
		foreach ( $this->inbox_notes as $inbox_note ) {
			if ( ! $inbox_note->is_enabled() ) {
				try {
					$this->unregister( $inbox_note->name() );
				} catch ( Exception $exeption ) {
					continue;
				}
				continue;
			}

			$inbox_note_name = $inbox_note->name();

			if ( Notes::get_note_by_name( $inbox_note_name ) ) {
				continue;
			}

			$note = new Note();
			$note->set_title( $inbox_note->title() );
			$note->set_content( $inbox_note->content() );
			$note->set_type( $inbox_note->type() );
			$note->set_name( $inbox_note_name );
			$note->set_source( $this->plugin->getBaseName() );
			$note->set_status( $inbox_note->status() );

			$inbox_note_action = $inbox_note->action();

			$note->add_action(
				$inbox_note_action->name(),
				$inbox_note_action->label(),
				$inbox_note_action->url(),
				$inbox_note_action->status(),
				$inbox_note_action->is_primary()
			);

			$note->save();
		}
	}

	/**
	 * @throws NotesUnavailableException If unable to unregister the note.
	 */
	public function unregister( string $inbox_note_name ): void {
		$data_store        = Notes::load_data_store();
		$existing_note_ids = $data_store->get_notes_with_name( $inbox_note_name );

		foreach ( $existing_note_ids as $note_id ) {
			$note = Notes::get_note( $note_id );

			if ( $note ) {
				/** @psalm-suppress PossiblyInvalidArgument */
				$data_store->delete( $note );
			}
		}
	}
}
