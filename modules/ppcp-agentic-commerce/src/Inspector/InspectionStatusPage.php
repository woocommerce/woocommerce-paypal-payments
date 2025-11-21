<?php
/**
 * PayPal Agentic Commerce Status Tab
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Inspector
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Inspector;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\AgenticCommerce\Registration\RegistrationService;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\AgenticCommerce\Registration\RegistrationEligibility;

/**
 * Class InspectionStatusPage
 *
 * Adds a custom tab to WooCommerce → Status page displaying PayPal Agentic Commerce
 * registration status with manual toggle capability. Owns and initializes the form handler.
 */
class InspectionStatusPage {

	private InspectionFormHandler $form_handler;
	private RegistrationService $registration_service;
	private RegistrationEligibility $eligibility_check;
	private GeneralSettings $general_settings;

	public function __construct(
		InspectionFormHandler $form_handler,
		RegistrationService $registration_service,
		RegistrationEligibility $eligibility_check,
		GeneralSettings $general_settings
	) {

		$this->form_handler         = $form_handler;
		$this->registration_service = $registration_service;
		$this->eligibility_check    = $eligibility_check;
		$this->general_settings     = $general_settings;
	}

	/**
	 * Initialize the status tab and form handler by registering WordPress hooks.
	 */
	public function init(): void {
		$this->form_handler->init();

		add_filter( 'woocommerce_admin_status_tabs', fn( array $tabs ) => $this->add_tab( $tabs ), 99 );
		add_action( 'woocommerce_admin_status_content_paypal-agentic', fn() => $this->render_content() );
	}

	/**
	 * Add PayPal Agentic tab to WooCommerce status tabs.
	 *
	 * @param array $tabs Existing status tabs.
	 * @return array Modified tabs array with PayPal Agentic tab added.
	 */
	private function add_tab( array $tabs ): array {
		$tabs['paypal-agentic'] = __( 'PayPal Agentic', 'woocommerce-paypal-payments' );

		return $tabs;
	}

	/**
	 * Render the tab content.
	 */
	private function render_content(): void {
		$is_eligible   = $this->eligibility_check->is_eligible();
		$is_registered = $this->registration_service->is_registered();
		$merchant_id   = $this->general_settings->get_merchant_id();

		?>
		<div class="wrap">
			<h2><?php esc_html_e( 'PayPal Agentic Commerce', 'woocommerce-paypal-payments' ); ?></h2>

			<?php $this->render_notices(); ?>

			<table class="wc_status_table widefat">
				<thead>
				<tr>
					<th colspan="3">
						<?php esc_html_e( 'Registration Status', 'woocommerce-paypal-payments' ); ?>
					</th>
				</tr>
				</thead>
				<tbody>
				<tr>
					<td>
						<?php esc_html_e( 'Eligible', 'woocommerce-paypal-payments' ); ?>:
					</td>
					<td class="help">
						<?php $this->render_help( __( 'Whether this store can use agentic commerce features', 'woocommerce-paypal-payments' ) ); ?>
					</td>
					<td>
						<?php
						$this->render_boolean_badge(
							$is_eligible,
							esc_html__( 'Eligible', 'woocommerce-paypal-payments' ),
							esc_html__( 'Not eligible', 'woocommerce-paypal-payments' )
						);
						?>
					</td>
				</tr>
				<tr>
					<td>
						<?php esc_html_e( 'Status', 'woocommerce-paypal-payments' ); ?>:
					</td>
					<td class="help">
						<?php $this->render_help( __( 'Is the store registered with the joinhoney service?', 'woocommerce-paypal-payments' ) ); ?>
					</td>
					<td>
						<div style="display: flex; align-items: center; gap: 12px;">
							<?php
							$this->render_boolean_badge(
								$is_registered,
								esc_html__( 'Registered', 'woocommerce-paypal-payments' ),
								esc_html__( 'Not registered', 'woocommerce-paypal-payments' )
							);
							?>
							<?php $this->render_toggle_form( $is_registered ); ?>
						</div>
					</td>
				</tr>
				<?php if ( $is_registered && $merchant_id ) : ?>
					<tr>
						<td>
							<?php esc_html_e( 'Merchant ID', 'woocommerce-paypal-payments' ); ?>:
						</td>
						<td class="help"></td>
						<td><code><?php echo esc_html( $merchant_id ); ?></code></td>
					</tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function render_help( string $label ): void {
		if ( ! $label ) {
			return;
		}

		echo wp_kses_post( sprintf( '<span class="woocommerce-help-tip" tabindex="0" title="%s"></span>', esc_attr( $label ) ) );
	}

	private function render_boolean_badge( bool $is_true, string $label_true, string $label_false ): void {
		if ( $is_true ) {
			echo wp_kses_post( sprintf( '<mark class="yes"><span class="dashicons dashicons-yes"></span> %s</mark>', $label_true ) );

			return;
		}

		echo wp_kses_post( sprintf( '<mark class="no"><span class="dashicons dashicons-minus"></span> %s</mark>', $label_false ) );
	}

	/**
	 * Render the toggle form.
	 *
	 * @param bool $is_registered Whether the merchant is registered.
	 */
	private function render_toggle_form( bool $is_registered ): void {
		if ( $is_registered ) {
			$action      = 'unregister';
			$button_text = __( 'Unregister', 'woocommerce-paypal-payments' );
		} else {
			$action      = 'register';
			$button_text = __( 'Register', 'woocommerce-paypal-payments' );
		}

		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'ppcp_agentic_toggle_nonce', 'ppcp_agentic_nonce' ); ?>
			<input type="hidden" name="action" value="ppcp_agentic_toggle_registration" />
			<input type="hidden" name="toggle_action" value="<?php echo esc_attr( $action ); ?>" />
			<button type="submit" class="button button-secondary">
				<?php echo esc_html( $button_text ); ?>
			</button>
		</form>
		<?php
	}

	/**
	 * Render admin notices based on URL parameters.
	 */
	private function render_notices(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['ppcp_agentic_notice'] ) || ! is_string( $_GET['ppcp_agentic_notice'] ) ) {
			return;
		}

		$notice_type = sanitize_text_field( wp_unslash( $_GET['ppcp_agentic_notice'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$messages = array(
			'registered'   => __( 'Successfully registered with PayPal Agentic Commerce.', 'woocommerce-paypal-payments' ),
			'unregistered' => __( 'Successfully unregistered from PayPal Agentic Commerce.', 'woocommerce-paypal-payments' ),
			'error'        => __( 'Failed to update registration status. Please try again.', 'woocommerce-paypal-payments' ),
		);

		if ( ! isset( $messages[ $notice_type ] ) ) {
			return;
		}

		$class = $notice_type === 'error' ? 'error' : 'updated';
		?>
		<div class="<?php echo esc_attr( $class ); ?> notice is-dismissible">
			<p><?php echo esc_html( $messages[ $notice_type ] ); ?></p>
		</div>
		<?php
	}
}
