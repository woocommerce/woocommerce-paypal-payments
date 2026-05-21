<?php

/**
 * Defines the payment method schema.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Schema
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
/**
 * @see PaymentMethodTest - Unit tests for this class.
 */
class PaymentMethod extends \WooCommerce\PayPalCommerce\StoreSync\Schema\AgenticSchema
{
    private ?string $token = null;
    private ?string $payer_id = null;
    public static function create_empty(): self
    {
        return self::from_array(array(), new StoreValidation());
    }
    protected function parse_fields(array $input, StoreValidation $validation): void
    {
        // Reset all fields.
        $this->token = null;
        $this->payer_id = null;
        // Mandatory fields.
        if (!isset($input['type']) || !is_string($input['type'])) {
            $validation->add_missing_field('type', 'No value for the payment method type found');
        } else {
            $type = trim($input['type']);
            if (empty($type)) {
                $validation->add_missing_field('type', 'No value for the payment method type found');
            } elseif ('paypal' !== $type) {
                $validation->add_invalid_data('type', 'Unexpected payment method type', 'Only PayPal is supported');
            }
        }
        // Optional fields.
        if (isset($input['token']) && is_string($input['token'])) {
            $this->token = trim($input['token']);
        }
        if (isset($input['payer_id']) && is_string($input['payer_id'])) {
            $this->payer_id = trim($input['payer_id']);
        }
    }
    public function type(): string
    {
        return 'paypal';
    }
    public function token(?string $default = null): ?string
    {
        return $this->token ?? $default;
    }
    public function payer_id(?string $default = null): ?string
    {
        return $this->payer_id ?? $default;
    }
    public function to_array(): array
    {
        $data = array('type' => $this->type(), 'token' => $this->token, 'payer_id' => $this->payer_id);
        return array_filter($data, static fn($v) => $v !== null);
    }
}
