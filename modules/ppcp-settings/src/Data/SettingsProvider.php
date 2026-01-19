<?php
/**
 * PayPal Commerce Provider Class
 *
 * The goal of the class is to have all new settings UI classes injected and serve as settings provider from one single place.
 * Modules would use this SettingsProvider class to update the code from using the legacy Settings class to use the new settings.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Data
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Settings\Data;

use WooCommerce\PayPalCommerce\Settings\DTO\LocationStylingDTO;
use WooCommerce\PayPalCommerce\Settings\DTO\MerchantConnectionDTO;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

class SettingsProvider {
	private GeneralSettings $general_settings;
	private OnboardingProfile $onboarding_profile;
	private PaymentSettings $payment_settings;
	private SettingsModel $settings_model;
	private StylingSettings $styling_settings;
	private FastlaneSettings $fastlane_settings;
	private PayLaterMessagingSettings $paylater_messaging_settings;

	public function __construct(
		GeneralSettings $general_settings,
		OnboardingProfile $onboarding_profile,
		PaymentSettings $payment_settings,
		SettingsModel $settings_model,
		StylingSettings $styling_settings,
		FastlaneSettings $fastlane_settings,
		PayLaterMessagingSettings $paylater_messaging_settings
	) {
		$this->general_settings            = $general_settings;
		$this->onboarding_profile          = $onboarding_profile;
		$this->payment_settings            = $payment_settings;
		$this->settings_model              = $settings_model;
		$this->styling_settings            = $styling_settings;
		$this->fastlane_settings           = $fastlane_settings;
		$this->paylater_messaging_settings = $paylater_messaging_settings;
	}

	/**
	 * Gets the 'use sandbox' setting.
	 *
	 * @return bool
	 */
	public function use_sandbox(): bool {
		return $this->general_settings->get_sandbox();
	}

	/**
	 * Whether the currently connected merchant is a sandbox account.
	 *
	 * @return bool
	 */
	public function sandbox_merchant(): bool {
		return $this->general_settings->is_sandbox_merchant();
	}

	/**
	 * Whether the merchant uses a business account.
	 *
	 * Note: It's possible that the seller type is unknown, and both methods,
	 * `is_casual_seller()` and `is_business_seller()` return false.
	 *
	 * @return bool
	 */
	public function business_seller(): bool {
		return $this->general_settings->is_business_seller();
	}

	/**
	 * Whether the merchant is a casual seller using a personal account.
	 *
	 * Note: It's possible that the seller type is unknown, and both methods,
	 * `is_casual_seller()` and `is_business_seller()` return false.
	 *
	 * @return bool
	 */
	public function casual_seller(): bool {
		return $this->general_settings->is_casual_seller();
	}

	/**
	 * Returns the list of read-only customization flags.
	 *
	 * @return array
	 */
	public function woo_settings(): array {
		return $this->general_settings->get_woo_settings();
	}

	/**
	 * Returns the full merchant connection DTO for the current connection.
	 *
	 * @return MerchantConnectionDTO All connection details.
	 */
	public function merchant_data(): MerchantConnectionDTO {
		return $this->general_settings->get_merchant_data();
	}

	/**
	 * Whether the merchant successfully logged into their PayPal account.
	 *
	 * @return bool
	 */
	public function merchant_connected(): bool {
		return $this->general_settings->is_merchant_connected();
	}

	/**
	 * Gets the currently connected merchant ID.
	 *
	 * @return string
	 */
	public function merchant_id(): string {
		return $this->general_settings->get_merchant_id();
	}

	/**
	 * Gets the currently connected merchant's email.
	 *
	 * @return string
	 */
	public function merchant_email(): string {
		return $this->general_settings->get_merchant_email();
	}

	/**
	 * Gets the currently connected merchant's country.
	 *
	 * @return string
	 */
	public function merchant_country(): string {
		return $this->general_settings->get_merchant_country();
	}

	/**
	 * Whether the plugin is in the branded-experience mode and shows/enables only
	 * payment methods that are PayPal's own brand.
	 *
	 * @return bool
	 */
	public function own_brand_only(): bool {
		return $this->general_settings->own_brand_only();
	}

	/**
	 * Retrieves the installation path. Used for the branded experience.
	 *
	 * @return string
	 */
	public function installation_path(): string {
		return $this->general_settings->get_installation_path();
	}
	/**
	 * Gets the Onboarding 'completed' flag.
	 *
	 * @return bool
	 */
	public function onboarding_completed(): bool {
		return $this->onboarding_profile->get_completed();
	}

	/**
	 * Gets the Onboarding 'step' setting.
	 *
	 * @return int
	 */
	public function onboarding_step(): int {
		return $this->onboarding_profile->get_step();
	}

	/**
	 * Whether the merchant wants to accept card payments via the PayPal plugin.
	 *
	 * @return bool
	 */
	public function accept_card_payments(): bool {
		return $this->onboarding_profile->get_accept_card_payments();
	}

	/**
	 * Gets the active product types for this store.
	 *
	 * @return string[] Any of ['virtual'|'physical'|'subscriptions'].
	 */
	public function products(): array {
		return $this->onboarding_profile->get_products();
	}

	/**
	 * Returns the list of read-only customization flags
	 *
	 * @return array
	 */
	public function flags(): array {
		return $this->onboarding_profile->get_flags();
	}

	/**
	 * Gets the 'setup_done' flag.
	 *
	 * @return bool
	 */
	public function setup_done(): bool {
		return $this->onboarding_profile->is_setup_done();
	}

	/**
	 * Get whether gateways have been synced.
	 *
	 * @return bool
	 */
	public function gateways_synced(): bool {
		return $this->onboarding_profile->is_gateways_synced();
	}

	/**
	 * Get whether gateways have been refreshed.
	 *
	 * @return bool
	 */
	public function gateways_refreshed(): bool {
		return $this->onboarding_profile->is_gateways_refreshed();
	}

	/**
	 * If it should show the PayPal logo.
	 *
	 * @return bool
	 */
	public function show_paypal_logo(): bool {
		return $this->payment_settings->get_paypal_show_logo();
	}

	/**
	 * If it should show CardHolder name.
	 *
	 * @return bool
	 */
	public function show_cardholder_name(): bool {
		return $this->payment_settings->get_cardholder_name();
	}

	/**
	 * Get if Fastlane should display watermark.
	 *
	 * @return bool
	 */
	public function show_fastlane_watermark(): bool {
		return $this->payment_settings->get_fastlane_display_watermark();
	}

	/**
	 * Get if Venmo is enabled.
	 *
	 * @return bool
	 */
	public function venmo_enabled(): bool {
		return $this->payment_settings->get_venmo_enabled();
	}

	/**
	 * Get if Pay Later is enabled.
	 *
	 * @return bool
	 */
	public function paylater_enabled(): bool {
		return $this->payment_settings->get_paylater_enabled();
	}

	/**
	 * Gets the PayPal gateway title as configured in WooCommerce settings.
	 *
	 * @return string The gateway title, defaults to 'PayPal' if not set.
	 */
	public function paypal_gateway_title(): string {
		return $this->payment_settings->get_method_title( PayPalGateway::ID, 'PayPal' );
	}

	/**
	 * Gets the invoice prefix.
	 *
	 * @return string The invoice prefix.
	 */
	public function invoice_prefix(): string {
		return $this->settings_model->get_invoice_prefix();
	}

	/**
	 * Gets the brand name.
	 *
	 * @return string The brand name.
	 */
	public function brand_name(): string {
		return $this->settings_model->get_brand_name();
	}

	/**
	 * Gets the soft descriptor.
	 *
	 * @return string The soft descriptor.
	 */
	public function soft_descriptor(): string {
		return $this->settings_model->get_soft_descriptor();
	}

	/**
	 * Gets the subtotal adjustment setting.
	 *
	 * @return string The subtotal adjustment setting.
	 */
	public function subtotal_adjustment(): string {
		return $this->settings_model->get_subtotal_adjustment();
	}

	/**
	 * Gets the landing page setting.
	 *
	 * @return string The landing page setting.
	 */
	public function landing_page(): string {
		return $this->settings_model->get_landing_page();
	}

	/**
	 * Gets the landing page setting as API enum value.
	 *
	 * @return string The landing page API enum ('NO_PREFERENCE', 'LOGIN', 'GUEST_CHECKOUT').
	 */
	public function landing_page_enum(): string {
		return $this->settings_model->get_landing_page_enum();
	}

	/**
	 * Gets the button language setting.
	 *
	 * @return string The button language.
	 */
	public function button_language(): string {
		return $this->settings_model->get_button_language();
	}

	/**
	 * Gets the 3D Secure setting.
	 *
	 * @return string The 3D Secure setting.
	 */
	public function three_d_secure(): string {
		return $this->settings_model->get_three_d_secure();
	}

	/**
	 * Gets the 3D Secure setting as API enum value.
	 *
	 * @return string The 3D Secure API enum ('NO_3D_SECURE', 'SCA_WHEN_REQUIRED', 'SCA_ALWAYS').
	 */
	public function three_d_secure_enum(): string {
		return $this->settings_model->get_three_d_secure_enum();
	}

	/**
	 * Gets the authorize only setting.
	 *
	 * @return bool True if authorize only is enabled, false otherwise.
	 */
	public function authorize_only(): bool {
		return $this->settings_model->get_authorize_only();
	}

	/**
	 * Gets the capture virtual orders setting.
	 *
	 * @return bool True if capturing virtual orders is enabled, false otherwise.
	 */
	public function capture_virtual_orders(): bool {
		return $this->settings_model->get_capture_virtual_orders();
	}

	/**
	 * Gets the save PayPal and Venmo setting.
	 *
	 * @return bool True if saving PayPal and Venmo is enabled, false otherwise.
	 */
	public function save_paypal_and_venmo(): bool {
		return $this->settings_model->get_save_paypal_and_venmo();
	}

	/**
	 * Gets the instant payments only setting.
	 *
	 * @return bool True if instant payments only setting is enabled, false otherwise.
	 */
	public function instant_payments_only(): bool {
		return $this->settings_model->get_instant_payments_only();
	}

	/**
	 * Gets the custom-shipping-contact flag ("Contact Module").
	 *
	 * @return bool True if the contact module feature is enabled, false otherwise.
	 */
	public function enable_contact_module(): bool {
		return $this->settings_model->get_enable_contact_module();
	}

	/**
	 * Gets the save card details setting.
	 *
	 * @return bool True if saving card details is enabled, false otherwise.
	 */
	public function save_card_details(): bool {
		return $this->settings_model->get_save_card_details();
	}

	/**
	 * Gets the enable Pay Now setting.
	 *
	 * @return bool True if Pay Now is enabled, false otherwise.
	 */
	public function enable_pay_now(): bool {
		return $this->settings_model->get_enable_pay_now();
	}

	/**
	 * Gets the enable logging setting.
	 *
	 * @return bool True if logging is enabled, false otherwise.
	 */
	public function enable_logging(): bool {
		return $this->settings_model->get_enable_logging();
	}

	/**
	 * Gets the disabled cards.
	 *
	 * @return array The array of disabled cards.
	 */
	public function disabled_cards(): array {
		return $this->settings_model->get_disabled_cards();
	}

	/**
	 * Gets the card icons.
	 *
	 * @return array The array of card icons.
	 */
	public function card_icons(): array {
		return $this->settings_model->get_card_icons();
	}

	/**
	 * Gets the Stay Updated setting.
	 *
	 * @return bool True if Stay Updated is enabled, false otherwise.
	 */
	public function stay_updated(): bool {
		return $this->settings_model->get_stay_updated();
	}

	/**
	 * Get styling details for Cart and Block Cart.
	 *
	 * @return LocationStylingDTO
	 */
	public function styling_cart(): LocationStylingDTO {
		return $this->styling_settings->get_cart();
	}

	/**
	 * Get styling details for Classic Checkout.
	 *
	 * @return LocationStylingDTO
	 */
	public function styling_classic_checkout(): LocationStylingDTO {
		return $this->styling_settings->get_classic_checkout();
	}

	/**
	 * Get styling details for Express Checkout.
	 *
	 * @return LocationStylingDTO
	 */
	public function styling_express_checkout(): LocationStylingDTO {
		return $this->styling_settings->get_express_checkout();
	}

	/**
	 * Get styling details for Mini Cart
	 *
	 * @return LocationStylingDTO
	 */
	public function styling_mini_cart(): LocationStylingDTO {
		return $this->styling_settings->get_mini_cart();
	}

	/**
	 * Get styling details for Product Page.
	 *
	 * @return LocationStylingDTO
	 */
	public function styling_product(): LocationStylingDTO {
		return $this->styling_settings->get_product();
	}

	/**
	 * Get Fastlane name on card setting.
	 *
	 * @return string
	 */
	public function fastlane_name_on_card(): string {
		return $this->fastlane_settings->get_name_on_card();
	}

	/**
	 * Get Fastlane root styles.
	 *
	 * @return array
	 */
	public function fastlane_root_styles(): array {
		return $this->fastlane_settings->get_root_styles();
	}

	/**
	 * Get Fastlane input styles.
	 *
	 * @return array
	 */
	public function fastlane_input_styles(): array {
		return $this->fastlane_settings->get_input_styles();
	}

	/**
	 * Checks if the provided payment method is enabled.
	 *
	 * @param string $method_id ID of the payment method.
	 * @return bool True if the method is enabled, false otherwise.
	 */
	public function is_method_enabled( string $method_id ): bool {
		return $this->payment_settings->is_method_enabled( $method_id );
	}

	/**
	 * Get if Apple Pay button is enabled.
	 *
	 * @return bool
	 */
	public function applepay_button_enabled(): bool {
		return $this->payment_settings->get_applepay_button_enabled();
	}

	/**
	 * Whether the domain verification for ApplePay completed successfully.
	 */
	public function applepay_validated(): bool {
		return $this->payment_settings->get_applepay_validated();
	}

	/**
	 * Get Apple Pay button type.
	 *
	 * @return string
	 */
	public function applepay_button_type(): string {
		return $this->payment_settings->get_applepay_button_type();
	}

	/**
	 * Get Apple Pay button color.
	 *
	 * @return string
	 */
	public function applepay_button_color(): string {
		return $this->payment_settings->get_applepay_button_color();
	}

	/**
	 * Get Apple Pay button language.
	 *
	 * @return string
	 */
	public function applepay_button_language(): string {
		return $this->payment_settings->get_applepay_button_language();
	}

	/**
	 * Get Apple Pay checkout data mode.
	 *
	 * @return string
	 */
	public function applepay_checkout_data_mode(): string {
		return $this->payment_settings->get_applepay_checkout_data_mode();
	}

	/**
	 * Get PPCP onboarding Apple flag.
	 *
	 * @return string
	 */
	public function applepay_onboarding(): string {
		return $this->payment_settings->get_ppcp_onboarding_apple();
	}

	/**
	 * Whether Pay Later messaging styling should be customized per location.
	 *
	 * @return bool
	 */
	public function pay_later_styling_per_location(): bool {
		return $this->styling_settings->get_pay_later_styling_per_location();
	}

	/**
	 * Whether the given gateway is enabled.
	 *
	 * @param string $method_id ID of the payment method.
	 * @return bool
	 */
	public function gateway_enabled( string $method_id ): bool {
		return $this->payment_settings->is_method_enabled( $method_id );
	}

	/**
	 * Gets the payment intent (authorize or capture).
	 *
	 * @return string The payment intent ('authorize' or 'capture').
	 */
	public function payment_intent(): string {
		return $this->authorize_only() ? 'authorize' : 'capture';
	}

	/**
	 * Gets Pay Later messaging style settings for a given location.
	 *
	 * @param string $location The location (general, cart, checkout, product, etc.).
	 * @return array The messaging style settings.
	 */
	public function pay_later_messaging_style( string $location ): array {
		$method_map = array(
			'cart'             => 'get_cart',
			'checkout'         => 'get_checkout',
			'product'          => 'get_product',
			'shop'             => 'get_shop',
			'home'             => 'get_home',
			'custom_placement' => 'get_custom_placement',
		);

		if ( isset( $method_map[ $location ] ) ) {
			$method = $method_map[ $location ];
			$dto    = $this->paylater_messaging_settings->$method();

			return array(
				'layout'        => $dto->layout,
				'logo_type'     => $dto->logo_type,
				'logo_position' => $dto->logo_position,
				'text_color'    => $dto->text_color,
				'flex_color'    => $dto->flex_color,
				'ratio'         => $dto->flex_ratio,
				'text_size'     => $dto->text_size,
			);
		}

		return array(
			'layout'        => 'text',
			'logo_type'     => 'primary',
			'logo_position' => 'left',
			'text_color'    => 'black',
			'flex_color'    => 'blue',
			'ratio'         => '1x1',
			'text_size'     => '12',
		);
	}

	public function pay_later_messaging_locations(): array {
		return $this->paylater_messaging_settings->get_messaging_locations();
	}

	/**
	 * Gets the PayPal gateway description as configured in WooCommerce settings.
	 *
	 * @return string The gateway description.
	 */
	public function paypal_gateway_description(): string {
		return $this->payment_settings->get_method_description(
			PayPalGateway::ID,
			__( 'Pay via PayPal.', 'woocommerce-paypal-payments' )
		);
	}

	/**
	 * Gets the DCC (Credit Card) gateway title as configured in WooCommerce settings.
	 *
	 * @return string The gateway title, defaults to 'Credit Cards' if not set.
	 */
	public function dcc_gateway_title(): string {
		return $this->payment_settings->get_method_title(
			'ppcp-credit-card-gateway',
			__( 'Credit Cards', 'woocommerce-paypal-payments' )
		);
	}

	/**
	 * Gets the DCC (Credit Card) gateway description as configured in WooCommerce settings.
	 *
	 * @return string The gateway description.
	 */
	public function dcc_gateway_description(): string {
		return $this->payment_settings->get_method_description(
			'ppcp-credit-card-gateway',
			__( 'Pay with your credit card.', 'woocommerce-paypal-payments' )
		);
	}

	/**
	 * Gets the enabled smart button locations.
	 *
	 * @return array Array of location names where buttons are enabled.
	 */
	public function smart_button_locations(): array {
		return $this->styling_settings->get_smart_button_locations();
	}

	/**
	 * Gets the enabled Pay Later button locations.
	 *
	 * @return array Array of location names where Pay Later buttons are enabled.
	 */
	public function pay_later_button_locations(): array {
		return $this->styling_settings->get_pay_later_button_locations();
	}

	/**
	 * Gets if Pay Later button is enabled.
	 *
	 * @return bool True if Pay Later button is enabled, false otherwise.
	 */
	public function pay_later_button_enabled(): bool {
		return $this->payment_settings->get_paylater_enabled();
	}

	/**
	 * Gets if Pay Later messaging is enabled.
	 *
	 * @return bool True if Pay Later messaging is enabled, false otherwise.
	 */
	public function pay_later_messaging_enabled(): bool {
		return $this->paylater_messaging_settings->get_messaging_enabled();
	}

	/**
	 * Gets the name on card setting for DCC gateway.
	 *
	 * @return string The name on card setting ('yes', 'no', or empty).
	 */
	public function dcc_name_on_card(): string {
		$name_on_card = $this->fastlane_settings->get_name_on_card();
		if ( ! empty( $name_on_card ) ) {
			return $name_on_card;
		}
		return $this->payment_settings->get_cardholder_name() ? 'yes' : 'no';
	}
}
