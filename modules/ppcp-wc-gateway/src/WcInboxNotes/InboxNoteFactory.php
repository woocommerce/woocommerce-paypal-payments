<?php

/**
 * @package WooCommerce\PayPalCommerce\WcGateway\WcInboxNotes
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\WcInboxNotes;

/**
 * A factory for creating inbox notes.
 */
class InboxNoteFactory
{
    public function create_note(string $title, string $content, string $type, string $name, string $status, bool $is_enabled, \WooCommerce\PayPalCommerce\WcGateway\WcInboxNotes\InboxNoteActionInterface ...$actions): \WooCommerce\PayPalCommerce\WcGateway\WcInboxNotes\InboxNoteInterface
    {
        return new \WooCommerce\PayPalCommerce\WcGateway\WcInboxNotes\InboxNote($title, $content, $type, $name, $status, $is_enabled, ...$actions);
    }
}
