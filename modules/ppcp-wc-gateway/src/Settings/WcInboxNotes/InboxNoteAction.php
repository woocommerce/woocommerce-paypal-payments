<?php

/**
 * @package WooCommerce\PayPalCommerce\WcGateway\Settings
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Settings\WcInboxNotes;

/**
 * An action that can be performed on a WooCommerce inbox note.
 */
class InboxNoteAction implements \WooCommerce\PayPalCommerce\WcGateway\Settings\WcInboxNotes\InboxNoteActionInterface
{
    protected string $name;
    protected string $label;
    protected string $url;
    protected string $status;
    protected bool $is_primary;
    public function __construct(string $name, string $label, string $url, string $status, bool $is_primary)
    {
        $this->name = $name;
        $this->label = $label;
        $this->url = $url;
        $this->status = $status;
        $this->is_primary = $is_primary;
    }
    public function name(): string
    {
        return $this->name;
    }
    public function label(): string
    {
        return $this->label;
    }
    public function url(): string
    {
        return $this->url;
    }
    public function status(): string
    {
        return $this->status;
    }
    public function is_primary(): bool
    {
        return $this->is_primary;
    }
}
