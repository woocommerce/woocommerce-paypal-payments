<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Entity;


class Order
{

    private $data;

    /**
     * Order constructor.
     *
     * @param \stdClass $data $data is formed like the PayPal create order response.
     *
     * @see https://developer.paypal.com/docs/api/orders/v2/#orders-create-response
     */
    public function __construct(\stdClass $data)
    {
        $this->data = $data;
    }

    public function id() : string {
        return $this->data->id;
    }

    public function isApproved() : bool {
        return $this->data->status === 'APPROVED';
    }

    public function isCompleted() : bool {
        return $this->data->status === 'COMPLETED';
    }

    public function isCreated() : bool {
        return $this->data->status === 'CREATED';
    }

    public function toArray() : array {
        return (array) $this->data;
    }
}