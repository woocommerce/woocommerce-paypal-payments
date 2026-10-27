<?php

/**
 * The refund entity.
 *
 * @link https://developer.paypal.com/docs/api/orders/v2/#definition-refund
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class Refund
 */
class Refund
{
    /**
     * The ID.
     *
     * @var string
     */
    private $id;
    /**
     * The status.
     *
     * @var RefundStatus
     */
    private $status;
    /**
     * The amount.
     *
     * @var Amount
     */
    private $amount;
    /**
     * The detailed breakdown of the refund activity (fees, ...).
     *
     * @var SellerPayableBreakdown|null
     */
    private $seller_payable_breakdown;
    /**
     * The invoice id.
     *
     * @var string
     */
    private $invoice_id;
    /**
     * The custom id.
     *
     * @var string
     */
    private $custom_id;
    /**
     * The acquirer reference number.
     *
     * @var string
     */
    private $acquirer_reference_number;
    /**
     * The acquirer reference number.
     *
     * @var string
     */
    private $note_to_payer;
    /**
     * The payer of the refund.
     *
     * @var ?RefundPayer
     */
    private $payer;
    /**
     * Refund constructor.
     *
     * @param string                      $id The ID.
     * @param RefundStatus                $status The status.
     * @param Amount                      $amount The amount.
     * @param string                      $invoice_id The invoice id.
     * @param string                      $custom_id The custom id.
     * @param SellerPayableBreakdown|null $seller_payable_breakdown The detailed breakdown of the refund activity (fees, ...).
     * @param string                      $acquirer_reference_number The acquirer reference number.
     * @param string                      $note_to_payer The note to payer.
     * @param RefundPayer|null            $payer The payer.
     */
    public function __construct(string $id, \WooCommerce\PayPalCommerce\ApiClient\Entity\RefundStatus $status, \WooCommerce\PayPalCommerce\ApiClient\Entity\Amount $amount, string $invoice_id, string $custom_id, ?\WooCommerce\PayPalCommerce\ApiClient\Entity\SellerPayableBreakdown $seller_payable_breakdown, string $acquirer_reference_number, string $note_to_payer, ?\WooCommerce\PayPalCommerce\ApiClient\Entity\RefundPayer $payer)
    {
        $this->id = $id;
        $this->status = $status;
        $this->amount = $amount;
        $this->invoice_id = $invoice_id;
        $this->custom_id = $custom_id;
        $this->seller_payable_breakdown = $seller_payable_breakdown;
        $this->acquirer_reference_number = $acquirer_reference_number;
        $this->note_to_payer = $note_to_payer;
        $this->payer = $payer;
    }
    /**
     * Returns the ID.
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
     * @return RefundStatus
     */
    public function status(): \WooCommerce\PayPalCommerce\ApiClient\Entity\RefundStatus
    {
        return $this->status;
    }
    /**
     * Returns the amount.
     *
     * @return Amount
     */
    public function amount(): \WooCommerce\PayPalCommerce\ApiClient\Entity\Amount
    {
        return $this->amount;
    }
    /**
     * Returns the invoice id.
     *
     * @return string
     */
    public function invoice_id(): string
    {
        return $this->invoice_id;
    }
    /**
     * Returns the custom id.
     *
     * @return string
     */
    public function custom_id(): string
    {
        return $this->custom_id;
    }
    /**
     * Returns the detailed breakdown of the refund activity (fees, ...).
     *
     * @return SellerPayableBreakdown|null
     */
    public function seller_payable_breakdown(): ?\WooCommerce\PayPalCommerce\ApiClient\Entity\SellerPayableBreakdown
    {
        return $this->seller_payable_breakdown;
    }
    /**
     * The acquirer reference number.
     *
     * @return string
     */
    public function acquirer_reference_number(): string
    {
        return $this->acquirer_reference_number;
    }
    /**
     * The note to payer.
     *
     * @return string
     */
    public function note_to_payer(): string
    {
        return $this->note_to_payer;
    }
    /**
     * Returns the refund payer.
     *
     * @return RefundPayer|null
     */
    public function payer(): ?\WooCommerce\PayPalCommerce\ApiClient\Entity\RefundPayer
    {
        return $this->payer;
    }
    /**
     * Returns the entity as array.
     *
     * @return array
     */
    public function to_array(): array
    {
        $data = array('id' => $this->id(), 'status' => $this->status()->name(), 'amount' => $this->amount()->to_array(), 'invoice_id' => $this->invoice_id(), 'custom_id' => $this->custom_id(), 'acquirer_reference_number' => $this->acquirer_reference_number(), 'note_to_payer' => (array) $this->note_to_payer());
        $details = $this->status()->details();
        if ($details) {
            $data['status_details'] = array('reason' => $details->reason());
        }
        if ($this->seller_payable_breakdown) {
            $data['seller_payable_breakdown'] = $this->seller_payable_breakdown->to_array();
        }
        if ($this->payer) {
            $data['payer'] = $this->payer->to_array();
        }
        return $data;
    }
}
