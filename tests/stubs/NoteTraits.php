<?php

namespace Automattic\WooCommerce\Admin\Notes;

if (!trait_exists('NoteTraits')) {
	/**
	 * Stub for WooCommerce Admin NoteTraits
	 */
	trait NoteTraits {
		/**
		 * Get the note.
		 */
		public static function get_note() {
			return null;
		}

		/**
		 * Possibly add the note.
		 */
		public static function possibly_add_note() {
			// Stub method
		}

		/**
		 * Can the note be added?
		 */
		public static function can_be_added() {
			return false;
		}
	}
}
