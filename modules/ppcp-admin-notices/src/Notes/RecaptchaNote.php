<?php
/**
 * Inbox Note for reCAPTCHA protection feature.
 *
 * @package WooCommerce\PayPalCommerce\AdminNotices\Notes
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AdminNotices\Notes;

use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\NotesUnavailableException;
use Automattic\WooCommerce\Admin\Notes\NoteTraits;
use Exception;

class RecaptchaNote {
	use NoteTraits;

	const NOTE_NAME = 'ppcp-recaptcha-protection-note';

	public static function init(): void {
		try {
			/**
			 * The method exists in the NoteTraits trait.
			 *
			 * @psalm-suppress UndefinedMethod
			 */
			self::possibly_add_note();
		} catch ( Exception $e ) {
			return;
		}
	}

	/**
	 * Add the note if it passes predefined conditions.
	 *
	 * @throws NotesUnavailableException Throws exception when notes are unavailable.
	 */
	public static function possibly_add_note(): void {
		$note = self::get_note();

		if ( ! self::can_be_added() ) {
			return;
		}

		$note->save();
	}

	public static function get_note(): Note {
		$note = new Note();
		$note->set_name( self::NOTE_NAME );
		$note->set_type( Note::E_WC_ADMIN_NOTE_INFORMATIONAL );
		$note->set_source( 'woocommerce-paypal-payments' );
		$note->set_title(
			__( 'Protect PayPal Checkout from Bots', 'woocommerce-paypal-payments' )
		);
		$note->set_content(
			__(
				'Many stores are experiencing an increase in spam orders, failed transactions, and wasted resources during the holiday season due to card testing bots. To secure your store during this holiday season, we\'ve introduced a dedicated reCAPTCHA integration in WooCommerce PayPal Payments 3.3.0+ to protect these endpoints.',
				'woocommerce-paypal-payments'
			)
		);

		$note->add_action(
			'protect-paypal-with-recaptcha',
			__( 'Protect PayPal with reCAPTCHA', 'woocommerce-paypal-payments' ),
			admin_url( 'admin.php?page=wc-settings&tab=integration&section=ppcp-recaptcha' ),
			Note::E_WC_ADMIN_NOTE_UNACTIONED,
			true
		);

		$note->add_action(
			'learn-more-recaptcha',
			__( 'Learn More', 'woocommerce-paypal-payments' ),
			'https://woocommerce.com/document/woocommerce-paypal-payments/#recaptcha-configuration',
			Note::E_WC_ADMIN_NOTE_UNACTIONED
		);

		return $note;
	}

	/**
	 * @throws NotesUnavailableException Throws exception when notes are unavailable.
	 */
	public static function can_be_added(): bool {
		/**
		 * The method exists in the NoteTraits trait.
		 *
		 * @psalm-suppress UndefinedMethod
		 */
		if ( self::note_exists() ) {
			return false;
		}

		$recaptcha_settings = get_option( 'woocommerce_ppcp-recaptcha_settings', array() );
		if ( isset( $recaptcha_settings['enabled'] ) && 'yes' === $recaptcha_settings['enabled'] ) {
			return false;
		}

		return true;
	}
}
