<?php

/**
 * @package WooCommerce\PayPalCommerce\WcGateway\Settings
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Settings\WcInboxNotes;

/**
 * A factory for creating inbox notes.
 */
class InboxNoteFactory
{
    public function create_note(string $title, string $content, string $type, string $name, string $status, bool $is_enabled, \WooCommerce\PayPalCommerce\WcGateway\Settings\WcInboxNotes\InboxNoteActionInterface $action): \WooCommerce\PayPalCommerce\WcGateway\Settings\WcInboxNotes\InboxNoteInterface
    {
        return new \WooCommerce\PayPalCommerce\WcGateway\Settings\WcInboxNotes\InboxNote($title, $content, $type, $name, $status, $is_enabled, $action);
    }
}
