<?php
/**
 * Cart Session Section
 *
 * Handles the display and inspection of agentic cart sessions.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Inspector\Page
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Inspector\Page;

use WooCommerce\PayPalCommerce\AgenticCommerce\Inspector\InspectionSessionData;

/**
 * Class CartSessionSection
 *
 * Renders cart session statistics, list, and detailed inspection.
 */
class CartSessionSection {

	use StatusTableRenderer;

	private InspectionSessionData $inspector;

	/**
	 * Constructor.
	 *
	 * @param InspectionSessionData $inspector Session inspector service.
	 */
	public function __construct( InspectionSessionData $inspector ) {
		$this->inspector = $inspector;
	}

	/**
	 * Render the cart session section.
	 */
	public function render(): void {
		$stats    = $this->inspector->get_session_statistics();
		$sessions = $this->inspector->list_all_sessions();

		// Check if we should inspect a specific session.
		$inspect_session_id = $this->get_inspected_session_id();

		?>
		<div class="wrap" style="margin-top: 40px;">
			<h2><?php esc_html_e( 'Agentic Cart Sessions', 'woocommerce-paypal-payments' ); ?></h2>

			<?php if ( $inspect_session_id ) : ?>
				<?php $this->render_session_details( $inspect_session_id ); ?>
			<?php endif; ?>

			<?php $this->render_statistics_table( $stats ); ?>
			<?php $this->render_session_list( $sessions, $inspect_session_id ); ?>
		</div>
		<?php
	}

