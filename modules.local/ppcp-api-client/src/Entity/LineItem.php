<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;


class LineItem
{

    private $data;
    public function __construct(\stdClass $data)
    {
        $this->data = $data;
    }

    public function referenceId() : int {
        return $this->data->reference_id;
    }

    public function description() : string {
        return $this->data->description;
    }

    public function quantity() : float {
        return $this->data->quantity;
    }

    public function totalAmount() : float {
        return $this->data->total_amount;
    }

    public function currencyCode() : string {
        return $this->data->currency_code;
    }

    public function toArray() : array {
        return [
            'reference_id' => $this->referenceId(),
            'description' => $this->quantity() . '× ' . $this->description(),
            'amount' => [
                'value' => $this->totalAmount(),
                'currency_code' => $this->currencyCode(),
            ]
        ];
    }
}