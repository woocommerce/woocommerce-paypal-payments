<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\FraudProtection;

/**
 * A counter saving the current state into WP option.
 */
class PersistentCounter
{
    protected string $option_id;
    public function __construct(string $option_id)
    {
        $this->option_id = $option_id;
    }
    public function increment(): void
    {
        update_option($this->option_id, $this->current() + 1);
    }
    public function current(): int
    {
        return (int) get_option($this->option_id, 0);
    }
}
