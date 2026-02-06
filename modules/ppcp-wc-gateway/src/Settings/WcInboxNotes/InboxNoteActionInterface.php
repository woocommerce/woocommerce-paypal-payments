<?php

/**
 * Represents an action that can be performed on a WooCommerce inbox note.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Settings
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Settings\WcInboxNotes;

interface InboxNoteActionInterface
{
    public function name(): string;
    public function label(): string;
    public function url(): string;
    public function status(): string;
    public function is_primary(): bool;
}
