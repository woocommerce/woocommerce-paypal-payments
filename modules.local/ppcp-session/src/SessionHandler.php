<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Session;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;

class SessionHandler
{
    const ID = 'ppcp';

    private $order;
    public function order() : ?Order
    {
        return $this->order;
    }

    public function replaceOrder(Order $order) : SessionHandler
    {
        $this->order = $order;
        $this->storeSession();
        return $this;
    }

    public function destroySessionData() : SessionHandler
    {
        $this->order = null;
        $this->storeSession();
        return $this;
    }

    private function storeSession()
    {
        WC()->session->set(self::ID, $this);
    }
}
