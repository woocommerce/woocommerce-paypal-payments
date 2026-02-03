<?php

/**
 * Onboarding Profile class
 *
 * @package WooCommerce\PayPalCommerce\Settings\Data
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Data;

use RuntimeException;
/**
 * Class OnboardingProfile
 *
 * This class serves as a container for managing the onboarding profile details
 * within the WooCommerce PayPal Commerce plugin.
 *
 * This profile impacts the onboarding wizard and help to apply default
 * settings. The details here should not be used outside the onboarding process.
 */
class OnboardingProfile extends \WooCommerce\PayPalCommerce\Settings\Data\AbstractDataModel
{
    /**
     * Option key where profile details are stored.
     *
     * @var string
     */
    protected const OPTION_KEY = 'woocommerce-ppcp-data-onboarding';
    /**
     * List of customization flags, provided by the server (read-only).
     *
     * @var array
     */
    protected array $flags = array();
    /**
     * Constructor.
     *
     * @param bool $can_use_casual_selling Whether casual selling is enabled in the store's country.
     * @param bool $can_use_vaulting       Whether vaulting is enabled in the store's country.
     * @param bool $can_use_card_payments  Whether credit card payments are possible.
     * @param bool $can_use_subscriptions  Whether WC Subscriptions plugin is active.
     * @param bool $should_skip_payment_methods  Whether it should skip payment methods screen.
     * @param bool $can_use_fastlane  Whether it can use Fastlane or not.
     * @param bool $can_use_pay_later  Whether it can use Pay Later or not.
     *
     * @throws RuntimeException If the OPTION_KEY is not defined in the child class.
     */
    public function __construct(bool $can_use_casual_selling = \false, bool $can_use_vaulting = \false, bool $can_use_card_payments = \false, bool $can_use_subscriptions = \false, bool $should_skip_payment_methods = \false, bool $can_use_fastlane = \false, bool $can_use_pay_later = \false)
    {
        parent::__construct();
        $this->flags['can_use_casual_selling'] = $can_use_casual_selling;
        $this->flags['can_use_vaulting'] = $can_use_vaulting;
        $this->flags['can_use_card_payments'] = $can_use_card_payments;
        $this->flags['can_use_subscriptions'] = $can_use_subscriptions;
        $this->flags['should_skip_payment_methods'] = $should_skip_payment_methods;
        $this->flags['can_use_fastlane'] = $can_use_fastlane;
        $this->flags['can_use_pay_later'] = $can_use_pay_later;
    }
    /**
     * Get default values for the model.
     *
     * @return array
     */
    protected function get_defaults(): array
    {
        return array('completed' => \false, 'step' => 0, 'is_casual_seller' => null, 'accept_card_payments' => null, 'products' => array(), 'setup_done' => \false, 'gateways_synced' => \false, 'gateways_refreshed' => \false);
    }
    // -----
    /**
     * Gets the 'completed' flag.
     *
     * @return bool
     */
    public function get_completed(): bool
    {
        return (bool) $this->data['completed'];
    }
    /**
     * Sets the 'completed' flag.
     *
     * @param bool $state Whether the onboarding process has been completed.
     */
    public function set_completed(bool $state): void
    {
        $this->data['completed'] = $state;
    }
    /**
     * Gets the 'step' setting.
     *
     * @return int
     */
    public function get_step(): int
    {
        return (int) $this->data['step'];
    }
    /**
     * Sets the 'step' setting.
     *
     * @param int $step The current onboarding step.
     */
    public function set_step(int $step): void
    {
        $this->data['step'] = $step;
    }
    /**
     * Gets the casual seller flag.
     *
     * @return bool|null
     */
    public function get_casual_seller(): ?bool
    {
        return $this->data['is_casual_seller'];
    }
    /**
     * Sets the casual-seller flag.
     *
     * @param bool|null $casual_seller Whether the merchant uses a personal account for selling.
     */
    public function set_casual_seller(?bool $casual_seller): void
    {
        $this->data['is_casual_seller'] = $casual_seller;
    }
    /**
     * Whether the merchant wants to accept card payments via the PayPal plugin.
     *
     * @return bool
     */
    public function get_accept_card_payments(): bool
    {
        return (bool) $this->data['accept_card_payments'];
    }
    /**
     * Sets the "accept card payments" flag.
     *
     * @param bool|null $accept_cards Whether to accept card payments via the PayPal plugin.
     */
    public function set_accept_card_payments(?bool $accept_cards): void
    {
        $this->data['accept_card_payments'] = $accept_cards;
    }
    /**
     * Gets the active product types for this store.
     *
     * @return string[]
     */
    public function get_products(): array
    {
        return $this->data['products'];
    }
    /**
     * Sets the list of active product types.
     *
     * @param string[] $products Any of ['virtual'|'physical'|'subscriptions'].
     */
    public function set_products(array $products): void
    {
        $this->data['products'] = $products;
    }
    /**
     * Returns the list of read-only customization flags
     *
     * @return array
     */
    public function get_flags(): array
    {
        return $this->flags;
    }
    /**
     * Gets the 'setup_done' flag.
     *
     * @return bool
     */
    public function is_setup_done(): bool
    {
        return (bool) $this->data['setup_done'];
    }
    /**
     * Sets the 'setup_done' flag.
     *
     * @param bool $done Whether the onboarding process has been setup_done.
     */
    public function set_setup_done(bool $done): void
    {
        $this->data['setup_done'] = $done;
    }
    /**
     * Get whether gateways have been synced.
     *
     * @return bool
     */
    public function is_gateways_synced(): bool
    {
        return $this->data['gateways_synced'] ?? \false;
    }
    /**
     * Set whether gateways have been synced.
     *
     * @param bool $synced Whether gateways have been synced.
     */
    public function set_gateways_synced(bool $synced): void
    {
        $this->data['gateways_synced'] = $synced;
        // If enabling the flag, trigger the action.
        if ($synced) {
            do_action('woocommerce_paypal_payments_sync_gateways');
        }
    }
    /**
     * Get whether gateways have been refreshed.
     *
     * @return bool
     */
    public function is_gateways_refreshed(): bool
    {
        return $this->data['gateways_refreshed'] ?? \false;
    }
    /**
     * Set whether gateways have been refreshed.
     *
     * @param bool $refreshed Whether gateways have been refreshed.
     */
    public function set_gateways_refreshed(bool $refreshed): void
    {
        $this->data['gateways_refreshed'] = $refreshed;
    }
}
