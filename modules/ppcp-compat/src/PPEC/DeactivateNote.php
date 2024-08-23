<?php
/**
 * Deactivate PayPal Express Checkout inbox note.
 *
 * @package WooCommerce\PayPalCommerce\Compat\PPEC
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat\PPEC;

use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\Notes;
use Automattic\WooCommerce\Admin\Notes\NoteTraits;

/**
 * Inbox note for PayPal Express Checkout deactivation.
 */
class DeactivateNote {

	use NoteTraits;

	/**
	 * Name of the note for use in the database.
	 */
	const NOTE_NAME = 'ppcp-disable-ppxo-note';

	/**
	 * Note initialization.
	 */
	public static function init(): void {
		if ( ! PPECHelper::is_plugin_active() ) {
			self::maybe_mark_note_as_actioned();
			return;
		}

		try {
			/**
			 * The method exists in the NoteTraits trait.
			 *
			 * @psalm-suppress UndefinedMethod
			 */
			self::possibly_add_note();
		} catch ( \Exception $e ) {
			return;
		}
	}

	/**
	 * Get the note.
	 *
	 * @return Note
	 */
	public static function get_note() {
		if ( PPECHelper::site_has_ppec_subscriptions() ) {
			$msg = __(
				'As of 1 Sept 2021, PayPal Checkout will be officially retired from WooCommerce.com, and support for this product will end as of 1 March 2022. PayPal Payments can now handle all your subscription renewals even if they were first created using PayPal Checkout. To fully switch over, all you need to do is deactivate and/or remove the PayPal Checkout plugin from your store.',
				'woocommerce-paypal-payments'
			);
		} else {
			$msg = __(
				'As of 1 Sept 2021, PayPal Checkout will be officially retired from WooCommerce.com, and support for this product will end as of 1 March 2022. To fully switch over, all you need to do is deactivate and/or remove the PayPal Checkout plugin from your store.',
				'woocommerce-paypal-payments'
			);
		}

		$note = new Note();
		$note->set_name( self::NOTE_NAME );
		$note->set_type( Note::E_WC_ADMIN_NOTE_INFORMATIONAL );
		$note->set_source( 'woocommerce-paypal-payments' );
		$note->set_title(
			__( 'Action Required: Deactivate PayPal Checkout', 'woocommerce-paypal-payments' )
		);
		$note->set_content( $msg );
		$note->add_action(
			'deactivate-paypal-checkout-plugin',
			__( 'Deactivate PayPal Checkout', 'woocommerce-paypal-payments' ),
			admin_url( 'plugins.php?action=deactivate&plugin=' . rawurlencode( PPECHelper::PPEC_PLUGIN_FILE ) . '&plugin_status=all&paged=1&_wpnonce=' . wp_create_nonce( 'deactivate-plugin_' . PPECHelper::PPEC_PLUGIN_FILE ) ),
			Note::E_WC_ADMIN_NOTE_UNACTIONED,
			true
		);
		$note->add_action(
			'learn-more',
			__( 'Learn More', 'woocommerce-paypal-payments' ),
			'https://docs.woocommerce.com/document/woocommerce-paypal-payments/paypal-payments-upgrade-guide/',
			Note::E_WC_ADMIN_NOTE_UNACTIONED
		);

		return $note;
	}

	/**
	 * Marks the inbox note as actioned so that it doesn't re-appear.
	 */
	private static function maybe_mark_note_as_actioned(): void {
		try {
			$data_store = \WC_Data_Store::load( 'admin-note' );
		} catch ( \Exception $e ) {
			$data_store = null;
		}

		if ( ! $data_store ) {
			return;
		}

		$note_ids = $data_store->get_notes_with_name( self::NOTE_NAME );

		if ( empty( $note_ids ) ) {
			return;
		}

		$note = Notes::get_note( $note_ids[0] );

		if ( $note instanceof Note && Note::E_WC_ADMIN_NOTE_ACTIONED !== $note->get_status() ) {
			$note->set_status( Note::E_WC_ADMIN_NOTE_ACTIONED );
			$note->save();
		}
	}

}
