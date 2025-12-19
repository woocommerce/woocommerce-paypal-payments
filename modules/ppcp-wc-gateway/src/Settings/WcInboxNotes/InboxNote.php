<?php

/**
 * @package WooCommerce\PayPalCommerce\WcGateway\Settings
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Settings\WcInboxNotes;

/**
 * A note that can be displayed in the WooCommerce inbox section.
 */
class InboxNote implements \WooCommerce\PayPalCommerce\WcGateway\Settings\WcInboxNotes\InboxNoteInterface
{
    protected string $title;
    protected string $content;
    protected string $type;
    protected string $name;
    protected string $status;
    protected bool $is_enabled;
    protected \WooCommerce\PayPalCommerce\WcGateway\Settings\WcInboxNotes\InboxNoteActionInterface $action;
    public function __construct(string $title, string $content, string $type, string $name, string $status, bool $is_enabled, \WooCommerce\PayPalCommerce\WcGateway\Settings\WcInboxNotes\InboxNoteActionInterface $action)
    {
        $this->title = $title;
        $this->content = $content;
        $this->type = $type;
        $this->name = $name;
        $this->status = $status;
        $this->is_enabled = $is_enabled;
        $this->action = $action;
    }
    public function title(): string
    {
        return $this->title;
    }
    public function content(): string
    {
        return $this->content;
    }
    public function type(): string
    {
        return $this->type;
    }
    public function name(): string
    {
        return $this->name;
    }
    public function status(): string
    {
        return $this->status;
    }
    public function is_enabled(): bool
    {
        return $this->is_enabled;
    }
    public function action(): \WooCommerce\PayPalCommerce\WcGateway\Settings\WcInboxNotes\InboxNoteActionInterface
    {
        return $this->action;
    }
}
