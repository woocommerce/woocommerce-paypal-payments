<?php
/**
 * Status Table Helper Trait
 *
 * Provides shared UI rendering methods for status pages via trait composition.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Inspector\Renderer
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Inspector\Page;

/**
 * Trait StatusTableHelper
 *
 * Reusable UI rendering methods that can be mixed into any class.
 * Use this trait in classes that need to render status page UI elements.
 */
trait StatusTableRenderer {

	/**
	 * Render a help tooltip icon.
	 *
	 * @param string $label Tooltip text.
	 */
	protected function render_help( string $label ): void {
		if ( ! $label ) {
			return;
		}

		echo wp_kses_post(
			sprintf(
				'<span class="woocommerce-help-tip" tabindex="0" title="%s"></span>',
				esc_attr( $label )
			)
		);
	}

	/**
	 * Render a boolean status badge (yes/no with icon).
	 *
	 * @param bool   $is_true     Whether the status is true.
	 * @param string $label_true  Label to display when true.
	 * @param string $label_false Label to display when false.
	 */
	protected function render_boolean_badge( bool $is_true, string $label_true, string $label_false ): void {
		if ( $is_true ) {
			echo wp_kses_post(
				sprintf(
					'<mark class="yes"><span class="dashicons dashicons-yes"></span> %s</mark>',
					$label_true
				)
			);

			return;
		}

		echo wp_kses_post(
			sprintf(
				'<mark class="no"><span class="dashicons dashicons-minus"></span> %s</mark>',
				$label_false
			)
		);
	}
}
