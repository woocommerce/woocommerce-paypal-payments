<?php

/**
 * Resolution option for validation issues.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Validation\\Resolution
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Validation\Resolution;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ResolutionAction;
/**
 * Immutable resolution option builder with factory methods.
 */
class ResolutionOption
{
    /**
     * @readonly Must only be changed via the constructor!
     */
    private string $resolution_action;
    private ?string $label = null;
    private ?string $url = null;
    private ?string $priority = null;
    private array $metadata = array();
    private function __construct(string $action)
    {
        $this->resolution_action = $action;
    }
    public static function create_accept_back_order(): self
    {
        return new self(ResolutionAction::ACCEPT_BACK_ORDER);
    }
    public static function create_accept_new_price(): self
    {
        return new self(ResolutionAction::ACCEPT_NEW_PRICE);
    }
    public static function create_accept_pre_order(): self
    {
        return new self(ResolutionAction::ACCEPT_PRE_ORDER);
    }
    public static function create_accept_terms(): self
    {
        return new self(ResolutionAction::ACCEPT_TERMS);
    }
    public static function create_apply_different_coupon(): self
    {
        return new self(ResolutionAction::APPLY_DIFFERENT_COUPON);
    }
    public static function create_choose_different_variant(): self
    {
        return new self(ResolutionAction::CHOOSE_DIFFERENT_VARIANT);
    }
    public static function create_contact_support(): self
    {
        return new self(ResolutionAction::CONTACT_SUPPORT);
    }
    public static function create_keep_current_coupon(): self
    {
        return new self(ResolutionAction::KEEP_CURRENT_COUPON);
    }
    public static function create_modify_cart(): self
    {
        return new self(ResolutionAction::MODIFY_CART);
    }
    public static function create_provide_missing_field(): self
    {
        return new self(ResolutionAction::PROVIDE_MISSING_FIELD);
    }
    public static function create_redirect_to_merchant(): self
    {
        return new self(ResolutionAction::REDIRECT_TO_MERCHANT);
    }
    public static function create_remove_coupon(): self
    {
        return new self(ResolutionAction::REMOVE_COUPON);
    }
    public static function create_remove_item(): self
    {
        return new self(ResolutionAction::REMOVE_ITEM);
    }
    public static function create_request_approval(): self
    {
        return new self(ResolutionAction::REQUEST_APPROVAL);
    }
    public static function create_retry_later(): self
    {
        return new self(ResolutionAction::RETRY_LATER);
    }
    public static function create_split_order(): self
    {
        return new self(ResolutionAction::SPLIT_ORDER);
    }
    public static function create_suggest_alternative(): self
    {
        return new self(ResolutionAction::SUGGEST_ALTERNATIVE);
    }
    public static function create_update_address(): self
    {
        return new self(ResolutionAction::UPDATE_ADDRESS);
    }
    public static function create_update_shipping_method(): self
    {
        return new self(ResolutionAction::UPDATE_SHIPPING_METHOD);
    }
    public static function create_use_different_currency(): self
    {
        return new self(ResolutionAction::USE_DIFFERENT_CURRENCY);
    }
    public static function create_use_different_payment(): self
    {
        return new self(ResolutionAction::USE_DIFFERENT_PAYMENT);
    }
    public static function create_verify_account(): self
    {
        return new self(ResolutionAction::VERIFY_ACCOUNT);
    }
    public static function create_wait_for_restock(): self
    {
        return new self(ResolutionAction::WAIT_FOR_RESTOCK);
    }
    /**
     * Assign a custom label to the resolution option.
     */
    public function label(string $label): self
    {
        $this->label = $label;
        return $this;
    }
    /**
     * Assign a new resolution URL to the option.
     */
    public function url(string $url): self
    {
        $this->url = wp_validate_redirect($url);
        return $this;
    }
    /**
     * Changes the priority of the resolution option; available options are defined in
     * the `Priority` enum.
     */
    public function priority(string $priority): self
    {
        $this->priority = $priority;
        return $this;
    }
    /**
     * Sets (or unsets) a single meta value.
     *
     * @param string     $meta_key   The meta-key to update.
     * @param null|mixed $meta_value The new value, or null to unset.
     */
    public function set_meta(string $meta_key, $meta_value = null): self
    {
        if (null === $meta_value) {
            unset($this->metadata[$meta_key]);
        } else {
            $this->metadata[$meta_key] = $meta_value;
        }
        return $this;
    }
    /**
     * Replaces or extends the resolution metadata.
     */
    public function metadata(array $metadata, bool $replace = \false): self
    {
        $this->metadata = $replace ? $metadata : array_merge($this->metadata, $metadata);
        return $this;
    }
    /**
     * Converts to array for JSON serialization.
     *
     * @return array Resolution option data.
     */
    public function to_array(): array
    {
        $data = array('action' => $this->resolution_action, 'label' => $this->label);
        if ($this->url) {
            $data['url'] = $this->url;
        }
        if (!empty($this->metadata)) {
            $data['metadata'] = $this->metadata;
        }
        if ($this->priority) {
            $data['metadata'] = $data['metadata'] ?? array();
            $data['metadata']['priority'] = $this->priority;
        }
        return $data;
    }
}
