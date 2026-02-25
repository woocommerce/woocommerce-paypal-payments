<?php

/**
 * Status Table Helper Trait
 *
 * Provides shared UI rendering methods for status pages via trait composition.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Inspector\Renderer
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Inspector\Page;

/**
 * Trait StatusTableHelper
 *
 * Reusable UI rendering methods that can be mixed into any class.
 * Use this trait in classes that need to render status page UI elements.
 */
trait StatusTableRenderer
{
    /**
     * Render a help tooltip icon.
     *
     * @param string $label Tooltip text.
     */
    protected function render_help(string $label): void
    {
        if (!$label) {
            return;
        }
        echo wp_kses_post(sprintf('<span class="woocommerce-help-tip" tabindex="0" title="%s"></span>', esc_attr($label)));
    }
    /**
     * Render a boolean status badge (yes/no with icon).
     *
     * @param bool   $is_true     Whether the status is true.
     * @param string $label_true  Label to display when true.
     * @param string $label_false Label to display when false.
     */
    protected function render_boolean_badge(bool $is_true, string $label_true, string $label_false): string
    {
        if ($is_true) {
            return sprintf('<mark class="yes"><span class="dashicons dashicons-yes"></span> %s</mark>', $label_true);
        }
        return sprintf('<mark class="no"><span class="dashicons dashicons-minus"></span> %s</mark>', $label_false);
    }
    /**
     * Displays the value with a valid/invalid icon.
     *
     * The expected value (which PayPal uses/expects) is compared to the actual value stored in the
     * local database.
     *
     * @param string $expected The expected value used by PayPal.
     * @param string $actual   The actual value, stored in local DB.
     */
    protected function render_with_validation(string $expected, string $actual): string
    {
        $is_valid = $expected === $actual;
        $icon = $is_valid ? 'dashicons-yes' : 'dashicons-no-alt';
        $content = sprintf('<mark class="%1$s"><span class="dashicons %2$s"></span></mark> <code>%3$s</code>', esc_attr($is_valid ? 'yes' : 'error'), esc_attr($icon), esc_html($expected));
        if (!$is_valid) {
            $content .= sprintf(' <mark class="actual no">(%s)</mark>', esc_html($actual));
        }
        return $content;
    }
    /**
     * Render a table row with optional help text.
     *
     * @param string          $label The row label.
     * @param string|callable $value The value to display (string or callable that echoes/returns
     *                               content).
     * @param string          $help  Optional help text for tooltip. Defaults to empty string.
     */
    protected function render_row(string $label, $value, string $help = ''): void
    {
        $label = trim($label, ' :');
        if ($label) {
            $label .= ':';
        }
        ?>
		<tr>
			<td>
				<?php 
        echo esc_html($label);
        ?>
			</td>
			<td class="help">
				<?php 
        $this->render_help($help);
        ?>
			</td>
			<td>
				<?php 
        if (is_callable($value)) {
            $value = $value();
        }
        if (is_string($value) && $value) {
            echo wp_kses_post($value);
        }
        ?>
			</td>
		</tr>
		<?php 
    }
    protected function render_note(string $note): string
    {
        return sprintf('<div class="ppcp-notice notice notice-warning below-h2" style="margin:0"><p style="margin:0.5em 0">%s</p></div>', wp_kses_post($note));
    }
}
