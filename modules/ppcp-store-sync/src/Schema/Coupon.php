<?php

/**
 * Defines a discount coupon.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Schema
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Validation\InvalidData;
/**
 * @see CouponTest - Unit tests for this class.
 */
class Coupon extends \WooCommerce\PayPalCommerce\StoreSync\Schema\AgenticSchema
{
    private ?string $code = null;
    private ?string $action = null;
    protected function parse_fields(array $input, callable $add_issue): void
    {
        // Reset all fields.
        $this->code = null;
        $this->action = null;
        if (isset($input['code']) && is_string($input['code'])) {
            $this->code = trim($input['code']);
        } else {
            $add_issue(new InvalidData('Missing required field', 'Please provide a coupon code.', 'code'));
        }
        if (isset($input['action']) && is_string($input['action'])) {
            $action = strtoupper(trim($input['action']));
            $valid_actions = array('APPLY', 'REMOVE');
            if (in_array($action, $valid_actions, \true)) {
                $this->action = $action;
            } else {
                $add_issue(new InvalidData('Action must be APPLY or REMOVE', 'Please provide a valid action.', 'action'));
            }
        } else {
            $add_issue(new InvalidData('Missing required field', 'Please provide an action.', 'action'));
        }
    }
    public function code(): ?string
    {
        return $this->code;
    }
    public function action(): ?string
    {
        return $this->action;
    }
}
