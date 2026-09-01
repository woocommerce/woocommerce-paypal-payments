<?php
/**
 * Minimal stand-in for WP_HTML_Tag_Processor. Parses and rewrites only the
 * first opening tag, which is all the renderers under test emit.
 */

if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
	class WP_HTML_Tag_Processor {

		private $html;

		private $attributes = array();

		private $has_tag = false;

		public function __construct( $html ) {
			$this->html = $html;
		}

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
