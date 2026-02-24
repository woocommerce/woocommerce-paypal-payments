<?php

/**
 * The Authorization object
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class Authorization
 */
class Authorization
{
    /**
     * The Id.
     *
     * @var string
     */
    private $id;
    /**
     * The status.
     *
     * @var AuthorizationStatus
     */
    private $authorization_status;
    /**
     * The fraud processor response (AVS, CVV ...).
     *
     * @var FraudProcessorResponse|null
     */
    protected $fraud_processor_response;
    /**
     * Authorization constructor.
     *
     * @param string                      $id The id.
     * @param AuthorizationStatus         $authorization_status The status.
     * @param FraudProcessorResponse|null $fraud_processor_response The fraud processor response (AVS, CVV ...).
     */
    public function __construct(string $id, \WooCommerce\PayPalCommerce\ApiClient\Entity\AuthorizationStatus $authorization_status, ?\WooCommerce\PayPalCommerce\ApiClient\Entity\FraudProcessorResponse $fraud_processor_response)
    {
        $this->id = $id;
        $this->authorization_status = $authorization_status;
        $this->fraud_processor_response = $fraud_processor_response;
    }
    /**
     * Returns the Id.
     *
     * @return string
     */
    public function id(): string
    {
        return $this->id;
    }
    /**
     * Returns the status.
     *
     * @return AuthorizationStatus
     */
    public function status(): \WooCommerce\PayPalCommerce\ApiClient\Entity\AuthorizationStatus
    {
        return $this->authorization_status;
    }
    /**
     * Checks whether the authorization can be voided.
     *
     * @return bool
     */
    public function is_voidable(): bool
    {
        return $this->authorization_status->is(\WooCommerce\PayPalCommerce\ApiClient\Entity\AuthorizationStatus::CREATED) || $this->authorization_status->is(\WooCommerce\PayPalCommerce\ApiClient\Entity\AuthorizationStatus::PENDING);
    }
    /**
     * Returns the fraud processor response (AVS, CVV ...).
     *
     * @return FraudProcessorResponse|null
     */
    public function fraud_processor_response(): ?\WooCommerce\PayPalCommerce\ApiClient\Entity\FraudProcessorResponse
    {
        return $this->fraud_processor_response;
    }
    /**
     * Returns the object as array.
     *
     * @return array
     */
    public function to_array(): array
    {
        $data = array('id' => $this->id, 'status' => $this->authorization_status->name());
        if ($this->fraud_processor_response) {
            $data['fraud_processor_response'] = $this->fraud_processor_response->to_array();
        }
        return $data;
    }
}
