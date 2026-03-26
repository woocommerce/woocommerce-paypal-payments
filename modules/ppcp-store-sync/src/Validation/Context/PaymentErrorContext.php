<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Validation\Context;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ContextPaymentIssue;
/**
 * Context class for payment-related validation issues.
 *
 * All props are optional and included in to_array() only when set.
 */
class PaymentErrorContext extends \WooCommerce\PayPalCommerce\StoreSync\Validation\Context\IssueContext
{
    public static function create_payment_amount_too_large(): self
    {
        return new self(ContextPaymentIssue::PAYMENT_AMOUNT_TOO_LARGE);
    }
    public static function create_payment_amount_too_small(): self
    {
        return new self(ContextPaymentIssue::PAYMENT_AMOUNT_TOO_SMALL);
    }
    public static function create_payment_method_not_accepted(): self
    {
        return new self(ContextPaymentIssue::PAYMENT_METHOD_NOT_ACCEPTED);
    }
    public static function create_currency_conversion_failed(): self
    {
        return new self(ContextPaymentIssue::CURRENCY_CONVERSION_FAILED);
    }
    public static function create_payment_processor_unavailable(): self
    {
        return new self(ContextPaymentIssue::PAYMENT_PROCESSOR_UNAVAILABLE);
    }
    public static function create_merchant_account_issue(): self
    {
        return new self(ContextPaymentIssue::MERCHANT_ACCOUNT_ISSUE);
    }
    public static function create_payment_declined(): self
    {
        return new self(ContextPaymentIssue::PAYMENT_DECLINED);
    }
    public static function create_payment_insufficient_funds(): self
    {
        return new self(ContextPaymentIssue::PAYMENT_INSUFFICIENT_FUNDS);
    }
    public static function create_payment_expired(): self
    {
        return new self(ContextPaymentIssue::PAYMENT_EXPIRED);
    }
    public static function create_payment_fraud_detected(): self
    {
        return new self(ContextPaymentIssue::PAYMENT_FRAUD_DETECTED);
    }
    private ?string $order_total = null;
    private ?string $payment_limit = null;
    private ?string $minimum_amount = null;
    private ?string $excess_amount = null;
    private ?string $payment_method = null;
    private ?string $currency_code = null;
    private ?string $from_currency = null;
    private ?string $to_currency = null;
    private ?string $conversion_service = null;
    private ?array $supported_payment_methods = null;
    private ?string $processor_error_code = null;
    private ?string $decline_reason = null;
    private ?string $payment_token = null;
    /**
     * Total order amount.
     */
    public function order_total(?string $order_total): self
    {
        $this->order_total = $order_total;
        return $this;
    }
    /**
     * Maximum payment limit.
     */
    public function payment_limit(?string $payment_limit): self
    {
        $this->payment_limit = $payment_limit;
        return $this;
    }
    /**
     * Minimum payment amount.
     */
    public function minimum_amount(?string $minimum_amount): self
    {
        $this->minimum_amount = $minimum_amount;
        return $this;
    }
    /**
     * Amount exceeding limit.
     */
    public function excess_amount(?string $excess_amount): self
    {
        $this->excess_amount = $excess_amount;
        return $this;
    }
    /**
     * Payment method being used.
     */
    public function payment_method(?string $payment_method): self
    {
        $this->payment_method = $payment_method;
        return $this;
    }
    /**
     * Transaction currency.
     */
    public function currency_code(?string $currency_code): self
    {
        $this->currency_code = $currency_code;
        return $this;
    }
    /**
     * Source currency for conversion.
     */
    public function from_currency(?string $from_currency): self
    {
        $this->from_currency = $from_currency;
        return $this;
    }
    /**
     * Target currency for conversion.
     */
    public function to_currency(?string $to_currency): self
    {
        $this->to_currency = $to_currency;
        return $this;
    }
    /**
     * Currency conversion service status.
     */
    public function conversion_service(?string $conversion_service): self
    {
        $this->conversion_service = $conversion_service;
        return $this;
    }
    /**
     * List of supported payment methods.
     */
    public function supported_payment_methods(?array $supported_payment_methods): self
    {
        $this->supported_payment_methods = $this->sanitize_string_array($supported_payment_methods);
        return $this;
    }
    /**
     * Payment processor specific error code.
     */
    public function processor_error_code(?string $processor_error_code): self
    {
        $this->processor_error_code = $processor_error_code;
        return $this;
    }
    /**
     * Reason for payment decline.
     */
    public function decline_reason(?string $decline_reason): self
    {
        $this->decline_reason = $decline_reason;
        return $this;
    }
    /**
     * Payment token that was declined.
     */
    public function payment_token(?string $payment_token): self
    {
        $this->payment_token = $payment_token;
        return $this;
    }
    public function to_array(): array
    {
        $data = array('specific_issue' => $this->specific_issue);
        if ($this->order_total !== null) {
            $data['order_total'] = $this->order_total;
        }
        if ($this->payment_limit !== null) {
            $data['payment_limit'] = $this->payment_limit;
        }
        if ($this->minimum_amount !== null) {
            $data['minimum_amount'] = $this->minimum_amount;
        }
        if ($this->excess_amount !== null) {
            $data['excess_amount'] = $this->excess_amount;
        }
        if ($this->payment_method !== null) {
            $data['payment_method'] = $this->payment_method;
        }
        if ($this->currency_code !== null) {
            $data['currency_code'] = $this->currency_code;
        }
        if ($this->from_currency !== null) {
            $data['from_currency'] = $this->from_currency;
        }
        if ($this->to_currency !== null) {
            $data['to_currency'] = $this->to_currency;
        }
        if ($this->conversion_service !== null) {
            $data['conversion_service'] = $this->conversion_service;
        }
        if ($this->supported_payment_methods !== null) {
            $data['supported_payment_methods'] = $this->supported_payment_methods;
        }
        if ($this->processor_error_code !== null) {
            $data['processor_error_code'] = $this->processor_error_code;
        }
        if ($this->decline_reason !== null) {
            $data['decline_reason'] = $this->decline_reason;
        }
        if ($this->payment_token !== null) {
            $data['payment_token'] = $this->payment_token;
        }
        return $data;
    }
}
