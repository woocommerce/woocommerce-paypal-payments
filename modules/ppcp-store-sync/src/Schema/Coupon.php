<?php

/**
 * Defines a discount coupon.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Schema
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;
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
            $add_issue(ValidationIssue::create_invalid_data('Missing required field')->user_message('Please provide a coupon code.')->for_field('code'));
        }
        if (isset($input['action']) && is_string($input['action'])) {
            $action = strtoupper(trim($input['action']));
            $valid_actions = array('APPLY', 'REMOVE');
            if (in_array($action, $valid_actions, \true)) {
                $this->action = $action;
            } else {
                $add_issue(ValidationIssue::create_invalid_data('Action must be APPLY or REMOVE')->user_message('Please provide a valid action.')->for_field('action'));
            }
        } else {
            $add_issue(ValidationIssue::create_invalid_data('Missing required field')->user_message('Please provide an action.')->for_field('action'));
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
