<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Enums;

/**
 * Resolution actions for error handling.
 *
 * Used in resolution_options array to specify available actions
 * for resolving validation issues.
 */
class ResolutionAction
{
    public const REDIRECT_TO_MERCHANT = 'REDIRECT_TO_MERCHANT';
    public const MODIFY_CART = 'MODIFY_CART';
    public const ACCEPT_NEW_PRICE = 'ACCEPT_NEW_PRICE';
    public const ACCEPT_BACK_ORDER = 'ACCEPT_BACK_ORDER';
    public const SUGGEST_ALTERNATIVE = 'SUGGEST_ALTERNATIVE';
    public const REMOVE_ITEM = 'REMOVE_ITEM';
    public const UPDATE_ADDRESS = 'UPDATE_ADDRESS';
    public const PROVIDE_MISSING_FIELD = 'PROVIDE_MISSING_FIELD';
    public const USE_DIFFERENT_PAYMENT = 'USE_DIFFERENT_PAYMENT';
    public const SPLIT_ORDER = 'SPLIT_ORDER';
    public const CONTACT_SUPPORT = 'CONTACT_SUPPORT';
    public const RETRY_LATER = 'RETRY_LATER';
    public const REQUEST_APPROVAL = 'REQUEST_APPROVAL';
    public const WAIT_FOR_RESTOCK = 'WAIT_FOR_RESTOCK';
    public const USE_DIFFERENT_CURRENCY = 'USE_DIFFERENT_CURRENCY';
    public const ACCEPT_PRE_ORDER = 'ACCEPT_PRE_ORDER';
    public const UPDATE_SHIPPING_METHOD = 'UPDATE_SHIPPING_METHOD';
    public const ACCEPT_TERMS = 'ACCEPT_TERMS';
    public const VERIFY_ACCOUNT = 'VERIFY_ACCOUNT';
    public const APPLY_DIFFERENT_COUPON = 'APPLY_DIFFERENT_COUPON';
    public const REMOVE_COUPON = 'REMOVE_COUPON';
    public const KEEP_CURRENT_COUPON = 'KEEP_CURRENT_COUPON';
    public const CHOOSE_DIFFERENT_VARIANT = 'CHOOSE_DIFFERENT_VARIANT';
}
