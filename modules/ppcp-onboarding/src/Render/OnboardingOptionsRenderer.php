<?php

/**
 * Renders the onboarding options.
 *
 * @package WooCommerce\PayPalCommerce\Onboarding\Render
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Onboarding\Render;

use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
/**
 * Class OnboardingRenderer
 */
class OnboardingOptionsRenderer
{
    /**
     * The module url.
     *
     * @var string
     */
    private $module_url;
    /**
     * 2-letter country code of the shop.
     *
     * @var string
     */
    private $country;
    /**
     * The settings.
     *
     * @var Settings
     */
    protected $settings;
    /**
     * OnboardingOptionsRenderer constructor.
     *
     * @param string   $module_url The module url (for assets).
     * @param string   $country 2-letter country code of the shop.
     * @param Settings $settings The settings.
     */
    public function __construct(string $module_url, string $country, Settings $settings)
    {
        $this->module_url = $module_url;
        $this->country = $country;
        $this->settings = $settings;
    }
    /**
     * Renders the onboarding options.
     *
     * @param bool $is_shop_supports_dcc Whether the shop can use DCC (country, currency).
     * @param bool $make_dcc_default Whether DCC should be selected by default.
     */
    public function render(bool $is_shop_supports_dcc, bool $make_dcc_default): string
    {
        $checked_cards = $is_shop_supports_dcc && !$make_dcc_default ? '' : 'checked';
        $on_boarding_options = '
			<li>
				<label><input type="checkbox" disabled checked> ' . __('Enable PayPal Payments — includes PayPal, Venmo, Pay Later — with fraud protection', 'woocommerce-paypal-payments') . '
				</label>
			</li>
			<li>
				<label><input type="checkbox" id="ppcp-onboarding-accept-cards" ' . $checked_cards . '> ' . __('Securely accept all major credit & debit cards on the strength of the PayPal network', 'woocommerce-paypal-payments') . '</label>
			</li>
			<li>' . $this->render_dcc($is_shop_supports_dcc, $make_dcc_default) . '</li>' . $this->render_pui_option();
        return ' <ul class="ppcp-onboarding-options">' . apply_filters('ppcp_onboarding_options', $on_boarding_options) . '</ul>';
    }
    /**
     * Renders pui option.
     *
     * @return string
     * @throws NotFoundException When setting is not found.
     */
    private function render_pui_option(): string
    {
        if ('DE' === $this->country) {
            $checked = '';
            try {
                $onboard_with_pui = $this->settings->get('ppcp-onboarding-pui');
                if ($onboard_with_pui === '1') {
                    $checked = 'checked';
                }
            } catch (NotFoundException $exception) {
                $checked = '';
            }
            return '<li><label><input type="checkbox" id="ppcp-onboarding-pui" ' . $checked . ' data-onboarding-option="ppcp-onboarding-pui"> ' . __('Onboard with Pay upon Invoice', 'woocommerce-paypal-payments') . '
		</label></li>';
        }
        return '';
    }
    /**
     * Renders the onboarding DCC options.
     *
     * @param bool $is_shop_supports_dcc Whether the shop can use DCC (country, currency).
     * @param bool $make_dcc_default Whether DCC should be selected by default.
     */
    private function render_dcc(bool $is_shop_supports_dcc, bool $make_dcc_default): string
    {
        $items = array();
        $is_us_shop = 'US' === $this->country;
        $basic_table_rows = array($this->render_table_row(__('Credit & Debit Card form fields', 'woocommerce-paypal-payments'), __('Prebuilt user experience', 'woocommerce-paypal-payments')), !$is_us_shop ? '' : $this->render_table_row(__('Credit & Debit Card pricing', 'woocommerce-paypal-payments'), __('3.49% + $0.49', 'woocommerce-paypal-payments'), '', __('for US domestic transactions', 'woocommerce-paypal-payments')), $this->render_table_row(__('Seller Protection', 'woocommerce-paypal-payments'), __('Yes', 'woocommerce-paypal-payments'), __('No matter what you sell, Seller Protection can help you avoid chargebacks, reversals, and fees on eligible PayPal payment transactions — even when a customer has filed a dispute.', 'woocommerce-paypal-payments'), __('for eligible PayPal transactions', 'woocommerce-paypal-payments')), $this->render_table_row(__('Seller Account Type', 'woocommerce-paypal-payments'), __('Business or Casual', 'woocommerce-paypal-payments'), __('For Standard payments, Casual sellers may connect their Personal PayPal account in eligible countries to sell on WooCommerce. For Advanced payments, a Business PayPal account is required.', 'woocommerce-paypal-payments')));
        $basic_table_rows = apply_filters('ppcp_onboarding_basic_table_rows', $basic_table_rows);
        $items[] = '
<li>
	<label>
		<input type="radio" id="ppcp-onboarding-dcc-basic" name="ppcp_onboarding_dcc" value="basic" checked ' . (!$is_shop_supports_dcc ? 'checked' : '') . ' data-screen-url="' . $this->get_screen_url('basic') . '"' . '> ' . __('Standard Card Processing', 'woocommerce-paypal-payments') . '
	</label>
	' . $this->render_tooltip(__('Card transactions are managed by PayPal, which simplifies compliance requirements for you.', 'woocommerce-paypal-payments')) . '
	<table>
		' . implode($basic_table_rows) . '
	</table>
</li>';
        if ($is_shop_supports_dcc) {
            $dcc_table_rows = array($this->render_table_row(__('Credit & Debit Card form fields', 'woocommerce-paypal-payments'), __('Customizable user experience', 'woocommerce-paypal-payments')), !$is_us_shop ? '' : $this->render_table_row(__('Credit & Debit Card pricing', 'woocommerce-paypal-payments'), __('2.59% + $0.49', 'woocommerce-paypal-payments'), '', __('for US domestic transactions', 'woocommerce-paypal-payments')), $this->render_table_row(__('Seller Protection', 'woocommerce-paypal-payments'), __('Yes', 'woocommerce-paypal-payments'), __('No matter what you sell, Seller Protection can help you avoid chargebacks, reversals, and fees on eligible PayPal payment transactions — even when a customer has filed a dispute.', 'woocommerce-paypal-payments'), __('for eligible PayPal transactions', 'woocommerce-paypal-payments')), $this->render_table_row(__('Fraud Protection', 'woocommerce-paypal-payments'), __('Yes', 'woocommerce-paypal-payments'), __('Included with Advanced Checkout at no extra cost, Fraud Protection gives you the insight and control you need to better balance chargebacks and declines.', 'woocommerce-paypal-payments')), !$is_us_shop ? '' : $this->render_table_row(__('Chargeback Protection', 'woocommerce-paypal-payments'), __('Optional', 'woocommerce-paypal-payments'), __('If you choose this optional, fee-based alternative to Fraud Protection, PayPal will manage chargebacks for eligible credit and debit card transactions — so you won’t have to worry about unexpected costs.', 'woocommerce-paypal-payments'), __('extra 0.4% per transaction', 'woocommerce-paypal-payments')), $this->render_table_row(__('Additional Vetting and Underwriting Required', 'woocommerce-paypal-payments'), __('Yes', 'woocommerce-paypal-payments'), __('Business Ownership and other business information will be required during the application for Advanced Card Processing.', 'woocommerce-paypal-payments')), $this->render_table_row(__('Seller Account Type', 'woocommerce-paypal-payments'), __('Business', 'woocommerce-paypal-payments'), __('For Standard payments, Casual sellers may connect their Personal PayPal account in eligible countries to sell on WooCommerce. For Advanced payments, a Business PayPal account is required.', 'woocommerce-paypal-payments')));
            $dcc_table_rows = apply_filters('ppcp_onboarding_dcc_table_rows', $dcc_table_rows, $this);
            $items[] = '
<li>
	<label>
		<input type="radio" id="ppcp-onboarding-dcc-acdc" name="ppcp_onboarding_dcc" value="acdc" ' . ($make_dcc_default ? 'checked' : '') . ' data-screen-url="' . $this->get_screen_url('acdc') . '"> ' . __('Advanced Card Processing', 'woocommerce-paypal-payments') . '
	</label>
	' . $this->render_tooltip(__('PayPal acts as the payment processor for card transactions. You can add optional features like Chargeback Protection for more security.', 'woocommerce-paypal-payments')) . '
	<table>
		' . implode('', $dcc_table_rows) . '
	</table>
</li>';
        }
        return '
<div class="ppcp-onboarding-cards-options">
	<ul id="ppcp-onboarding-dcc-options" class="ppcp-onboarding-options-sublist">' . implode('', $items) . '
	</ul>
	<div class="ppcp-onboarding-cards-screen"><img id="ppcp-onboarding-cards-screen-img" /></div>
</div>';
    }
    /**
     * Returns HTML of a row for the cards options tables.
     *
     * @param string $header The text in the first cell.
     * @param string $value The text in the second cell.
     * @param string $tooltip The text shown on hover.
     * @param string $note The additional description text, such as about conditions.
     * @return string
     */
    public function render_table_row(string $header, string $value, string $tooltip = '', string $note = ''): string
    {
        $value_html = $value;
        if ($note) {
            $value_html .= '<br/><span class="ppcp-muted-text">' . $note . '</span>';
        }
        $tooltip_html = '';
        if ($tooltip) {
            $tooltip_html = $this->render_tooltip($tooltip, array('ppcp-table-row-tooltip'));
        }
        return "\n<tr>\n\t<th>{$tooltip_html} {$header}</th>\n\t<td>{$value_html}</td>\n</tr>";
    }
    /**
     * Returns HTML of a tooltip (question mark icon).
     *
     * @param string   $tooltip The text shown on hover.
     * @param string[] $classes Additional CSS classes.
     * @return string
     */
    private function render_tooltip(string $tooltip, array $classes = array()): string
    {
        return '<span class="woocommerce-help-tip ' . implode(' ', $classes) . '" data-tip="' . esc_attr($tooltip) . '"></span> ';
    }
    /**
     * Returns the screen image URL.
     *
     * @param string $key The image suffix, 'acdc' or 'basic'.
     * @return string
     */
    private function get_screen_url(string $key): string
    {
        return untrailingslashit($this->module_url) . "/assets/images/cards-screen-{$key}.png";
    }
}
