<?php

/**
 * Defines the gift option schema.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Schema
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use DateTime;
use DateTimeInterface;
use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;
/**
 * @see GiftOptionsTest - Unit tests for this class.
 */
class GiftOptions extends \WooCommerce\PayPalCommerce\StoreSync\Schema\AgenticSchema
{
    private bool $is_gift = \false;
    private bool $gift_wrap = \false;
    private ?string $sender_name = null;
    private ?string $gift_message = null;
    private ?string $delivery_date = null;
    private ?array $recipient = null;
    protected function parse_fields(array $input, callable $add_issue): void
    {
        // Reset all fields.
        $this->is_gift = \false;
        $this->gift_wrap = \false;
        $this->sender_name = null;
        $this->gift_message = null;
        $this->delivery_date = null;
        $this->recipient = null;
        // Optional fields.
        if (isset($input['is_gift']) && is_bool($input['is_gift'])) {
            $this->is_gift = $input['is_gift'];
        }
        if (isset($input['gift_wrap']) && is_bool($input['gift_wrap'])) {
            $this->gift_wrap = $input['gift_wrap'];
        }
        if (isset($input['sender_name']) && is_string($input['sender_name'])) {
            $this->sender_name = trim($input['sender_name']);
        }
        if (isset($input['gift_message']) && is_string($input['gift_message'])) {
            $gift_message = trim($input['gift_message']);
            if (strlen($gift_message) > 500) {
                $add_issue(ValidationIssue::create_invalid_data('Gift message too long')->user_message('The gift message must be no longer than 500 characters')->for_field('gift_message'));
            } else {
                $this->gift_message = $gift_message;
            }
        }
        if (isset($input['delivery_date']) && is_string($input['delivery_date'])) {
            $delivery_date = trim($input['delivery_date']);
            $rfc_date = DateTime::createFromFormat(DateTimeInterface::RFC3339, $delivery_date);
            if ($rfc_date) {
                $this->delivery_date = $delivery_date;
            } else {
                $add_issue(ValidationIssue::create_invalid_data('Invalid delivery date format')->user_message('The delivery date must be in RFC3339 format (e.g., 2024-12-25T09:00:00Z)')->for_field('delivery_date'));
            }
        }
        if (!empty($input['recipient']) && is_array($input['recipient'])) {
            $recipient_email = $input['recipient']['email'] ?? null;
            $recipient_name = $input['recipient']['name'] ?? null;
            if ($recipient_email && is_string($recipient_email)) {
                $recipient_email = trim($recipient_email);
                if (!filter_var($recipient_email, \FILTER_VALIDATE_EMAIL)) {
                    $recipient_email = null;
                    $add_issue(ValidationIssue::create_invalid_data('Invalid recipient email')->user_message('The recipient email is not valid')->for_field('recipient.email'));
                }
            }
            if ($recipient_name && is_string($recipient_name)) {
                $recipient_name = trim($recipient_name);
            }
            $this->recipient = array('email' => $recipient_email, 'name' => $recipient_name);
        }
    }
    public function is_gift(): bool
    {
        return $this->is_gift;
    }
    public function gift_wrap(): bool
    {
        return $this->gift_wrap;
    }
    public function sender_name(): ?string
    {
        return $this->sender_name;
    }
    public function gift_message(): ?string
    {
        return $this->gift_message;
    }
    /**
     * @return string|null The scheduled delivery date, in RFC3339 format, or null.
     */
    public function delivery_date(): ?string
    {
        return $this->delivery_date;
    }
    /**
     * @return null|array Recipient as a simple array, no own schema.
     */
    public function recipient(): ?array
    {
        return $this->recipient;
    }
}
