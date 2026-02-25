<?php

/**
 * Defines a single PayPal-controlled checkout field.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Schema
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Validation\InvalidData;
use WooCommerce\PayPalCommerce\StoreSync\Validation\MissingField;
/**
 * @see CheckoutFieldTest - Unit tests for this class.
 */
class CheckoutField extends \WooCommerce\PayPalCommerce\StoreSync\Schema\AgenticSchema
{
    private const VALID_STATUS = array('PENDING', 'COMPLETED', 'REJECTED', 'ERROR');
    private ?string $type = null;
    private string $status = '';
    private ?array $value = null;
    private ?array $context = null;
    protected function parse_fields(array $input, callable $add_issue): void
    {
        // Reset all fields.
        $this->type = null;
        $this->status = 'ERROR';
        $this->value = null;
        $this->context = null;
        // Parse mandatory fields.
        if (!empty($input['type']) && is_string($input['type'])) {
            $this->type = strtoupper(trim($input['type']));
        } else {
            $add_issue(new MissingField('Type is required', 'The field type is mandatory', 'type'));
        }
        if (!empty($input['status']) && is_string($input['status'])) {
            $status = strtoupper(trim($input['status']));
            if (in_array($status, self::VALID_STATUS, \true)) {
                $this->status = $status;
            } else {
                $add_issue(new InvalidData('Status is invalid', 'The status value is not supported', 'status'));
            }
        } else {
            $add_issue(new MissingField('Status is required', 'The field status is mandatory', 'status'));
        }
        // Parse optional fields.
        if (isset($input['value']) && is_array($input['value'])) {
            $this->value = $input['value'];
        }
        if (isset($input['context']) && is_array($input['context'])) {
            $this->context = $input['context'];
        }
    }
    public function type(): ?string
    {
        return $this->type;
    }
    public function status(): string
    {
        return $this->status;
    }
    public function value(): ?array
    {
        return $this->value;
    }
    public function context(): ?array
    {
        return $this->context;
    }
}
