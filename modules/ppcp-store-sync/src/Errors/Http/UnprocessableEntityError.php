<?php

/**
 * 422 Unprocessable Entity HTTP error.
 *
 * TODO: Is this class used or can it be deleted?
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Errors\Http
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Errors\Http;

use WooCommerce\PayPalCommerce\StoreSync\Errors\AgenticError;
use WooCommerce\PayPalCommerce\StoreSync\Enums\HttpErrorName;
use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;
/**
 * Use when business rules prevent cart operations.
 * Can optionally include rich business context via ValidationIssue.
 */
class UnprocessableEntityError extends AgenticError
{
    protected const ERROR_NAME = HttpErrorName::UNPROCESSABLE_ENTITY;
    protected const STATUS_CODE = 422;
    private ?ValidationIssue $business_context = null;
    public function with_issue(ValidationIssue $business_context): self
    {
        $this->business_context = $business_context;
        return $this;
    }
    public function get_business_context(): ?ValidationIssue
    {
        return $this->business_context;
    }
    public function to_array(): array
    {
        $data = parent::to_array();
        if ($this->business_context) {
            $data['business_context'] = $this->business_context->to_array();
        }
        return $data;
    }
}
