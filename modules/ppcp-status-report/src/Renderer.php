<?php
/**
 * The status report renderer.
 *
 * @package WooCommerce\PayPalCommerce\StatusReport
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\StatusReport;

/**
 * Class Renderer
 */
class Renderer {

	/**
	 * It renders the status report content.
	 *
	 * @param string $title The title.
	 * @param array  $items The items.
	 * @return false|string
	 */
	public function render( string $title, array $items ) {
		ob_start();
		?>
		<table class="wc_status_table widefat" id="status">
			<thead>
			<tr>
				<th colspan="3" data-export-label="<?php echo esc_attr( $title ); ?>">
					<h2><?php echo esc_attr( $title ); ?></h2>
				</th>
			</tr>
			</thead>
			<tbody>
			<?php
			foreach ( $items as $item ) {
				?>
				<tr>
					<td data-export-label="<?php echo esc_attr( $item['label'] ); ?>"><?php echo esc_attr( $item['label'] ); ?></td>
					<td class="help"><?php echo wc_help_tip( $item['description'] ); ?></td>
					<td><?php echo esc_attr( $item['value'] ); ?></td>
				</tr>
				<?php
			}
			?>
			</tbody>
		</table>
		<?php
		return ob_get_clean();
	}
}
