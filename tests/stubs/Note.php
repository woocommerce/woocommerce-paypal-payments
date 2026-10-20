<?php
/**
 * Minimal constants-only stub of \Automattic\WooCommerce\Admin\Notes\Note for unit tests.
 */

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Admin\Notes;

if ( ! class_exists( __NAMESPACE__ . '\\Note' ) ) {
	class Note {
		const E_WC_ADMIN_NOTE_INFORMATIONAL = 'info';
		const E_WC_ADMIN_NOTE_UNACTIONED    = 'unactioned';
	}
}
