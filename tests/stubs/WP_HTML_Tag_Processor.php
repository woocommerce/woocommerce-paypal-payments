<?php
/**
 * A minimal, real (non-Mockery) stand-in for WordPress' WP_HTML_Tag_Processor.
 *
 * WordPress core is not loaded in the unit-test runtime, so the real class does
 * not exist. This stub parses and rewrites only the first opening tag of the
 * given HTML fragment - enough to exercise renderers that build a single
 * `<div ...>` wrapper and set attributes on it, which is the only usage this
 * codebase makes of the WP HTML Tag Processor API surface used here
 * (`next_tag()`, `set_attribute()`, `get_updated_html()`).
 *
 * Guarded because integration tests boot a different bootstrap
 * (tests/integration/PHPUnit/bootstrap.php) that loads real WordPress via
 * wp-load.php; this file is only required from the unit-test bootstrap, but
 * the guard keeps that safe by construction rather than by coincidence.
 */

if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
	class WP_HTML_Tag_Processor {

		/**
		 * @var string
		 */
		private $html;

		/**
		 * @var array<string, string>
		 */
		private $attributes = array();

		/**
		 * @var bool
		 */
		private $has_tag = false;

		/**
		 * @param string $html
		 */
		public function __construct( $html ) {
			$this->html = $html;
		}

		/**
		 * @param array|string|null $query
		 */
		public function next_tag( $query = null ): bool {
			$this->has_tag = (bool) preg_match( '/^<([a-zA-Z0-9]+)([^>]*)>/', $this->html, $matches );

			if ( $this->has_tag ) {
				preg_match_all( '/([a-zA-Z0-9_-]+)="([^"]*)"/', $matches[2], $attribute_matches, PREG_SET_ORDER );
				foreach ( $attribute_matches as $attribute_match ) {
					$this->attributes[ $attribute_match[1] ] = $attribute_match[2];
				}
			}

			return $this->has_tag;
		}

		/**
		 * @param string $name
		 * @param mixed  $value
		 */
		public function set_attribute( $name, $value ): bool {
			$this->attributes[ $name ] = (string) $value;

			return true;
		}

		public function get_updated_html(): string {
			if ( ! $this->has_tag ) {
				return $this->html;
			}

			$attribute_string = '';
			foreach ( $this->attributes as $name => $value ) {
				$attribute_string .= ' ' . $name . '="' . $value . '"';
			}

			return (string) preg_replace( '/^<([a-zA-Z0-9]+)([^>]*)>/', '<$1' . $attribute_string . '>', $this->html, 1 );
		}
	}
}
