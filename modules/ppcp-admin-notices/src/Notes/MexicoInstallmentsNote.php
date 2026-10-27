<?php

/**
 * Inbox Note for Mexico Installments feature.
 *
 * @package WooCommerce\PayPalCommerce\AdminNotices\Notes
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\AdminNotices\Notes;

use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\NotesUnavailableException;
use Automattic\WooCommerce\Admin\Notes\NoteTraits;
use Exception;
/**
 * Class MexicoInstallmentsNote
 */
class MexicoInstallmentsNote
{
    use NoteTraits;
    /**
     * Name of the note for use in the database.
     */
    const NOTE_NAME = 'ppcp-mexico-installments-note';
    /**
     * Note initialization.
     */
    public static function init(): void
    {
        try {
            /**
             * The method exists in the NoteTraits trait.
             *
             * @psalm-suppress UndefinedMethod
             */
            self::possibly_add_note();
        } catch (Exception $e) {
            return;
        }
    }
    /**
     * Add the note if it passes predefined conditions.
     *
     * @throws NotesUnavailableException Throws exception when notes are unavailable.
     */
    public static function possibly_add_note(): void
    {
        $note = self::get_note();
        if (!self::can_be_added()) {
            return;
        }
        $note->save();
    }
    /**
     * Returns a new Note.
     *
     * @return Note
     */
    public static function get_note(): Note
    {
        $note = new Note();
        $note->set_name(self::NOTE_NAME);
        $note->set_type(Note::E_WC_ADMIN_NOTE_INFORMATIONAL);
        $note->set_source('woocommerce-paypal-payments');
        $note->set_title(__('Enable Installments with PayPal', 'woocommerce-paypal-payments'));
        $note->set_content(sprintf(
            // translators: %1$s and %2$s are the opening and closing of HTML <a> tag. %3$s and %4$s are the opening and closing of HTML <p> tag.
            __('Allow your customers to pay in installments without interest while you receive the full payment in a single transaction.*
					%3$sActivate your Installments without interest with PayPal.%4$s
					%3$sYou will receive the full payment minus the applicable PayPal fee. See %1$sterms and conditions%2$s.%4$s', 'woocommerce-paypal-payments'),
            '<a href="https://www.paypal.com/mx/webapps/mpp/merchant-fees" target="_blank">',
            '</a>',
            '<p>',
            '</p>'
        ));
        $note->add_action('enable-installments-action-link', __('Enable Installments', 'woocommerce-paypal-payments'), esc_url('https://www.paypal.com/businessmanage/preferences/installmentplan'), Note::E_WC_ADMIN_NOTE_UNACTIONED, \true);
        return $note;
    }
    /**
     * Checks if a note can and should be added.
     *
     * @return bool
     * @throws NotesUnavailableException Throws exception when notes are unavailable.
     */
    public static function can_be_added(): bool
    {
        $country = wc_get_base_location()['country'] ?? '';
        if ($country !== 'MX') {
            return \false;
        }
        /**
         * The method exists in the NoteTraits trait.
         *
         * @psalm-suppress UndefinedMethod
         */
        if (self::note_exists()) {
            return \false;
        }
        return \true;
    }
}
