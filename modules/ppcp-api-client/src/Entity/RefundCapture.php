<?php

/**
 * The refund capture object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class RefundCapture
 */
class RefundCapture
{
    /**
     * The Capture.
     *
     * @var Capture
     */
    private $capture;
    /**
     * The invoice id.
     *
     * @var string
     */
    private $invoice_id;
    /**
     * The note to the payer.
     *
     * @var string
     */
    private $note_to_payer;
    /**
     * The Amount.
     *
     * @var Amount|null
     */
    private $amount;
    /**
     * Refund constructor.
     *
     * @param Capture     $capture The capture where the refund is supposed to be applied at.
     * @param string      $invoice_id The invoice id.
     * @param string      $note_to_payer The note to the payer.
     * @param Amount|null $amount The Amount.
     */
    public function __construct(\WooCommerce\PayPalCommerce\ApiClient\Entity\Capture $capture, string $invoice_id, string $note_to_payer = '', ?\WooCommerce\PayPalCommerce\ApiClient\Entity\Amount $amount = null)
    {
        $this->capture = $capture;
        $this->invoice_id = $invoice_id;
        $this->note_to_payer = $note_to_payer;
        $this->amount = $amount;
    }
    /**
     * Returns the capture for the refund.
     *
     * @return Capture
     */
    public function for_capture(): \WooCommerce\PayPalCommerce\ApiClient\Entity\Capture
    {
        return $this->capture;
    }
    /**
     * Return the invoice id.
     *
     * @return string
     */
    public function invoice_id(): string
    {
        return $this->invoice_id;
    }
    /**
     * Returns the note to the payer.
     *
     * @return string
     */
    public function note_to_payer(): string
    {
        return $this->note_to_payer;
    }
    /**
     * Returns the Amount.
     *
     * @return Amount|null
     */
    public function amount()
    {
        return $this->amount;
    }
    /**
     * Returns the object as array.
     *
     * @return array
     */
    public function to_array(): array
    {
        $data = array('invoice_id' => $this->invoice_id());
        if ($this->note_to_payer()) {
            $data['note_to_payer'] = $this->note_to_payer();
        }
        if ($this->amount) {
            $data['amount'] = $this->amount->to_array();
        }
        return $data;
    }
}
