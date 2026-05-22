<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Setting;

use WooCommerce\PayPalCommerce\Settings\Extension\ExtensionDataModel;
class AgenticSettingsDataModel extends ExtensionDataModel
{
    protected const NAME = 'agentic';
    protected function get_defaults(): array
    {
        return array('active' => \true, 'eligible' => null);
    }
    public function is_active(): bool
    {
        return (bool) $this->data['active'];
    }
    public function set_active(bool $state): void
    {
        $this->data['active'] = $state;
    }
    public function is_eligible(): ?bool
    {
        return $this->data['eligible'];
    }
    public function set_eligible(bool $state): void
    {
        $this->data['eligible'] = $state;
    }
    /**
     * Whether agentic features should be initialized.
     *
     * We use the settings model to cache eligibility, because it's available quite late in the
     * request (after REST endpoints are initialized), while this settings model is accessible
     * already during plugin load.
     *
     * Returns false if:
     * - Eligibility not yet determined (first load)
     * - Merchant is ineligible
     * - Feature is disabled in settings
     */
    public function should_initialize_features(): bool
    {
        return \true === $this->data['eligible'] && $this->is_active();
    }
}
