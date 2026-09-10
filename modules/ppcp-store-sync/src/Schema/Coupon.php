<?php

/**
 * Defines a discount coupon.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Schema
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
/**
 * @see CouponTest - Unit tests for this class.
 */
class Coupon extends \WooCommerce\PayPalCommerce\StoreSync\Schema\AgenticSchema
{
    private ?string $code = null;
    private ?string $action = null;
    protected function parse_fields(array $input, StoreValidation $validation): void
    {
        // Reset all fields.
        $this->code = null;
        $this->action = null;
        if (isset($input['code']) && is_string($input['code'])) {
            $this->code = trim($input['code']);
        } else {
            $validation->add_invalid_data('code', 'Missing required field', 'Please provide a coupon code.');
        }
        if (isset($input['action']) && is_string($input['action'])) {
            $action = strtoupper(trim($input['action']));
            $valid_actions = array('APPLY', 'REMOVE');
            if (in_array($action, $valid_actions, \true)) {
                $this->action = $action;
            } else {
                $validation->add_invalid_data('action', 'Action must be APPLY or REMOVE', 'Please provide a valid action.');
            }
        } else {
            $validation->add_invalid_data('action', 'Missing required field', 'Please provide an action.');
        }
    }
    public function code(?string $default = null): ?string
    {
        return $this->code ?? $default;
    }
    public function action(?string $default = null): ?string
    {
        return $this->action ?? $default;
    }
}
