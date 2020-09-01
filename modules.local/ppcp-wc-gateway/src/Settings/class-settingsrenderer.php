<?php
/**
 * Renders the settings of the Gateways.
 *
 * @package Inpsyde\PayPalCommerce\WcGateway\Settings
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Settings;

use Inpsyde\PayPalCommerce\ApiClient\Helper\DccApplies;
use Inpsyde\PayPalCommerce\Button\Helper\MessagesApply;
use Inpsyde\PayPalCommerce\Onboarding\State;
use Psr\Container\ContainerInterface;

/**
 * Class SettingsRenderer
 */
class SettingsRenderer {

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
	 * SettingsRenderer constructor.
	 *
	 * @param ContainerInterface $settings The Settings.
	 * @param State              $state                 The current state.
	 * @param array              $fields                The setting fields.
	 * @param DccApplies         $dcc_applies       Whether DCC gateway can be shown.
	 * @param MessagesApply      $messages_apply Whether messages can be shown.
	 */
	public function __construct(
		ContainerInterface $settings,
		State $state,
		array $fields,
		DccApplies $dcc_applies,
		MessagesApply $messages_apply
	) {

		$this->settings       = $settings;
		$this->state          = $state;
		$this->fields         = $fields;
		$this->dcc_applies    = $dcc_applies;
		$this->messages_apply = $messages_apply;
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
			esc_attr( implode( ' ', $config['class'] ) ),
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
	 * Renders the text input field.
	 *
	 * @param string $field The current field HTML.
	 * @param string $key   The current key.
	 * @param array  $config The configuration array.
	 * @param string $value The current value.
	 *
	 * @return string
	 */
	public function render_text_input( $field, $key, $config, $value ): string {

		if ( 'ppcp-text-input' !== $config['type'] ) {
			return $field;
		}

		$html = sprintf(
			'<input
                        type="text"
                        autocomplete="off"
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
			'<h3 class="%s">%s</h3>',
			esc_attr( implode( ' ', $config['class'] ) ),
			esc_html( $config['heading'] )
		);

		return $html;
	}

	/**
	 * Renders the settings.
	 *
	 * @param bool $is_dcc Whether it is the DCC gateway or not.
	 */
	public function render( bool $is_dcc ) {

		$nonce = wp_create_nonce( SettingsListener::NONCE );
		?>
		<input type="hidden" name="ppcp-nonce" value="<?php echo esc_attr( $nonce ); ?>">
		<?php
		foreach ( $this->fields as $field => $config ) :
			if ( ! in_array( $this->state->current_state(), $config['screens'], true ) ) {
				continue;
			}
			if ( $is_dcc && ! in_array( $config['gateway'], array( 'all', 'dcc' ), true ) ) {
				continue;
			}
			if ( ! $is_dcc && ! in_array( $config['gateway'], array( 'all', 'paypal' ), true ) ) {
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
			$value        = $this->settings->has( $field ) ? $this->settings->get( $field ) : null;
			$key          = 'ppcp[' . $field . ']';
			$id           = 'ppcp-' . $field;
			$config['id'] = $id;
			$th_td        = 'ppcp-heading' !== $config['type'] ? 'td' : 'th';
			$colspan      = 'ppcp-heading' !== $config['type'] ? 1 : 2;

			?>
		<tr valign="top" id="<?php echo esc_attr( 'field-' . $field ); ?>">
			<?php if ( 'ppcp-heading' !== $config['type'] ) : ?>
			<th>
				<label
					for="<?php echo esc_attr( $id ); ?>"
				><?php echo esc_html( $config['title'] ); ?></label>
				<?php if ( isset( $config['desc_tip'] ) && $config['desc_tip'] ) : ?>
				<span
						class="woocommerce-help-tip"
						data-tip="<?php echo esc_attr( $config['description'] ); ?>"
				></span>
					<?php
					unset( $config['description'] );
				endif;
				?>
			</th>
			<?php endif; ?>
			<<?php echo esc_attr( $th_td ); ?> colspan="<?php echo (int) $colspan; ?>">
						<?php
						'ppcp-text' === $config['type'] ?
						$this->render_text( $config )
						: woocommerce_form_field( $key, $config, $value );
						?>
			</<?php echo esc_attr( $th_td ); ?>>
		</tr>
			<?php
		endforeach;

		if ( $is_dcc ) :
			?>
		<tr>
			<th><?php esc_html_e( '3D Secure', 'woocommerce-paypal-commerce-gateway' ); ?></th>
			<td>
				<p>
					<?php
					/**
					 * We still need to provide a docs link.
					 *
					 * @todo: Provide link to documentation.
					 */
					echo wp_kses_post(
						sprintf(
							// translators: %1$s and %2$s is a link tag.
							__(
								'3D Secure benefits cardholders and merchants by providing
                                  an additional layer of verification using Verified by Visa,
                                  MasterCard SecureCode and American Express SafeKey.
                                  %1$sLearn more about 3D Secure.%2$s',
								'woocommerce-paypal-commerce-gateway'
							),
							'<a href = "#">',
							'</a>'
						)
					);
					?>
				</p>
			</td>
		</tr>
			<?php
		endif;
	}

	/**
	 * Renders the ppcp-text field given a configuration.
	 *
	 * @param array $config The configuration array.
	 */
	private function render_text( array $config ) {
		echo wp_kses_post( $config['text'] );
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
}
