<?php
/**
 * Renders the settings of the Gateways.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Settings
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Settings;

use WooCommerce\PayPalCommerce\AdminNotices\Entity\Message;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\Button\Helper\MessagesApply;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\DCCProductStatus;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;

/**
 * Class SettingsRenderer
 */
class SettingsRenderer {

	use PageMatcherTrait;

	/**
	 * The Settings status helper.
	 *
	 * @var SettingsStatus
	 */
	protected $settings_status;

	/**
	 * The api shop country.
	 *
	 * @var string
	 */
	protected $api_shop_country;

	/**
	 * The settings.
	 *
	 * @var ContainerInterface
	 */
	private $settings;

	/**
	 * The current onboarding state.
	 *
	 * @var State
	 */
	private $state;

	/**
	 * The setting fields.
	 *
	 * @var array
	 */
	private $fields;

	/**
	 * Helper to see if DCC gateway can be shown.
	 *
	 * @var DccApplies
	 */
	private $dcc_applies;

	/**
	 * Helper to see if messages are supposed to show up.
	 *
	 * @var MessagesApply
	 */
	private $messages_apply;

	/**
	 * The DCC Product Status.
	 *
	 * @var DCCProductStatus
	 */
	private $dcc_product_status;

	/**
	 * ID of the current PPCP gateway settings page, or empty if it is not such page.
	 *
	 * @var string
	 */
	protected $page_id;

	/**
	 * SettingsRenderer constructor.
	 *
	 * @param ContainerInterface $settings The Settings.
	 * @param State              $state The current state.
	 * @param array              $fields The setting fields.
	 * @param DccApplies         $dcc_applies Whether DCC gateway can be shown.
	 * @param MessagesApply      $messages_apply Whether messages can be shown.
	 * @param DCCProductStatus   $dcc_product_status The product status.
	 * @param SettingsStatus     $settings_status The Settings status helper.
	 * @param string             $page_id ID of the current PPCP gateway settings page, or empty if it is not such page.
	 * @param string             $api_shop_country The api shop country.
	 */
	public function __construct(
		ContainerInterface $settings,
		State $state,
		array $fields,
		DccApplies $dcc_applies,
		MessagesApply $messages_apply,
		DCCProductStatus $dcc_product_status,
		SettingsStatus $settings_status,
		string $page_id,
		string $api_shop_country
	) {

		$this->settings           = $settings;
		$this->state              = $state;
		$this->fields             = $fields;
		$this->dcc_applies        = $dcc_applies;
		$this->messages_apply     = $messages_apply;
		$this->dcc_product_status = $dcc_product_status;
		$this->settings_status    = $settings_status;
		$this->page_id            = $page_id;
		$this->api_shop_country   = $api_shop_country;
	}

	/**
	 * Returns notices list.
	 *
	 * @return array
	 */
	public function messages() : array {

		$messages = array();

        //phpcs:disable WordPress.Security.NonceVerification.Recommended
        //phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_GET['ppcp-onboarding-error'] ) || ! empty( $_POST ) ) {
			return $messages;
		}
		//phpcs:enable WordPress.Security.NonceVerification.Recommended
		//phpcs:enable WordPress.Security.NonceVerification.Missing

		$messages[] = new Message(
			__(
				'We could not complete the onboarding process. Some features, such as card processing, will not be available. To fix this, please try again.',
				'woocommerce-paypal-payments'
			),
			'error',
			false
		);

