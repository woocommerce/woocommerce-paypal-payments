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

use WooCommerce\PayPalCommerce\Settings\DTO\MerchantConnectionDTO;

class SettingsProvider {
	private GeneralSettings $general_settings;
	private OnboardingProfile $onboarding_profile;

	public function __construct(
		GeneralSettings $general_settings,
		OnboardingProfile $onboarding_profile
	) {
		$this->general_settings   = $general_settings;
		$this->onboarding_profile = $onboarding_profile;
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
	 * Whether the currently connected merchant is a sandbox account.
	 *
	 * @return bool
	 */
	public function sandbox_merchant(): bool {
		return $this->general_settings->is_sandbox_merchant();
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
		return (bool) $this->general_settings->is_casual_seller();
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
}