	/**
	 * Get the session ID being inspected from URL parameters.
	 *
	 * @return string|null Session ID if valid, null otherwise.
	 */
	private function get_inspected_session_id(): ?string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['inspect_session'] ) || ! is_string( $_GET['inspect_session'] ) ||
			empty( $_GET['inspect_nonce'] ) || ! is_string( $_GET['inspect_nonce'] ) ) {
			return null;
		}

		$session_id = sanitize_text_field( wp_unslash( $_GET['inspect_session'] ) );
		$nonce      = sanitize_text_field( wp_unslash( $_GET['inspect_nonce'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( wp_verify_nonce( $nonce, 'ppcp_inspect_session_' . $session_id ) ) {
			return $session_id;
		}

		return null;
	}

	/**
	 * Render session statistics table.
	 *
	 * @param array $stats Statistics array from inspector.
	 */
	private function render_statistics_table( array $stats ): void {
		?>
		<table class="wc_status_table widefat" style="margin-bottom: 20px;">
			<thead>
			<tr>
				<th colspan="3">
					<?php esc_html_e( 'Session Statistics', 'woocommerce-paypal-payments' ); ?>
				</th>
			</tr>
			</thead>
			<tbody>
			<tr>
				<td><?php esc_html_e( 'Total Sessions', 'woocommerce-paypal-payments' ); ?>:</td>
				<td class="help">
					<?php $this->render_help( __( 'Number of active agentic cart sessions', 'woocommerce-paypal-payments' ) ); ?>
				</td>
				<td><strong><?php echo esc_html( $stats['total_sessions'] ); ?></strong></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Total Items', 'woocommerce-paypal-payments' ); ?>:</td>
				<td class="help">
					<?php $this->render_help( __( 'Total number of items across all carts', 'woocommerce-paypal-payments' ) ); ?>
				</td>
				<td><strong><?php echo esc_html( $stats['total_items'] ); ?></strong></td>
			</tr>
			<?php if ( $stats['average_age_hours'] !== null ) : ?>
				<tr>
					<td><?php esc_html_e( 'Average Session Age', 'woocommerce-paypal-payments' ); ?>:</td>
					<td class="help">
						<?php $this->render_help( __( 'Average age of sessions in hours', 'woocommerce-paypal-payments' ) ); ?>
					</td>
					<td>
						<strong><?php echo esc_html( number_format( $stats['average_age_hours'], 1 ) ); ?> hours</strong>
					</td>
				</tr>
			<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render session list table.
	 *
	 * @param array       $sessions           List of sessions from inspector.
	 * @param string|null $inspect_session_id Currently inspected session ID.
	 */
	private function render_session_list( array $sessions, ?string $inspect_session_id ): void {
		if ( empty( $sessions ) ) {
			?>
			<div class="notice notice-info inline">
				<p><?php esc_html_e( 'No active agentic cart sessions found.', 'woocommerce-paypal-payments' ); ?></p>
			</div>
			<?php
			return;
		}

		?>
		<table class="wc_status_table widefat">
			<thead>
			<tr>
				<th><?php esc_html_e( 'Session ID', 'woocommerce-paypal-payments' ); ?></th>
				<th><?php esc_html_e( 'Items', 'woocommerce-paypal-payments' ); ?></th>
				<th><?php esc_html_e( 'Created', 'woocommerce-paypal-payments' ); ?></th>
				<th><?php esc_html_e( 'Modified', 'woocommerce-paypal-payments' ); ?></th>
				<th><?php esc_html_e( 'Age', 'woocommerce-paypal-payments' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'woocommerce-paypal-payments' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php
			foreach ( $sessions as $session ) :
				$session_id    = $session['session_id'];
				$created_time  = $session['created'] ? ( wp_date( 'Y-m-d H:i:s', $session['created'] ) ?: '-' ) : '-';
				$modified_time = $session['modified'] ? ( wp_date( 'Y-m-d H:i:s', $session['modified'] ) ?: '-' ) : '-';
				$age_hours     = $session['created'] ? round( ( time() - $session['created'] ) / 3600, 1 ) : 0;

				// Build inspect URL with nonce.
				$inspect_url = add_query_arg(
					array(
						'page'            => 'wc-status',
						'tab'             => 'paypal-agentic',
						'inspect_session' => $session_id,
						'inspect_nonce'   => wp_create_nonce( 'ppcp_inspect_session_' . $session_id ),
					),
					admin_url( 'admin.php' )
				);

				$is_inspecting = $inspect_session_id === $session_id;
				?>
				<tr <?php echo $is_inspecting ? 'style="background: #f0f6fc;"' : ''; ?>>
					<td>
						<code style="font-size: 11px;">
							<?php echo esc_html( substr( $session_id, 0, 20 ) ?: $session_id ); ?>...
						</code>
					</td>
					<td><?php echo esc_html( (string) $session['item_count'] ); ?></td>
					<td><?php echo esc_html( $created_time ); ?></td>
					<td><?php echo esc_html( $modified_time ); ?></td>
					<td><?php echo esc_html( (string) $age_hours ); ?>h</td>
					<td>
						<?php if ( $is_inspecting ) : ?>
							<a
								href="<?php echo esc_url( admin_url( 'admin.php?page=wc-status&tab=paypal-agentic' ) ); ?>"
								class="button button-small"
							>
								<?php esc_html_e( 'Close', 'woocommerce-paypal-payments' ); ?>
							</a>
						<?php else : ?>
							<a
								href="<?php echo esc_url( $inspect_url ); ?>"
								class="button button-small"
							>
								<?php esc_html_e( 'Inspect', 'woocommerce-paypal-payments' ); ?>
							</a>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render detailed session information.
	 *
	 * @param string $session_id The session ID to inspect.
	 */
	private function render_session_details( string $session_id ): void {
		$details = $this->inspector->inspect_cart_session( $session_id );

		if ( ! $details ) {
			?>
			<div class="notice notice-error inline" style="margin-bottom: 20px;">
				<p><?php esc_html_e( 'Session not found or has expired.', 'woocommerce-paypal-payments' ); ?></p>
			</div>
			<?php
			return;
		}

		$cart     = $details['cart'];
		$ec_token = $details['ec_token'];
		$created  = $details['created'] ? ( wp_date( 'Y-m-d H:i:s', $details['created'] ) ?: '-' ) : '-';
		$modified = $details['modified'] ? ( wp_date( 'Y-m-d H:i:s', $details['modified'] ) ?: '-' ) : '-';
		$expires  = $details['expires'] ? ( wp_date( 'Y-m-d H:i:s', $details['expires'] ) ?: '-' ) : '-';

		?>
		<div style="margin-bottom: 30px; padding: 20px; background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
				<h3 style="margin: 0;"><?php esc_html_e( 'Inspecting Session', 'woocommerce-paypal-payments' ); ?></h3>
				<a
					href="<?php echo esc_url( admin_url( 'admin.php?page=wc-status&tab=paypal-agentic' ) ); ?>"
					class="button"
				>
					<?php esc_html_e( 'Close', 'woocommerce-paypal-payments' ); ?>
				</a>
			</div>

			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
				<div>
					<h4 style="margin: 0 0 10px 0; color: #1d2327;"><?php esc_html_e( 'Session Metadata', 'woocommerce-paypal-payments' ); ?></h4>
					<table class="wc_status_table widefat">
						<tbody>
						<tr>
							<td><?php esc_html_e( 'Session ID', 'woocommerce-paypal-payments' ); ?>:</td>
							<td><code style="font-size: 11px;"><?php echo esc_html( $session_id ); ?></code></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'EC Token', 'woocommerce-paypal-payments' ); ?>:</td>
							<td><code><?php echo esc_html( $ec_token ?: '-' ); ?></code></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Created', 'woocommerce-paypal-payments' ); ?>:</td>
							<td><?php echo esc_html( $created ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Modified', 'woocommerce-paypal-payments' ); ?>:</td>
							<td><?php echo esc_html( $modified ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Expires', 'woocommerce-paypal-payments' ); ?>:</td>
							<td><?php echo esc_html( $expires ); ?></td>
						</tr>
						</tbody>
					</table>
				</div>

				<div>
					<h4 style="margin: 0 0 10px 0; color: #1d2327;"><?php esc_html_e( 'Cart Totals', 'woocommerce-paypal-payments' ); ?></h4>
					<table class="wc_status_table widefat">
						<tbody>
						<tr>
							<td><?php esc_html_e( 'Subtotal', 'woocommerce-paypal-payments' ); ?>:</td>
							<td><?php echo esc_html( $cart->totals->subtotal . ' ' . $cart->currency ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Shipping', 'woocommerce-paypal-payments' ); ?>:</td>
							<td><?php echo esc_html( $cart->totals->shipping . ' ' . $cart->currency ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Tax', 'woocommerce-paypal-payments' ); ?>:</td>
							<td><?php echo esc_html( $cart->totals->tax . ' ' . $cart->currency ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Discount', 'woocommerce-paypal-payments' ); ?>:</td>
							<td><?php echo esc_html( $cart->totals->discount . ' ' . $cart->currency ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Total', 'woocommerce-paypal-payments' ); ?>:</strong></td>
							<td><strong><?php echo esc_html( $cart->totals->total . ' ' . $cart->currency ); ?></strong></td>
						</tr>
						</tbody>
					</table>
				</div>
			</div>

			<h4 style="margin: 20px 0 10px 0; color: #1d2327;"><?php esc_html_e( 'Cart Items', 'woocommerce-paypal-payments' ); ?></h4>
			<table class="wc_status_table widefat">
				<thead>
				<tr>
					<th><?php esc_html_e( 'Product', 'woocommerce-paypal-payments' ); ?></th>
					<th><?php esc_html_e( 'SKU', 'woocommerce-paypal-payments' ); ?></th>
					<th style="text-align: center;"><?php esc_html_e( 'Quantity', 'woocommerce-paypal-payments' ); ?></th>
					<th style="text-align: right;"><?php esc_html_e( 'Price', 'woocommerce-paypal-payments' ); ?></th>
					<th style="text-align: right;"><?php esc_html_e( 'Total', 'woocommerce-paypal-payments' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<?php foreach ( $cart->items as $item ) : ?>
					<tr>
						<td>
							<?php echo esc_html( $item->name ); ?>
							<?php if ( ! empty( $item->image_url ) ) : ?>
								<br><small><a href="<?php echo esc_url( $item->image_url ); ?>" target="_blank"><?php esc_html_e( 'View image', 'woocommerce-paypal-payments' ); ?></a></small>
							<?php endif; ?>
						</td>
						<td><code><?php echo esc_html( $item->sku ?: '-' ); ?></code></td>
						<td style="text-align: center;"><?php echo esc_html( $item->quantity ); ?></td>
						<td style="text-align: right;"><?php echo esc_html( $item->unit_amount . ' ' . $cart->currency ); ?></td>
						<td style="text-align: right;">
							<strong><?php echo esc_html( ( $item->unit_amount * $item->quantity ) . ' ' . $cart->currency ); ?></strong>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( ! empty( $cart->shipping_address ) ) : ?>
				<h4 style="margin: 20px 0 10px 0; color: #1d2327;"><?php esc_html_e( 'Shipping Address', 'woocommerce-paypal-payments' ); ?></h4>
				<div style="padding: 15px; background: #f9f9f9; border: 1px solid #ddd;">
					<?php
					$address = $cart->shipping_address;
					echo esc_html( $address->name ?? '' );
					if ( ! empty( $address->address_line_1 ) ) {
						echo '<br>' . esc_html( $address->address_line_1 );
					}
					if ( ! empty( $address->address_line_2 ) ) {
						echo '<br>' . esc_html( $address->address_line_2 );
					}
					if ( ! empty( $address->city ) || ! empty( $address->state ) || ! empty( $address->postal_code ) ) {
						echo '<br>' . esc_html(
							implode(
								', ',
								array_filter(
									array(
										$address->city ?? '',
										$address->state ?? '',
										$address->postal_code ?? '',
									)
								)
							)
						);
					}
					if ( ! empty( $address->country_code ) ) {
						echo '<br>' . esc_html( $address->country_code );
					}
					?>
				</div>
			<?php endif; ?>

			<div style="margin-top: 20px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107;">
				<strong><?php esc_html_e( 'Note:', 'woocommerce-paypal-payments' ); ?></strong>
				<?php esc_html_e( 'This is a read-only view of the cart session for debugging purposes.', 'woocommerce-paypal-payments' ); ?>
			</div>
		</div>
		<?php
	}
}
