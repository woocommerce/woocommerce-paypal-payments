<?php

/**
 * Responsibility: WC_Session
 *
 * Provides idempotent enable/disable to swap WC session with no-op-session
 * that satisfies WC's session expectations without persisting data.
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Helper;

use WC_Session;
use WooCommerce;
class AgenticSessionManager
{
    private WooCommerce $wc;
    private ?WC_Session $original_session = null;
    private ?WC_Session $agentic_session = null;
    private bool $is_enabled = \false;
    public function __construct(WooCommerce $wc)
    {
        $this->wc = $wc;
    }
    public function enable(): void
    {
        if ($this->is_enabled) {
            return;
        }
        // Swap the WC session.
        $this->original_session = $this->wc->session;
        $this->wc->session = $this->get_agentic_session();
        // Disable WC_Cart_Session hooks.
        add_filter('woocommerce_cart_session_initialize', '__return_false', 9999);
        $this->is_enabled = \true;
    }
    public function disable(): void
    {
        if (!$this->is_enabled) {
            return;
        }
        // Restore the WC session.
        /** @psalm-suppress PossiblyNullPropertyAssignmentValue */
        $this->wc->session = $this->original_session;
        $this->original_session = null;
        // Remove custom filters.
        remove_filter('woocommerce_cart_session_initialize', '__return_false', 9999);
        $this->is_enabled = \false;
    }
    public function is_enabled(): bool
    {
        return $this->is_enabled;
    }
    /**
     * @param callable $callback Function to execute with agentic session.
     * @return mixed Result of callback.
     */
    public function with_session(callable $callback)
    {
        $previously_enabled = $this->is_enabled();
        $this->enable();
        try {
            return $callback();
        } finally {
            if (!$previously_enabled) {
                $this->disable();
            }
        }
    }
    private function get_agentic_session(): WC_Session
    {
        if (!$this->agentic_session) {
            $this->agentic_session = new class extends WC_Session
            {
            };
        }
        return $this->agentic_session;
    }
}