		return $messages;
	}

	/**
	 * Check whether vaulting is enabled.
	 *
	 * @return bool
	 */
	private function paypal_vaulting_is_enabled(): bool {
		return $this->settings->has( 'vault_enabled' ) && (bool) $this->settings->get( 'vault_enabled' );
	}

	/**
	 * Check if current screen is Standard Payments settings screen.
	 *
	 * @return bool Whether is Standard Payments screen or not.
	 */
	private function is_paypal_checkout_screen(): bool {
		return PayPalGateway::ID === $this->page_id;
	}

	/**
	 * Renders the multiselect field.
	 *
	 * @param string $field The current field HTML.
	 * @param string $key   The current key.
	 * @param array  $config The configuration array.
	 * @param string $value The current value.
	 *
	 * @return string
	 */
	public function render_multiselect( $field, $key, $config, $value ): string {

		if ( 'ppcp-multiselect' !== $config['type'] ) {
			return $field;
		}

		$options = array();
		foreach ( $config['options'] as $option_key => $option_value ) {
			$selected = ( in_array( $option_key, $value, true ) ) ? 'selected="selected"' : '';

			$options[] = '<option value="' . esc_attr( $option_key ) . '" ' . $selected . '>' .
			esc_html( $option_value ) .
			'</option>';
		}

		$html = sprintf(
			'<select
                        multiple
                         class="%s"
                         name="%s"
                     >%s</select>',
			esc_attr( implode( ' ', isset( $config['input_class'] ) ? $config['input_class'] : array() ) ),
			esc_attr( $key ) . '[]',
			implode( '', $options )
		);

		return $html;
	}

	/**
	 * Renders the password input field.
	 *
	 * @param string $field The current field HTML.
	 * @param string $key   The current key.
	 * @param array  $config The configuration array.
	 * @param string $value The current value.
	 *
	 * @return string
	 */
	public function render_password( $field, $key, $config, $value ): string {

		if ( 'ppcp-password' !== $config['type'] ) {
			return $field;
		}

		$html = sprintf(
			'<input
                        type="password"
                        autocomplete="new-password"
                        class="%s"
                        name="%s"
                        value="%s"
                     >',
			esc_attr( implode( ' ', $config['class'] ) ),
			esc_attr( $key ),
			esc_attr( $value )
		);

		return $html;
	}

	/**
	 * Renders the heading field.
	 *
	 * @param string $field The current field HTML.
	 * @param string $key   The current key.
	 * @param array  $config The configuration array.
	 * @param string $value The current value.
	 *
	 * @return string
	 */
	public function render_heading( $field, $key, $config, $value ): string {

		if ( 'ppcp-heading' !== $config['type'] ) {
			return $field;
		}

		$html = sprintf(
			'<h3 class="wc-settings-sub-title %s">%s</h3>',
			esc_attr( implode( ' ', $config['class'] ) ),
			esc_html( $config['heading'] )
		);

		return $html;
	}

	/**
	 * Renders the table row.
	 *
	 * @param array  $data Values of the row cells.
	 * @param string $tag HTML tag ('td', 'th').
	 * @return string
	 */
	public function render_table_row( array $data, string $tag = 'td' ): string {
		$cells = array_map(
			function ( $value ) use ( $tag ): string {
				return "<$tag>" . (string) $value . "</$tag>";
			},
			$data
		);
		return '<tr>' . implode( $cells ) . '</tr>';
	}

	/**
	 * Renders the table field.
	 *
	 * @param string $field The current field HTML.
	 * @param string $key   The key.
	 * @param array  $config The configuration of the field.
	 * @param array  $value The current value.
	 *
	 * @return string HTML.
	 */
	public function render_table( $field, $key, $config, $value ): string {
		if ( 'ppcp-table' !== $config['type'] ) {
			return $field;
		}

		$data = $value['data'];
		if ( empty( $data ) ) {
			$empty_placeholder = $value['empty_placeholder'] ?? ( $config['empty_placeholder'] ?? null );
			if ( $empty_placeholder ) {
				return $empty_placeholder;
			}
		}

		$header_row_html = $this->render_table_row( $value['headers'], 'th' );
		$data_rows_html  = implode(
			array_map(
				array( $this, 'render_table_row' ),
				$data
			)
		);

		return "<table>
$header_row_html
$data_rows_html
</table>";
	}

	/**
	 * Renders the settings.
	 */
	public function render(): void {

		$is_dcc = CreditCardGateway::ID === $this->page_id;
		//phpcs:enable WordPress.Security.NonceVerification.Recommended
		//phpcs:enable WordPress.Security.NonceVerification.Missing
		$nonce = wp_create_nonce( SettingsListener::NONCE );
		?>
		<input type="hidden" name="ppcp-nonce" value="<?php echo esc_attr( $nonce ); ?>">
		<?php

		// Create a hidden first row with 2 cells to avoid issues with table-layout: fixed
		// when the first visible row needs to have one cell.
		?>
		<tr style="height: 1px; padding-top: 0; padding-bottom: 0;">
			<th style="padding-top: 0; padding-bottom: 0;"></th>
			<td style="padding-top: 0; padding-bottom: 0;"></td>
		</tr>
		<?php

		foreach ( $this->fields as $field => $config ) :
			if ( ! in_array( $this->state->environment_state( $config['state_from'] ?? null ), $config['screens'], true ) ) {
				continue;
			}
			if ( ! $this->field_matches_page( $config, $this->page_id ) ) {
				continue;
			}
			if (
				in_array( 'dcc', $config['requirements'], true )
				&& ! $this->dcc_applies->for_country_currency()
			) {
				continue;
			}
			if (
				in_array( 'messages', $config['requirements'], true )
				&& ! $this->messages_apply->for_country()
			) {
				continue;
			}
			if (
				in_array( 'pui_ready', $config['requirements'], true )
				&& $this->api_shop_country !== 'DE'
			) {
				continue;
			}
			$value        = $this->settings->has( $field ) ? $this->settings->get( $field ) : ( isset( $config['value'] ) ? $config['value']() : null );
			$key          = 'ppcp[' . $field . ']';
			$id           = 'ppcp-' . $field;
			$config['id'] = $id;
			$colspan      = ( 'ppcp-heading' !== $config['type'] && isset( $config['title'] ) ) ? 1 : 2;
			$classes      = isset( $config['classes'] ) ? $config['classes'] : array();
			$classes[]    = 'ppcp-settings-field';
			$classes[]    = sprintf( 'ppcp-settings-field-%s', str_replace( 'ppcp-', '', $config['type'] ) );
			if ( 1 !== $colspan ) {
				$classes[] = 'ppcp-settings-no-title-col';
			}
			$description = isset( $config['description'] ) ? $config['description'] : '';
			unset( $config['description'] );
			?>
		<tr valign="top" id="<?php echo esc_attr( 'field-' . $field ); ?>" class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
			<?php if ( 'ppcp-heading' !== $config['type'] && isset( $config['title'] ) ) : ?>
			<th scope="row">
				<label
					for="<?php echo esc_attr( $id ); ?>"
				><?php echo esc_html( $config['title'] ); ?></label>
				<?php if ( isset( $config['desc_tip'] ) && $config['desc_tip'] ) : ?>
				<span
						class="woocommerce-help-tip"
						data-tip="<?php echo esc_attr( $description ); ?>"
				></span>
					<?php
					$description = '';
				endif;
				?>
			</th>
			<?php endif; ?>
			<td colspan="<?php echo (int) $colspan; ?>">
					<?php
					'ppcp-text' === $config['type'] ?
					$this->render_text( $config )
					: woocommerce_form_field( $key, $config, $value );
					?>

				<?php if ( $description ) : ?>
				<p class="<?php echo 'ppcp-heading' === $config['type'] ? '' : 'description'; ?>"><?php echo wp_kses_post( $description ); ?></p>
				<?php endif; ?>

				<?php if ( isset( $config['description_with_tip'] ) && $config['description_with_tip'] ) : ?>
					<p class="<?php echo 'description'; ?>"><?php echo wp_kses_post( $config['description_with_tip'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
			<?php
		endforeach;
		if ( $is_dcc ) {
			if ( $this->dcc_applies->for_country_currency() ) {
				if ( State::STATE_ONBOARDED > $this->state->current_state() ) {
					$this->render_dcc_onboarding_info();
				} elseif ( ! $this->dcc_product_status->dcc_is_active() ) {
					$this->render_dcc_not_active_yet();
				}
			} else {
				$this->render_dcc_does_not_apply_info();
			}
		}
	}

	/**
	 * Renders the ppcp-text field given a configuration.
	 *
	 * @param array $config The configuration array.
	 */
	private function render_text( array $config ) {
		$raw = $config['raw'] ?? false;
		if ( $raw ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $config['text'];
		} else {
			echo wp_kses_post( $config['text'] );
		}

		if ( isset( $config['hidden'] ) ) {
			$value = $this->settings->has( $config['hidden'] ) ?
				(string) $this->settings->get( $config['hidden'] )
				: '';
			echo ' <input
                    type = "hidden"
                    name = "ppcp[' . esc_attr( $config['hidden'] ) . ']"
                    value = "' . esc_attr( $value ) . '"
                    > ';
		}
	}

	/**
	 * Renders the information that the PayPal account can not yet process DCC.
	 */
	private function render_dcc_not_active_yet() {
		?>
		<tr>
			<th><?php esc_html_e( 'Onboarding', 'woocommerce-paypal-payments' ); ?></th>
			<td class="notice notice-error">
				<p>
					<?php
					esc_html_e(
						'Credit Card processing for your account has not yet been activated by PayPal. If your account is new, this can take some days. Otherwise, please get in contact with PayPal.',
						'woocommerce-paypal-payments'
					);
					?>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renders the DCC onboarding info.
	 */
	private function render_dcc_onboarding_info(): void {
		?>
<tr>
	<th><?php esc_html_e( 'Onboarding', 'woocommerce-paypal-payments' ); ?></th>
<td class="notice notice-error">
	<p>
		<?php
			esc_html_e(
				'You need to complete your onboarding, before you can use the Advanced Card Processing option.',
				'woocommerce-paypal-payments'
			);
		?>

		<a
			href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway' ) ); ?>"
			>
			<?php esc_html_e( 'Click here to complete your onboarding.', 'woocommerce-paypal-payments' ); ?>
		</a>
	</p>
</td>
</tr>
		<?php
	}

	/**
	 * Renders the info, that DCC is not available in the merchant's country.
	 */
	private function render_dcc_does_not_apply_info() {
		?>
		<tr>
			<th><?php esc_html_e( 'Card Processing not available', 'woocommerce-paypal-payments' ); ?></th>
			<td class="notice notice-error">
				<p>
					<?php
					esc_html_e(
						'Unfortunately, the card processing option is not yet available in your country.',
						'woocommerce-paypal-payments'
					);
					?>
					<a href="https://developer.paypal.com/docs/checkout/advanced/currency-availability-advanced-cards/">
						<?php
						esc_html_e(
							'Advanced credit and debit country and currency availability.',
							'woocommerce-paypal-payments'
						);
						?>
					</a>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Checks if vaulting admin message can be displayed.
	 *
	 * @return bool Whether the message can be displayed or not.
	 */
	private function can_display_vaulting_admin_message(): bool {
		if ( State::STATE_ONBOARDED !== $this->state->current_state() ) {
			return false;
		}

		return $this->is_paypal_checkout_screen()
			&& ( $this->paypal_vaulting_is_enabled() || $this->settings_status->is_pay_later_messaging_enabled() );
	}
}

