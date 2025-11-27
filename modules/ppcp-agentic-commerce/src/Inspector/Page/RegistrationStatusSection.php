<?php
/**
 * Registration Status Section
 *
 * Handles the display of PayPal Agentic Commerce registration status.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Inspector\Page
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Inspector\Page;

use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;

use WooCommerce\PayPalCommerce\AgenticCommerce\Auth\AuthServiceProvider;
use WooCommerce\PayPalCommerce\AgenticCommerce\Registration\RegistrationEligibility;
use WooCommerce\PayPalCommerce\AgenticCommerce\Registration\RegistrationService;

/**
 * Class RegistrationStatusSection
 *
 * Renders the registration status information and controls.
 */
class RegistrationStatusSection {

	use StatusTableRenderer;

	private RegistrationService $registration_service;
	private RegistrationEligibility $eligibility_check;
	private AuthServiceProvider $auth_provider;
	private GeneralSettings $general_settings;

	/**
	 * Constructor.
	 *
	 * @param RegistrationService     $registration_service Service for managing registration.
	 * @param RegistrationEligibility $eligibility_check    Checks if store is eligible.
	 * @param AuthServiceProvider     $auth_provider        Provides auth service info.
	 * @param GeneralSettings         $general_settings     Access to merchant settings.
	 */
	public function __construct(
		RegistrationService $registration_service,
		RegistrationEligibility $eligibility_check,
		AuthServiceProvider $auth_provider,
		GeneralSettings $general_settings
	) {

		$this->registration_service = $registration_service;
		$this->eligibility_check    = $eligibility_check;
		$this->auth_provider        = $auth_provider;
		$this->general_settings     = $general_settings;
	}

	/**
	 * Render the registration status section.
	 */
	public function render(): void {
		$is_eligible   = $this->eligibility_check->is_eligible();
		$is_registered = $this->registration_service->is_registered();
		$auth_service  = $this->auth_provider->auth_service();

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
						<?php esc_html_e( 'JWK Auth Service', 'woocommerce-paypal-payments' ); ?>:
					</td>
					<td class="help">
						<?php $this->render_help( __( 'Which implementation verifies the JWK token?', 'woocommerce-paypal-payments' ) ); ?>
					</td>
					<td>
						<?php echo wp_kses_post( sprintf( '<code>%s</code>', get_class( $auth_service ) ) ); ?>
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

				<?php $this->render_registration_data(); ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function render_registration_data(): void {
		$metadata = $this->registration_service->get_registration_data();

		// Meta-data value is "null" when not registered.
		if ( ! $metadata ) {
			return;
		}

		$woo_config         = $this->general_settings->get_woo_settings();
		$onboarded_merchant = $this->general_settings->get_merchant_id();
		$store_identifier   = $metadata['wooSydeCommerceId'] ?? '?';
		$merchant_id        = $metadata['paypalMerchantId'] ?? '?';
		$store_country      = $metadata['country'] ?? '?';
		$store_currency     = $metadata['currency'] ?? '?';
		$shipping_countries = (array) ( $metadata['shippingCountries'] ?? array() );
		?>
		<tr>
			<td>
				<?php esc_html_e( 'Store URL', 'woocommerce-paypal-payments' ); ?>:
			</td>
			<td class="help">
				<?php $this->render_help( __( 'This store is identified using that URL. It should not change!', 'woocommerce-paypal-payments' ) ); ?>
			</td>
			<td>
				<code><?php echo esc_html( $store_identifier ); ?></code>
			</td>
		</tr>
		<tr>
			<td>
				<?php esc_html_e( 'Merchant ID', 'woocommerce-paypal-payments' ); ?>:
			</td>
			<td class="help"></td>
			<td>
				<?php $this->render_with_validation( $merchant_id, $onboarded_merchant ); ?>
			</td>
		</tr>
		<tr>
			<td>
				<?php esc_html_e( 'Store Country / Currency', 'woocommerce-paypal-payments' ); ?>:
			</td>
			<td class="help"></td>
			<td>
				<?php $this->render_with_validation( $store_country, $woo_config['country'] ); ?> /
				<?php $this->render_with_validation( $store_currency, $woo_config['currency'] ); ?>
			</td>
		</tr>
		<tr>
			<td>
				<?php esc_html_e( 'Shipping Countries', 'woocommerce-paypal-payments' ); ?>:
			</td>
			<td class="help"></td>
			<td>
				<?php echo esc_html( implode( ', ', $shipping_countries ) ); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render the toggle form for registration/unregistration.
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
